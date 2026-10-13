<?php

namespace Tests\Feature\Seo;

use App\Models\Post;
use App\Support\MarketLandingPages;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Technical SEO of the public (central) site: canonical/hreflang, robots,
 * sitemap, structured data, landing pages, redirects and error pages.
 * See config/seo.php and App\Support\Seo.
 */
class PublicSeoTest extends TestCase
{
    private const HOST = 'http://fakturalista.test';
    private const CANON = 'https://fakturalista.com';

    /** @return array<int, array> decoded JSON-LD blocks of a page */
    private function jsonLd(string $html): array
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

        return array_map(function ($json) {
            $data = json_decode($json, true);
            $this->assertIsArray($data, 'JSON-LD must be valid JSON: ' . substr($json, 0, 120));

            return $data;
        }, $m[1]);
    }

    /** @return string[] every @type found in the page's JSON-LD graphs */
    private function schemaTypes(string $html): array
    {
        $types = [];
        foreach ($this->jsonLd($html) as $block) {
            foreach ($block['@graph'] ?? [$block] as $node) {
                $types[] = $node['@type'] ?? null;
            }
        }

        return array_values(array_filter($types));
    }

    private function graphNode(string $html, string $type): ?array
    {
        foreach ($this->jsonLd($html) as $block) {
            foreach ($block['@graph'] ?? [$block] as $node) {
                if (($node['@type'] ?? null) === $type) {
                    return $node;
                }
            }
        }

        return null;
    }

    // ── Homepage ────────────────────────────────────────────────────────

    public function test_homepage_has_canonical_hreflang_social_and_structured_data(): void
    {
        $html = $this->get(self::HOST . '/')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="' . self::CANON . '/" />', $html);
        $this->assertStringContainsString('hreflang="fr" href="' . self::CANON . '/"', $html);
        $this->assertStringContainsString('hreflang="ar" href="' . self::CANON . '/?lang=ar"', $html);
        $this->assertStringContainsString('hreflang="en" href="' . self::CANON . '/?lang=en"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="' . self::CANON . '/"', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image" />', $html);
        $this->assertStringNotContainsString('name="keywords"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        $types = $this->schemaTypes($html);
        foreach (['Organization', 'WebSite', 'SoftwareApplication', 'FAQPage'] as $type) {
            $this->assertContains($type, $types);
        }
    }

    public function test_software_application_schema_uses_real_prices_and_no_ratings(): void
    {
        $html = $this->get(self::HOST . '/')->getContent();
        $app  = $this->graphNode($html, 'SoftwareApplication');

        $this->assertNotNull($app);
        $this->assertArrayNotHasKey('aggregateRating', $app);
        $this->assertArrayNotHasKey('review', $app);

        $expected = \App\Models\Plan::on('mysql')->where('active', true)->with('prices')->get()
            ->map(fn ($plan) => $plan->prices->first(fn ($p) => $p->country_code === 'MA' && $p->interval === 'monthly'))
            ->filter()
            ->map(fn ($price) => number_format($price->amount / 100, 2, '.', ''))
            ->sort()->values()->all();

        $actual = collect($app['offers'] ?? [])->pluck('price')->sort()->values()->all();
        $this->assertSame($expected, $actual);
        foreach ($app['offers'] ?? [] as $offer) {
            $this->assertSame('MAD', $offer['priceCurrency']);
        }
    }

    public function test_language_versions_have_their_own_url_lang_and_canonical(): void
    {
        $response = $this->get(self::HOST . '/pricing?lang=ar')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<html lang="ar" dir="rtl"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="' . self::CANON . '/pricing?lang=ar" />', $html);
        $this->assertSame('ar', session('locale'), 'The language choice is remembered for the visit.');

        // The default language never carries the parameter in its canonical.
        $html = $this->get(self::HOST . '/pricing?lang=fr')->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="' . self::CANON . '/pricing" />', $html);

        // Tracking or market parameters never leak into the canonical.
        $html = $this->get(self::HOST . '/pricing?market=ES&utm_source=x')->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="' . self::CANON . '/pricing" />', $html);
    }

    public function test_language_switcher_uses_crawlable_links(): void
    {
        $html = $this->get(self::HOST . '/about')->getContent();

        $this->assertMatchesRegularExpression('~<a href="[^"]*/about\?lang=ar"[^>]*hreflang="ar"~', $html);
        $this->assertMatchesRegularExpression('~<a href="[^"]*/about\?lang=en"[^>]*hreflang="en"~', $html);
    }

    public function test_every_public_page_has_one_h1_and_its_own_title(): void
    {
        $titles = [];
        foreach (['/', '/pricing', '/about', '/contact', '/faq', '/security', '/integrations', '/documentation', '/help-center', '/api-docs', '/changelog', '/blog'] as $path) {
            $html = $this->get(self::HOST . $path)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<h1'), "$path must have exactly one h1");
            preg_match('~<title>(.*?)</title>~s', $html, $m);
            $title = trim($m[1] ?? '');
            $this->assertNotSame('', $title, "$path has no title");
            $this->assertSame(1, substr_count($title, 'Fakturalista'), "$path repeats the brand: $title");
            $titles[$path] = $title;
        }

        $this->assertSame(count($titles), count(array_unique($titles)), 'Titles must be unique: ' . json_encode($titles));
    }

    // ── Market landing pages ────────────────────────────────────────────

    public function test_morocco_landing_pages_render_in_french_and_arabic(): void
    {
        $titles = [];
        foreach (MarketLandingPages::all() as $page) {
            // Like a crawler: no session carrying an earlier ?lang=ar choice.
            // (App::setLocale() also rewrites config('app.locale') on the
            // app instance the test reuses across requests.)
            $this->flushSession();
            config(['app.locale' => 'fr']);
            $fr = $this->get(self::HOST . $page['path'])->assertOk()->getContent();
            $this->assertStringContainsString('<html lang="fr-MA" dir="ltr"', $fr);
            $this->assertSame(1, substr_count($fr, '<h1'));
            $this->assertStringContainsString('<link rel="canonical" href="' . self::CANON . $page['path'] . '" />', $fr);
            $this->assertStringContainsString('hreflang="fr-MA" href="' . self::CANON . $page['path'] . '"', $fr);
            $this->assertStringContainsString('hreflang="ar-MA" href="' . self::CANON . $page['path'] . '?lang=ar"', $fr);
            $this->assertStringContainsString(url('/register'), $fr);
            foreach (['BreadcrumbList', 'FAQPage', 'WebPage'] as $type) {
                $this->assertContains($type, $this->schemaTypes($fr), "{$page['path']} lacks $type");
            }
            $this->assertStringNotContainsString(':days', $fr);
            preg_match('~<title>(.*?)</title>~s', $fr, $m);
            $titles[] = $m[1];

            $ar = $this->get(self::HOST . $page['path'] . '?lang=ar')->assertOk()->getContent();
            $this->assertStringContainsString('<html lang="ar-MA" dir="rtl"', $ar);
            $this->assertStringContainsString('<link rel="canonical" href="' . self::CANON . $page['path'] . '?lang=ar" />', $ar);
            $this->assertSame(1, substr_count($ar, '<h1'));
        }

        $this->assertSame(count($titles), count(array_unique($titles)));
    }

    public function test_a_landing_page_viewed_in_english_serves_the_french_version(): void
    {
        $html = $this->get(self::HOST . '/ma/logiciel-facturation?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="fr-MA"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="' . self::CANON . '/ma/logiciel-facturation" />', $html);
    }

    public function test_unknown_landing_pages_and_urls_are_404_and_noindex(): void
    {
        $this->get(self::HOST . '/ma/logiciel-gratuit')->assertNotFound();

        $response = $this->get(self::HOST . '/page-qui-nexiste-pas')->assertNotFound();
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow" />', $response->getContent());
    }

    public function test_removed_pages_answer_410_gone(): void
    {
        Route::get('/__seo-test-gone', fn () => abort(410));

        $response = $this->get('/__seo-test-gone')->assertStatus(410);
        $this->assertStringContainsString('noindex', $response->getContent());
    }

    // ── Indexing directives and redirects ──────────────────────────────

    public function test_utility_pages_are_noindex(): void
    {
        foreach (['/register', '/login'] as $path) {
            $html = $this->get(self::HOST . $path)->assertOk()->getContent();
            $this->assertStringContainsString('<meta name="robots" content="noindex, follow" />', $html, $path);
        }
    }

    public function test_replaced_urls_redirect_permanently(): void
    {
        $this->get(self::HOST . '/free-trial')->assertStatus(301)->assertRedirect('/register');
    }

    public function test_the_www_host_redirects_to_the_canonical_host(): void
    {
        $this->get('http://www.fakturalista.com/pricing?lang=ar')
            ->assertStatus(301)
            ->assertRedirect(self::CANON . '/pricing?lang=ar');
    }

    public function test_the_backoffice_is_kept_out_of_search_engines(): void
    {
        $this->get(self::HOST . '/backoffice/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    // ── robots.txt and sitemap ─────────────────────────────────────────

    public function test_robots_txt_allows_the_site_and_points_to_the_sitemap(): void
    {
        $body = $this->get(self::HOST . '/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('Sitemap: ' . self::CANON . '/sitemap.xml', $body);
        $this->assertStringContainsString('Disallow: /backoffice', $body);
        $this->assertDoesNotMatchRegularExpression('~^Disallow: /$~m', $body);
    }

    public function test_sitemap_lists_indexable_pages_with_hreflang_and_blog_posts(): void
    {
        $xml = $this->get(self::HOST . '/sitemap.xml')->assertOk()->getContent();

        $this->assertNotFalse(simplexml_load_string($xml), 'The sitemap must be well-formed XML.');
        preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);
        $locs = array_map('html_entity_decode', $m[1]);

        foreach ($locs as $loc) {
            $this->assertStringStartsWith(self::CANON . '/', $loc);
        }
        foreach (['/', '/pricing', '/blog', '/ma/logiciel-facturation', '/ma/facturation-btp'] as $path) {
            $this->assertContains(self::CANON . ($path === '/' ? '/' : $path), $locs);
        }
        $this->assertContains(self::CANON . '/ma/logiciel-facturation?lang=ar', $locs);
        $this->assertContains(self::CANON . '/pricing?lang=en', $locs);

        foreach (['/register', '/login', '/free-trial', '/tenant-not-found'] as $private) {
            $this->assertNotContains(self::CANON . $private, $locs);
        }

        $this->assertStringContainsString('hreflang="ar-MA"', $xml);

        foreach (Post::published()->pluck('slug') as $slug) {
            $this->assertContains(self::CANON . '/blog/' . $slug, $locs);
        }
    }

    // ── Blog ───────────────────────────────────────────────────────────

    public function test_the_morocco_seo_articles_are_published_with_article_schema(): void
    {
        $slugs = [
            'meilleur-logiciel-facturation-maroc-comment-choisir',
            'creer-facture-professionnelle-maroc',
            'devis-factures-erreurs-tpe-maroc',
            'facturation-electronique-maroc-ce-qu-il-faut-savoir',
            'suivre-factures-impayees-relancer-clients',
            'passer-excel-logiciel-facturation',
            'creer-gerer-devis-factures-en-ligne',
        ];
        $metaTitles = [];

        foreach ($slugs as $slug) {
            $html = $this->get(self::HOST . '/blog/' . $slug)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), $slug);
            $this->assertStringContainsString('<link rel="canonical" href="' . self::CANON . '/blog/' . $slug . '" />', $html);
            $post = $this->graphNode($html, 'BlogPosting');
            $this->assertNotNull($post, $slug);
            $this->assertSame('fr', $post['inLanguage']);
            $this->assertContains('BreadcrumbList', $this->schemaTypes($html));
            // Every article links to at least one market landing page and to signup.
            $this->assertMatchesRegularExpression('~href="/ma/[a-z-]+"~', $html, $slug);
            $this->assertStringContainsString('href="/register"', $html);
            $metaTitles[] = Post::where('slug', $slug)->value('meta_title');
        }

        $this->assertSame(count($metaTitles), count(array_unique($metaTitles)));
    }
}
