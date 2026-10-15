<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\View\View;

/**
 * Backoffice preview of any blog post (drafts included) with the public
 * article template. Registered inside the Filament panel's authenticated
 * routes ('admin' guard) and covered by the panel's NoIndex middleware.
 * Nothing is saved: an unpublished post gets a temporary display date.
 */
class BlogPostPreviewController extends Controller
{
    public function __invoke(Post $post): View
    {
        $post->published_at ??= now();

        return view('blog.show', ['post' => $post]);
    }
}
