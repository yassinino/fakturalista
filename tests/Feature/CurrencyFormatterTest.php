<?php

namespace Tests\Feature;

use App\Services\CurrencyFormatter;
use Tests\TestCase;

/**
 * Morocco Phase 1A - docs/morocco-phase-1a-implementation.md §3.
 *
 * Currency is never a blanket "replace € with MAD" - separators come from
 * locale, the suffix from the actual currency, so both Morocco and Spain
 * (and anything else) render correctly from the same mechanism.
 */
class CurrencyFormatterTest extends TestCase
{
    /** @test */
    public function morocco_amount_uses_french_separators_and_the_mad_code_not_a_guessed_symbol(): void
    {
        $formatted = app(CurrencyFormatter::class)->format(1250.0, 'MAD', 'fr');

        $this->assertEquals('1 250,00 MAD', $formatted);
        $this->assertStringNotContainsString('€', $formatted);
    }

    /** @test */
    public function spain_amount_keeps_its_existing_locale_formatting_and_the_euro_symbol(): void
    {
        $formatted = app(CurrencyFormatter::class)->format(1250.0, 'EUR', 'es');

        $this->assertEquals('1.250,00 €', $formatted);
    }

    /** @test */
    public function a_currency_with_no_well_known_symbol_falls_back_to_its_plain_iso_code(): void
    {
        $formatted = app(CurrencyFormatter::class)->format(100.0, 'TND', 'en');

        $this->assertEquals('100.00 TND', $formatted);
    }

    /** @test */
    public function formatting_the_same_amount_in_two_currencies_never_reuses_the_others_suffix(): void
    {
        $formatter = app(CurrencyFormatter::class);

        $mad = $formatter->format(500.0, 'MAD', 'fr');
        $eur = $formatter->format(500.0, 'EUR', 'fr');

        $this->assertStringContainsString('MAD', $mad);
        $this->assertStringNotContainsString('€', $mad);
        $this->assertStringContainsString('€', $eur);
        $this->assertStringNotContainsString('MAD', $eur);
    }
}
