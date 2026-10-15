<?php

/*
 * Public-site SEO settings - read through App\Support\Seo.
 *
 * Language lives in the URL (?lang=ar|en; French is the default, so it has
 * no parameter) so every language version is crawlable on its own URL,
 * with canonical + hreflang pointing between them. The visitor's session
 * still remembers the choice (SetLocale middleware).
 */
return [

    // Absolute base of every canonical/hreflang/sitemap URL. The live site
    // serves https://fakturalista.com as the primary host; www and any other
    // production alias 301-redirect to it (RedirectToCanonicalHost).
    'canonical_url' => rtrim(env('SEO_CANONICAL_URL', 'https://fakturalista.com'), '/'),

    // Hosts serving the public site (routes/web.php, one route group matched
    // with a {siteDomain} host pattern so every route name exists once and
    // `php artisan route:cache` works). Tenant subdomains are not listed.
    'site_domains' => ['fakturalista.com', 'www.fakturalista.com', 'fakturalista.test'],

    // Production hosts that must answer with a 301 to canonical_url's host.
    // Local/dev hosts (fakturalista.test) are never redirected.
    'redirect_hosts' => ['www.fakturalista.com'],

    'default_locale' => 'fr',

    // Locales exposed as their own URL (?lang=xx) and announced in hreflang.
    // Spanish stays supported in the session (lang switcher hidden) but has
    // no indexable URL of its own.
    'url_locales' => ['fr', 'ar', 'en'],

    // hreflang for the generic (market-neutral) pages.
    'hreflang' => [
        'fr' => 'fr',
        'ar' => 'ar',
        'en' => 'en',
    ],

    'default_image' => 'assets/panel_fakturalista.png',

    'organization' => [
        'name'        => 'Fakturalista',
        'logo'        => 'assets/logo.svg',
        'email'       => env('FAKTURALISTA_CONTACT_EMAIL', 'contact@fakturalista.com'),
        'telephone'   => env('FAKTURALISTA_CONTACT_PHONE_SCHEMA', '+212601878155'),
        'area_served' => ['MA'],
        // Official profiles only (LinkedIn, Facebook, ...) - leave empty
        // rather than guessing.
        'same_as'     => array_values(array_filter(explode(',', (string) env('SEO_SAME_AS', '')))),
    ],

    // Search engine ownership verification (<meta> tags). Empty = not rendered.
    'verification' => [
        'google' => env('GOOGLE_SITE_VERIFICATION'),
        'bing'   => env('BING_SITE_VERIFICATION'),
    ],

    // Google Analytics 4. The measurement id below is the one the site
    // already used (previously hardcoded in layouts/master); set GA4_ENABLED
    // =false to switch analytics off entirely. GA4_API_SECRET enables the
    // server-side `sign_up` conversion (GoogleAnalyticsConversion).
    'ga4' => [
        'enabled'        => (bool) env('GA4_ENABLED', true),
        'measurement_id' => env('GA4_MEASUREMENT_ID', 'G-YY0S2MP872'),
        'api_secret'     => env('GA4_API_SECRET'),
    ],

    // Replaced public URLs -> their permanent replacement (301).
    'redirects' => [
        '/free-trial' => '/register',
    ],

    // Public URLs that were removed for good (410 Gone). Add paths here
    // when a page is deleted without a replacement.
    'gone' => [],
];
