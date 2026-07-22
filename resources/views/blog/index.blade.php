@extends('layouts.master')

@section('title', __('site.blog.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.blog.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.blog.og_title') }}" />
<meta property="og:description" content="{{ __('site.blog.og_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/blog') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }
    .blog-card-link:hover { text-decoration: none; }
    .blog-card-img-wrapper { overflow: hidden; border-radius: 6px 6px 0 0; }
    .blog-card-img-wrapper img { width: 100%; height: 220px; object-fit: cover; transition: transform .35s ease; }
    .blog-post:hover .blog-card-img-wrapper img { transform: scale(1.04); }
    .blog-post-placeholder { width: 100%; height: 220px; background: linear-gradient(135deg, #fde8e8 0%, #fdfafa 100%); display: flex; align-items: center; justify-content: center; }
    .blog-post-placeholder i { font-size: 3rem; color: #fa7070; opacity: .4; }
    .post-date { font-size: 13px; color: #797687; }
    .entry-title a { color: #2b2350; }
    .entry-title a:hover { color: #fa7070; }
    .read-more-link { color: #fa7070; font-size: 14px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; }
    .read-more-link:hover { color: #e05555; }
    .pagination .page-link { color: #fa7070; border-color: #efe7e7; }
    .pagination .page-item.active .page-link { background-color: #fa7070; border-color: #fa7070; color: #fff; }
    .pagination .page-link:hover { background-color: #fde8e8; color: #fa7070; }
    .blog-empty { padding: 60px 0; text-align: center; color: #797687; }
    .blog-empty i { font-size: 4rem; color: #fa7070; opacity: .3; display: block; margin-bottom: 16px; }
</style>

<!--==========================-->
<!--=     Page Banner        =-->
<!--==========================-->
<section class="page-banner">
    <div class="container">
        <div class="page-title-wrapper">
            <h1 class="page-title">{{ __('site.blog.banner_title') }}</h1>
            <p>{{ __('site.blog.banner_sub') }}</p>
        </div>
    </div>
    <svg class="circle" data-parallax='{"x" : -200}' xmlns="http://www.w3.org/2000/svg" width="950px" height="950px">
        <path fill-rule="evenodd" stroke="rgb(250, 112, 112)" stroke-width="100px" stroke-linecap="butt" stroke-linejoin="miter" opacity="0.051" fill="none" d="M450.000,50.000 C670.914,50.000 850.000,229.086 850.000,450.000 C850.000,670.914 670.914,850.000 450.000,850.000 C229.086,850.000 50.000,670.914 50.000,450.000 C50.000,229.086 229.086,50.000 450.000,50.000 Z" />
    </svg>
    <ul class="animate-ball">
        <li class="ball"></li>
        <li class="ball"></li>
        <li class="ball"></li>
        <li class="ball"></li>
        <li class="ball"></li>
    </ul>
</section>

<!--==========================-->
<!--=     Blog Grid          =-->
<!--==========================-->
<section id="blog-grid">
    <div class="container">

        @if ($posts->isEmpty())
            <div class="blog-empty">
                <i class="far fa-newspaper"></i>
                <h3>{{ __('site.blog.empty_title') }}</h3>
                <p>{{ __('site.blog.empty_text') }}</p>
            </div>
        @else
            <div class="row">
                @foreach ($posts as $post)
                <div class="col-lg-4 col-md-6">
                    <article class="blog-post">
                        <a href="{{ route('blog.show', $post->slug) }}" class="blog-card-link d-block">
                            <div class="blog-card-img-wrapper">
                                @if ($post->featured_image)
                                    <img
                                        src="{{ Storage::disk('public')->url($post->featured_image) }}"
                                        alt="{{ $post->title }}"
                                        loading="lazy"
                                    />
                                @else
                                    <div class="blog-post-placeholder">
                                        <i class="far fa-image"></i>
                                    </div>
                                @endif
                            </div>
                        </a>

                        <div class="blog-content">
                            <ul class="post-meta">
                                <li>
                                    <time class="post-date" datetime="{{ $post->published_at->toIso8601String() }}">
                                        {{ $post->published_at->translatedFormat('d M Y') }}
                                    </time>
                                </li>
                            </ul>

                            <h2 class="entry-title h5">
                                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                            </h2>

                            @if ($post->excerpt)
                                <p class="mb-3" style="font-size:14px; line-height:1.7; color:#797687;">
                                    {{ Str::limit($post->excerpt, 120) }}
                                </p>
                            @endif

                            <a href="{{ route('blog.show', $post->slug) }}" class="read-more-link">
                                {{ __('site.blog.read_more') }} &rarr;
                            </a>
                        </div>
                    </article>
                </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($posts->hasPages())
            <div class="d-flex justify-content-center mt-5">
                {{ $posts->links('pagination::bootstrap-5') }}
            </div>
            @endif
        @endif

    </div>
</section>

@endsection
