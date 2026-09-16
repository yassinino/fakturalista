@extends('layouts.master')

@section('title', __('site.integrations.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.integrations.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.integrations.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.integrations.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/integrations') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    .ig-page { background: #f7f8fc; padding-bottom: 80px; }

    /* ── Shared section layout ───────────────────────────────── */
    .ig-section {
        max-width: 1000px;
        margin: 0 auto;
        padding: 56px 24px 0;
    }
    .ig-section-title {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #9ca3af;
        margin-bottom: 24px;
    }

    /* ── Stripe card ─────────────────────────────────────────── */
    .ig-stripe-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 32px 32px;
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 28px;
        align-items: start;
        transition: box-shadow .2s, border-color .2s;
        max-width: 680px;
    }
    .ig-stripe-card:hover {
        box-shadow: 0 6px 24px rgba(26,32,54,.07);
        border-color: #d1d5db;
    }
    @media (max-width: 575px) {
        .ig-stripe-card {
            grid-template-columns: 1fr;
            padding: 24px 20px;
        }
    }

    .ig-stripe-logo {
        width: 56px;
        height: 56px;
        background: #635bff;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .ig-stripe-logo i {
        font-size: 26px;
        color: #fff;
    }

    .ig-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }
    .ig-card-name {
        font-size: 18px;
        font-weight: 700;
        color: #1a2036;
        margin: 0;
        line-height: 1;
    }
    .ig-badge-live {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        background: #dcfce7;
        color: #15803d;
        padding: 3px 9px;
        border-radius: 100px;
    }
    .ig-card-cat {
        font-size: 12px;
        font-weight: 500;
        color: #9ca3af;
        background: #f3f4f6;
        padding: 3px 9px;
        border-radius: 100px;
    }
    .ig-card-desc {
        font-size: 14px;
        line-height: 1.65;
        color: #6b7280;
        margin-bottom: 18px;
    }
    .ig-feat-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 7px;
    }
    .ig-feat-list li {
        font-size: 13px;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ig-feat-list li::before {
        content: '';
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #635bff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2 6l3 3 5-5' stroke='%23fff' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E%3C/svg%3E");
        background-size: 10px;
        background-repeat: no-repeat;
        background-position: center;
        flex-shrink: 0;
    }

    /* ── Coming soon ─────────────────────────────────────────── */
    .ig-coming-card {
        background: #fff;
        border: 1px dashed #d1d5db;
        border-radius: 16px;
        padding: 48px 40px;
        text-align: center;
        max-width: 560px;
    }
    .ig-coming-icon {
        width: 56px;
        height: 56px;
        background: #f3f4f6;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    .ig-coming-icon i {
        font-size: 22px;
        color: #9ca3af;
    }
    .ig-coming-title {
        font-size: 18px;
        font-weight: 700;
        color: #1a2036;
        margin-bottom: 12px;
    }
    .ig-coming-body {
        font-size: 14px;
        line-height: 1.65;
        color: #6b7280;
        margin-bottom: 24px;
    }
    .ig-request-btn {
        display: inline-block;
        background: #fa7070;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        padding: 11px 28px;
        border-radius: 8px;
        text-decoration: none;
        transition: background .2s, transform .15s;
    }
    .ig-request-btn:hover {
        background: #e85c5c;
        transform: translateY(-1px);
        text-decoration: none;
        color: #fff;
    }
    @media (max-width: 575px) {
        .ig-coming-card { padding: 36px 24px; }
    }
</style>

@include('partials.page-hero', [
    'title'      => __('site.integrations.hero_title'),
    'paragraphs' => [__('site.integrations.hero_sub')],
])

<div class="ig-page">

    {{-- Live integrations --}}
    <div class="ig-section">
        <p class="ig-section-title">{{ __('site.integrations.live_title') }}</p>

        <div class="ig-stripe-card">
            <div class="ig-stripe-logo">
                <i class="fab fa-stripe-s"></i>
            </div>
            <div>
                <div class="ig-card-header">
                    <h3 class="ig-card-name">{{ __('site.integrations.stripe_name') }}</h3>
                    <span class="ig-badge-live">{{ __('site.integrations.stripe_badge') }}</span>
                    <span class="ig-card-cat">{{ __('site.integrations.stripe_cat') }}</span>
                </div>
                <p class="ig-card-desc">{{ __('site.integrations.stripe_desc') }}</p>
                <ul class="ig-feat-list">
                    <li>{{ __('site.integrations.stripe_feat_1') }}</li>
                    <li>{{ __('site.integrations.stripe_feat_2') }}</li>
                    <li>{{ __('site.integrations.stripe_feat_3') }}</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Coming soon --}}
    <div class="ig-section">
        <p class="ig-section-title">{{ __('site.integrations.coming_title') }}</p>

        <div class="ig-coming-card">
            <div class="ig-coming-icon"><i class="fas fa-plug"></i></div>
            <h3 class="ig-coming-title">{{ __('site.integrations.coming_title') }}</h3>
            <p class="ig-coming-body">{{ __('site.integrations.coming_body') }}</p>
            <a href="{{ url('/contact') }}" class="ig-request-btn">{{ __('site.integrations.request_btn') }}</a>
        </div>
    </div>

</div>

@endsection
