@extends('layouts.master')

@section('title', $post->meta_title)

@section('meta')
<meta name="description" content="{{ $post->meta_description }}" />

{{-- Open Graph --}}
<meta property="og:title" content="{{ $post->meta_title }}" />
<meta property="og:description" content="{{ $post->meta_description }}" />
<meta property="og:type" content="article" />
<meta property="og:url" content="{{ url('/blog/' . $post->slug) }}" />
@if ($post->featured_image)
<meta property="og:image" content="{{ Storage::disk('public')->url($post->featured_image) }}" />
@endif
<meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}" />
<meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}" />

{{-- Canonical --}}
<link rel="canonical" href="{{ url('/blog/' . $post->slug) }}" />

{{-- JSON-LD BlogPosting --}}
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    "headline": {{ Js::from($post->meta_title) }},
    "description": {{ Js::from($post->meta_description) }},
    "url": {{ Js::from(url('/blog/' . $post->slug)) }},
    @if ($post->featured_image)
    "image": {{ Js::from(Storage::disk('public')->url($post->featured_image)) }},
    @endif
    "datePublished": "{{ $post->published_at->toIso8601String() }}",
    "dateModified": "{{ $post->updated_at->toIso8601String() }}",
    "author": {
        "@type": "Organization",
        "name": "Fakturalista",
        "url": {{ Js::from(url('/')) }}
    },
    "publisher": {
        "@type": "Organization",
        "name": "Fakturalista",
        "logo": {
            "@type": "ImageObject",
            "url": {{ Js::from(url('assets/logo.svg')) }}
        }
    },
    "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": {{ Js::from(url('/blog/' . $post->slug)) }}
    }
}
</script>
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    /* Article layout */
    .post-article { max-width: 780px; margin: 0 auto; }

    /* Banner */
    .page-banner.blog-details-banner .page-title { font-size: clamp(1.6rem, 4vw, 2.5rem); }

    /* Featured image */
    .post-featured-image { border-radius: 12px; overflow: hidden; margin-bottom: 40px; }
    .post-featured-image img { width: 100%; max-height: 480px; object-fit: cover; }

    /* Body content */
    .post-body { font-size: 17px; line-height: 1.9; color: #444; }
    .post-body h2, .post-body h3 { color: #2b2350; margin-top: 2rem; margin-bottom: 1rem; }
    .post-body h2 { font-size: 1.5rem; }
    .post-body h3 { font-size: 1.2rem; }
    .post-body a { color: #fa7070; }
    .post-body a:hover { color: #e05555; }
    .post-body img { max-width: 100%; border-radius: 8px; margin: 1.5rem 0; }
    .post-body blockquote { border-left: 4px solid #fa7070; padding: 12px 20px; background: #fde8e8; border-radius: 0 6px 6px 0; margin: 1.5rem 0; color: #2b2350; font-style: italic; }
    .post-body ul, .post-body ol { padding-left: 1.4rem; margin-bottom: 1rem; }
    .post-body li { margin-bottom: .4rem; }
    .post-body pre { background: #f5f5f5; border-radius: 6px; padding: 1rem; overflow-x: auto; font-size: 14px; }
    .post-body code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-size: 14px; }

    /* Back link */
    .back-link { display: inline-flex; align-items: center; gap: 6px; color: #fa7070; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 40px; }
    .back-link:hover { color: #e05555; text-decoration: none; }

    /* Meta */
    .post-meta-bar { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
    .post-meta-bar time { font-size: 14px; color: #797687; }
</style>

<!--==========================-->
<!--=   Blog Post Banner     =-->
<!--==========================-->
<section class="page-banner blog-details-banner">
    <div class="container">
        <div class="page-title-wrapper">
            <h1 class="page-title">{{ $post->title }}</h1>
            <ul class="post-meta">
                <li>
                    <time datetime="{{ $post->published_at->toIso8601String() }}">
                        {{ $post->published_at->translatedFormat('d \d\e F \d\e Y') }}
                    </time>
                </li>
            </ul>
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
<!--=   Blog Post Content    =-->
<!--==========================-->
<section style="padding: 60px 0 80px;">
    <div class="container">
        <article class="post-article" itemscope itemtype="https://schema.org/BlogPosting">

            <a href="{{ route('blog.index') }}" class="back-link">
                <i class="fas fa-arrow-left"></i> {{ __('site.blog.back_to_blog') }}
            </a>

            {{-- Featured image --}}
            @if ($post->featured_image)
            <div class="post-featured-image">
                <img
                    src="{{ Storage::disk('public')->url($post->featured_image) }}"
                    alt="{{ $post->title }}"
                    itemprop="image"
                />
            </div>
            @endif

            {{-- Body --}}
            <div class="post-body" itemprop="articleBody">
                {!! $post->body !!}
            </div>

            {{-- Footer --}}
            <hr style="margin: 48px 0 32px; border-color: #efe7e7;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <a href="{{ route('blog.index') }}" class="back-link">
                    <i class="fas fa-arrow-left"></i> {{ __('site.blog.all_articles') }}
                </a>
                <a href="{{ url('/free-trial') }}" class="pix-btn">{{ __('site.blog.try_cta') }}</a>
            </div>

        </article>
    </div>
</section>

@endsection
