<?php

namespace Tests\Feature;

use App\Models\{CompanyProfile, Customer, Family, Invoice, Item, Tenant, User};
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Product/Service category (items.family_id) is optional everywhere:
 * create, edit, list, invoice selector and invoice creation - while
 * existing categorized items keep their category.
 */
class ItemOptionalCategoryTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-item-category-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        $user = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Category Co', 'country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr',
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

    /** The full Create page's payload (family_id null when no category is chosen). */
    private function fullPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Website maintenance', 'type' => 1, 'unite' => 'pc', 'sales_price' => 500,
            'purchase_price' => null, 'reference' => null, 'vta' => 20, 'tax_treatment' => 'taxable',
            'currency' => 'MAD', 'active' => true, 'description' => null, 'family_id' => null,
        ], $overrides);
    }

    private function itemByName(string $name): Item
    {
        return $this->tenant->run(fn () => Item::where('name', $name)->firstOrFail());
    }

    public function test_the_column_allows_null(): void
    {
        $this->tenant->run(function () {
            $column = collect(Schema::getConnection()->select("SHOW COLUMNS FROM items WHERE Field = 'family_id'"))->first();
            $this->assertSame('YES', $column->Null);
        });
    }

    public function test_an_item_can_be_created_without_a_category_and_with_one_as_before(): void
    {
        $familyId = $this->tenant->run(fn () => Family::create(['name' => 'Services'])->id);

        $this->postJson($this->url('items'), $this->fullPayload())->assertCreated();
        $this->postJson($this->url('items'), $this->fullPayload(['name' => 'Logo design', 'family_id' => $familyId]))->assertCreated();

        $this->assertNull($this->itemByName('Website maintenance')->family_id);
        $this->assertSame($familyId, (int) $this->itemByName('Logo design')->family_id);
    }

    public function test_items_without_a_category_work_in_the_list_and_edit_page(): void
    {
        $this->postJson($this->url('items'), $this->fullPayload())->assertCreated();
        $item = $this->itemByName('Website maintenance');

        // Product list + invoice product selector both use GET /items.
        $listed = collect($this->getJson($this->url('items'))->assertOk()->json('items'))->firstWhere('uuid', $item->uuid);
        $this->assertSame('Website maintenance', $listed['name']);
        $this->assertEquals(500, $listed['sales_price']);

        // Edit page loads with no category...
        $this->getJson($this->url('items/' . $item->uuid . '/edit'))->assertOk()->assertJsonPath('item.family_id', null);

        // ...and saves without one.
        $this->putJson($this->url('items/' . $item->uuid), $this->fullPayload(['name' => 'Website care']))->assertOk();
        $this->assertNull($this->itemByName('Website care')->family_id);
    }

    public function test_editing_can_set_or_clear_a_category_and_leaves_other_items_unchanged(): void
    {
        $familyId = $this->tenant->run(fn () => Family::create(['name' => 'Hosting'])->id);
        $this->postJson($this->url('items'), $this->fullPayload(['name' => 'Categorized', 'family_id' => $familyId]))->assertCreated();
        $this->postJson($this->url('items'), $this->fullPayload(['name' => 'Plain']))->assertCreated();
        $plain = $this->itemByName('Plain');

        // Set a category, then clear it again.
        $this->putJson($this->url('items/' . $plain->uuid), $this->fullPayload(['name' => 'Plain', 'family_id' => $familyId]))->assertOk();
        $this->assertSame($familyId, (int) $this->itemByName('Plain')->family_id);
        $this->putJson($this->url('items/' . $plain->uuid), $this->fullPayload(['name' => 'Plain', 'family_id' => null]))->assertOk();
        $this->assertNull($this->itemByName('Plain')->family_id);

        // The other, categorized item and the category itself are untouched.
        $this->assertSame($familyId, (int) $this->itemByName('Categorized')->family_id);
        $this->assertTrue($this->tenant->run(fn () => Family::whereKey($familyId)->exists()));
    }

    public function test_an_invoice_can_be_created_with_an_uncategorized_item(): void
    {
        $this->postJson($this->url('items'), $this->fullPayload())->assertCreated();
        $item = $this->itemByName('Website maintenance');
        $customerUuid = $this->tenant->run(fn () => Customer::factory()->create(['type' => 1])->uuid);

        $this->postJson($this->url('invoices'), [
            'customer_id' => $customerUuid, 'date' => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(), 'status' => 'draft', 'discount_rate' => 0,
            'carts' => [[
                'item_id' => (string) $item->id, 'description' => '', 'qty' => 1, 'unite' => 'pc',
                'price' => 500, 'discount' => 0, 'vta' => 20, 'tax_treatment' => 'taxable', 'total' => 500,
            ]],
        ])->assertOk();

        $invoice = $this->tenant->run(fn () => Invoice::latest('id')->first());
        $this->assertEquals(600.00, (float) $invoice->total);
    }
}
