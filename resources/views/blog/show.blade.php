@extends('layouts.master')

@section('title', $post->meta_title)

@section('meta')
@php
    $postImage = $post->featured_image ? Storage::disk('public')->url($post->featured_image) : Seo::asset(config('seo.default_image'));
    $postUrl   = Seo::canonical('/blog/' . $post->slug);
@endphp
@include('partials.seo', [
    'description' => $post->meta_description,
    'path'        => '/blog/' . $post->slug,
    'locales'     => ['fr'],
    'type'        => 'article',
    'image'       => $postImage,
    'breadcrumbs' => [[__('site.blog.page_title'), '/blog'], [$post->title, '/blog/' . $post->slug]],
    'schema'      => [[
        '@type'            => 'BlogPosting',
        '@id'              => $postUrl . '#article',
        'headline'         => \Illuminate\Support\Str::limit($post->title, 110, ''),
        'description'      => $post->meta_description,
        'url'              => $postUrl,
        'mainEntityOfPage' => $postUrl,
        'image'            => $postImage,
        'inLanguage'       => 'fr',
        'datePublished'    => $post->published_at->toIso8601String(),
        'dateModified'     => $post->updated_at->toIso8601String(),
        'author'           => ['@type' => 'Organization', '@id' => Seo::organizationId(), 'name' => 'Fakturalista', 'url' => config('seo.canonical_url') . '/'],
        'publisher'        => ['@id' => Seo::organizationId()],
    ]],
])
<meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}" />
<meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}" />
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

    /* End-of-article CTA + related solutions */
    .post-cta { margin: 56px 0 0; padding: 32px; border-radius: 16px; background: #fff5f8; border: 1px solid #fde0ea; }
    .post-cta h2 { font-size: 1.35rem; color: #2b2350; margin: 0 0 8px; }
    .post-cta p { color: #4b5563; margin: 0 0 20px; font-size: 16px; line-height: 1.6; }
    .post-cta-actions { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
    .post-cta-link { color: #c2185b; font-weight: 600; }
    .post-related { margin-top: 36px; }
    .post-related h2 { font-size: 1.1rem; color: #2b2350; margin-bottom: 12px; }
    .post-related ul { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
    .post-related a { display: block; padding: 12px 14px; border: 1px solid #efe7e7; border-radius: 10px; color: #2b2350; font-weight: 600; font-size: 15px; }
    .post-related a:hover { border-color: #fa7070; color: #c2185b; text-decoration: none; }
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
                        {{ $post->published_at->locale('fr')->isoFormat('LL') }}
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
        {{-- Articles are written in French: keep that language/direction even
             when the surrounding site chrome is shown in Arabic. --}}
        <article class="post-article" lang="fr" dir="ltr">

            <a href="{{ url('/blog') }}" class="back-link">
                <i class="fas fa-arrow-left"></i> {{ __('site.blog.back_to_blog') }}
            </a>

            {{-- Featured image --}}
            @if ($post->featured_image)
            <div class="post-featured-image">
                <img
                    src="{{ Storage::disk('public')->url($post->featured_image) }}"
                    alt="{{ $post->title }}"
                    width="1200" height="630"
                />
            </div>
            @endif

            {{-- Body --}}
            <div class="post-body">
                {!! $post->body !!}
            </div>

            {{-- Conversion + internal links to the market pages. --}}
            <aside class="post-cta" aria-labelledby="post-cta-title">
                <h2 id="post-cta-title">{{ __('site.seo.blog_cta_title') }}</h2>
                <p>{{ __('site.seo.blog_cta_text', ['days' => Seo::trialDays()]) }}</p>
                <div class="post-cta-actions">
                    <a href="{{ url('/register') }}" class="pix-btn">{{ __('site.seo.cta_primary') }}</a>
                    <a href="{{ url('/pricing') }}" class="post-cta-link">{{ __('site.seo.cta_pricing') }} →</a>
                </div>
            </aside>

            <nav class="post-related" aria-labelledby="post-related-title">
                <h2 id="post-related-title">{{ __('site.seo.blog_related') }}</h2>
                <ul>
                    @foreach (\App\Support\MarketLandingPages::all() as $relatedPage)
                        <li><a href="{{ url($relatedPage['path']) }}">{{ __('landing.' . $relatedPage['key'] . '.nav_label', [], 'fr') }}</a></li>
                    @endforeach
                </ul>
            </nav>

            {{-- Footer --}}
            <hr style="margin: 48px 0 32px; border-color: #efe7e7;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <a href="{{ url('/blog') }}" class="back-link">
                    <i class="fas fa-arrow-left"></i> {{ __('site.blog.all_articles') }}
                </a>
            </div>

        </article>
    </div>
</section>

@endsection
