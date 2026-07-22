@extends('layouts.master')

@section('title', __('site.pricing.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.pricing.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.pricing.page_title') }} — Fakturalista" />
<meta property="og:description" content="{{ __('site.pricing.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/pricing') }}" />
@endsection

@section('content')
<style>
    .site-header .site-main-menu li > a { color: #000000; }
</style>

<div class="pr-page">

    <!-- =============================
         HEADER
         ============================= -->
    <section class="pr-header">
        <div class="container">
            <div class="pr-header-inner">
                <span class="pr-badge">{{ __('site.pricing.badge') }}</span>
                <h1 class="pr-title">{{ __('site.pricing.title') }}</h1>
                <p class="pr-sub">{{ __('site.pricing.sub') }}</p>
            </div>
        </div>
    </section>

    <!-- =============================
         PRICING CARDS
         ============================= -->
    <section class="pr-cards">
        <div class="container">
            <div class="row g-4 justify-content-center align-items-stretch">

                <!-- ── Basic ── -->
                <div class="col-lg-4 col-md-6 d-flex">
                    <div class="pr-card w-100">
                        <div class="pr-card-top">
                            <p class="pr-card-plan">{{ __('site.pricing.plan_basic') }}</p>
                            <p class="pr-card-desc">{{ __('site.pricing.plan_basic_desc') }}</p>
                            <div class="pr-price-row">
                                <span class="pr-price-amount">9€</span>
                                <span class="pr-price-period">{{ __('site.pricing.period') }}</span>
                            </div>
                        </div>

                        <ul class="pr-features">
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_basic_f1') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_basic_f2') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_basic_f3') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_basic_f4') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_basic_f5') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_basic_f6') }}</li>
                        </ul>

                        <div class="pr-card-actions">
                            <a href="{{ url('/free-trial') }}" class="pr-btn-primary">{{ __('site.pricing.btn_trial') }}</a>
                            <a href="#" class="pr-btn-outline">{{ __('site.pricing.btn_choose') }}</a>
                        </div>
                    </div>
                </div>

                <!-- ── Professional (popular) ── -->
                <div class="col-lg-4 col-md-6 d-flex">
                    <div class="pr-card pr-card--popular w-100">
                        <div class="pr-popular-badge">{{ __('site.pricing.popular_badge') }}</div>

                        <div class="pr-card-top">
                            <p class="pr-card-plan">{{ __('site.pricing.plan_pro') }}</p>
                            <p class="pr-card-desc">{{ __('site.pricing.plan_pro_desc') }}</p>
                            <div class="pr-price-row">
                                <span class="pr-price-amount">19€</span>
                                <span class="pr-price-period">{{ __('site.pricing.period') }}</span>
                            </div>
                        </div>

                        <ul class="pr-features">
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_pro_f1') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_pro_f2') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_pro_f3') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_pro_f4') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_pro_f5') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_pro_f6') }}</li>
                        </ul>

                        <div class="pr-card-actions">
                            <a href="{{ url('/free-trial') }}" class="pr-btn-primary">{{ __('site.pricing.btn_trial') }}</a>
                            <a href="#" class="pr-btn-outline">{{ __('site.pricing.btn_choose') }}</a>
                        </div>
                    </div>
                </div>

                <!-- ── Business ── -->
                <div class="col-lg-4 col-md-6 d-flex">
                    <div class="pr-card w-100">
                        <div class="pr-card-top">
                            <p class="pr-card-plan">{{ __('site.pricing.plan_business') }}</p>
                            <p class="pr-card-desc">{{ __('site.pricing.plan_business_desc') }}</p>
                            <div class="pr-price-row">
                                <span class="pr-price-amount">29€</span>
                                <span class="pr-price-period">{{ __('site.pricing.period') }}</span>
                            </div>
                        </div>

                        <ul class="pr-features">
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_business_f1') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_business_f2') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_business_f3') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_business_f4') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_business_f5') }}</li>
                            <li class="pr-feature"><i class="fas fa-check pr-check-icon"></i> {{ __('site.pricing.plan_business_f6') }}</li>
                        </ul>

                        <div class="pr-card-actions">
                            <a href="{{ url('/free-trial') }}" class="pr-btn-primary">{{ __('site.pricing.btn_trial') }}</a>
                            <a href="#" class="pr-btn-outline">{{ __('site.pricing.btn_choose') }}</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- =============================
         REASSURANCE STRIP
         ============================= -->
    <section class="pr-reassurance">
        <div class="container">
            <div class="pr-trust-row">
                <div class="pr-trust-item">
                    <i class="fas fa-credit-card"></i>
                    <span>{{ __('site.pricing.trust_1') }}</span>
                </div>
                <div class="pr-trust-item">
                    <i class="fas fa-times-circle"></i>
                    <span>{{ __('site.pricing.trust_2') }}</span>
                </div>
                <div class="pr-trust-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>{{ __('site.pricing.trust_3') }}</span>
                </div>
                <div class="pr-trust-item">
                    <i class="fas fa-headset"></i>
                    <span>{{ __('site.pricing.trust_4') }}</span>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================
         FAQ
         ============================= -->
    <section class="pr-faq">
        <div class="container">
            <h2 class="pr-faq-title">{{ __('site.pricing.faq_title') }}</h2>
            <div class="pr-faq-list">

                <details class="pr-faq-item">
                    <summary class="pr-faq-q">{{ __('site.pricing.faq_1_q') }}</summary>
                    <p class="pr-faq-a">{{ __('site.pricing.faq_1_a') }}</p>
                </details>

                <details class="pr-faq-item">
                    <summary class="pr-faq-q">{{ __('site.pricing.faq_2_q') }}</summary>
                    <p class="pr-faq-a">{{ __('site.pricing.faq_2_a') }}</p>
                </details>

                <details class="pr-faq-item">
                    <summary class="pr-faq-q">{{ __('site.pricing.faq_3_q') }}</summary>
                    <p class="pr-faq-a">{{ __('site.pricing.faq_3_a') }}</p>
                </details>

                <details class="pr-faq-item">
                    <summary class="pr-faq-q">{{ __('site.pricing.faq_4_q') }}</summary>
                    <p class="pr-faq-a">{{ __('site.pricing.faq_4_a') }}</p>
                </details>

            </div>
        </div>
    </section>

</div>
@endsection
