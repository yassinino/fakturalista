<?php

namespace App\Services\Analytics;

use MaxMind\Db\Reader;
use Throwable;

/**
 * Country of an IP from a LOCAL MaxMind-format database (no external API
 * call per page view - config('analytics.geoip.database')).
 *
 * The IP given here is request()->ip(): with App\Http\Middleware\TrustProxies
 * trusting no proxy (the production default: nginx serves PHP directly), it
 * is the TCP peer address, so a spoofed X-Forwarded-For header is ignored.
 * Returns null ("Unknown") whenever the country can't be determined.
 */
class CountryResolver
{
    private ?Reader $reader = null;
    private bool $unavailable = false;

    public function countryCode(?string $ip): ?string
    {
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $reader = $this->reader();
        if (! $reader) {
            return null;
        }

        try {
            $code = $reader->get($ip)['country']['iso_code'] ?? null;
        } catch (Throwable) {
            return null;
        }

        return is_string($code) && preg_match('~^[A-Z]{2}$~', $code) && $code !== 'ZZ' ? $code : null;
    }

    public function isAvailable(): bool
    {
        return $this->reader() !== null;
    }

    private function reader(): ?Reader
    {
        if ($this->reader || $this->unavailable) {
            return $this->reader;
        }

        $path = config('analytics.geoip.database');
        try {
            if ($path && is_readable($path)) {
                return $this->reader = new Reader($path);
            }
        } catch (Throwable $e) {
            report($e);
        }
        $this->unavailable = true;

        return null;
    }
}
