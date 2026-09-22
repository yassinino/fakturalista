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

@include('partials.page-hero', [
    'title'      => __('site.pricing.title'),
    'paragraphs' => [__('site.pricing.sub')],
])

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    :root {
        --pr-brand:       #fa7070;
        --pr-brand-dark:  #e05050;
        --pr-navy:        #1a1a2e;
        --pr-page-bg:     #f7f8fc;
        --pr-card-bg:     #ffffff;
        --pr-text:        #111827;
        --pr-muted:       #6b7280;
        --pr-border:      #e5e7eb;
        --pr-shadow:      0 2px 12px rgba(0,0,0,.06);
        --pr-radius:      18px;
    }

    .pr-page {
        background: var(--pr-page-bg);
        color: var(--pr-text);
        font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    }

    /* ── Market switch + reassurance line above cards ────────── */
    .pr-top {
        text-align: center;
        max-width: 640px;
        margin: 0 auto 36px;
        padding: 0 24px;
    }
    .pr-market-switch {
        font-size: .82rem;
        color: var(--pr-muted);
        text-decoration: underline;
    }
    .pr-reassurance-line {
        font-size: .92rem;
        font-weight: 600;
        color: var(--pr-text);
        margin-top: 10px;
    }

    /* ── Grid ──────────────────────────────────────────────── */
    .pr-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
        max-width: 1000px;
        margin: 0 auto;
        padding: 0 24px 56px;
        align-items: stretch;
    }

    /* ── Card ──────────────────────────────────────────────── */
    .pr-card {
        background: var(--pr-card-bg);
        border: 1.5px solid var(--pr-border);
        border-radius: var(--pr-radius);
        padding: 32px 28px;
        box-shadow: var(--pr-shadow);
        display: flex;
        flex-direction: column;
        position: relative;
        transition: transform .2s, box-shadow .2s;
    }
    .pr-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 32px rgba(0,0,0,.09);
    }
    .pr-card.featured {
        border-color: var(--pr-brand);
        box-shadow: 0 8px 28px rgba(250,112,112,.16);
    }
    .pr-card.featured:hover {
        transform: translateY(-5px);
        box-shadow: 0 14px 40px rgba(250,112,112,.22);
    }

    /* ── Badge ─────────────────────────────────────────────── */
    .pr-plan-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--pr-brand);
        color: #fff;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        padding: 4px 14px;
        border-radius: 99px;
        white-space: nowrap;
    }

    /* ── Plan name + description ───────────────────────────── */
    .pr-plan-name {
        font-size: 1.15rem;
        font-weight: 700;
        margin: 4px 0 6px;
        color: var(--pr-text);
    }
    .pr-short-desc {
        font-size: .84rem;
        color: var(--pr-muted);
        margin: 0 0 20px;
        line-height: 1.5;
        min-height: 38px;
    }

    /* ── Price ─────────────────────────────────────────────── */
    .pr-price-block { margin-bottom: 4px; }
    .pr-price { display: flex; align-items: baseline; gap: 6px; }
    .pr-price-num {
        font-size: 2.4rem;
        font-weight: 800;
        color: var(--pr-text);
        line-height: 1;
    }
    .pr-price-currency {
        font-size: .95rem;
        font-weight: 700;
        color: var(--pr-muted);
    }
    .pr-price-period {
        font-size: .78rem;
        color: var(--pr-muted);
        margin-bottom: 14px;
    }

    /* ── Capacity line (the one headline benefit) ─────────────── */
    .pr-capacity {
        font-size: .88rem;
        font-weight: 600;
        color: var(--pr-navy);
        background: rgba(250,112,112,.08);
        border-radius: 8px;
        padding: 8px 12px;
        margin: 0 0 20px;
    }

    /* ── Divider ───────────────────────────────────────────── */
    .pr-divider {
        border: none;
        border-top: 1px solid var(--pr-border);
        margin: 0 0 18px;
    }

    /* ── Benefit list ──────────────────────────────────────── */
    .pr-features {
        list-style: none;
        padding: 0;
        margin: 0 0 24px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .pr-features li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: .88rem;
        line-height: 1.4;
        color: var(--pr-text);
    }
    .pr-feat-icon {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: rgba(16,185,129,.12);
        color: #10b981;
        font-size: .68rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 1px;
    }

    /* ── CTA buttons ───────────────────────────────────────── */
    .pr-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 48px;
        border-radius: 10px;
        text-align: center;
        font-weight: 700;
        font-size: .92rem;
        text-decoration: none;
        transition: background .2s, color .2s, transform .15s, border-color .2s;
        cursor: pointer;
        box-sizing: border-box;
    }
    .pr-btn-primary {
        background: var(--pr-brand);
        color: #fff;
        border: 2px solid var(--pr-brand);
    }
    .pr-btn-primary:hover {
        background: var(--pr-brand-dark);
        border-color: var(--pr-brand-dark);
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
    .pr-btn-dark {
        background: var(--pr-navy);
        color: #fff;
        border: 2px solid var(--pr-navy);
    }
    .pr-btn-dark:hover {
        background: #000;
        border-color: #000;
        transform: translateY(-1px);
    }

    /* ── Reassurance row below the cards ──────────────────────── */
    .pr-trust {
        text-align: center;
        padding: 0 24px 64px;
        color: var(--pr-muted);
        font-size: .85rem;
    }
    .pr-trust-items {
        display: flex;
        justify-content: center;
        gap: 28px;
        flex-wrap: wrap;
    }
    .pr-trust-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .pr-trust-item .pr-trust-check {
        color: #10b981;
        font-weight: 800;
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

    @media (max-width: 900px) {
        .pr-grid { grid-template-columns: 1fr; max-width: 420px; }
        .pr-card.featured { order: -1; }
        .pr-trust-items { gap: 16px; }
    }
</style>

<div class="pr-page">

    {{-- ── Market switch + single reassurance line (never repeated
         per-card) - market is independent of language; default is
         Morocco/MAD ───────────────────────────────────────────── --}}
    <div class="pr-top">
        @if ($market === 'ES')
            <a href="{{ url('/pricing?market=MA') }}" class="pr-market-switch">{{ __('site.pricing.switch_to_morocco') }}</a>
        @else
            <a href="{{ url('/pricing?market=ES') }}" class="pr-market-switch">{{ __('site.pricing.switch_to_spain') }}</a>
        @endif
        <p class="pr-reassurance-line">{{ __('site.pricing.reassurance_line') }}</p>
    </div>

    {{-- ── Plans grid - each $card is fully pre-computed by
         PlanPricingPresenter from Plan/PlanLimit/PlanPrice/Feature, so
         this template only renders, never decides what a plan includes ── --}}
    <div class="pr-grid">
        @forelse ($cards as $card)
            <div class="pr-card {{ $card['is_featured'] ? 'featured' : '' }}">

                @if ($card['badge'])
                    <div class="pr-plan-badge">{{ $card['badge'] }}</div>
                @endif

                <div class="pr-plan-name">{{ $card['name'] }}</div>

                @if ($card['description'])
                    <p class="pr-short-desc">{{ $card['description'] }}</p>
                @endif

                <div class="pr-price-block">
                    @if ($card['price'] !== null)
                        <div class="pr-price">
                            <span class="pr-price-num">{{ $card['price'] }}</span>
                            <span class="pr-price-currency">{{ $card['currency'] }}</span>
                        </div>
                        <div class="pr-price-period">{{ __('site.pricing.per_month') }}</div>
                    @else
                        <div class="pr-price"><span class="pr-price-currency">{{ __('site.pricing.price_unavailable') }}</span></div>
                    @endif
                </div>

                <p class="pr-capacity">{{ $card['capacity_line'] }}</p>

                <hr class="pr-divider">

                <ul class="pr-features">
                    @foreach ($card['benefits'] as $line)
                        <li><span class="pr-feat-icon">✓</span><span>{{ $line }}</span></li>
                    @endforeach
                </ul>

                <a href="{{ $card['button_url'] }}" class="pr-btn {{ $card['is_featured'] ? 'pr-btn-primary' : ($card['slug'] === 'business' ? 'pr-btn-dark' : 'pr-btn-secondary') }}">
                    {{ $card['button_text'] }}
                </a>
            </div>
        @empty
            <p style="text-align:center;color:var(--pr-muted)">{{ __('site.pricing.no_plans') }}</p>
        @endforelse
    </div>

    {{-- ── Small reassurance row below the cards - true claims only ── --}}
    <div class="pr-trust">
        <div class="pr-trust-items">
            <div class="pr-trust-item"><span class="pr-trust-check">✓</span> {{ __('site.pricing.reassurance_trial') }}</div>
            <div class="pr-trust-item"><span class="pr-trust-check">✓</span> {{ __('site.pricing.reassurance_no_card') }}</div>
            <div class="pr-trust-item"><span class="pr-trust-check">✓</span> {{ __('site.pricing.reassurance_pdf') }}</div>
            <div class="pr-trust-item"><span class="pr-trust-check">✓</span> {{ __('site.pricing.reassurance_data') }}</div>
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
