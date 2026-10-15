<?php

namespace App\Services\Analytics;

/**
 * Where a visit came from, from the referrer and UTM parameters only.
 *
 *  - campaign: any utm_source / utm_medium / utm_campaign present
 *  - search / social / ai: known referrer hosts
 *  - referral: any other external website
 *  - direct: no referrer at all. Browsers also hide the referrer in some
 *    cases (apps, privacy settings), so this bucket is labelled
 *    "Direct / sans référent" in the dashboard, not "typed the URL".
 *  - internal: navigation within the site (not a traffic source)
 */
class TrafficSourceClassifier
{
    private const SEARCH = [
        'google'     => '~(^|\.)google\.[a-z.]+$~',
        'bing'       => '~(^|\.)bing\.com$~',
        'yahoo'      => '~(^|\.)yahoo\.[a-z.]+$~',
        'duckduckgo' => '~(^|\.)duckduckgo\.com$~',
        'ecosia'     => '~(^|\.)ecosia\.org$~',
        'qwant'      => '~(^|\.)qwant\.com$~',
        'yandex'     => '~(^|\.)yandex\.[a-z.]+$~',
        'baidu'      => '~(^|\.)baidu\.com$~',
        'brave'      => '~^search\.brave\.com$~',
    ];

    private const SOCIAL = [
        'facebook'  => '~(^|\.)(facebook\.com|fb\.me|fb\.com)$~',
        'instagram' => '~(^|\.)instagram\.com$~',
        'linkedin'  => '~(^|\.)(linkedin\.com|lnkd\.in)$~',
        'x'         => '~(^|\.)(twitter\.com|x\.com|t\.co)$~',
        'youtube'   => '~(^|\.)(youtube\.com|youtu\.be)$~',
        'tiktok'    => '~(^|\.)tiktok\.com$~',
        'whatsapp'  => '~(^|\.)(whatsapp\.com|wa\.me)$~',
        'pinterest' => '~(^|\.)pinterest\.[a-z.]+$~',
        'reddit'    => '~(^|\.)reddit\.com$~',
        'snapchat'  => '~(^|\.)snapchat\.com$~',
        'telegram'  => '~(^|\.)(t\.me|telegram\.org)$~',
    ];

    private const AI = [
        'chatgpt'    => '~(^|\.)(chatgpt\.com|chat\.openai\.com)$~',
        'perplexity' => '~(^|\.)perplexity\.ai$~',
        'gemini'     => '~^gemini\.google\.com$~',
        'copilot'    => '~^copilot\.microsoft\.com$~',
        'claude'     => '~^claude\.ai$~',
    ];

    /**
     * @param  array{utm_source?: ?string, utm_medium?: ?string, utm_campaign?: ?string}  $utm
     * @return array{source: string, source_name: ?string, referrer_host: ?string}
     */
    public function classify(?string $referrer, array $utm, array $internalHosts): array
    {
        $host = $this->host($referrer);

        if (! empty($utm['utm_source']) || ! empty($utm['utm_medium']) || ! empty($utm['utm_campaign'])) {
            return ['source' => 'campaign', 'source_name' => $utm['utm_source'] ?: ($utm['utm_medium'] ?: $utm['utm_campaign']), 'referrer_host' => $host];
        }

        if ($host === null) {
            return ['source' => 'direct', 'source_name' => null, 'referrer_host' => null];
        }

        if (in_array($host, $internalHosts, true) || str_ends_with($host, '.' . ($internalHosts[0] ?? ''))) {
            return ['source' => 'internal', 'source_name' => null, 'referrer_host' => $host];
        }

        // AI before search: gemini.google.com would otherwise match google.
        foreach (['ai' => self::AI, 'search' => self::SEARCH, 'social' => self::SOCIAL] as $type => $patterns) {
            foreach ($patterns as $name => $pattern) {
                if (preg_match($pattern, $host)) {
                    return ['source' => $type, 'source_name' => $name, 'referrer_host' => $host];
                }
            }
        }

        return ['source' => 'referral', 'source_name' => $host, 'referrer_host' => $host];
    }

    private function host(?string $referrer): ?string
    {
        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return preg_match('~^[a-z0-9.-]{1,253}$~', $host) ? $host : null;
    }
}
