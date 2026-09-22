<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\Tenant;
use App\Models\Verifactu\VerifactuChainState;
use App\Models\Verifactu\VerifactuRecord;
use App\Services\InvoiceNumberingService;
use App\Services\Verifactu\VerifactuChainService;
use App\Services\Verifactu\VerifactuHashService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;
use Tests\TestCase;

/**
 * Phase 2B: VerifactuChainService is standalone here, not wired into
 * InvoiceController::issue()/cancel() yet (see the service's own
 * docblock) - so these tests call it directly, the same way a future
 * wiring step eventually will.
 */
class VerifactuChainServiceTest extends TestCase
{
    protected Tenant $tenant;
    protected string $domain;
    protected CompanyProfile $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'test-verifactu-' . uniqid()]);
        $this->domain = 'test-verifactu-' . uniqid() . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);

        tenancy()->initialize($this->tenant);

        $this->company = CompanyProfile::create([
            'legal_name'                     => 'Test Company S.L.',
            'tax_id'                         => '89890001K',
            'invoice_prefix'                 => 'INV',
            'onboarding_completed_at'        => now(),
            'verifactu_installation_number'  => 'TEST-INSTALL-1',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    private function service(): VerifactuChainService
    {
        return new VerifactuChainService(new VerifactuHashService());
    }

    /**
     * A DRAFT invoice already issued (numbered) via InvoiceNumberingService
     * - VerifactuChainService requires a real legal number to exist first.
     */
    private function issuedInvoice(array $overrides = []): Invoice
    {
        $customer = Customer::factory()->create();

        $invoice = Invoice::create(array_merge([
            'uuid'                  => Str::uuid()->toString(),
            'reference'             => 'DRAFT-TMP',
            'customer_id'           => $customer->id,
            'date'                  => now()->toDateString(),
            'expiration_date'       => now()->addDays(30)->toDateString(),
            'status'                => Invoice::STATUS_DRAFT,
            'sub_total'             => 100.00,
            'total'                 => 121.00,
            'vta'                   => 21.00,
            'vta4'                  => 0,
            'vta10'                 => 0,
            'vta21'                 => 21.00,
            'discount_rate'         => 0,
            'discount_amount'       => 0,
            'descripcion_operacion' => 'Servicios de consultoría de prueba',
        ], $overrides));

        app(InvoiceNumberingService::class)->assignLegalNumber($invoice, $this->company);
        $invoice->status    = Invoice::STATUS_ISSUED;
        $invoice->issued_at = now();
        $invoice->save();

        return $invoice;
    }

    // ── Alta: first record and chaining ─────────────────────

    /** @test */
    public function first_alta_record_for_a_nif_has_no_previous_hash_and_is_flagged_as_first(): void
    {
        $invoice = $this->issuedInvoice();

        $record = $this->service()->recordAlta($invoice, $this->company);

        $this->assertTrue($record->es_primer_registro);
        $this->assertNull($record->huella_registro_anterior);
        $this->assertEquals(VerifactuRecord::TIPO_ALTA, $record->tipo_registro);
        $this->assertEquals(64, strlen($record->huella));
        $this->assertEquals($this->company->tax_id, $record->nif_emisor);
        $this->assertEquals($invoice->reference, $record->serie_numero);
    }

    /** @test */
    public function second_alta_record_chains_to_the_first_records_hash(): void
    {
        $invoice1 = $this->issuedInvoice();
        $record1  = $this->service()->recordAlta($invoice1, $this->company);

        $invoice2 = $this->issuedInvoice();
        $record2  = $this->service()->recordAlta($invoice2, $this->company);

        $this->assertFalse($record2->es_primer_registro);
        $this->assertEquals($record1->huella, $record2->huella_registro_anterior);
        $this->assertNotEquals($record1->huella, $record2->huella);
    }

    /** @test */
    public function chain_state_advances_to_the_latest_records_hash(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->service()->recordAlta($invoice, $this->company);

        $chain = VerifactuChainState::where('nif_emisor', $this->company->tax_id)->first();

        $this->assertNotNull($chain);
        $this->assertEquals($record->huella, $chain->last_huella);
        $this->assertEquals($record->id, $chain->last_verifactu_record_id);
    }

    // ── Anulación ─────────────────────────────────────────────

    /** @test */
    public function anulacion_chains_off_the_latest_hash_and_references_its_own_invoice(): void
    {
        $invoice = $this->issuedInvoice();
        $alta    = $this->service()->recordAlta($invoice, $this->company);

        $anulacion = $this->service()->recordAnulacion($invoice, $this->company);

        $this->assertEquals(VerifactuRecord::TIPO_ANULACION, $anulacion->tipo_registro);
        $this->assertEquals($alta->huella, $anulacion->huella_registro_anterior);
        $this->assertEquals($invoice->id, $anulacion->invoice_id);
        $this->assertEquals($invoice->reference, $anulacion->serie_numero);
        // Anulación hash input has no TipoFactura/CuotaTotal/ImporteTotal.
        $this->assertNull($anulacion->tipo_factura);
        $this->assertNull($anulacion->cuota_total);
    }

    /** @test */
    public function anulacion_chains_off_this_invoices_own_alta_not_a_different_invoices(): void
    {
        $invoiceA = $this->issuedInvoice();
        $altaA    = $this->service()->recordAlta($invoiceA, $this->company);

        $invoiceB = $this->issuedInvoice();
        $altaB    = $this->service()->recordAlta($invoiceB, $this->company);

        // Cancel invoice A - its anulación must chain off the LATEST hash
        // in the (per-NIF) chain, which by now is altaB's, and must be
        // recorded against invoice A, never against invoice B.
        $anulacionA = $this->service()->recordAnulacion($invoiceA, $this->company);

        $this->assertEquals($invoiceA->id, $anulacionA->invoice_id);
        $this->assertEquals($invoiceA->reference, $anulacionA->serie_numero);
        $this->assertEquals($altaB->huella, $anulacionA->huella_registro_anterior);
        $this->assertNotEquals($altaA->huella, $anulacionA->huella_registro_anterior);
    }

    /** @test */
    public function cannot_generate_anulacion_without_a_prior_alta_record(): void
    {
        $invoice = $this->issuedInvoice();

        $this->expectException(\RuntimeException::class);

        $this->service()->recordAnulacion($invoice, $this->company);
    }

    // ── Preconditions ─────────────────────────────────────────

    /** @test */
    public function cannot_generate_alta_without_a_definitive_legal_number(): void
    {
        $customer = Customer::factory()->create();
        $draft    = Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'DRAFT-1',
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_DRAFT,
            'sub_total'       => 100.00,
            'total'           => 121.00,
            'vta'             => 21.00,
            'vta4'            => 0,
            'vta10'           => 0,
            'vta21'           => 21.00,
            'discount_rate'   => 0,
            'discount_amount' => 0,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service()->recordAlta($draft, $this->company);
    }

    /** @test */
    public function cannot_generate_a_record_without_company_tax_id(): void
    {
        $invoice = $this->issuedInvoice();
        $this->company->tax_id = null;
        $this->company->save();

        $this->expectException(\RuntimeException::class);

        $this->service()->recordAlta($invoice, $this->company->fresh());
    }

    /** @test */
    public function cannot_generate_a_record_without_an_installation_number(): void
    {
        $invoice = $this->issuedInvoice();
        $this->company->verifactu_installation_number = null;
        $this->company->save();

        $this->expectException(\RuntimeException::class);

        $this->service()->recordAlta($invoice, $this->company->fresh());
    }

    /** @test */
    public function cannot_generate_an_alta_record_without_descripcion_operacion(): void
    {
        $invoice = $this->issuedInvoice(['descripcion_operacion' => null]);

        $this->expectException(\RuntimeException::class);

        $this->service()->recordAlta($invoice, $this->company);
    }

    // ── Rollback safety ─────────────────────────────────────────

    /** @test */
    public function a_failed_transaction_does_not_advance_the_chain_without_a_persisted_record(): void
    {
        $invoice = $this->issuedInvoice();
        $this->service()->recordAlta($invoice, $this->company);

        $invoice2 = $this->issuedInvoice();

        try {
            DB::transaction(function () use ($invoice2) {
                // Same shape as VerifactuChainService::recordAlta(), but
                // deliberately interrupted after the record would have
                // been written, to prove the whole thing rolls back
                // atomically rather than leaving the chain half-advanced.
                $this->service()->recordAlta($invoice2, $this->company);
                throw new \RuntimeException('Simulated failure after the record would have been persisted.');
            });
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertDatabaseMissing('verifactu_records', ['invoice_id' => $invoice2->id]);

        $chain = VerifactuChainState::where('nif_emisor', $this->company->tax_id)->first();
        $this->assertNotEquals(
            $invoice2->id,
            $chain->last_verifactu_record_id,
            'A rolled-back attempt must not leave the chain pointing at a record that was never actually persisted.'
        );
    }

    // ── Immutability ─────────────────────────────────────────────

    /** @test */
    public function verifactu_record_cannot_be_deleted(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->service()->recordAlta($invoice, $this->company);

        $this->expectException(\RuntimeException::class);

        $record->delete();
    }

    // ── Auditability ─────────────────────────────────────────────

    /** @test */
    public function generating_a_record_logs_it_to_invoice_history(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->service()->recordAlta($invoice, $this->company);

        $this->assertDatabaseHas('invoice_history', [
            'invoice_id' => $invoice->id,
            'action'     => InvoiceHistory::ACTION_VERIFACTU_RECORD_GENERATED,
        ]);

        $entry = InvoiceHistory::where('invoice_id', $invoice->id)
            ->where('action', InvoiceHistory::ACTION_VERIFACTU_RECORD_GENERATED)
            ->first();

        $this->assertEquals($record->id, $entry->context['verifactu_record_id']);
        $this->assertEquals($record->huella, $entry->context['huella']);
    }

    // ── Multi-tenant isolation ─────────────────────────────────

    /** @test */
    public function chain_state_is_structurally_isolated_per_tenant(): void
    {
        $invoiceA = $this->issuedInvoice();
        $recordA  = $this->service()->recordAlta($invoiceA, $this->company);

        $tenantB = Tenant::create(['id' => 'test-verifactu-b-' . uniqid()]);
        $tenantB->domains()->create(['domain' => 'test-verifactu-b-' . uniqid() . '.fakturalista.test']);

        $recordB = $tenantB->run(function () {
            $companyB = CompanyProfile::create([
                'legal_name'              => 'Other Company S.L.',
                // Deliberately the SAME NIF as tenant A's company - proves
                // isolation is structural (separate databases), not just
                // "different NIF values happened not to collide."
                'tax_id'                  => '89890001K',
                'invoice_prefix'          => 'INV',
                'onboarding_completed_at' => now(),
                'verifactu_installation_number' => 'TEST-INSTALL-2',
            ]);

            $customer = Customer::factory()->create();
            $invoice  = Invoice::create([
                'uuid'                  => Str::uuid()->toString(),
                'reference'             => 'DRAFT-B',
                'customer_id'           => $customer->id,
                'date'                  => now()->toDateString(),
                'expiration_date'       => now()->addDays(30)->toDateString(),
                'status'                => Invoice::STATUS_DRAFT,
                'sub_total'             => 50.00,
                'total'                 => 55.00,
                'vta'                   => 5.00,
                'vta4'                  => 0,
                'vta10'                 => 5.00,
                'vta21'                 => 0,
                'discount_rate'         => 0,
                'discount_amount'       => 0,
                'descripcion_operacion' => 'Servicios distintos del tenant B',
            ]);
            app(InvoiceNumberingService::class)->assignLegalNumber($invoice, $companyB);
            $invoice->status    = Invoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            $invoice->save();

            return app(VerifactuChainService::class)->recordAlta($invoice, $companyB);
        });

        // Tenant B's chain must start fresh (its own first record), even
        // though it shares the same NIF value as Tenant A - because it
        // lives in a structurally separate database, never Tenant A's.
        $this->assertTrue($recordB->es_primer_registro);
        $this->assertNotEquals($recordA->huella, $recordB->huella);

        // Back in Tenant A's context (tenant->run() restores it): Tenant
        // A's own chain state must be completely unaffected by Tenant B's
        // activity above.
        $chainA = VerifactuChainState::where('nif_emisor', $this->company->tax_id)->first();
        $this->assertEquals($recordA->huella, $chainA->last_huella);
        $this->assertEquals(1, VerifactuChainState::count());

        $tenantB->delete();
    }
}
