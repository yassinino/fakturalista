@extends('layouts.master')

@section('title', __('site.legal_notice.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.legal_notice.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.legal_notice.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.legal_notice.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/legal-notice') }}" />
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
    'title'   => __('site.legal_notice.hero_title'),
    'paragraphs' => [__('site.legal_notice.hero_sub')],
])

<section class="lp-page">
    <div class="lp-content">

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.legal_notice.pub_title') }}</h2>
            <p class="lp-body">{{ __('site.legal_notice.pub_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.legal_notice.host_title') }}</h2>
            <p class="lp-body">{{ __('site.legal_notice.host_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.legal_notice.ip_title') }}</h2>
            <p class="lp-body">{{ __('site.legal_notice.ip_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.legal_notice.data_title') }}</h2>
            <p class="lp-body">{{ __('site.legal_notice.data_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.legal_notice.contact_title') }}</h2>
            <p class="lp-body">{{ __('site.legal_notice.contact_body') }}</p>
        </div>

    </div>
</section>

@endsection
