@extends('layouts.master')

@section('title', __('site.docs.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.docs.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.docs.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.docs.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/documentation') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    .dc-page { background: #f7f8fc; }

    .dc-layout {
        display: flex;
        gap: 48px;
        max-width: 1100px;
        margin: 0 auto;
        padding: 56px 24px 80px;
        align-items: flex-start;
    }

    /* ── Sidebar ─────────────────────────────────────────────── */
    .dc-sidebar {
        width: 210px;
        flex-shrink: 0;
        position: sticky;
        top: 88px;
    }
    .dc-nav-title {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #9ca3af;
        margin: 0 0 12px;
    }
    .dc-nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
        border-left: 2px solid #e5e7eb;
    }
    .dc-nav-link {
        display: block;
        padding: 7px 14px;
        font-size: .9rem;
        color: #4b5563;
        text-decoration: none;
        transition: color .15s, border-color .15s;
        border-left: 2px solid transparent;
        margin-left: -2px;
    }
    .dc-nav-link:hover,
    .dc-nav-link.active {
        color: #fa7070;
        border-left-color: #fa7070;
        text-decoration: none;
    }

    /* ── Main content ────────────────────────────────────────── */
    .dc-main { flex: 1; min-width: 0; }

    .dc-section {
        margin-bottom: 56px;
        scroll-margin-top: 100px;
    }
    .dc-section:last-child { margin-bottom: 0; }

    .dc-section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
        padding-bottom: 12px;
        border-bottom: 2px solid #fde8e8;
    }
    .dc-section-icon {
        width: 36px;
        height: 36px;
        background: #fff0f0;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fa7070;
        font-size: 15px;
        flex-shrink: 0;
    }
    .dc-section-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2b2350;
        margin: 0;
    }
    .dc-section-sub {
        font-size: .95rem;
        color: #6b7280;
        margin: 0 0 20px;
    }

    .dc-placeholder {
        background: #fff;
        border: 1.5px dashed #d1d5db;
        border-radius: 12px;
        padding: 32px 28px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        color: #6b7280;
    }
    .dc-placeholder-icon {
        font-size: 22px;
        color: #d1d5db;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .dc-placeholder-text { font-size: .9rem; line-height: 1.7; margin: 0; }
    .dc-placeholder-label {
        display: inline-block;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        background: #f3f4f6;
        color: #9ca3af;
        border-radius: 4px;
        padding: 2px 8px;
        margin-bottom: 8px;
    }

    /* ── CTA ─────────────────────────────────────────────────── */
    .dc-cta {
        background: #fff;
        border-top: 1px solid #e5e7eb;
        text-align: center;
        padding: 56px 24px 72px;
    }
    .dc-cta-inner { max-width: 480px; margin: 0 auto; }
    .dc-cta-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 52px;
        height: 52px;
        background: #fff0f0;
        border-radius: 14px;
        color: #fa7070;
        font-size: 22px;
        margin-bottom: 20px;
    }
    .dc-cta h2 {
        font-size: clamp(1.3rem, 3vw, 1.75rem);
        font-weight: 700;
        color: #111827;
        margin: 0 0 10px;
    }
    .dc-cta p { font-size: 1rem; color: #6b7280; margin: 0 0 28px; }

    /* ── Responsive ──────────────────────────────────────────── */
    @media (max-width: 768px) {
        .dc-layout { flex-direction: column; gap: 0; padding: 32px 16px 64px; }
        .dc-sidebar { width: 100%; position: static; margin-bottom: 32px; }
        .dc-nav-list { display: flex; flex-wrap: wrap; gap: 4px 0; border-left: none; }
        .dc-nav-link { border-left: none; border-bottom: 2px solid transparent; padding: 6px 12px; font-size: .85rem; }
        .dc-nav-link:hover, .dc-nav-link.active { border-left-color: transparent; border-bottom-color: #fa7070; }
    }
</style>

@php
$sections = [
    ['id' => 'getting-started', 'icon' => 'fas fa-rocket',      'label' => __('site.docs.cat_start'),     'sub' => __('site.docs.cat_start_sub')],
    ['id' => 'invoices',        'icon' => 'fas fa-file-invoice', 'label' => __('site.docs.cat_invoices'),  'sub' => __('site.docs.cat_invoices_sub')],
    ['id' => 'quotes',          'icon' => 'fas fa-file-alt',     'label' => __('site.docs.cat_quotes'),    'sub' => __('site.docs.cat_quotes_sub')],
    ['id' => 'clients',         'icon' => 'fas fa-users',        'label' => __('site.docs.cat_clients'),   'sub' => __('site.docs.cat_clients_sub')],
    ['id' => 'payments',        'icon' => 'fas fa-credit-card',  'label' => __('site.docs.cat_payments'),  'sub' => __('site.docs.cat_payments_sub')],
    ['id' => 'templates',       'icon' => 'fas fa-palette',      'label' => __('site.docs.cat_templates'), 'sub' => __('site.docs.cat_templates_sub')],
    ['id' => 'settings',        'icon' => 'fas fa-cog',          'label' => __('site.docs.cat_settings'),  'sub' => __('site.docs.cat_settings_sub')],
];
@endphp

@include('partials.page-hero', [
    'title'      => __('site.docs.hero_title'),
    'paragraphs' => [__('site.docs.hero_sub')],
])

<div class="dc-page">
    <div class="dc-layout">

        {{-- Sidebar navigation --}}
        <nav class="dc-sidebar" aria-label="Documentation topics">
            <p class="dc-nav-title">{{ __('site.docs.nav_title') }}</p>
            <ul class="dc-nav-list">
                @foreach ($sections as $s)
                    <li>
                        <a href="#{{ $s['id'] }}" class="dc-nav-link">{{ $s['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Main content --}}
        <main class="dc-main">
            @foreach ($sections as $s)
                <section id="{{ $s['id'] }}" class="dc-section">
                    <div class="dc-section-header">
                        <div class="dc-section-icon" aria-hidden="true">
                            <i class="{{ $s['icon'] }}"></i>
                        </div>
                        <h2 class="dc-section-title">{{ $s['label'] }}</h2>
                    </div>
                    <p class="dc-section-sub">{{ $s['sub'] }}</p>
                    {{-- TODO: Replace this placeholder with real documentation content for this section. --}}
                    <div class="dc-placeholder">
                        <span class="dc-placeholder-icon" aria-hidden="true">
                            <i class="fas fa-pencil-ruler"></i>
                        </span>
                        <div>
                            <span class="dc-placeholder-label">{{ __('site.docs.coming_soon') }}</span>
                            <p class="dc-placeholder-text">{{ __('site.docs.placeholder') }}</p>
                        </div>
                    </div>
                </section>
            @endforeach
        </main>

    </div>

    <div class="dc-cta">
        <div class="dc-cta-inner">
            <div class="dc-cta-icon" aria-hidden="true"><i class="fas fa-headset"></i></div>
            <h2>{{ __('site.docs.cta_title') }}</h2>
            <p>{{ __('site.docs.cta_body') }}</p>
            <a href="{{ url('/contact') }}" class="pix-btn">{{ __('site.docs.cta_btn') }}</a>
        </div>
    </div>
</div>

<script>
(function () {
    var links = document.querySelectorAll('.dc-nav-link');
    var sections = document.querySelectorAll('.dc-section');

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                links.forEach(function (l) { l.classList.remove('active'); });
                var active = document.querySelector('.dc-nav-link[href="#' + entry.target.id + '"]');
                if (active) active.classList.add('active');
            }
        });
    }, { rootMargin: '-30% 0px -60% 0px' });

    sections.forEach(function (s) { observer.observe(s); });
})();
</script>

@endsection
