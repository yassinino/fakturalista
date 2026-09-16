@extends('layouts.master')

@section('title', __('site.security.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.security.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.security.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.security.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/security') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    .se-page { background: #f7f8fc; padding-bottom: 80px; }

    /* ── Intro ───────────────────────────────────────────────── */
    .se-intro-wrap {
        max-width: 680px;
        margin: 0 auto;
        padding: 48px 24px 0;
        text-align: center;
    }
    .se-intro {
        font-size: 16px;
        line-height: 1.7;
        color: #4b5563;
    }

    /* ── Feature grid ────────────────────────────────────────── */
    .se-grid-wrap {
        max-width: 1060px;
        margin: 0 auto;
        padding: 48px 24px 0;
    }
    .se-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }
    @media (max-width: 991px) {
        .se-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 575px) {
        .se-grid { grid-template-columns: 1fr; }
    }

    .se-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 28px 24px;
        transition: box-shadow .2s, border-color .2s;
    }
    .se-card:hover {
        box-shadow: 0 6px 24px rgba(26,32,54,.07);
        border-color: #d1d5db;
    }
    .se-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #1a2036;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
        flex-shrink: 0;
    }
    .se-card-icon i {
        font-size: 18px;
        color: #fff;
    }
    .se-card-title {
        font-size: 16px;
        font-weight: 600;
        color: #1a2036;
        margin-bottom: 10px;
        line-height: 1.3;
    }
    .se-card-body {
        font-size: 14px;
        line-height: 1.65;
        color: #6b7280;
        margin: 0;
    }
    .se-card-body a {
        color: #fa7070;
        text-decoration: none;
        font-weight: 500;
    }
    .se-card-body a:hover { text-decoration: underline; }

    /* ── Disclosure strip ────────────────────────────────────── */
    .se-disclosure {
        margin: 64px 24px 0;
        max-width: 760px;
        margin-left: auto;
        margin-right: auto;
        background: #1a2036;
        border-radius: 16px;
        padding: 40px 40px;
        text-align: center;
    }
    .se-disclosure-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 12px;
    }
    .se-disclosure-body {
        font-size: 15px;
        line-height: 1.6;
        color: #a0aec0;
        margin-bottom: 24px;
    }
    .se-disclosure-link {
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
    .se-disclosure-link:hover {
        background: #e85c5c;
        transform: translateY(-1px);
        text-decoration: none;
        color: #fff;
    }
    @media (max-width: 575px) {
        .se-disclosure { padding: 32px 24px; }
    }
</style>

@include('partials.page-hero', [
    'title'      => __('site.security.hero_title'),
    'paragraphs' => [__('site.security.hero_sub')],
])

<div class="se-page">

    <div class="se-intro-wrap">
        <p class="se-intro">{{ __('site.security.intro') }}</p>
    </div>

    <div class="se-grid-wrap">
        <div class="se-grid">

            <div class="se-card">
                <div class="se-card-icon"><i class="fas fa-lock"></i></div>
                <h3 class="se-card-title">{{ __('site.security.tls_title') }}</h3>
                <p class="se-card-body">{{ __('site.security.tls_body') }}</p>
            </div>

            <div class="se-card">
                <div class="se-card-icon"><i class="fas fa-server"></i></div>
                <h3 class="se-card-title">{{ __('site.security.infra_title') }}</h3>
                <p class="se-card-body">{{ __('site.security.infra_body') }}</p>
            </div>

            <div class="se-card">
                <div class="se-card-icon"><i class="fas fa-database"></i></div>
                <h3 class="se-card-title">{{ __('site.security.backups_title') }}</h3>
                <p class="se-card-body">{{ __('site.security.backups_body') }}</p>
            </div>

            <div class="se-card">
                <div class="se-card-icon"><i class="fas fa-shield-alt"></i></div>
                <h3 class="se-card-title">{{ __('site.security.auth_title') }}</h3>
                <p class="se-card-body">{{ __('site.security.auth_body') }}</p>
            </div>

            <div class="se-card">
                <div class="se-card-icon"><i class="fas fa-credit-card"></i></div>
                <h3 class="se-card-title">{{ __('site.security.payments_title') }}</h3>
                <p class="se-card-body">{{ __('site.security.payments_body') }}</p>
            </div>

            <div class="se-card">
                <div class="se-card-icon"><i class="fas fa-user-shield"></i></div>
                <h3 class="se-card-title">{{ __('site.security.gdpr_title') }}</h3>
                <p class="se-card-body">
                    {{ __('site.security.gdpr_body') }}
                    <a href="{{ url('/privacy-policy') }}">{{ __('site.security.gdpr_link') }}</a>
                </p>
            </div>

        </div>
    </div>

    <div class="se-disclosure">
        <h3 class="se-disclosure-title">{{ __('site.security.disclosure_title') }}</h3>
        <p class="se-disclosure-body">{{ __('site.security.disclosure_body') }}</p>
        <a href="mailto:contact@fakturalista.com" class="se-disclosure-link">contact@fakturalista.com</a>
    </div>

</div>

@endsection
