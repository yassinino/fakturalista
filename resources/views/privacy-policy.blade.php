@extends('layouts.master')

@section('title', __('site.privacy_policy.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.privacy_policy.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.privacy_policy.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.privacy_policy.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/privacy-policy') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    .lp-page {
        background: #f7f8fc;
    }
    .lp-content {
        max-width: 780px;
        margin: 0 auto;
        padding: 48px 24px 80px;
    }
    .lp-updated {
        font-size: 13px;
        color: #9ca3af;
        margin-bottom: 40px;
    }
    .lp-section {
        margin-bottom: 40px;
    }
    .lp-heading {
        font-size: 1.15rem;
        font-weight: 700;
        color: #2b2350;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #fde8e8;
    }
    .lp-body {
        font-size: 15.5px;
        line-height: 1.85;
        color: #374151;
        margin: 0;
    }
    .lp-rights-list {
        list-style: none;
        padding: 0;
        margin: 12px 0 0;
    }
    .lp-rights-list li {
        font-size: 15.5px;
        line-height: 1.75;
        color: #374151;
        padding: 8px 12px 8px 36px;
        position: relative;
        border-radius: 6px;
        margin-bottom: 4px;
    }
    .lp-rights-list li:hover {
        background: #fdf2f2;
    }
    .lp-rights-list li::before {
        content: "→";
        position: absolute;
        left: 12px;
        color: #fa7070;
        font-weight: 700;
    }

    /* ══════════════════════════════════════════════════════════════
       RTL (Arabic) mirror - see master.blade.php for pattern/rationale
       ══════════════════════════════════════════════════════════════ */
    html[dir="rtl"] .lp-rights-list li {
        padding: 8px 36px 8px 12px;
    }
    html[dir="rtl"] .lp-rights-list li::before {
        left: auto;
        right: 12px;
    }
</style>

@include('partials.page-hero', [
    'title'      => __('site.privacy_policy.hero_title'),
    'paragraphs' => [__('site.privacy_policy.hero_sub')],
])

<section class="lp-page">
    <div class="lp-content">

        <p class="lp-updated">{{ __('site.privacy_policy.updated') }}</p>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.intro_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.intro_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.controller_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.controller_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.data_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.data_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.purpose_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.purpose_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.basis_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.basis_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.retention_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.retention_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.third_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.third_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.rights_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.rights_intro') }}</p>
            <ul class="lp-rights-list">
                <li>{{ __('site.privacy_policy.rights_access') }}</li>
                <li>{{ __('site.privacy_policy.rights_rectify') }}</li>
                <li>{{ __('site.privacy_policy.rights_erase') }}</li>
                <li>{{ __('site.privacy_policy.rights_portability') }}</li>
                <li>{{ __('site.privacy_policy.rights_object') }}</li>
                <li>{{ __('site.privacy_policy.rights_restrict') }}</li>
            </ul>
            <p class="lp-body" style="margin-top: 16px;">{{ __('site.privacy_policy.rights_contact') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.transfers_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.transfers_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.changes_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.changes_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.privacy_policy.contact_title') }}</h2>
            <p class="lp-body">{{ __('site.privacy_policy.contact_body') }}</p>
        </div>

    </div>
</section>

@endsection
