<?php

namespace Tests\Feature;

use App\Http\Middleware\SiteDomain;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `php artisan route:cache` must keep working: every route name unique, and
 * the public site served on all its hosts from ONE route group
 * ({siteDomain} pattern, routes/web.php) with route() following the host.
 * Regression: registering the site group once per domain produced three
 * routes per name ("Another route has already been assigned name
 * [locale.set]") and route() pointed at www.fakturalista.com.
 */
class RouteCacheabilityTest extends TestCase
{
    public function test_route_names_are_unique(): void
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            if ($name = $route->getName()) {
                $names[$name][] = ($route->getDomain() ?? '*') . ' ' . $route->uri();
            }
        }

        $duplicates = array_filter($names, fn ($routes) => count($routes) > 1);
        $this->assertSame([], $duplicates, 'Duplicate route names break route:cache.');
    }

    public function test_the_route_collection_compiles_like_route_cache_does(): void
    {
        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            $route->prepareForSerialization();
        }

        $this->assertIsArray($routes->compile());
    }

    public function test_public_routes_are_registered_once_for_every_site_domain(): void
    {
        $home = Route::getRoutes()->getByName('home');

        $this->assertSame('{siteDomain}', $home->getDomain());
        foreach (config('seo.site_domains') as $domain) {
            $this->assertTrue($home->matches(\Illuminate\Http\Request::create("http://{$domain}/")), $domain);
        }
        $this->assertFalse($home->matches(\Illuminate\Http\Request::create('http://walid.fakturalista.test/')), 'Tenant subdomains must not hit the public site.');
    }

    public function test_route_urls_follow_the_visited_site_host(): void
    {
        foreach (['fakturalista.test', 'fakturalista.com'] as $host) {
            $this->get("https://{$host}/contact")->assertOk();
            $this->assertSame($host, parse_url(route('contact.send'), PHP_URL_HOST), "route() during a {$host} request");
            $this->assertSame('/blog/abc', parse_url(route('blog.show', 'abc'), PHP_URL_PATH));
        }

        $this->get('https://www.fakturalista.com/contact')->assertRedirect('https://fakturalista.com/contact');
    }

    public function test_outside_a_site_request_route_uses_the_app_url_host(): void
    {
        config(['app.url' => 'https://fakturalista.com']);
        \Illuminate\Support\Facades\URL::defaults([SiteDomain::PARAMETER => \App\Support\Seo::siteDomain()]);
        $this->assertSame('fakturalista.com', parse_url(route('blog.show', 'abc'), PHP_URL_HOST));

        config(['app.url' => 'https://some-other-host.example']);
        $this->assertSame('fakturalista.com', \App\Support\Seo::siteDomain(), 'Unknown APP_URL hosts fall back to the canonical host.');

        config(['app.url' => 'http://fakturalista.test:8888/']);
        $this->assertSame('fakturalista.test', \App\Support\Seo::siteDomain());
    }

    public function test_controllers_receive_their_own_parameters_only(): void
    {
        $slug = \App\Models\Post::published()->value('slug');
        $this->assertNotNull($slug);

        $this->get('https://fakturalista.com/blog/' . $slug)->assertOk();
        $this->get('https://fakturalista.com/ma/facturation-btp')->assertOk();
        $this->get('https://fakturalista.com/blog/does-not-exist-' . uniqid())->assertNotFound();
    }
}
