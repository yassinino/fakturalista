<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsConversion;
use App\Models\AnalyticsPageview;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * First-party, cookieless page-view and conversion tracking.
 *
 * Flow: a public page embeds a small signed payload (pagePayload()); the
 * browser posts it back once the page is visible (partials/analytics-
 * tracker); recordPageview() verifies the signature, filters bots/opt-outs
 * and stores one row. The signature means only pages we actually rendered
 * can be recorded, with the path/title/locale we rendered - a forged
 * beacon can't inject arbitrary pages. Nothing here may ever break a page:
 * every public method swallows its own errors.
 */
class AnalyticsTracker
{
    public function __construct(
        private readonly VisitorIdentifier $visitors,
        private readonly UserAgentClassifier $agents,
        private readonly TrafficSourceClassifier $sources,
        private readonly CountryResolver $countries,
    ) {}

    // ── Page payload (rendered into public pages) ────────────────────────

    /** Null when this page view must not be tracked at all. */
    public function pagePayload(Request $request, ?string $title): ?array
    {
        try {
            if (! config('analytics.enabled')
                || tenancy()->initialized
                || auth('admin')->check()
                || $request->method() !== 'GET') {
                return null;
            }

            $data = [
                'p'  => self::normalizePath($request->path()),
                't'  => Str::limit(trim((string) $title), 250, ''),
                'l'  => app()->getLocale(),
                'ts' => time(),
            ];
            $data['s'] = $this->sign($data);

            return $data;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    // ── Beacon ───────────────────────────────────────────────────────────

    /** @return bool whether a row was stored (for tests/diagnostics only) */
    public function recordPageview(Request $request, array $data): bool
    {
        try {
            if (! $this->shouldRecord($request, $data)) {
                return false;
            }

            $now   = CarbonImmutable::now();
            $local = $now->setTimezone(config('analytics.timezone'));
            $ua    = (string) $request->userAgent();
            $hash  = $this->visitors->forRequest($request->ip(), $ua, $local);
            $path  = $data['p'];

            // A reload (or a replayed beacon) of the same page right away.
            $recent = AnalyticsPageview::where('visitor_hash', $hash)
                ->where('path', $path)
                ->where('viewed_at', '>=', $now->subSeconds(config('analytics.dedupe_seconds')))
                ->exists();
            if ($recent) {
                return false;
            }

            $utm = [
                'utm_source'   => self::cleanParam($data['us'] ?? null, 100),
                'utm_medium'   => self::cleanParam($data['um'] ?? null, 100),
                'utm_campaign' => self::cleanParam($data['uc'] ?? null, 150),
            ];
            $origin = $this->sources->classify(
                is_string($data['r'] ?? null) ? Str::limit($data['r'], 2000, '') : null,
                $utm,
                config('analytics.internal_hosts', [])
            );

            $lastView = AnalyticsPageview::where('visitor_hash', $hash)
                ->where('view_date', $local->toDateString())
                ->max('viewed_at');
            $isEntrance = ! $lastView
                || CarbonImmutable::parse($lastView)->lt($now->subMinutes(config('analytics.visit_timeout_minutes')))
                || $origin['source'] === 'campaign';

            if (! $isEntrance) {
                // Mid-visit page: the visit keeps its entry source.
                $origin = ['source' => 'internal', 'source_name' => null, 'referrer_host' => $origin['referrer_host']];
            }

            AnalyticsPageview::create([
                'viewed_at'     => $now,
                'view_date'     => $local->toDateString(),
                'visitor_hash'  => $hash,
                'is_entrance'   => $isEntrance,
                'path'          => $path,
                'title'         => $data['t'] !== '' ? $data['t'] : null,
                'locale'        => $data['l'],
                'country_code'  => $this->countries->countryCode($request->ip()),
                'device'        => $this->agents->device($ua),
                'source'        => $origin['source'],
                'source_name'   => $origin['source_name'] ? Str::limit($origin['source_name'], 100, '') : null,
                'referrer_host' => $origin['referrer_host'] ? Str::limit($origin['referrer_host'], 255, '') : null,
            ] + ($isEntrance ? $utm : []));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    // ── Conversions (called by the backend after a confirmed success) ────

    public function recordSignup(Request $request): void
    {
        try {
            if (! config('analytics.enabled')) {
                return;
            }

            $now   = CarbonImmutable::now();
            $local = $now->setTimezone(config('analytics.timezone'));
            $ua    = (string) $request->userAgent();
            $hash  = $this->agents->isBot($ua) ? null : $this->visitors->forRequest($request->ip(), $ua, $local);

            // Attribute to the source of this visitor's latest visit today, if any.
            $entry = $hash ? AnalyticsPageview::where('visitor_hash', $hash)
                ->where('view_date', $local->toDateString())
                ->where('is_entrance', true)
                ->latest('viewed_at')
                ->first(['source', 'source_name']) : null;

            AnalyticsConversion::create([
                'type'            => AnalyticsConversion::FREE_TRIAL_SIGNUP,
                'converted_at'    => $now,
                'conversion_date' => $local->toDateString(),
                'visitor_hash'    => $entry ? $hash : null,
                'country_code'    => $this->countries->countryCode($request->ip()),
                'source'          => $entry?->source,
                'source_name'     => $entry?->source_name,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function shouldRecord(Request $request, array $data): bool
    {
        if (! config('analytics.enabled') || auth('admin')->check()) {
            return false;
        }
        if (config('analytics.respect_dnt') && ($request->header('DNT') === '1' || $request->header('Sec-GPC') === '1')) {
            return false;
        }
        if ($this->agents->isBot($request->userAgent())) {
            return false;
        }

        foreach (['p', 't', 'l', 'ts', 's'] as $key) {
            if (! isset($data[$key]) || ! is_scalar($data[$key])) {
                return false;
            }
        }
        $signed = ['p' => (string) $data['p'], 't' => (string) $data['t'], 'l' => (string) $data['l'], 'ts' => (int) $data['ts']];
        if (! hash_equals($this->sign($signed), (string) $data['s'])) {
            return false;
        }

        $age = time() - $signed['ts'];

        return $age >= -60 && $age <= config('analytics.token_ttl_minutes') * 60;
    }

    private function sign(array $data): string
    {
        $payload = implode("\n", [$data['p'], $data['t'], $data['l'], $data['ts']]);

        return hash_hmac('sha256', 'analytics|' . $payload, (string) config('app.key'));
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return Str::limit($path, 250, '');
    }

    private static function cleanParam(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        // Campaign labels only: no email-like or overly long values.
        $value = trim(strtolower($value));
        if ($value === '' || str_contains($value, '@') || ! preg_match('~^[\p{L}\p{N} ._+\-/|:]+$~u', $value)) {
            return null;
        }

        return Str::limit($value, $max, '');
    }
}
