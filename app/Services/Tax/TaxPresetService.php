<?php

namespace App\Services\Tax;

use App\Models\CompanyProfile;
use App\Services\TenantContextService;

/**
 * The one central source of which tax options a tenant may pick from -
 * Morocco Phase 1C.2 (docs/morocco-phase-1c2-tax-configuration.md §1/§2).
 *
 * System-defined, not a database table: the Phase 1C audit's own
 * recommendation ("Option B") was to avoid a TaxRate catalog table
 * unless there's a strong reason, and there isn't one yet - nothing in
 * this phase needs per-tenant custom rates, only a fixed, per-country
 * list a tenant chooses from. If that ever changes, this class is the
 * one place to become DB-backed without touching any caller (every
 * caller already only sees TaxPreset objects, never this class's
 * internals).
 *
 * Presets are NOT legal advice and this class does not claim any
 * business may freely choose between them - it only exposes what the
 * UI offers; which one actually applies to a given operation remains a
 * human decision (see docs/morocco-phase-1c2-tax-configuration.md
 * "remaining limitations").
 */
class TaxPresetService
{
    public function __construct(private TenantContextService $context)
    {
    }

    public function forTenant(): array
    {
        return $this->getForCountry($this->context->country());
    }

    public function defaultForTenant(): ?TaxPreset
    {
        $stored = $this->find(CompanyProfile::value('default_tax_code'));

        return $stored && $stored->countryCode === strtoupper($this->context->country())
            ? $stored
            : $this->getDefaultForCountry($this->context->country());
    }

    public function taxName(string $country): string
    {
        return match (strtoupper($country)) {
            'MA' => 'TVA',
            'ES' => 'IVA',
            default => 'Tax',
        };
    }

    public function label(string $country, float $rate, string $treatment): string
    {
        return match ($treatment) {
            TaxTreatment::EXEMPT => 'Exonéré',
            TaxTreatment::OUT_OF_SCOPE => 'Out of scope',
            default => $this->taxName($country) . ' ' . $rate . '%',
        };
    }

    /**
     * @return array<int, TaxPreset>
     */
    public function getForCountry(string $countryCode): array
    {
        return self::catalog()[strtoupper($countryCode)] ?? [];
    }

    public function getDefaultForCountry(string $countryCode): ?TaxPreset
    {
        foreach ($this->getForCountry($countryCode) as $preset) {
            if ($preset->isDefault) {
                return $preset;
            }
        }

        return $this->getForCountry($countryCode)[0] ?? null;
    }

    /**
     * Looks a preset up by its own code, regardless of country - used to
     * resolve a tenant's stored `default_tax_code` (Settings, §11)
     * without needing to also know the country up front.
     */
    public function find(?string $code): ?TaxPreset
    {
        if (empty($code)) {
            return null;
        }

        foreach (self::catalog() as $presets) {
            foreach ($presets as $preset) {
                if ($preset->code === $code) {
                    return $preset;
                }
            }
        }

        return null;
    }

    /** @var array<string, array<int, TaxPreset>>|null */
    private static ?array $builtCatalog = null;

    /**
     * Spain: preserves exactly the pre-existing 21/10/4% IVA choices
     * (Phase 1C audit §5/§14) - no change to what a Spanish tenant sees.
     *
     * Morocco: the initial TVA 20%/10% + Exonéré options (Phase 1C.2 §3).
     * Not a claim that every Moroccan business may choose freely between
     * them - see the class docblock. Built once, lazily (PHP doesn't
     * allow object instances in a class constant), rather than a DB
     * table - see the class docblock for why.
     */
    private static function catalog(): array
    {
        if (self::$builtCatalog !== null) {
            return self::$builtCatalog;
        }

        return self::$builtCatalog = [
            'ES' => [
                new TaxPreset('ES_IVA_21', 'ES', 21.0, TaxTreatment::TAXABLE, isDefault: true),
                new TaxPreset('ES_IVA_10', 'ES', 10.0, TaxTreatment::TAXABLE),
                new TaxPreset('ES_IVA_4',  'ES', 4.0,  TaxTreatment::TAXABLE),
            ],
            'MA' => [
                new TaxPreset('MA_TVA_20', 'MA', 20.0, TaxTreatment::TAXABLE, isDefault: true),
                new TaxPreset('MA_TVA_10', 'MA', 10.0, TaxTreatment::TAXABLE),
                new TaxPreset('MA_EXEMPT', 'MA', 0.0,  TaxTreatment::EXEMPT),
            ],
        ];
    }
}
