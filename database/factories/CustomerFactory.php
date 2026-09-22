<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 *
 * Was previously missing entirely, even though tests/Feature/InvoiceLifecycleTest.php
 * already called Customer::factory() - every test in that file failed with
 * "Class Database\Factories\CustomerFactory not found" before this was added
 * (confirmed pre-existing, unrelated to the Phase 2A changes).
 *
 * Defaults to type=1 (company) with a plausible-format Spanish CIF in
 * `tax_id`, so invoices created from factory customers satisfy the new
 * "customer NIF/CIF required for a complete (F1) invoice" validation
 * without every test having to set it explicitly.
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'uuid'               => Str::uuid()->toString(),
            'reference'          => 'CUST-' . $this->faker->unique()->numberBetween(1, 999999),
            'type'               => 1, // company
            'company_name'       => $this->faker->company(),
            'first_name'         => null,
            'last_name'          => null,
            'vat_number'         => 'ES' . $this->faker->numerify('B########'),
            'tax_id'             => $this->faker->numerify('B########'),
            'email'              => $this->faker->unique()->safeEmail(),
            'phone'              => '+34' . $this->faker->numerify('#########'),
            'website'            => null,
            'billing_country_id' => null,
            'delivery_country_id'=> null,
            'city_billing'       => $this->faker->city(),
            'address_billing'    => $this->faker->streetAddress(),
            'post_code_billing'  => $this->faker->postcode(),
            'is_same_address'    => 1,
        ];
    }

    /**
     * An individual (non-company) customer.
     */
    public function individual(): static
    {
        return $this->state(fn () => [
            'type'         => 2,
            'company_name' => null,
            'first_name'   => $this->faker->firstName(),
            'last_name'    => $this->faker->lastName(),
        ]);
    }

    /**
     * A customer with no tax_id on file - for testing the F2 (simplified
     * invoice) path, or the validation error when F1 is required.
     */
    public function withoutTaxId(): static
    {
        return $this->state(fn () => ['tax_id' => null]);
    }

    /**
     * A customer identified via AEAT's IDOtro branch (foreign tax/identity
     * document, Orden HAC/1177/2024 Anexo lista L7) instead of a Spanish
     * NIF - e.g. "03" (Pasaporte). See Customer::hasForeignTaxId().
     */
    public function foreignTaxId(string $type = '03', ?string $id = null): static
    {
        return $this->state(fn () => [
            'tax_id'              => null,
            'foreign_tax_id_type' => $type,
            'foreign_tax_id'      => $id ?? $this->faker->numerify('FR#########'),
        ]);
    }
}
