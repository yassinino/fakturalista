<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = Post::published()
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('blog.index', compact('posts'));
    }

    public function show(string $slug): View
    {
        $post = Post::published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('blog.show', compact('post'));
    }
}
