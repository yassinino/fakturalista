<?php

namespace Tests\Feature;

use App\Models\{CompanyProfile, Customer, Invoice, Quote, Tenant, User};
use Tests\TestCase;

/**
 * Create Invoice / Create Quote with the optional fields now under
 * "Plus d'options" (status, note, Spain-only operation description): the
 * documents still save the same way - with or without those fields.
 */
class CreateFormOptionalFieldsTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;
    private string $customerUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-more-options-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        [$user, $this->customerUuid] = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Options Co', 'country_code' => 'ES', 'currency' => 'EUR', 'locale' => 'es',
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

    private function payload(array $extra = []): array
    {
        return $extra + [
            'customer_id' => $this->customerUuid, 'date' => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(), 'status' => '', 'discount_rate' => 0, 'note' => '',
            'carts' => [['item_id' => '', 'description' => 'Consultoría', 'qty' => 1, 'unite' => 'pc',
                'price' => 100, 'discount' => 0, 'vta' => 21, 'tax_treatment' => 'taxable', 'total' => 100]],
        ];
    }

    private function url(string $path): string
    {
        return 'http://' . $this->domain . '/api/' . $path;
    }

    public function test_invoice_saves_with_and_without_the_optional_fields(): void
    {
        // Section never opened: optional fields left as the form initialises them.
        $this->postJson($this->url('invoices'), $this->payload())->assertOk();

        // Section opened and filled in.
        $this->postJson($this->url('invoices'), $this->payload([
            'note' => 'Gracias por su confianza',
            'descripcion_operacion' => 'Servicios de consultoría',
        ]))->assertOk();

        $last = $this->tenant->run(fn () => Invoice::latest('id')->first());
        $this->assertSame('Gracias por su confianza', $last->note);
        $this->assertSame('Servicios de consultoría', $last->descripcion_operacion);
        $this->assertEquals(121.00, (float) $last->total);
        $this->assertSame(2, $this->tenant->run(fn () => Invoice::count()));
    }

    public function test_quote_saves_with_and_without_the_optional_fields(): void
    {
        $this->postJson($this->url('quotes'), $this->payload())->assertOk();
        $this->postJson($this->url('quotes'), $this->payload(['note' => 'Válido 30 días']))->assertOk();

        $last = $this->tenant->run(fn () => Quote::latest('id')->first());
        $this->assertSame('Válido 30 días', $last->note);
        $this->assertEquals(121.00, (float) $last->total);
        $this->assertSame(2, $this->tenant->run(fn () => Quote::count()));
    }

    public function test_the_operation_description_limit_is_the_one_the_form_now_checks(): void
    {
        $this->postJson($this->url('invoices'), $this->payload(['descripcion_operacion' => str_repeat('x', 501)]))
            ->assertStatus(422)->assertJsonValidationErrors(['descripcion_operacion']);
    }
}
