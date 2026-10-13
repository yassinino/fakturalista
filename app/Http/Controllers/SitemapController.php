<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\MarketLandingPages;
use App\Support\Seo;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * XML sitemap of every indexable public URL, on the canonical host. Each
 * language version is listed as its own <url> with the full hreflang set
 * (xhtml:link), as Google recommends. Login, signup and private pages are
 * deliberately absent (they are noindex).
 */
class SitemapController extends Controller
{
    /** path => [priority, changefreq] - available in every URL locale. */
    private const PAGES = [
        '/'               => [1.0, 'weekly'],
        '/pricing'        => [0.9, 'monthly'],
        '/faq'            => [0.7, 'monthly'],
        '/about'          => [0.6, 'monthly'],
        '/security'       => [0.6, 'monthly'],
        '/integrations'   => [0.6, 'monthly'],
        '/contact'        => [0.6, 'yearly'],
        '/documentation'  => [0.5, 'monthly'],
        '/help-center'    => [0.5, 'monthly'],
        '/api-docs'       => [0.4, 'monthly'],
        '/changelog'      => [0.4, 'weekly'],
        '/verifactu'      => [0.4, 'monthly'],
        '/legal-notice'   => [0.2, 'yearly'],
        '/privacy-policy' => [0.2, 'yearly'],
        '/terms'          => [0.2, 'yearly'],
        '/cookie-policy'  => [0.2, 'yearly'],
    ];

    public function index()
    {
        $sitemap = Sitemap::create();

        foreach (self::PAGES as $path => [$priority, $frequency]) {
            $this->addLocalized($sitemap, $path, Seo::urlLocales(), null, $priority, $frequency);
        }

        foreach (MarketLandingPages::all() as $page) {
            $this->addLocalized($sitemap, $page['path'], $page['locales'], $page['hreflang'], $page['priority'], 'monthly');
        }

        $latestPost = Post::published()->max('updated_at');
        $sitemap->add(
            Url::create(Seo::canonical('/blog'))
                ->setPriority(0.8)
                ->setChangeFrequency('weekly')
                ->setLastModificationDate($latestPost ? \Carbon\Carbon::parse($latestPost) : now())
        );

        Post::published()->orderByDesc('published_at')->each(function (Post $post) use ($sitemap) {
            $sitemap->add(
                Url::create(Seo::canonical('/blog/' . $post->slug))
                    ->setLastModificationDate($post->updated_at)
                    ->setPriority(0.7)
                    ->setChangeFrequency('monthly')
            );
        });

        return $sitemap->toResponse(request());
    }

    private function addLocalized(Sitemap $sitemap, string $path, array $locales, ?array $hreflang, float $priority, string $frequency): void
    {
        $alternates = Seo::alternates($path, $locales, $hreflang);

        foreach ($locales as $locale) {
            $url = Url::create(Seo::canonical($path, $locale))
                ->setPriority($locale === Seo::defaultLocale() ? $priority : round($priority * 0.8, 1))
                ->setChangeFrequency($frequency);

            foreach ($alternates as $code => $href) {
                $url->addAlternate($href, $code);
            }

            $sitemap->add($url);
        }
    }
}
