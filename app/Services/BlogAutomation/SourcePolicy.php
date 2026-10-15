<?php

namespace App\Services\BlogAutomation;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Which links an AI article may keep:
 *  - internal: known Fakturalista pages (config internal_pages, market
 *    landing pages) and published blog posts - stored as relative paths;
 *  - external: official / reliable domains from config only, and only when
 *    they answer (a bounded check, so invented deep links are dropped).
 * Anything else is unwrapped (text kept, link removed).
 */
class SourcePolicy
{
    /** @var array<string, true> */
    private array $internalPaths = [];

    /** @var array<string, bool> url => reachable */
    private array $reachable = [];

    public function __construct(private readonly bool $checkReachability = true)
    {
        foreach (config('blog_automation.internal_pages') as $page) {
            $this->internalPaths[$page['path']] = true;
        }
        foreach (\App\Support\MarketLandingPages::all() as $page) {
            $this->internalPaths[$page['path']] = true;
        }
        Post::published()->pluck('slug')->each(fn ($slug) => $this->internalPaths['/blog/' . $slug] = true);
    }

    public static function hostMatches(?string $host, array $domains): bool
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/^www\./', '', $host);
        if ($host === '') {
            return false;
        }
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    public static function trustedDomains(): array
    {
        $sources = config('blog_automation.sources');

        return array_merge(...array_values($sources['official']), ...[$sources['reliable']]);
    }

    /** Checks the trusted external links of a body once, before sanitizing. */
    public function prefetch(string $html): void
    {
        preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $html, $m);
        $checked = 0;
        foreach (array_unique($m[1]) as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($this->externalCandidate($href) && ! isset($this->reachable[$href]) && $checked < 8) {
                $this->reachable[$href] = $this->isReachable($href);
                $checked++;
            }
        }
    }

    /** @return array<string,string>|null attributes to keep, null to unwrap */
    public function __invoke(string $href): ?array
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $canonicalHost = parse_url((string) config('seo.canonical_url'), PHP_URL_HOST);
        $host = parse_url($href, PHP_URL_HOST);
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            $host = null;
        } elseif ($host && self::hostMatches($host, [$canonicalHost])) {
            $href = (string) parse_url($href, PHP_URL_PATH);
            $host = null;
        }

        if ($host === null) {
            $path = '/' . trim((string) parse_url($href, PHP_URL_PATH), '/');

            return isset($this->internalPaths[$path]) ? ['href' => $path] : null;
        }

        if (! $this->externalCandidate($href)) {
            return null;
        }
        if ($this->checkReachability && ! ($this->reachable[$href] ??= $this->isReachable($href))) {
            return null;
        }

        return ['href' => $href, 'rel' => 'noopener', 'target' => '_blank'];
    }

    private function externalCandidate(string $href): bool
    {
        return filter_var($href, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($href, PHP_URL_SCHEME)), ['http', 'https'], true)
            && self::hostMatches(parse_url($href, PHP_URL_HOST), self::trustedDomains());
    }

    private function isReachable(string $url): bool
    {
        if (! $this->checkReachability) {
            return true;
        }
        try {
            $response = Http::timeout(6)->connectTimeout(4)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; FakturalistaLinkCheck/1.0; +https://fakturalista.com)'])
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);

            return $response->status() < 400;
        } catch (Throwable) {
            return false;
        }
    }
}
