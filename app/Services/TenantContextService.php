<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Country;
use App\Services\Tax\TaxPresetService;

/**
 * Single source of truth for "what country/currency/locale/timezone is the
 * current tenant" - Morocco Phase 1A (docs/morocco-phase-1a-implementation.md).
 *
 * Authoritative source, in order:
 *   1. CompanyProfile (tenant DB) - the tenant's own, user-editable fiscal
 *      identity. Once it exists, it is authoritative for every business
 *      document (PDF/email) and for country-gated features like VERI*FACTU.
 *   2. Tenant (central DB) - the provisioning-time values set when the
 *      tenant was created. Used only as a fallback for a brand new
 *      CompanyProfile row (see ensureCompanyProfile()) or when no
 *      CompanyProfile exists at all yet.
 *   3. Hardcoded defaults (Morocco) - the ultimate safety net, reached only
 *      when neither of the above has a value. This does NOT retroactively
 *      change any existing tenant: every existing CompanyProfile row
 *      already has real, persisted values from its own schema defaults.
 */
class TenantContextService
{
    public const DEFAULT_COUNTRY  = 'MA';
    public const DEFAULT_CURRENCY = 'MAD';
    public const DEFAULT_LOCALE   = 'fr';
    public const DEFAULT_TIMEZONE = 'Africa/Casablanca';

    private ?CompanyProfile $company = null;
    private bool $companyLoaded = false;

    public static function defaultsForCountry(string $country = self::DEFAULT_COUNTRY): array
    {
        return strtoupper($country) === 'ES'
            ? ['country' => 'ES', 'currency' => 'EUR', 'locale' => 'es', 'timezone' => 'Europe/Madrid']
            : ['country' => strtoupper($country), 'currency' => self::DEFAULT_CURRENCY, 'locale' => self::DEFAULT_LOCALE, 'timezone' => self::DEFAULT_TIMEZONE];
    }

    public function countryName(): string
    {
        return Country::where('code', $this->country())->value('name') ?? $this->country();
    }

    public function toArray(): array
    {
        return [
            'country' => $this->country(),
            'country_name' => $this->countryName(),
            'currency' => $this->currency(),
            'locale' => $this->locale(),
            'timezone' => $this->timezone(),
        ];
    }

    public function country(): string
    {
        return strtoupper($this->company()?->country_code
            ?: optional($this->tenant())->country
            ?: self::DEFAULT_COUNTRY);
    }

    public function currency(): string
    {
        return $this->company()?->currency
            ?: optional($this->tenant())->currency
            ?: self::defaultsForCountry($this->country())['currency'];
    }

    public function locale(): string
    {
        $locale = $this->company()?->locale
            ?: optional($this->tenant())->language
            ?: self::defaultsForCountry($this->country())['locale'];

        $supported = config('app.supported_locales', ['es', 'fr', 'en']);

        return in_array($locale, $supported, true) ? $locale : self::DEFAULT_LOCALE;
    }

    public function timezone(): string
    {
        return $this->company()?->timezone
            ?: optional($this->tenant())->timezone
            ?: self::defaultsForCountry($this->country())['timezone'];
    }

    public function isSpain(): bool
    {
        return strtoupper($this->country()) === 'ES';
    }

    /**
     * Return the tenant's CompanyProfile, creating it if it doesn't exist
     * yet - seeded from this tenant's own provisioning-time country/
     * currency/language/timezone rather than the table's Spain-shaped
     * schema defaults, so a fresh Moroccan tenant is never silently
     * defaulted to Spain. Never touches an already-existing row.
     */
    public function ensureCompanyProfile(): CompanyProfile
    {
        $tenant = $this->tenant();
        $country = strtoupper($tenant?->country ?: self::DEFAULT_COUNTRY);
        $defaults = self::defaultsForCountry($country);

        $profile = CompanyProfile::firstOrCreate([], [
            'legal_name'   => '',
            'country_code' => $country,
            'country' => Country::where('code', $country)->value('name') ?? $country,
            'default_tax_code' => app(TaxPresetService::class)->getDefaultForCountry($country)?->code,
            'currency'     => $tenant?->currency ?: $defaults['currency'],
            'locale'       => $tenant?->language ?: $defaults['locale'],
            'timezone'     => $tenant?->timezone ?: $defaults['timezone'],
        ]);

        $this->company      = $profile;
        $this->companyLoaded = true;

        return $profile;
    }

    private function company(): ?CompanyProfile
    {
        if (!$this->companyLoaded) {
            $this->company      = CompanyProfile::first();
            $this->companyLoaded = true;
        }

        return $this->company;
    }

    private function tenant()
    {
        return function_exists('tenancy') ? tenancy()->tenant : null;
    }
}
