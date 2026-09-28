<?php

namespace App\Services\EInvoicing\DTO;

/**
 * Generic e-invoicing "party" (seller or customer) - Step 1 of the
 * e-invoicing groundwork (see docs mentioned in the mapper). Deliberately
 * country-agnostic: no field here is Morocco- or Spain-specific.
 *
 * `taxIdentifiers` is a flat, generic label => value map instead of
 * fixed ICE/IF/RC/VAT/NIF properties, because which identifiers exist -
 * and what they're called - already differs per tenant country in
 * Fakturalista itself (CompanyProfile/Customer::identitySnapshot()).
 * This DTO only ever carries whatever identifiers the source record
 * already has; it never invents or requires a specific one.
 */
final class EInvoicePartyData
{
    /**
     * @param array<string, string> $taxIdentifiers e.g. ['ice' => '...', 'if_number' => '...', 'vat_number' => '...'] - only non-empty ones are included.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $tradeName,
        public readonly ?string $addressLine1,
        public readonly ?string $addressLine2,
        public readonly ?string $city,
        public readonly ?string $postalCode,
        public readonly ?string $countryCode,
        public readonly ?string $countryName,
        public readonly array $taxIdentifiers,
        public readonly ?string $email,
        public readonly ?string $phone,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name'            => $this->name,
            'trade_name'      => $this->tradeName,
            'address_line1'   => $this->addressLine1,
            'address_line2'   => $this->addressLine2,
            'city'            => $this->city,
            'postal_code'     => $this->postalCode,
            'country_code'    => $this->countryCode,
            'country_name'    => $this->countryName,
            'tax_identifiers' => $this->taxIdentifiers,
            'email'           => $this->email,
            'phone'           => $this->phone,
        ];
    }
}
