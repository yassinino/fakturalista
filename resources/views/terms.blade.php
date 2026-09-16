@extends('layouts.master')

@section('title', __('site.terms.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.terms.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.terms.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.terms.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/terms') }}" />
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
</style>

@include('partials.page-hero', [
    'title'      => __('site.terms.hero_title'),
    'paragraphs' => [__('site.terms.hero_sub')],
])

<section class="lp-page">
    <div class="lp-content">

        <p class="lp-updated">{{ __('site.terms.updated') }}</p>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.intro_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.intro_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.account_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.account_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.trial_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.trial_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.plans_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.plans_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.payment_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.payment_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.cancel_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.cancel_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.use_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.use_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.availability_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.availability_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.ip_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.ip_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.liability_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.liability_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.law_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.law_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.changes_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.changes_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.terms.contact_title') }}</h2>
            <p class="lp-body">{{ __('site.terms.contact_body') }}</p>
        </div>

    </div>
</section>

@endsection
