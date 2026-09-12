@extends('layouts.master')

@section('title', __('site.faq.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.faq.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.faq.page_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.faq.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/faq') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    /* ── Page wrapper ────────────────────────────────────────── */
    .fq-page {
        background: #f7f8fc;
        font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        color: #111827;
    }

    /* ── Content area ────────────────────────────────────────── */
    .fq-content {
        max-width: 780px;
        margin: 0 auto;
        padding: 56px 24px 80px;
    }

    /* ── Category section ────────────────────────────────────── */
    .fq-category {
        margin-bottom: 48px;
    }
    .fq-category:last-of-type {
        margin-bottom: 0;
    }
    .fq-cat-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #fa7070;
        margin-bottom: 16px;
    }
    .fq-cat-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }

    /* ── Accordion item ──────────────────────────────────────── */
    .fq-item {
        background: #ffffff;
        border: 1.5px solid #e5e7eb;
        border-radius: 12px;
        margin-bottom: 8px;
        overflow: hidden;
        transition: border-color .2s, box-shadow .2s;
    }
    .fq-item:last-child { margin-bottom: 0; }
    .fq-item.is-open {
        border-color: #fa7070;
        box-shadow: 0 4px 20px rgba(250,112,112,.10);
    }

    /* ── Question button ─────────────────────────────────────── */
    .fq-btn {
        width: 100%;
        background: none;
        border: none;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        text-align: left;
        cursor: pointer;
        font-family: inherit;
    }
    .fq-btn:focus-visible {
        outline: 2px solid #fa7070;
        outline-offset: -2px;
        border-radius: 10px;
    }
    .fq-question {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
        line-height: 1.45;
        flex: 1;
    }
    .fq-item.is-open .fq-question {
        color: #c0392b;
    }

    /* ── Chevron ─────────────────────────────────────────────── */
    .fq-chevron {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        color: #9ca3af;
        transition: transform .22s ease, color .2s;
    }
    .fq-item.is-open .fq-chevron {
        transform: rotate(180deg);
        color: #fa7070;
    }

    /* ── Answer panel ────────────────────────────────────────── */
    .fq-body {
        max-height: 0;
        overflow: hidden;
        transition: max-height .3s ease;
    }
    .fq-item.is-open .fq-body {
        max-height: 600px;
    }
    .fq-body-inner {
        padding: 0 24px 22px;
        font-size: .925rem;
        color: #4b5563;
        line-height: 1.7;
    }

    /* ── "Still have questions?" CTA ─────────────────────────── */
    .fq-cta {
        text-align: center;
        padding: 64px 24px 80px;
        background: #ffffff;
        border-top: 1px solid #e5e7eb;
    }
    .fq-cta-inner { max-width: 500px; margin: 0 auto; }
    .fq-cta-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 52px;
        height: 52px;
        background: #fff0f0;
        border-radius: 14px;
        margin-bottom: 20px;
        color: #fa7070;
        font-size: 22px;
    }
    .fq-cta h2 {
        font-size: clamp(1.4rem, 3vw, 1.9rem);
        font-weight: 700;
        color: #111827;
        margin: 0 0 12px;
        letter-spacing: -.02em;
    }
    .fq-cta p {
        font-size: 1rem;
        color: #6b7280;
        line-height: 1.65;
        margin: 0 0 28px;
    }

    /* ── Responsive ──────────────────────────────────────────── */
    @media (max-width: 600px) {
        .fq-content { padding: 40px 16px 64px; }
        .fq-btn { padding: 16px 18px; }
        .fq-body-inner { padding: 0 18px 18px; }
        .fq-cta { padding: 48px 16px 64px; }
    }
</style>

@php
$categories = [
    [
        'label' => __('site.faq.cat_start'),
        'items' => [
            ['q' => __('site.faq.start_1_q'), 'a' => __('site.faq.start_1_a')],
            ['q' => __('site.faq.start_2_q'), 'a' => __('site.faq.start_2_a')],
            ['q' => __('site.faq.start_3_q'), 'a' => __('site.faq.start_3_a')],
            ['q' => __('site.faq.start_4_q'), 'a' => __('site.faq.start_4_a')],
        ],
    ],
    [
        'label' => __('site.faq.cat_billing'),
        'items' => [
            ['q' => __('site.faq.billing_1_q'), 'a' => __('site.faq.billing_1_a')],
            ['q' => __('site.faq.billing_2_q'), 'a' => __('site.faq.billing_2_a')],
            ['q' => __('site.faq.billing_3_q'), 'a' => __('site.faq.billing_3_a')],
            ['q' => __('site.faq.billing_4_q'), 'a' => __('site.faq.billing_4_a')],
        ],
    ],
    [
        'label' => __('site.faq.cat_invoices'),
        'items' => [
            ['q' => __('site.faq.invoices_1_q'), 'a' => __('site.faq.invoices_1_a')],
            ['q' => __('site.faq.invoices_2_q'), 'a' => __('site.faq.invoices_2_a')],
            ['q' => __('site.faq.invoices_3_q'), 'a' => __('site.faq.invoices_3_a')],
            ['q' => __('site.faq.invoices_4_q'), 'a' => __('site.faq.invoices_4_a')],
        ],
    ],
    [
        'label' => __('site.faq.cat_security'),
        'items' => [
            ['q' => __('site.faq.security_1_q'), 'a' => __('site.faq.security_1_a')],
            ['q' => __('site.faq.security_2_q'), 'a' => __('site.faq.security_2_a')],
            ['q' => __('site.faq.security_3_q'), 'a' => __('site.faq.security_3_a')],
        ],
    ],
];
@endphp

@include('partials.page-hero', [
    'title'      => __('site.faq.hero_title'),
    'paragraphs' => [__('site.faq.hero_sub')],
])

<div class="fq-page">

    <div class="fq-content">
        @foreach ($categories as $cat)
            <div class="fq-category">
                <div class="fq-cat-label">{{ $cat['label'] }}</div>

                @foreach ($cat['items'] as $i => $item)
                    <div class="fq-item" id="fq-{{ $loop->parent->index }}-{{ $i }}">
                        <button
                            class="fq-btn"
                            type="button"
                            aria-expanded="false"
                            aria-controls="fq-body-{{ $loop->parent->index }}-{{ $i }}"
                        >
                            <span class="fq-question">{{ $item['q'] }}</span>
                            <svg class="fq-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>
                        <div
                            class="fq-body"
                            id="fq-body-{{ $loop->parent->index }}-{{ $i }}"
                            role="region"
                        >
                            <div class="fq-body-inner">{{ $item['a'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <!-- Still have questions? -->
    <div class="fq-cta">
        <div class="fq-cta-inner">
            <div class="fq-cta-icon">💬</div>
            <h2>{{ __('site.faq.still_title') }}</h2>
            <p>{{ __('site.faq.still_sub') }}</p>
            <a href="{{ url('/contact') }}" class="pix-btn">{{ __('site.faq.still_btn') }}</a>
        </div>
    </div>

</div>

<script>
(function () {
    document.querySelectorAll('.fq-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = btn.closest('.fq-item');
            var isOpen = item.classList.contains('is-open');
            item.classList.toggle('is-open', !isOpen);
            btn.setAttribute('aria-expanded', !isOpen);
        });
    });
})();
</script>

@endsection
