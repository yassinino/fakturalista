<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index()
    {
        $sitemap = Sitemap::create();

        // Static marketing pages
        $sitemap->add(Url::create('/')->setPriority(1.0)->setChangeFrequency('weekly'));
        $sitemap->add(Url::create('/about')->setPriority(0.8)->setChangeFrequency('monthly'));
        $sitemap->add(Url::create('/pricing')->setPriority(0.9)->setChangeFrequency('monthly'));
        $sitemap->add(Url::create('/contact')->setPriority(0.6)->setChangeFrequency('yearly'));
        $sitemap->add(Url::create('/blog')->setPriority(0.9)->setChangeFrequency('daily'));

        // Blog posts
        Post::published()
            ->orderByDesc('published_at')
            ->each(function (Post $post) use ($sitemap) {
                $sitemap->add(
                    Url::create("/blog/{$post->slug}")
                        ->setLastModificationDate($post->updated_at)
                        ->setPriority(0.8)
                        ->setChangeFrequency('monthly')
                );
            });

        return $sitemap->toResponse(request());
    }
}
