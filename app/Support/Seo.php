<?php

namespace App\Support;

/**
 * Public-site URL helpers for SEO (config/seo.php).
 *
 * - url():        internal link on the current host, carrying ?lang= for a
 *                 non-default language so crawlers following links stay in
 *                 that language version.
 * - canonical():  absolute URL on the canonical host (canonical, hreflang,
 *                 sitemap, Open Graph, JSON-LD).
 * - alternates(): hreflang => URL map (+ x-default) for a page.
 */
class Seo
{
    public static function defaultLocale(): string
    {
        return config('seo.default_locale', 'fr');
    }

    /** @return string[] */
    public static function urlLocales(): array
    {
        return config('seo.url_locales', ['fr']);
    }

    /** Current locale if it has its own URL, the default locale otherwise. */
    public static function locale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, self::urlLocales(), true) ? $locale : self::defaultLocale();
    }

    /**
     * Host used by route() for public-site routes when no site request is
     * being served (tenant subdomains, queue, console): APP_URL's host when
     * it is a site domain (fakturalista.test locally), else the canonical one.
     */
    public static function siteDomain(): string
    {
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return in_array($appHost, config('seo.site_domains', []), true)
            ? $appHost
            : (string) parse_url((string) config('seo.canonical_url'), PHP_URL_HOST);
    }

    public static function url(string $path, ?string $locale = null): string
    {
        return self::withLang(url($path), $locale ?? self::locale());
    }

    public static function canonical(string $path, ?string $locale = null): string
    {
        $path = '/' . ltrim($path, '/');
        $base = config('seo.canonical_url');

        return self::withLang($path === '/' ? $base . '/' : $base . rtrim($path, '/'), $locale ?? self::defaultLocale());
    }

    /**
     * @param  string[]|null              $locales  locales the page exists in
     * @param  array<string,string>|null  $hreflang locale => hreflang code
     * @return array<string,string>       hreflang => absolute URL
     */
    public static function alternates(string $path, ?array $locales = null, ?array $hreflang = null): array
    {
        $locales  = $locales ?? self::urlLocales();
        $hreflang = $hreflang ?? config('seo.hreflang', []);

        $out = [];
        foreach ($locales as $locale) {
            $out[$hreflang[$locale] ?? $locale] = self::canonical($path, $locale);
        }

        if (count($out) > 1) {
            $out['x-default'] = self::canonical($path, in_array(self::defaultLocale(), $locales, true) ? self::defaultLocale() : $locales[0]);
        }

        return $out;
    }

    public static function asset(string $path): string
    {
        return config('seo.canonical_url') . '/' . ltrim($path, '/');
    }

    /** JSON for <script type="application/ld+json"> - safe inside HTML. */
    public static function jsonLd(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /**
     * SoftwareApplication node with the real Moroccan monthly prices from
     * plan_prices (the same rows /pricing renders) - never a rating or a
     * price that isn't in the database.
     *
     * @param  iterable<\App\Models\Plan>  $plans  with 'prices' loaded
     */
    public static function softwareApplication(iterable $plans, string $description, string $locale): array
    {
        $offers = [];
        foreach ($plans as $plan) {
            $price = $plan->priceFor('MA', 'monthly');
            if (! $price || $price->country_code !== 'MA') {
                continue;
            }
            $offers[] = [
                '@type'         => 'Offer',
                'name'          => $plan->translate('name', $locale) ?: $plan->slug,
                'price'         => number_format($price->amount / 100, 2, '.', ''),
                'priceCurrency' => $price->currency,
                'url'           => self::canonical('/pricing', $locale),
                'category'      => 'subscription',
            ];
        }

        return array_filter([
            '@type'               => 'SoftwareApplication',
            '@id'                 => config('seo.canonical_url') . '/#software',
            'name'                => 'Fakturalista',
            'url'                 => config('seo.canonical_url') . '/',
            'description'         => $description,
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Invoicing software',
            'operatingSystem'     => 'Web',
            'inLanguage'          => ['fr', 'ar', 'en'],
            'publisher'           => ['@id' => self::organizationId()],
            'offers'              => $offers ?: null,
        ]);
    }

    /** @param array<int, array{0:string,1:string}> $pairs question/answer */
    public static function faqPage(array $pairs): array
    {
        return [
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn ($qa) => [
                '@type'          => 'Question',
                'name'           => $qa[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
            ], $pairs),
        ];
    }

    /** Trial length shown in CTAs - read from the active plans, never hardcoded. */
    public static function trialDays(): int
    {
        static $days;

        return $days ??= (int) (\App\Models\Plan::on('mysql')->where('active', true)->max('trial_days') ?: 14);
    }

    public static function organizationId(): string
    {
        return config('seo.canonical_url') . '/#organization';
    }

    private static function withLang(string $url, string $locale): string
    {
        if ($locale === self::defaultLocale() || ! in_array($locale, self::urlLocales(), true)) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'lang=' . $locale;
    }
}
