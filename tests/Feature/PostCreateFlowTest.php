<?php

namespace Tests\Feature;

use App\Models\{CompanyProfile, Customer, Invoice, Quote, Tenant, User};
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * After "Create", the app opens the new document's own page using the id the
 * create API returns. Creating never issues, sends, numbers or changes the
 * status of anything; the destination page loads the new draft and its
 * existing actions keep working.
 */
class PostCreateFlowTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;
    private string $customerUuid;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->tenant = Tenant::create(['id' => 'test-post-create-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        [$user, $this->customerUuid] = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Post Create Co', 'country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr',
                'invoice_prefix' => 'INV', 'onboarding_completed_at' => now(),
            ]);
            return [User::factory()->create(), Customer::factory()->create(['type' => 1, 'email' => 'client@example.test'])->uuid];
        });
        $this->actingAs($user, 'api');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    private function url(string $path): string
    {
        return 'http://' . $this->domain . '/api/' . $path;
    }

    private function payload(): array
    {
        return [
            'customer_id' => $this->customerUuid, 'date' => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(), 'status' => '', 'discount_rate' => 0, 'note' => '',
            'carts' => [['item_id' => '', 'description' => 'Création site web', 'qty' => 1, 'unite' => 'pc',
                'price' => 4500, 'discount' => 0, 'vta' => 20, 'tax_treatment' => 'taxable', 'total' => 4500]],
        ];
    }

    public function test_invoice_create_returns_the_new_invoices_id_and_leaves_it_an_unsent_draft(): void
    {
        $uuid = $this->postJson($this->url('invoices'), $this->payload())->assertOk()->json('invoice.uuid');

        $invoice = $this->tenant->run(fn () => Invoice::latest('id')->first());
        $this->assertSame($invoice->uuid, $uuid, 'the id used for navigation is the new invoice');
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertNull($invoice->invoice_number, 'not numbered/issued automatically');
        $this->assertNull($invoice->sent_at);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        // The destination page loads it as an editable draft.
        $this->getJson($this->url('invoices/' . $uuid . '/edit'))->assertOk()
            ->assertJsonPath('invoice.uuid', $uuid)
            ->assertJsonPath('invoice.is_locked', false);
    }

    public function test_quote_create_returns_the_new_quotes_id_and_sends_nothing(): void
    {
        $uuid = $this->postJson($this->url('quotes'), $this->payload())->assertOk()
            ->assertJsonPath('message', 'Quote created successfully.')
            ->json('quote.uuid');

        $quote = $this->tenant->run(fn () => Quote::latest('id')->first());
        $this->assertSame($quote->uuid, $uuid);
        $this->assertNotSame(Quote::STATUS_SENT, $quote->status, 'not sent automatically');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        $this->getJson($this->url('quotes/' . $uuid . '/edit'))->assertOk()->assertJsonPath('quote.uuid', $uuid);
    }

    public function test_a_failed_creation_returns_no_id_and_creates_nothing(): void
    {
        $this->postJson($this->url('invoices'), ['date' => now()->toDateString()])->assertStatus(422)->assertJsonMissingPath('invoice');
        $this->postJson($this->url('quotes'), ['date' => now()->toDateString()])->assertStatus(422)->assertJsonMissingPath('quote');

        $this->assertSame(0, $this->tenant->run(fn () => Invoice::count()));
        $this->assertSame(0, $this->tenant->run(fn () => Quote::count()));
    }

    public function test_existing_actions_still_work_on_the_new_documents(): void
    {
        $invoiceUuid = $this->postJson($this->url('invoices'), $this->payload())->json('invoice.uuid');
        $quoteUuid   = $this->postJson($this->url('quotes'), $this->payload())->json('quote.uuid');

        // Save from the destination page (edit) and Duplicate still work.
        $this->putJson($this->url('invoices/' . $invoiceUuid), $this->payload())->assertOk();
        $this->postJson($this->url('invoices/' . $invoiceUuid . '/duplicate'))->assertOk()->assertJsonStructure(['duplicate_uuid']);
        $this->putJson($this->url('quotes/' . $quoteUuid), $this->payload())->assertOk();
        $this->postJson($this->url('quotes/' . $quoteUuid . '/duplicate'))->assertOk();

        $this->assertSame(Invoice::STATUS_DRAFT, $this->tenant->run(fn () => Invoice::where('uuid', $invoiceUuid)->value('status')));
    }
}
