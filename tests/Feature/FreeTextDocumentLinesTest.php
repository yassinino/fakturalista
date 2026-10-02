<?php

namespace Tests\Feature;

use App\Models\{Cart, CompanyProfile, Customer, Invoice, Item, Quote, Tenant, User};
use Tests\TestCase;

/**
 * Invoice / quote lines without a catalog item ("free-text" lines): the
 * line's own description carries the text, carts.item_id stays null, no
 * Item is ever created - alongside normal catalog lines.
 */
class FreeTextDocumentLinesTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;
    private string $customerUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-free-text-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        [$user, $this->customerUuid] = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Free Text Co', 'country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr',
                'invoice_prefix' => 'INV', 'onboarding_completed_at' => now(),
            ]);
            return [User::factory()->create(), Customer::factory()->create(['type' => 1])->uuid];
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

    /** A line exactly as the forms send it when nothing is picked in the selector. */
    private function freeLine(string $text, float $price, float $vta = 20, float $qty = 1): array
    {
        return ['item_id' => '', 'name' => '', 'description' => $text, 'qty' => $qty, 'unite' => 'pc',
            'price' => $price, 'discount' => 0, 'vta' => $vta, 'tax_treatment' => 'taxable', 'total' => $qty * $price];
    }

    /** A line as the forms send it after picking a catalog item (selectProduct). */
    private function catalogLine(Item $item, float $qty = 1): array
    {
        return ['item_id' => (string) $item->id, 'name' => $item->name, 'description' => $item->description ?? '',
            'qty' => $qty, 'unite' => $item->unite, 'price' => $item->sales_price, 'discount' => 0,
            'vta' => $item->vta, 'tax_treatment' => $item->tax_treatment, 'total' => $qty * $item->sales_price];
    }

    private function payload(array $lines): array
    {
        return [
            'customer_id' => $this->customerUuid, 'date' => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(), 'status' => 'draft', 'discount_rate' => 0,
            'carts' => $lines,
        ];
    }

    private function catalogItem(): Item
    {
        return $this->tenant->run(fn () => Item::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'reference' => 'PS-1', 'name' => 'Hébergement',
            'type' => 1, 'sales_price' => 1000, 'vta' => 20, 'tax_treatment' => 'taxable', 'unite' => 'pc',
        ]));
    }

    private function itemCount(): int
    {
        return $this->tenant->run(fn () => Item::count());
    }

    public function test_an_invoice_with_a_free_text_line_saves_without_any_item(): void
    {
        $this->postJson($this->url('invoices'), $this->payload([$this->freeLine('Création site web', 4500)]))->assertOk();

        $invoice = $this->tenant->run(fn () => Invoice::latest('id')->with('carts')->first());
        $line = $invoice->carts->first();
        $this->assertNull($line->item_id);
        $this->assertSame('Création site web', $line->description);
        $this->assertEquals(5400.00, (float) $invoice->total); // 4500 + 20%
        $this->assertSame(0, $this->itemCount(), 'No catalog item is ever created.');

        // Persists after reload (what the edit page loads).
        $carts = $this->getJson($this->url('invoices/' . $invoice->uuid . '/edit'))->assertOk()->json('invoice.carts');
        $this->assertSame('Création site web', $carts[0]['description']);
        $this->assertNull($carts[0]['item_id']);
    }

    public function test_a_quote_with_a_free_text_line_saves_without_any_item(): void
    {
        $this->postJson($this->url('quotes'), $this->payload([$this->freeLine('Consultation octobre', 1200, 10, 2)]))->assertOk();

        $quote = $this->tenant->run(fn () => Quote::latest('id')->with('carts')->first());
        $this->assertNull($quote->carts->first()->item_id);
        $this->assertSame('Consultation octobre', $quote->carts->first()->description);
        $this->assertEquals(2640.00, (float) $quote->total); // 2 x 1200 + 10%
        $this->assertSame(0, $this->itemCount());

        $carts = $this->getJson($this->url('quotes/' . $quote->uuid . '/edit'))->assertOk()->json('quote.carts');
        $this->assertSame('Consultation octobre', $carts[0]['description']);
    }

    public function test_catalog_and_free_text_lines_mix_in_one_document(): void
    {
        $item = $this->catalogItem();

        foreach (['invoices' => Invoice::class, 'quotes' => Quote::class] as $endpoint => $model) {
            $this->postJson($this->url($endpoint), $this->payload([
                $this->catalogLine($item),
                $this->freeLine('Déplacement', 300, 20),
            ]))->assertOk();

            $doc = $this->tenant->run(fn () => $model::latest('id')->with('carts.product')->first());
            [$catalog, $free] = $doc->carts->sortBy('id')->values()->all();

            $this->assertSame((string) $item->id, (string) $catalog->item_id);
            $this->assertSame('Hébergement', $catalog->product->name, 'A picked item still links to the catalog.');
            $this->assertNull($free->item_id);
            $this->assertSame('Déplacement', $free->description);
            $this->assertEquals(1560.00, (float) $doc->total, $endpoint); // (1000 + 300) + 20%
        }

        $this->assertSame(1, $this->itemCount(), 'Only the pre-existing catalog item.');
    }

    public function test_editing_a_draft_invoice_and_a_quote_preserves_free_text_lines(): void
    {
        $item = $this->catalogItem();

        foreach (['invoices' => Invoice::class, 'quotes' => Quote::class] as $endpoint => $model) {
            $this->postJson($this->url($endpoint), $this->payload([$this->freeLine('Maquette', 800)]))->assertOk();
            $doc = $this->tenant->run(fn () => $model::latest('id')->first());

            // Edit: change the free-text line's price and add a catalog line.
            $this->putJson($this->url($endpoint . '/' . $doc->uuid), $this->payload([
                $this->freeLine('Maquette', 900),
                $this->catalogLine($item),
            ]))->assertOk();

            $lines = $this->tenant->run(fn () => Cart::where('cartable_type', $model)->where('cartable_id', $doc->id)->orderBy('id')->get());
            $this->assertCount(2, $lines, $endpoint);
            $this->assertNull($lines[0]->item_id);
            $this->assertSame('Maquette', $lines[0]->description);
            $this->assertEquals(900, (float) $lines[0]->price);
            $this->assertSame((string) $item->id, (string) $lines[1]->item_id);
        }
    }
}
