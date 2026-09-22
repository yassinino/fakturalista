<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Tests for the invoice lifecycle workflow.
 *
 * These tests require tenant databases to be set up.
 * Run with: php artisan test --filter=InvoiceLifecycleTest
 *
 * Multi-tenant context: each test initializes a temporary tenant
 * and tears it down after, so tests are isolated.
 */
class InvoiceLifecycleTest extends TestCase
{
    protected Tenant $tenant;
    protected User   $user;
    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and initialize a test tenant
        $this->tenant = Tenant::create(['id' => 'test-lifecycle-' . uniqid()]);

        // Tenant routes (routes/tenant_api.php) sit behind
        // InitializeTenancyByDomain + PreventAccessFromCentralDomains
        // (app/Http/Kernel.php 'api' group), which resolve/gate the tenant
        // purely from the request's Host header - a domain row must exist,
        // and requests must actually target it, or PreventAccessFromCentralDomains
        // aborts with 404 because Laravel's default test host ("localhost")
        // is registered as a CENTRAL domain in config/tenancy.php. This
        // Laravel version's test client builds request URLs via the global
        // url() helper (not a $baseUrl property), so relative paths like
        // postJson('/api/...') always resolve against config('app.url') -
        // every request must use a fully-qualified URL via apiUrl() below
        // to actually hit this tenant's domain. Confirmed pre-existing:
        // true before the Phase 2A changes too, not introduced here.
        $this->domain = 'test-lifecycle-' . uniqid() . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);

        tenancy()->initialize($this->tenant);

        // Create a test user within the tenant context
        $this->user = User::factory()->create();

        // Fiscal identity for the seller - required by InvoiceController::issue()
        // as of the Phase 2A numbering/validation work (a complete invoice
        // cannot be issued without the issuer's own NIF/CIF on file).
        // onboarding_completed_at is required by the pre-existing
        // RequireOnboarding middleware (app/Http/Middleware/RequireOnboarding.php),
        // which 403s every business route otherwise - unrelated to Phase 2A,
        // but this test tenant never goes through the real onboarding flow.
        CompanyProfile::create([
            'legal_name'              => 'Test Company S.L.',
            'tax_id'                  => 'B12345678',
            'invoice_prefix'          => 'INV',
            'onboarding_completed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    private function apiUrl(string $path): string
    {
        return 'http://' . $this->domain . $path;
    }

    private function makeInvoice(array $overrides = []): Invoice
    {
        $customer = Customer::factory()->create();

        return Invoice::create(array_merge([
            'uuid'            => \Illuminate\Support\Str::uuid()->toString(),
            'reference'       => 'INV-TEST-1',
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
        ], $overrides));
    }

    // ── Status defaults ─────────────────────────────────────

    /** @test */
    public function new_invoice_defaults_to_draft(): void
    {
        $invoice = $this->makeInvoice();
        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertTrue($invoice->isDraft());
        $this->assertFalse($invoice->isLocked());
    }

    // ── Status helpers ──────────────────────────────────────

    /** @test */
    public function status_helpers_return_correct_values(): void
    {
        $draft     = $this->makeInvoice(['status' => Invoice::STATUS_DRAFT]);
        $issued    = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $paid      = $this->makeInvoice(['status' => Invoice::STATUS_PAID]);
        $cancelled = $this->makeInvoice(['status' => Invoice::STATUS_CANCELLED]);

        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isLocked());

        $this->assertTrue($issued->isIssued());
        $this->assertTrue($issued->isLocked());

        $this->assertTrue($paid->isPaid());
        $this->assertTrue($paid->isLocked());

        $this->assertTrue($cancelled->isCancelled());
        $this->assertTrue($cancelled->isLocked());
    }

    // ── Issue transition ────────────────────────────────────

    /** @test */
    public function draft_invoice_can_be_issued(): void
    {
        $invoice = $this->makeInvoice();
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(200)
                 ->assertJsonPath('status', Invoice::STATUS_ISSUED);

        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertNotNull($invoice->issued_at);
    }

    /** @test */
    public function cannot_issue_an_already_issued_invoice(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(422);
    }

    /** @test */
    public function issuing_assigns_a_definitive_legal_number(): void
    {
        $invoice = $this->makeInvoice();
        $this->actingAs($this->user, 'api');

        $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'))
             ->assertStatus(200);

        $invoice->refresh();
        $this->assertNotNull($invoice->invoice_series);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertEquals(Invoice::TYPE_F1, $invoice->invoice_type);
    }

    /** @test */
    public function cannot_issue_without_company_tax_id(): void
    {
        CompanyProfile::query()->update(['tax_id' => null]);

        $invoice = $this->makeInvoice();
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(422);
        $invoice->refresh();
        $this->assertTrue($invoice->isDraft());
    }

    /** @test */
    public function cannot_issue_without_customer_tax_id(): void
    {
        $customer = Customer::factory()->withoutTaxId()->create();
        $invoice  = $this->makeInvoice(['customer_id' => $customer->id]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(422);
        $invoice->refresh();
        $this->assertTrue($invoice->isDraft());
    }

    // ── Locked invoices cannot be edited ───────────────────

    /** @test */
    public function issued_invoice_update_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $invoice  = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid), [
            '_method'         => 'put',
            'customer_id'     => $customer->uuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'carts'           => [],
        ]);

        $response->assertStatus(403);
    }

    // ── Deletion ─────────────────────────────────────────────

    /** @test */
    public function draft_invoice_can_be_deleted(): void
    {
        $invoice = $this->makeInvoice();
        $this->actingAs($this->user, 'api');

        $response = $this->deleteJson($this->apiUrl('/api/invoices/' . $invoice->uuid));

        $response->assertStatus(200);
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    /** @test */
    public function issued_invoice_cannot_be_deleted(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->deleteJson($this->apiUrl('/api/invoices/' . $invoice->uuid));

        $response->assertStatus(403);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
    }

    /** @test */
    public function paid_invoice_cannot_be_deleted(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_PAID]);
        $this->actingAs($this->user, 'api');

        $response = $this->deleteJson($this->apiUrl('/api/invoices/' . $invoice->uuid));

        $response->assertStatus(403);
    }

    /** @test */
    public function bulk_delete_only_removes_drafts_and_reports_skipped(): void
    {
        $draft  = $this->makeInvoice();
        $issued = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/bulk-delete'), [
            'ids' => [$draft->uuid, $issued->uuid],
        ]);

        $response->assertStatus(200);
        $this->assertSoftDeleted('invoices', ['id' => $draft->id]);
        $this->assertDatabaseHas('invoices', ['id' => $issued->id, 'deleted_at' => null]);
        $this->assertEquals([$issued->uuid], $response->json('skipped'));
    }

    // ── Duplicate ───────────────────────────────────────────

    /** @test */
    public function invoice_can_be_duplicated(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/duplicate'));

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'duplicate_uuid', 'reference']);

        $duplicateUuid = $response->json('duplicate_uuid');
        $duplicate     = Invoice::where('uuid', $duplicateUuid)->first();

        $this->assertNotNull($duplicate);
        $this->assertEquals(Invoice::STATUS_DRAFT, $duplicate->status);
        $this->assertEquals($invoice->id, $duplicate->source_invoice_id);
        // Reference must be assigned immediately on duplicate creation
        $this->assertNotNull($duplicate->reference);
        $this->assertStringStartsWith('INV-', $duplicate->reference);
        // Must not reuse the source invoice's number
        $this->assertNotEquals($invoice->reference, $duplicate->reference);
    }

    /** @test */
    public function duplicate_has_new_number_different_from_source(): void
    {
        // Reserve a real draft label first, exactly as store() would, so the
        // source's reference reflects an actual sequence reservation rather
        // than a hardcoded string that could coincidentally collide with the
        // service's very first output for this fresh tenant database.
        $sourceReference = app(\App\Services\InvoiceNumberingService::class)->nextDraftLabel();
        $invoice = $this->makeInvoice([
            'reference' => $sourceReference,
            'status'    => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
        ]);
        $this->actingAs($this->user, 'api');

        $response      = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/duplicate'));
        $duplicateUuid = $response->json('duplicate_uuid');
        $duplicate     = Invoice::where('uuid', $duplicateUuid)->first();

        // The duplicated draft gets its own new reference
        $this->assertNotNull($duplicate->reference);
        $this->assertNotEquals($sourceReference, $duplicate->reference);
    }

    /** @test */
    public function sequential_duplicates_get_unique_references(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $r1 = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/duplicate'));
        $r2 = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/duplicate'));

        $ref1 = $r1->json('reference');
        $ref2 = $r2->json('reference');

        $this->assertNotEquals($ref1, $ref2, 'Consecutive duplicates must receive unique invoice numbers');
    }

    // ── Mark paid ───────────────────────────────────────────

    /** @test */
    public function issued_invoice_can_be_marked_paid(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/mark-paid'));

        $response->assertStatus(200)
                 ->assertJsonPath('status', Invoice::STATUS_PAID);

        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
    }

    /** @test */
    public function draft_invoice_cannot_be_marked_paid(): void
    {
        $invoice = $this->makeInvoice();
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/mark-paid'));
        $response->assertStatus(422);
    }

    // ── Cancel (anulación) ───────────────────────────────────

    /** @test */
    public function invoice_can_be_cancelled_with_a_valid_anulacion_reason(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/cancel'), [
            'reason' => 'operacion_inexistente',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', Invoice::STATUS_CANCELLED);

        $invoice->refresh();
        $this->assertTrue($invoice->isCancelled());
        $this->assertEquals('operacion_inexistente', $invoice->cancellation_reason);
    }

    /** @test */
    public function cancel_rejects_a_reason_that_requires_a_rectificativa(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/cancel'), [
            'reason' => 'devolucion',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('suggested_mechanism', 'rectificativa');

        $invoice->refresh();
        $this->assertTrue($invoice->isIssued());
    }

    /** @test */
    public function cancel_requires_a_reason(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/cancel'), []);

        $response->assertStatus(422);
    }

    // ── Rectification ─────────────────────────────────────────

    /** @test */
    public function rectify_creates_a_new_draft_and_leaves_the_original_issued(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/rectify'), [
            'reason'             => 'importe_incorrecto',
            'rectification_type' => 'I',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'rectification_uuid', 'reference']);

        $invoice->refresh();
        $this->assertTrue($invoice->isIssued(), 'The original invoice must remain issued, not cancelled.');

        $rectification = Invoice::where('uuid', $response->json('rectification_uuid'))->first();
        $this->assertNotNull($rectification);
        $this->assertTrue($rectification->isDraft());
        $this->assertEquals($invoice->id, $rectification->rectifies_invoice_id);
        $this->assertEquals(Invoice::TYPE_R1, $rectification->invoice_type);
        $this->assertEquals('I', $rectification->rectification_type);
    }

    /** @test */
    public function rectify_rejects_a_reason_that_requires_anulacion(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/rectify'), [
            'reason'             => 'operacion_inexistente',
            'rectification_type' => 'I',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('suggested_mechanism', 'anulacion');
    }

    /** @test */
    public function rectify_rejects_ambiguous_reason_without_explicit_mode(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/rectify'), [
            'reason'             => 'otro',
            'rectification_type' => 'I',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('requires_review', true);

        $invoice->refresh();
        $this->assertTrue($invoice->isIssued(), 'An undetermined mechanism must never silently create a rectification.');
    }

    /** @test */
    public function rectify_accepts_ambiguous_reason_with_explicit_mode(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/rectify'), [
            'reason'             => 'otro',
            'mode'               => 'rectificativa',
            'rectification_type' => 'S',
        ]);

        $response->assertStatus(200);
        $rectification = Invoice::where('uuid', $response->json('rectification_uuid'))->first();
        $this->assertEquals(Invoice::TYPE_R4, $rectification->invoice_type);
    }

    /** @test */
    public function cannot_rectify_a_draft_invoice(): void
    {
        $invoice = $this->makeInvoice();
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/rectify'), [
            'reason'             => 'error_datos',
            'rectification_type' => 'I',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function rectification_of_a_simplified_invoice_is_typed_r5(): void
    {
        $invoice = $this->makeInvoice([
            'status'       => Invoice::STATUS_ISSUED,
            'issued_at'    => now(),
            'invoice_type' => Invoice::TYPE_F2,
        ]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/rectify'), [
            'reason'             => 'error_datos',
            'rectification_type' => 'I',
        ]);

        $response->assertStatus(200);
        $rectification = Invoice::where('uuid', $response->json('rectification_uuid'))->first();
        $this->assertEquals(Invoice::TYPE_R5, $rectification->invoice_type);
    }

    // ── Numbering integrity ──────────────────────────────────

    /** @test */
    public function database_rejects_a_duplicate_series_and_number_combination(): void
    {
        $customer = Customer::factory()->create();

        Invoice::create([
            'uuid'            => \Illuminate\Support\Str::uuid()->toString(),
            'reference'       => 'INV-2027-0001',
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_ISSUED,
            'issued_at'       => now(),
            'invoice_series'  => \App\Services\InvoiceNumberingService::SERIES_DEFAULT,
            'invoice_number'  => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Invoice::create([
            'uuid'            => \Illuminate\Support\Str::uuid()->toString(),
            'reference'       => 'INV-2027-0001-DUPLICATE',
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_ISSUED,
            'issued_at'       => now(),
            'invoice_series'  => \App\Services\InvoiceNumberingService::SERIES_DEFAULT,
            'invoice_number'  => 1, // same (series, number) pair as above
        ]);
    }

    /** @test */
    public function draft_default_and_rectification_series_number_independently(): void
    {
        $numbering = app(\App\Services\InvoiceNumberingService::class);
        $company   = CompanyProfile::first();

        // Advance the draft series a couple of times, as ordinary invoice
        // creation would.
        $numbering->nextDraftLabel();
        $numbering->nextDraftLabel();

        $invoice = $this->makeInvoice();
        $numbering->assignLegalNumber($invoice, $company);
        $this->assertEquals(1, $invoice->invoice_number, 'First number in the default series must be 1 regardless of draft-series activity.');

        $rectification = $this->makeInvoice();
        $numbering->assignRectificationNumber($rectification, $company);
        $this->assertEquals(1, $rectification->invoice_number, 'First number in the rectification series must be 1, independent of the default series.');
        $this->assertNotEquals($invoice->invoice_series, $rectification->invoice_series);
    }

    // ── Rectification issuance uses the separate series ─────

    /** @test */
    public function issuing_a_rectification_assigns_a_number_from_the_rectification_series(): void
    {
        $original = $this->makeInvoice();
        $this->actingAs($this->user, 'api');
        $this->postJson($this->apiUrl('/api/invoices/' . $original->uuid . '/issue'))->assertStatus(200);
        $original->refresh();

        $rectifyResponse = $this->postJson($this->apiUrl('/api/invoices/' . $original->uuid . '/rectify'), [
            'reason'             => 'importe_incorrecto',
            'rectification_type' => 'I',
        ]);
        $rectifyResponse->assertStatus(200);

        $rectification = Invoice::where('uuid', $rectifyResponse->json('rectification_uuid'))->first();
        $this->assertTrue($rectification->isDraft(), 'createRectification() must only create a draft; the series is assigned at issuance.');

        // This is the step that exercises the issueInvoice() fix: before it,
        // a rectificativa was unconditionally numbered from the DEFAULT
        // series instead of the mandated separate rectification series
        // (RD 1619/2012 art. 6.5).
        $issueResponse = $this->postJson($this->apiUrl('/api/invoices/' . $rectification->uuid . '/issue'));
        $issueResponse->assertStatus(200);

        $rectification->refresh();
        $this->assertEquals(
            \App\Services\InvoiceNumberingService::SERIES_RECTIFICATION,
            $rectification->invoice_series
        );
        $this->assertNotEquals($original->invoice_series, $rectification->invoice_series);
        $this->assertStringStartsWith('R-', $rectification->reference, 'The rectification series must use its own prefix, distinguishable from the default series.');
    }

    // ── Cancel/rectify: explicit mode & invoice_type overrides ──

    /** @test */
    public function cancel_accepts_an_explicit_mode_override_for_an_ambiguous_reason(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/cancel'), [
            'reason' => 'otro',
            'mode'   => 'anulacion',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', Invoice::STATUS_CANCELLED);
    }

    /** @test */
    public function cancel_requires_review_for_an_ambiguous_reason_without_explicit_mode(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/cancel'), [
            'reason' => 'otro',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('requires_review', true);

        $invoice->refresh();
        $this->assertTrue($invoice->isIssued(), 'An undetermined mechanism must never silently cancel the invoice.');
    }

    /** @test */
    public function rectify_accepts_an_explicit_invoice_type_override_reaching_r3(): void
    {
        // R3 (créditos incobrables, LIVA art. 80.4) can never be inferred
        // from a business reason alone - only reachable via the explicit
        // invoice_type override, per InvoiceRectificationService's docblock.
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/rectify'), [
            'reason'             => 'error_datos',
            'rectification_type' => 'S',
            'invoice_type'       => Invoice::TYPE_R3,
        ]);

        $response->assertStatus(200);
        $rectification = Invoice::where('uuid', $response->json('rectification_uuid'))->first();
        $this->assertEquals(Invoice::TYPE_R3, $rectification->invoice_type);
        $this->assertEquals('S', $rectification->rectification_type);
    }

    // ── Legacy invoices (pre-Phase-2A, NULL legal numbering) ──

    /** @test */
    public function legacy_issued_invoice_without_legal_number_is_not_editable(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->assertFalse($invoice->hasLegalNumber());

        $customer = Customer::factory()->create();
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid), [
            '_method'         => 'put',
            'customer_id'     => $customer->uuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'carts'           => [],
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function legacy_issued_invoice_without_legal_number_is_not_deletable(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->deleteJson($this->apiUrl('/api/invoices/' . $invoice->uuid));

        $response->assertStatus(403);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
    }

    /** @test */
    public function legacy_issued_invoice_cannot_be_silently_reissued_with_a_new_number(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(422);
        $invoice->refresh();
        $this->assertFalse(
            $invoice->hasLegalNumber(),
            'A legacy invoice must never be silently backfilled with a new legal number just by hitting /issue again.'
        );
    }

    /** @test */
    public function legacy_invoices_with_null_numbering_never_collide_with_newly_issued_invoices(): void
    {
        // Two legacy rows, both NULL/NULL series+number - must coexist under
        // the (invoice_series, invoice_number) unique index (MySQL allows
        // multiple NULL combinations; see database_rejects_a_duplicate_series_and_number_combination
        // above for the case this index DOES reject).
        $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->makeInvoice(['status' => Invoice::STATUS_PAID]);

        $invoice = $this->makeInvoice();
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(200);
        $invoice->refresh();
        $this->assertTrue($invoice->hasLegalNumber());
        $this->assertEquals(1, $invoice->invoice_number, 'The legacy NULL rows must not affect or collide with a fresh sequence.');
    }

    /** @test */
    public function legacy_invoice_pdf_generation_does_not_break_without_legal_number_fields(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->assertFalse($invoice->hasLegalNumber());
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/print'), ['uuid' => $invoice->uuid]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'pdf_url']);
    }

    // ── Numbering rollback safety ─────────────────────────────

    /** @test */
    public function a_failed_transaction_does_not_leave_a_partial_sequence_increment(): void
    {
        $numbering = app(\App\Services\InvoiceNumberingService::class);
        $company   = CompanyProfile::first();
        $invoice   = $this->makeInvoice();

        try {
            DB::transaction(function () use ($numbering, $invoice, $company) {
                $numbering->assignLegalNumber($invoice, $company);
                throw new \RuntimeException('Simulated failure after number assignment, before the surrounding transaction commits.');
            });
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertNull($invoice->fresh()->invoice_number, 'The rolled-back assignment must not persist.');

        // A subsequent, real assignment must get the FIRST number in the
        // series - proving the failed attempt did not permanently consume it.
        $next = $this->makeInvoice();
        $numbering->assignLegalNumber($next, $company);
        $this->assertEquals(1, $next->invoice_number, 'A rolled-back issuance must not leave a numbering gap.');
    }

    // ── Simplified invoices (F2) via the real endpoint ────────

    /** @test */
    public function f2_simplified_invoice_can_be_issued_via_the_real_endpoint_without_customer_tax_id(): void
    {
        $customer = Customer::factory()->withoutTaxId()->create();
        $invoice  = $this->makeInvoice(['customer_id' => $customer->id, 'invoice_type' => Invoice::TYPE_F2]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(200);
        $invoice->refresh();
        $this->assertTrue($invoice->isIssued());
        $this->assertEquals(Invoice::TYPE_F2, $invoice->invoice_type);
    }

    // ── SetLocale middleware regression ────────────────────────

    /** @test */
    public function api_request_does_not_crash_when_users_locale_is_empty(): void
    {
        // users.locale is a NOT NULL column (default 'es' - see
        // database/migrations/tenant/2025_12_20_090000_add_locale_to_users_table.php),
        // so a literal SQL NULL can never occur through normal writes; an
        // empty string is the realistic falsy value that can actually reach
        // this column. The 'api' middleware group (app/Http/Kernel.php)
        // carries no session middleware at all, so this exercises exactly
        // the path that used to throw "Session store not set on request"
        // before the hasSession() guard was added to SetLocale.
        $this->user->locale = '';
        $this->user->save();
        $this->actingAs($this->user, 'api');

        $response = $this->getJson($this->apiUrl('/api/invoices'));

        $response->assertStatus(200);
    }

    /** @test */
    public function api_request_does_not_crash_when_users_locale_is_unsupported(): void
    {
        $this->user->locale = 'zz';
        $this->user->save();
        $this->actingAs($this->user, 'api');

        $response = $this->getJson($this->apiUrl('/api/invoices'));

        $response->assertStatus(200);
    }

    // ── History / audit log ─────────────────────────────────

    /** @test */
    public function history_is_recorded_on_create(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->logHistory(InvoiceHistory::ACTION_CREATED);

        $this->assertDatabaseHas('invoice_history', [
            'invoice_id' => $invoice->id,
            'action'     => InvoiceHistory::ACTION_CREATED,
        ]);
    }

    /** @test */
    public function history_is_recorded_on_issue(): void
    {
        $invoice = $this->makeInvoice();
        $this->actingAs($this->user, 'api');

        $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/issue'));

        $this->assertDatabaseHas('invoice_history', [
            'invoice_id' => $invoice->id,
            'action'     => InvoiceHistory::ACTION_ISSUED,
        ]);
    }

    /** @test */
    public function history_endpoint_returns_audit_log(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->logHistory(InvoiceHistory::ACTION_CREATED);
        $this->actingAs($this->user, 'api');

        $response = $this->getJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/history'));

        $response->assertStatus(200)
                 ->assertJsonStructure(['history' => [['action', 'created_at']]]);
    }

    // ── descripcion_operacion (Phase 2C.1) ───────────────────

    /** @test */
    public function store_persists_descripcion_operacion_when_provided(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices'), [
            'customer_id'     => $customer->uuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'sub_total'       => 100,
            'total'           => 121,
            'vta'             => 21,
            'vta4'            => 0,
            'vta10'           => 0,
            'vta21'           => 21,
            'descripcion_operacion' => 'Diseño de identidad visual - marca ACME',
            'carts' => [[
                'item_id' => null, 'description' => 'Diseño', 'qty' => 1,
                'unite' => 'pc', 'price' => 100, 'discount' => 0, 'total' => 100, 'vta' => 21,
            ]],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoices', [
            'descripcion_operacion' => 'Diseño de identidad visual - marca ACME',
        ]);
    }

    /** @test */
    public function a_draft_invoice_can_still_be_saved_without_descripcion_operacion(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/invoices'), [
            'customer_id'     => $customer->uuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'sub_total'       => 100,
            'total'           => 121,
            'vta'             => 21,
            'vta4'            => 0,
            'vta10'           => 0,
            'vta21'           => 21,
            // descripcion_operacion intentionally omitted - a draft may
            // still be incomplete; only issuance/VERI*FACTU record
            // generation hard-blocks on it (VerifactuChainService).
            'carts' => [[
                'item_id' => null, 'description' => 'Diseño', 'qty' => 1,
                'unite' => 'pc', 'price' => 100, 'discount' => 0, 'total' => 100, 'vta' => 21,
            ]],
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function update_persists_descripcion_operacion_and_edit_returns_it(): void
    {
        $invoice = $this->makeInvoice();
        $customer = Customer::factory()->create();
        $this->actingAs($this->user, 'api');

        $this->postJson($this->apiUrl('/api/invoices/' . $invoice->uuid), [
            '_method'         => 'put',
            'customer_id'     => $customer->uuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'sub_total'       => $invoice->sub_total,
            'total'           => $invoice->total,
            'vta'             => $invoice->vta,
            'vta4'            => $invoice->vta4,
            'vta10'           => $invoice->vta10,
            'vta21'           => $invoice->vta21,
            'descripcion_operacion' => 'Servicios de mantenimiento web - marzo',
            'carts'           => [],
        ])->assertStatus(200);

        $edit = $this->getJson($this->apiUrl('/api/invoices/' . $invoice->uuid . '/edit'));
        $edit->assertStatus(200)
             ->assertJsonPath('invoice.descripcion_operacion', 'Servicios de mantenimiento web - marzo');
    }
}
