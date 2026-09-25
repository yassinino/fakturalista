@extends('layouts.master')

@section('title', __('site.help.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.help.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.help.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.help.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/help-center') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    .hc-page { background: #f7f8fc; }

    /* ── Search bar ──────────────────────────────────────────── */
    .hc-search-wrap {
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        padding: 32px 24px;
    }
    .hc-search-form {
        max-width: 560px;
        margin: 0 auto;
        display: flex;
        gap: 0;
        border: 1.5px solid #d1d5db;
        border-radius: 10px;
        overflow: hidden;
        transition: border-color .2s, box-shadow .2s;
    }
    .hc-search-form:focus-within {
        border-color: #fa7070;
        box-shadow: 0 0 0 3px rgba(250,112,112,.12);
    }
    .hc-search-input {
        flex: 1;
        border: none;
        outline: none;
        padding: 13px 18px;
        font-size: .95rem;
        color: #111827;
        background: transparent;
        font-family: inherit;
    }
    .hc-search-input::placeholder { color: #9ca3af; }
    .hc-search-btn {
        background: #fa7070;
        border: none;
        padding: 0 20px;
        color: #fff;
        font-size: 15px;
        cursor: pointer;
        transition: background .15s;
    }
    .hc-search-btn:hover { background: #e85555; }

    /* ── Category cards ──────────────────────────────────────── */
    .hc-browse {
        max-width: 960px;
        margin: 0 auto;
        padding: 56px 24px 48px;
    }
    .hc-section-title {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #9ca3af;
        margin: 0 0 20px;
    }
    .hc-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 16px;
    }
    .hc-card {
        background: #fff;
        border: 1.5px solid #e5e7eb;
        border-radius: 14px;
        padding: 24px 20px;
        text-decoration: none;
        transition: border-color .2s, box-shadow .2s, transform .15s;
        display: block;
    }
    .hc-card:hover {
        border-color: #fa7070;
        box-shadow: 0 4px 20px rgba(250,112,112,.10);
        transform: translateY(-2px);
        text-decoration: none;
    }
    .hc-card-icon {
        width: 44px;
        height: 44px;
        background: #fff0f0;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fa7070;
        font-size: 18px;
        margin-bottom: 14px;
    }
    .hc-card-title {
        font-size: .975rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 6px;
    }
    .hc-card-desc {
        font-size: .855rem;
        color: #6b7280;
        line-height: 1.6;
        margin: 0;
    }

    /* ── FAQ section ─────────────────────────────────────────── */
    .hc-faq {
        max-width: 960px;
        margin: 0 auto;
        padding: 0 24px 56px;
    }
    .hc-faq-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 8px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    .hc-faq-header h2 {
        font-size: 1.15rem;
        font-weight: 700;
        color: #2b2350;
        margin: 0;
    }
    .hc-faq-link {
        font-size: .875rem;
        font-weight: 600;
        color: #fa7070;
        text-decoration: none;
    }
    .hc-faq-link:hover { color: #e85555; text-decoration: none; }
    .hc-faq-sub {
        font-size: .9rem;
        color: #6b7280;
        margin: 0 0 24px;
    }

    /* Reuse .fq-* accordion styles from faq.blade with local overrides */
    .hc-faq .fq-item {
        background: #fff;
        border: 1.5px solid #e5e7eb;
        border-radius: 12px;
        margin-bottom: 8px;
        overflow: hidden;
        transition: border-color .2s, box-shadow .2s;
    }
    .hc-faq .fq-item.is-open {
        border-color: #fa7070;
        box-shadow: 0 4px 20px rgba(250,112,112,.10);
    }
    .hc-faq .fq-btn {
        width: 100%;
        background: none;
        border: none;
        padding: 18px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        text-align: left;
        cursor: pointer;
        font-family: inherit;
    }
    .hc-faq .fq-question {
        font-size: .95rem;
        font-weight: 600;
        color: #111827;
        line-height: 1.45;
        flex: 1;
    }
    .hc-faq .fq-item.is-open .fq-question { color: #c0392b; }
    .hc-faq .fq-chevron {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        color: #9ca3af;
        transition: transform .22s ease;
    }
    .hc-faq .fq-item.is-open .fq-chevron { transform: rotate(180deg); color: #fa7070; }
    .hc-faq .fq-body { max-height: 0; overflow: hidden; transition: max-height .3s ease; }
    .hc-faq .fq-item.is-open .fq-body { max-height: 400px; }
    .hc-faq .fq-body-inner { padding: 0 22px 18px; font-size: .88rem; color: #4b5563; line-height: 1.7; }

    /* ── CTA ─────────────────────────────────────────────────── */
    .hc-cta {
        background: #fff;
        border-top: 1px solid #e5e7eb;
        text-align: center;
        padding: 56px 24px 72px;
    }
    .hc-cta-inner { max-width: 480px; margin: 0 auto; }
    .hc-cta-icon {
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
    .hc-cta h2 { font-size: clamp(1.3rem, 3vw, 1.75rem); font-weight: 700; color: #111827; margin: 0 0 10px; }
    .hc-cta p { font-size: 1rem; color: #6b7280; margin: 0 0 28px; }

    @media (max-width: 600px) {
        .hc-cards { grid-template-columns: 1fr 1fr; }
        .hc-browse, .hc-faq { padding-left: 16px; padding-right: 16px; }
    }
    @media (max-width: 400px) {
        .hc-cards { grid-template-columns: 1fr; }
    }

    /* ══════════════════════════════════════════════════════════════
       RTL (Arabic) mirror - see master.blade.php for pattern/rationale
       ══════════════════════════════════════════════════════════════ */
    html[dir="rtl"] .hc-faq .fq-btn {
        text-align: right;
    }
</style>

@php
$cards = [
    ['icon' => 'fas fa-rocket',       'href' => url('/faq'), 'title' => __('site.help.cat_start'),    'desc' => __('site.help.cat_start_desc')],
    ['icon' => 'fas fa-credit-card',  'href' => url('/faq'), 'title' => __('site.help.cat_billing'),  'desc' => __('site.help.cat_billing_desc')],
    ['icon' => 'fas fa-file-invoice', 'href' => url('/faq'), 'title' => __('site.help.cat_invoices'), 'desc' => __('site.help.cat_invoices_desc')],
    ['icon' => 'fas fa-user-cog',     'href' => url('/faq'), 'title' => __('site.help.cat_account'),  'desc' => __('site.help.cat_account_desc')],
];

$featured_faqs = [
    ['q' => __('site.faq.start_1_q'), 'a' => __('site.faq.start_1_a')],
    ['q' => __('site.faq.billing_1_q'), 'a' => __('site.faq.billing_1_a')],
    ['q' => __('site.faq.billing_2_q'), 'a' => __('site.faq.billing_2_a')],
    ['q' => __('site.faq.invoices_1_q'), 'a' => __('site.faq.invoices_1_a')],
];
@endphp

@include('partials.page-hero', [
    'title'      => __('site.help.hero_title'),
    'paragraphs' => [__('site.help.hero_sub')],
])

<div class="hc-page">

    {{-- Search bar --}}
    <div class="hc-search-wrap">
        <form class="hc-search-form" action="{{ url('/faq') }}" method="GET" role="search">
            <input
                type="search"
                name="q"
                class="hc-search-input"
                placeholder="{{ __('site.help.search_placeholder') }}"
                aria-label="{{ __('site.help.search_placeholder') }}"
            >
            <button type="submit" class="hc-search-btn" aria-label="Search">
                <i class="fas fa-search" aria-hidden="true"></i>
            </button>
        </form>
    </div>

    {{-- Category cards --}}
    <div class="hc-browse">
        <p class="hc-section-title">{{ __('site.help.browse_title') }}</p>
        <div class="hc-cards">
            @foreach ($cards as $card)
                <a href="{{ $card['href'] }}" class="hc-card">
                    <div class="hc-card-icon" aria-hidden="true">
                        <i class="{{ $card['icon'] }}"></i>
                    </div>
                    <p class="hc-card-title">{{ $card['title'] }}</p>
                    <p class="hc-card-desc">{{ $card['desc'] }}</p>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Featured FAQ accordion --}}
    <div class="hc-faq">
        <div class="hc-faq-header">
            <h2>{{ __('site.help.faq_title') }}</h2>
            <a href="{{ url('/faq') }}" class="hc-faq-link">{{ __('site.help.faq_btn') }} →</a>
        </div>
        <p class="hc-faq-sub">{{ __('site.help.faq_sub') }}</p>

        @foreach ($featured_faqs as $i => $item)
            <div class="fq-item hc-fq-item">
                <button
                    class="fq-btn"
                    type="button"
                    aria-expanded="false"
                    aria-controls="hc-fq-body-{{ $i }}"
                >
                    <span class="fq-question">{{ $item['q'] }}</span>
                    <svg class="fq-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                </button>
                <div class="fq-body" id="hc-fq-body-{{ $i }}" role="region">
                    <div class="fq-body-inner">{{ $item['a'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- CTA --}}
    <div class="hc-cta">
        <div class="hc-cta-inner">
            <div class="hc-cta-icon" aria-hidden="true"><i class="fas fa-envelope"></i></div>
            <h2>{{ __('site.help.cta_title') }}</h2>
            <p>{{ __('site.help.cta_body') }}</p>
            <a href="{{ url('/contact') }}" class="pix-btn">{{ __('site.help.cta_btn') }}</a>
        </div>
    </div>

</div>

<script>
(function () {
    document.querySelectorAll('.hc-fq-item .fq-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = btn.closest('.fq-item');
            var isOpen = item.classList.contains('is-open');
            item.classList.toggle('is-open', !isOpen);
            btn.setAttribute('aria-expanded', String(!isOpen));
        });
    });
})();
</script>

@endsection
