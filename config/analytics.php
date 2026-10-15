<?php

/*
 * First-party, cookieless website analytics (Filament: "Analytics").
 * See App\Services\Analytics\* and docs in the classes. Definitions:
 *
 *  - Page view:  one tracked load of a public page (beacon accepted).
 *  - Visitor:    an anonymous daily identifier = hash(daily secret salt +
 *                truncated IP + user agent). The salt is random, stored
 *                server-side only and destroyed after the day, so the same
 *                person cannot be recognised across days and nothing is
 *                stored on their device. "Unique visitors" over several
 *                days is therefore the SUM of daily unique visitors
 *                (an estimate, shown as such).
 *  - Visit:      a series of page views by one visitor with no gap longer
 *                than `visit_timeout_minutes`; its traffic source is the
 *                one of its first page view.
 *  - Registration: a self-service signup the backend completed
 *                (RegisterTrialController), never a button click.
 */
return [

    'enabled' => (bool) env('ANALYTICS_ENABLED', true),

    // Days are counted in this timezone (Morocco is the primary market).
    'timezone' => env('ANALYTICS_TIMEZONE', 'Africa/Casablanca'),

    // Raw page views / registrations older than this are deleted daily
    // (analytics:prune). 395 days = 13 months, within the CNIL guidance for
    // audience-measurement data (25 months max).
    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 395),

    'visit_timeout_minutes' => 30,

    // Ignore the same visitor reloading the same page within N seconds.
    'dedupe_seconds' => 10,

    // A beacon must arrive within this delay after the page was rendered.
    'token_ttl_minutes' => 360,

    // Honour browser opt-out signals (Do Not Track / Global Privacy Control).
    'respect_dnt' => true,

    // Local GeoIP database (MaxMind DB format, e.g. DB-IP "IP to Country
    // Lite", CC BY 4.0). Refreshed monthly by analytics:geoip-update.
    // Missing file = country "Unknown", never a guess.
    'geoip' => [
        'database'     => env('ANALYTICS_GEOIP_DATABASE', storage_path('app/geoip/dbip-country-lite.mmdb')),
        'download_url' => env('ANALYTICS_GEOIP_URL', 'https://download.db-ip.com/free/dbip-country-lite-{YYYY}-{MM}.mmdb.gz'),
    ],

    // Own hosts: a referrer from these is internal navigation, not a source.
    'internal_hosts' => ['fakturalista.com', 'www.fakturalista.com', 'fakturalista.test'],
];
