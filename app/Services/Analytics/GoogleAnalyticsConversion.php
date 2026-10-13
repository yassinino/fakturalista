<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Server-side GA4 conversion events (Measurement Protocol).
 *
 * The free-trial signup redirects straight to the new tenant subdomain, so
 * the browser never renders a "thank you" page on fakturalista.com where
 * gtag could record it. This sends the `sign_up` event from the server,
 * attached to the visitor's GA client id (the `_ga` cookie) so it joins
 * their acquisition source. No personal data is sent: no email, name or
 * tenant identifier.
 *
 * Inactive unless config('seo.ga4.api_secret') (GA4_API_SECRET) is set.
 */
class GoogleAnalyticsConversion
{
    public function signUp(Request $request): void
    {
        $measurementId = config('seo.ga4.measurement_id');
        $apiSecret     = config('seo.ga4.api_secret');

        if (! config('seo.ga4.enabled') || ! $measurementId || ! $apiSecret) {
            return;
        }

        $clientId = self::clientId($request->cookies->get('_ga'));

        // After the response: never slow down or break a signup for analytics.
        dispatch(function () use ($measurementId, $apiSecret, $clientId) {
            try {
                Http::timeout(3)->post(
                    'https://www.google-analytics.com/mp/collect?' . http_build_query([
                        'measurement_id' => $measurementId,
                        'api_secret'     => $apiSecret,
                    ]),
                    [
                        'client_id' => $clientId,
                        'events'    => [[
                            'name'   => 'sign_up',
                            'params' => ['method' => 'free_trial'],
                        ]],
                    ]
                );
            } catch (Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }

    /** "GA1.1.123456789.1700000000" -> "123456789.1700000000"; random if absent. */
    public static function clientId(?string $gaCookie): string
    {
        if ($gaCookie && preg_match('/^GA\d\.\d+\.(\d+\.\d+)$/', $gaCookie, $m)) {
            return $m[1];
        }

        return random_int(100000000, 999999999) . '.' . time();
    }
}
