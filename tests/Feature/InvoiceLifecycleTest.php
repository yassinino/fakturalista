<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Create and initialize a test tenant
        $this->tenant = Tenant::create(['id' => 'test-lifecycle-' . uniqid()]);
        tenancy()->initialize($this->tenant);

        // Create a test user within the tenant context
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
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

        $response = $this->postJson('/api/invoices/' . $invoice->uuid . '/issue');

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

        $response = $this->postJson('/api/invoices/' . $invoice->uuid . '/issue');

        $response->assertStatus(422);
    }

    // ── Locked invoices cannot be edited ───────────────────

    /** @test */
    public function issued_invoice_update_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $invoice  = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/invoices/' . $invoice->uuid, [
            '_method'         => 'put',
            'customer_id'     => $customer->uuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'carts'           => [],
        ]);

        $response->assertStatus(403);
    }

    // ── Duplicate ───────────────────────────────────────────

    /** @test */
    public function invoice_can_be_duplicated(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/invoices/' . $invoice->uuid . '/duplicate');

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
        // Source gets INV-1 (or whatever the next number is)
        $invoice = $this->makeInvoice([
            'reference' => 'INV-1',
            'status'    => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
        ]);
        $this->actingAs($this->user, 'api');

        $response      = $this->postJson('/api/invoices/' . $invoice->uuid . '/duplicate');
        $duplicateUuid = $response->json('duplicate_uuid');
        $duplicate     = Invoice::where('uuid', $duplicateUuid)->first();

        // The duplicated draft gets its own new reference
        $this->assertNotNull($duplicate->reference);
        $this->assertNotEquals('INV-1', $duplicate->reference);
    }

    /** @test */
    public function sequential_duplicates_get_unique_references(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $r1 = $this->postJson('/api/invoices/' . $invoice->uuid . '/duplicate');
        $r2 = $this->postJson('/api/invoices/' . $invoice->uuid . '/duplicate');

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

        $response = $this->postJson('/api/invoices/' . $invoice->uuid . '/mark-paid');

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

        $response = $this->postJson('/api/invoices/' . $invoice->uuid . '/mark-paid');
        $response->assertStatus(422);
    }

    // ── Cancel ──────────────────────────────────────────────

    /** @test */
    public function invoice_can_be_cancelled(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now()]);
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/invoices/' . $invoice->uuid . '/cancel');

        $response->assertStatus(200)
                 ->assertJsonPath('status', Invoice::STATUS_CANCELLED);

        $invoice->refresh();
        $this->assertTrue($invoice->isCancelled());
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

        $this->postJson('/api/invoices/' . $invoice->uuid . '/issue');

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

        $response = $this->getJson('/api/invoices/' . $invoice->uuid . '/history');

        $response->assertStatus(200)
                 ->assertJsonStructure(['history' => [['action', 'created_at']]]);
    }
}
