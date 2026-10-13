<?php

namespace App\Support;

/**
 * Market-specific SEO landing pages (MarketLandingController). Content lives
 * in resources/lang/{fr,ar}/landing.php under each page's `key`.
 *
 * Morocco only for now: France pages wait until the product supports French
 * invoicing (TVA presets, pricing market) - see the SEO report.
 */
class MarketLandingPages
{
    public const PAGES = [
        'ma' => [
            'logiciel-facturation'     => ['key' => 'ma_invoicing',  'priority' => 0.9],
            'logiciel-devis-facture'   => ['key' => 'ma_quotes',     'priority' => 0.9],
            'facturation-electronique' => ['key' => 'ma_einvoicing', 'priority' => 0.8],
            'facturation-btp'          => ['key' => 'ma_btp',        'priority' => 0.8],
        ],
    ];

    public const MARKETS = [
        'ma' => [
            'locales'  => ['fr', 'ar'],
            'hreflang' => ['fr' => 'fr-MA', 'ar' => 'ar-MA'],
        ],
    ];

    public static function find(string $market, string $slug): ?array
    {
        $page = self::PAGES[$market][$slug] ?? null;

        return $page ? $page + self::MARKETS[$market] + ['market' => $market, 'slug' => $slug, 'path' => "/{$market}/{$slug}"] : null;
    }

    /** @return array<int, array> every page, resolved like find() */
    public static function all(): array
    {
        $out = [];
        foreach (self::PAGES as $market => $pages) {
            foreach (array_keys($pages) as $slug) {
                $out[] = self::find($market, $slug);
            }
        }

        return $out;
    }
}
