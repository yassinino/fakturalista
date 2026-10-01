<?php

namespace Tests\Feature;

use App\Models\{CompanyProfile, Customer, Family, Invoice, Item, Tenant, User};
use Tests\TestCase;

/**
 * Invoice page quick-create ("+ Nouveau client" / "+ Nouveau produit ou
 * service"). The modals reuse the existing POST /customers and POST /items;
 * these tests pin the contract they rely on: the created record's id is
 * returned, it is found in the very list the invoice form re-fetches with
 * the fields line selection uses, validation still applies, and a normal
 * invoice can be saved with the quick-created records.
 */
class InvoiceQuickCreateTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-quick-create-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        $user = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Quick Create Co', 'country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr',
                'invoice_prefix' => 'INV', 'onboarding_completed_at' => now(),
            ]);
            return User::factory()->create();
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

    public function test_quick_customer_creation_returns_the_new_customer_found_in_the_dropdown_list(): void
    {
        // Company: only the name (plus the type the modal always sends).
        $company = $this->postJson($this->url('customers'), ['type' => 1, 'name' => 'Quick Co', 'contacts' => []])
            ->assertOk()->json('customer');
        $this->assertNotEmpty($company['uuid']);

        // Individual: last name required, first name optional.
        $person = $this->postJson($this->url('customers'), ['type' => 2, 'first_name' => 'Jane', 'last_name' => 'Doe', 'contacts' => []])
            ->assertOk()->json('customer');

        $list = collect($this->getJson($this->url('customers'))->assertOk()->json('customers'))->keyBy('uuid');
        $this->assertSame('Quick Co', $list[$company['uuid']]['name']);
        $this->assertSame('Jane Doe', $list[$person['uuid']]['name']);
        // What onClientSelect() reads is present (null for a quick-created customer).
        $this->assertArrayHasKey('address_billing', $list[$company['uuid']]);
    }

    public function test_quick_item_creation_returns_an_id_matching_the_invoice_item_list(): void
    {
        $familyId = $this->tenant->run(fn () => Family::create(['name' => 'Services'])->id);

        $created = $this->postJson($this->url('items'), [
            'name' => 'Consulting hour', 'type' => 1, 'family_id' => $familyId,
            'sales_price' => 150, 'vta' => 20, 'tax_treatment' => 'taxable',
            'unite' => 'pc', 'currency' => 'MAD', 'active' => true,
        ])->assertCreated()->json('item');

        $item = collect($this->getJson($this->url('items'))->assertOk()->json('items'))
            ->firstWhere('id', $created['id']);

        $this->assertNotNull($item, 'The returned id must match the id format of GET /items (what the line selector uses).');
        // Everything selectProduct() copies onto the invoice line.
        $this->assertSame('Consulting hour', $item['name']);
        $this->assertEquals(150, $item['sales_price']);
        $this->assertEquals(20, $item['vta']);
        $this->assertSame('taxable', $item['tax_treatment']);
        $this->assertSame('pc', $item['unite']);
    }

    public function test_quick_item_is_created_without_a_category(): void
    {
        // Exactly what QuickItemModal sends (no category, empty price).
        $id = $this->postJson($this->url('items'), [
            'name' => 'Audit', 'type' => 1, 'sales_price' => null,
            'vta' => 20, 'tax_treatment' => 'taxable', 'unite' => 'pc', 'currency' => 'MAD', 'active' => true,
        ])->assertCreated()->json('item.id');

        $this->assertNull($this->tenant->run(fn () => Item::find($id)->family_id));
        $this->assertNotNull(collect($this->getJson($this->url('items'))->json('items'))->firstWhere('id', $id));
    }

    public function test_validation_errors_are_returned_and_nothing_is_created(): void
    {
        $before = $this->tenant->run(fn () => Item::count());

        $this->postJson($this->url('items'), ['type' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonMissingValidationErrors(['family_id']); // category is optional

        // ...but a category that doesn't exist is still rejected cleanly.
        $this->postJson($this->url('items'), ['name' => 'X', 'type' => 1, 'family_id' => 999999])
            ->assertStatus(422)->assertJsonValidationErrors(['family_id']);

        $this->postJson($this->url('items'), ['name' => 'X', 'type' => 9])
            ->assertStatus(422)->assertJsonValidationErrors(['type']);

        $this->postJson($this->url('customers'), ['type' => 1, 'name' => 'Co', 'ice' => str_repeat('9', 40), 'contacts' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['ice']);

        $this->assertSame($before, $this->tenant->run(fn () => Item::count()));
        $this->assertSame(0, $this->tenant->run(fn () => Customer::count()));
    }

    public function test_an_invoice_is_still_created_normally_with_quick_created_records(): void
    {
        $familyId = $this->tenant->run(fn () => Family::create(['name' => 'Services'])->id);
        $customerUuid = $this->postJson($this->url('customers'), ['type' => 1, 'name' => 'Invoice Co', 'contacts' => []])
            ->json('customer.uuid');
        $itemId = $this->postJson($this->url('items'), [
            'name' => 'Design', 'type' => 1, 'family_id' => $familyId, 'sales_price' => 1000, 'vta' => 20, 'tax_treatment' => 'taxable',
        ])->json('item.id');

        $this->postJson($this->url('invoices'), [
            'customer_id' => $customerUuid, 'date' => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(), 'status' => 'draft', 'discount_rate' => 0,
            'carts' => [[
                'item_id' => $itemId, 'description' => 'Design', 'qty' => 2, 'unite' => 'pc',
                'price' => 1000, 'discount' => 0, 'vta' => 20, 'tax_treatment' => 'taxable', 'total' => 2000,
            ]],
        ])->assertOk();

        $invoice = $this->tenant->run(fn () => Invoice::latest('id')->with('customer')->first());
        $this->assertSame('Invoice Co', $invoice->customer->name);
        $this->assertEquals(2400.00, (float) $invoice->total);
    }

    // ── Same records on the other pages (Edit invoice, Create/Edit quote) ──

    private function quickRecords(): array
    {
        $customerUuid = $this->postJson($this->url('customers'), ['type' => 1, 'name' => 'Quick Quote Co', 'contacts' => []])
            ->assertOk()->json('customer.uuid');
        $itemId = $this->postJson($this->url('items'), [
            'name' => 'Quick service', 'type' => 1, 'sales_price' => 300, 'vta' => 20, 'tax_treatment' => 'taxable', 'unite' => 'pc', 'active' => true,
        ])->assertCreated()->json('item.id');

        return [$customerUuid, $itemId];
    }

    private function documentPayload(string $customerUuid, string $itemId): array
    {
        return [
            'customer_id' => $customerUuid, 'date' => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(), 'status' => 'draft', 'discount_rate' => 0,
            'carts' => [[
                'item_id' => $itemId, 'description' => '', 'qty' => 1, 'unite' => 'pc',
                'price' => 300, 'discount' => 0, 'vta' => 20, 'tax_treatment' => 'taxable', 'total' => 300,
            ]],
        ];
    }

    public function test_quick_created_records_work_on_a_new_quote(): void
    {
        [$customerUuid, $itemId] = $this->quickRecords();

        $this->postJson($this->url('quotes'), $this->documentPayload($customerUuid, $itemId))->assertOk();

        $quote = $this->tenant->run(fn () => \App\Models\Quote::latest('id')->with('customer')->first());
        $this->assertSame('Quick Quote Co', $quote->customer->name);
        $this->assertEquals(360.00, (float) $quote->total);
    }

    public function test_quick_created_records_work_when_editing_a_draft_invoice(): void
    {
        $original = $this->tenant->run(fn () => Customer::factory()->create(['type' => 1]));
        [$customerUuid, $itemId] = $this->quickRecords();

        $this->postJson($this->url('invoices'), $this->documentPayload($original->uuid, $itemId))->assertOk();
        $invoice = $this->tenant->run(fn () => Invoice::latest('id')->first());

        // Edit: switch to the quick-created customer.
        $this->putJson($this->url('invoices/' . $invoice->uuid), $this->documentPayload($customerUuid, $itemId))->assertOk();

        $fresh = $this->tenant->run(fn () => Invoice::with('customer')->find($invoice->id));
        $this->assertSame('Quick Quote Co', $fresh->customer->name);
        $this->assertEquals(360.00, (float) $fresh->total);
    }

    public function test_an_accepted_quote_still_refuses_edits(): void
    {
        [$customerUuid, $itemId] = $this->quickRecords();
        $this->postJson($this->url('quotes'), $this->documentPayload($customerUuid, $itemId))->assertOk();
        $quote = $this->tenant->run(function () {
            $q = \App\Models\Quote::latest('id')->first();
            $q->update(['status' => \App\Models\Quote::STATUS_ACCEPTED, 'accepted_at' => now()]);
            return $q;
        });

        [$otherCustomer] = $this->quickRecords();
        $this->putJson($this->url('quotes/' . $quote->uuid), $this->documentPayload($otherCustomer, $itemId))->assertStatus(422);
    }
}
