<?php

namespace App\Services;

/**
 * Centralized amount formatting for business documents (PDF, email) -
 * Morocco Phase 1A. Currency and locale are inputs, never assumed - see
 * TenantContextService for how they're resolved for the current tenant.
 *
 * Deliberately not a global find-and-replace of "€" with "MAD": the
 * separators are driven by locale, the suffix by currency, so any
 * currency/locale combination (not just EUR/es and MAD/fr) renders
 * correctly.
 */
class CurrencyFormatter
{
    /**
     * Currencies with a single, unambiguous, widely-recognized glyph.
     * Anything else renders with its plain ISO 4217 code instead of a
     * guessed symbol (e.g. "1 250,00 MAD", not an invented "DH"/"د.م.").
     */
    private const SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
    ];

    public function format(float $amount, string $currency, string $locale): string
    {
        [$decimalSep, $thousandsSep] = self::separators($locale);
        $number = number_format($amount, 2, $decimalSep, $thousandsSep);
        $suffix = self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency);

        return $number . ' ' . $suffix;
    }

    public function formatNumber(float $amount, string $locale): string
    {
        [$decimalSep, $thousandsSep] = self::separators($locale);

        return number_format($amount, 2, $decimalSep, $thousandsSep);
    }

    /**
     * @return array{0: string, 1: string} [decimal separator, thousands separator]
     */
    public static function separators(string $locale): array
    {
        return match ($locale) {
            'en'    => ['.', ','],
            'fr'    => [',', ' '],
            default => [',', '.'], // es
        };
    }
}
