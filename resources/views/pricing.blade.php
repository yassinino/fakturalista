@extends('layouts.master')

@section('title', __('site.pricing.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.pricing.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.pricing.page_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.pricing.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/pricing') }}" />
@endsection

@section('content')
<style>
    .site-header .site-main-menu li > a { color: #000000; }

    :root {
        --pr-brand:       #fa7070;
        --pr-brand-dark:  #e05050;
        --pr-page-bg:     #f7f8fc;
        --pr-card-bg:     #ffffff;
        --pr-text:        #111827;
        --pr-muted:       #6b7280;
        --pr-border:      #e5e7eb;
        --pr-shadow:      0 2px 12px rgba(0,0,0,.07);
        --pr-radius:      16px;
    }

    .pr-page {
        background: var(--pr-page-bg);
        color: var(--pr-text);
        font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    }

    /* ── Header ────────────────────────────────────────────── */
    .pr-header {
        padding: 80px 0 56px;
        text-align: center;
    }
    .pr-header h1 {
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 800;
        margin: 0 0 16px;
        color: var(--pr-text);
    }
    .pr-header p {
        font-size: 1.125rem;
        color: var(--pr-muted);
        max-width: 560px;
        margin: 0 auto;
        line-height: 1.6;
    }

    /* ── Grid ──────────────────────────────────────────────── */
    .pr-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        max-width: 1080px;
        margin: 0 auto;
        padding: 0 24px 80px;
        align-items: start;
    }

    /* ── Card ──────────────────────────────────────────────── */
    .pr-card {
        background: var(--pr-card-bg);
        border: 1.5px solid var(--pr-border);
        border-radius: var(--pr-radius);
        padding: 36px 28px 32px;
        box-shadow: var(--pr-shadow);
        display: flex;
        flex-direction: column;
        gap: 0;
        position: relative;
        transition: transform .2s, box-shadow .2s;
    }
    .pr-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0,0,0,.10);
    }
    .pr-card.featured {
        border-color: var(--pr-brand);
        box-shadow: 0 8px 32px rgba(250,112,112,.18);
        transform: translateY(-6px);
    }
    .pr-card.featured:hover {
        transform: translateY(-10px);
        box-shadow: 0 14px 48px rgba(250,112,112,.25);
    }

    /* ── Badge ─────────────────────────────────────────────── */
    .pr-plan-badge {
        position: absolute;
        top: -13px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--pr-brand);
        color: #fff;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        padding: 4px 14px;
        border-radius: 99px;
        white-space: nowrap;
    }

    /* ── Plan name ─────────────────────────────────────────── */
    .pr-plan-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 8px;
        color: var(--pr-text);
    }

    /* ── Price ─────────────────────────────────────────────── */
    .pr-price-block {
        margin: 16px 0 8px;
    }
    .pr-price {
        font-size: 2.6rem;
        font-weight: 800;
        color: var(--pr-text);
        line-height: 1;
    }
    .pr-price sup {
        font-size: 1.2rem;
        font-weight: 700;
        vertical-align: super;
        margin-right: 2px;
    }
    .pr-price-period {
        font-size: .85rem;
        color: var(--pr-muted);
        margin-left: 4px;
    }
    .pr-commitment {
        font-size: .78rem;
        color: var(--pr-muted);
        margin-top: 4px;
    }

    /* ── Short description ──────────────────────────────────── */
    .pr-short-desc {
        font-size: .875rem;
        color: var(--pr-muted);
        margin: 12px 0 20px;
        line-height: 1.5;
        min-height: 40px;
    }

    /* ── Divider ───────────────────────────────────────────── */
    .pr-divider {
        border: none;
        border-top: 1px solid var(--pr-border);
        margin: 0 0 20px;
    }

    /* ── Feature list ──────────────────────────────────────── */
    .pr-features {
        list-style: none;
        padding: 0;
        margin: 0 0 28px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .pr-features li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: .9rem;
        line-height: 1.45;
        color: var(--pr-text);
    }
    .pr-features li.highlighted {
        font-weight: 600;
    }
    .pr-feat-icon {
        flex-shrink: 0;
        font-size: 1rem;
        line-height: 1.4;
    }

    /* ── CTA button ────────────────────────────────────────── */
    .pr-btn {
        display: block;
        width: 100%;
        padding: 14px 20px;
        border-radius: 10px;
        text-align: center;
        font-weight: 700;
        font-size: .95rem;
        text-decoration: none;
        transition: background .2s, color .2s, transform .15s;
        cursor: pointer;
        border: none;
    }
    .pr-btn-primary {
        background: var(--pr-brand);
        color: #fff;
    }
    .pr-btn-primary:hover {
        background: var(--pr-brand-dark);
        color: #fff;
        transform: translateY(-1px);
    }
    .pr-btn-secondary {
        background: #fff;
        color: var(--pr-brand);
        border: 2px solid var(--pr-brand);
    }
    .pr-btn-secondary:hover {
        background: #fff5f5;
        transform: translateY(-1px);
    }

    /* ── Trust strip ───────────────────────────────────────── */
    .pr-trust {
        text-align: center;
        padding: 0 24px 64px;
        color: var(--pr-muted);
        font-size: .875rem;
    }
    .pr-trust-items {
        display: flex;
        justify-content: center;
        gap: 32px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .pr-trust-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* ── FAQ ───────────────────────────────────────────────── */
    .pr-faq {
        max-width: 680px;
        margin: 0 auto;
        padding: 0 24px 80px;
    }
    .pr-faq h2 {
        text-align: center;
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 32px;
    }
    .pr-faq details {
        border-bottom: 1px solid var(--pr-border);
        padding: 16px 0;
    }
    .pr-faq summary {
        font-weight: 600;
        cursor: pointer;
        list-style: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: .95rem;
    }
    .pr-faq summary::-webkit-details-marker { display: none; }
    .pr-faq summary::after {
        content: '+';
        font-size: 1.4rem;
        color: var(--pr-brand);
        transition: transform .2s;
    }
    .pr-faq details[open] summary::after { transform: rotate(45deg); }
    .pr-faq details p {
        margin: 12px 0 0;
        color: var(--pr-muted);
        font-size: .9rem;
        line-height: 1.65;
    }

    @media (max-width: 640px) {
        .pr-card.featured { transform: none; }
        .pr-grid { grid-template-columns: 1fr; }
        .pr-trust-items { gap: 16px; }
    }
</style>

<div class="pr-page">

    {{-- ── Header ─────────────────────────────────────────── --}}
    <div class="pr-header">
        <h1>{{ __('site.pricing.title') }}</h1>
        <p>{{ __('site.pricing.subtitle') }}</p>
    </div>

    {{-- ── Plans grid ──────────────────────────────────────── --}}
    <div class="pr-grid">
        @forelse ($plans as $plan)
            @php
                $planName   = $plan->translate('name', $locale);
                $badge      = $plan->translate('badge', $locale);
                $shortDesc  = $plan->translate('short_description', $locale);
                $btnText    = $plan->translate('button_text', $locale) ?: __('site.pricing.default_cta');
                $btnUrl     = $plan->button_url ?: route('free-trial');
                $price      = number_format($plan->monthly_price / 100, 2, ',', '');
                $currency   = strtoupper($plan->currency ?? 'EUR') === 'EUR' ? '€' : strtoupper($plan->currency ?? 'EUR');
            @endphp
            <div class="pr-card {{ $plan->is_featured ? 'featured' : '' }}">

                @if ($badge)
                    <div class="pr-plan-badge">{{ $badge }}</div>
                @endif

                <div class="pr-plan-name">{{ $planName }}</div>

                <div class="pr-price-block">
                    <div class="pr-price">
                        <sup>{{ $currency }}</sup>{{ $price }}<span class="pr-price-period">/ {{ __('site.pricing.per_month') }}</span>
                    </div>
                    <div class="pr-commitment">{{ __('site.pricing.no_commitment') }}</div>
                </div>

                @if ($shortDesc)
                    <p class="pr-short-desc">{{ $shortDesc }}</p>
                @endif

                <hr class="pr-divider">

                @if ($plan->marketingItems->isNotEmpty())
                    <ul class="pr-features">
                        @foreach ($plan->marketingItems as $item)
                            <li class="{{ $item->is_highlighted ? 'highlighted' : '' }}">
                                <span class="pr-feat-icon">{{ $item->icon }}</span>
                                <span>{{ $item->{"text_{$locale}"} ?? $item->text_fr }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ $btnUrl }}" class="pr-btn {{ $plan->is_featured ? 'pr-btn-primary' : 'pr-btn-secondary' }}">
                    {{ $btnText }}
                </a>
            </div>
        @empty
            <p style="text-align:center;color:var(--pr-muted)">{{ __('site.pricing.no_plans') }}</p>
        @endforelse
    </div>

    {{-- ── Trust strip ──────────────────────────────────────── --}}
    <div class="pr-trust">
        <div>{{ __('site.pricing.trust_intro') }}</div>
        <div class="pr-trust-items">
            <div class="pr-trust-item">🔒 {{ __('site.pricing.trust_ssl') }}</div>
            <div class="pr-trust-item">🚫 {{ __('site.pricing.trust_no_card') }}</div>
            <div class="pr-trust-item">❌ {{ __('site.pricing.trust_cancel') }}</div>
        </div>
    </div>

    {{-- ── FAQ ─────────────────────────────────────────────── --}}
    <div class="pr-faq">
        <h2>{{ __('site.pricing.faq_title') }}</h2>

        @foreach (['faq_1', 'faq_2', 'faq_3', 'faq_4'] as $faq)
            <details>
                <summary>{{ __("site.pricing.{$faq}_q") }}</summary>
                <p>{{ __("site.pricing.{$faq}_a") }}</p>
            </details>
        @endforeach
    </div>

</div>
@endsection
