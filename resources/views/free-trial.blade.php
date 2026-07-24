@extends('layouts.master')

@section('title', __('site.freeTrial.page_title'))

@section('content')
<style>
    .site-header .site-main-menu li > a{
        color: #000000;
    }
</style>
<div class="ft-page">

    <!-- ============================
         HERO + FORM (two-column)
         ============================ -->
    <section class="ft-hero">
        <div class="container">
            <div class="row align-items-center g-5">

                <!-- ── Left: value proposition ── -->
                <div class="col-lg-6">
                    <div class="ft-badge">
                        <span class="pulse"></span>
                        {{ __('site.freeTrial.badge') }}
                    </div>

                    <h1 class="ft-headline">
                        {{ __('site.freeTrial.headline') }}<br>
                        <span class="accent">{{ __('site.freeTrial.headline_accent') }}</span>
                    </h1>

                    <p class="ft-subline">
                        {{ __('site.freeTrial.subline') }}
                    </p>

                    <ul class="ft-benefits">
                        <li class="ft-benefit-item">
                            <div class="ft-benefit-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="ft-benefit-text">
                                <strong>{{ __('site.freeTrial.benefit_1_title') }}</strong>
                                <span>{{ __('site.freeTrial.benefit_1_text') }}</span>
                            </div>
                        </li>
                        <li class="ft-benefit-item">
                            <div class="ft-benefit-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="ft-benefit-text">
                                <strong>{{ __('site.freeTrial.benefit_2_title') }}</strong>
                                <span>{{ __('site.freeTrial.benefit_2_text') }}</span>
                            </div>
                        </li>
                        <li class="ft-benefit-item">
                            <div class="ft-benefit-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="ft-benefit-text">
                                <strong>{{ __('site.freeTrial.benefit_3_title') }}</strong>
                                <span>{{ __('site.freeTrial.benefit_3_text') }}</span>
                            </div>
                        </li>
                        <li class="ft-benefit-item">
                            <div class="ft-benefit-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="ft-benefit-text">
                                <strong>{{ __('site.freeTrial.benefit_4_title') }}</strong>
                                <span>{{ __('site.freeTrial.benefit_4_text') }}</span>
                            </div>
                        </li>
                    </ul>

                    <div class="ft-trust-strip">
                        <div class="ft-trust-item">
                            <i class="fas fa-check-circle"></i>
                            <span>{{ __('site.freeTrial.trust_1') }}</span>
                        </div>
                        <div class="ft-trust-item">
                            <i class="fas fa-check-circle"></i>
                            <span>{{ __('site.freeTrial.trust_2') }}</span>
                        </div>
                        <div class="ft-trust-item">
                            <i class="fas fa-check-circle"></i>
                            <span>{{ __('site.freeTrial.trust_3') }}</span>
                        </div>
                    </div>
                </div>
                <!-- /left -->

                <!-- ── Right: form card ── -->
                <div class="col-lg-6">
                    <div class="ft-card">
                        <span class="ft-card-tag">{{ __('site.freeTrial.card_tag') }}</span>
                        <h2 class="ft-card-title">{{ __('site.freeTrial.card_title') }}</h2>
                        <p class="ft-card-sub">{{ __('site.freeTrial.card_sub') }}</p>

                        @if (session('status'))
                            <div class="ft-alert-success">
                                <i class="fas fa-check-circle"></i>
                                {{ session('status') }}
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="ft-alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                {{ __('site.freeTrial.error_msg') }}
                            </div>
                        @endif

                        <form method="POST"
                              action="{{ route('free-trial.send') }}"
                              class="contact-form"
                              data-pixsaas="contact-froms">
                            @csrf

                            <div class="row g-3">
                                <div class="col-sm-6 ft-field">
                                    <label class="ft-label" for="ft-name">{{ __('site.freeTrial.label_name') }} *</label>
                                    <input class="ft-input @error('name') is-invalid @enderror"
                                           type="text"
                                           id="ft-name"
                                           name="name"
                                           placeholder="{{ __('site.freeTrial.placeholder_name') }}"
                                           value="{{ old('name') }}"
                                           required>
                                </div>
                                <div class="col-sm-6 ft-field">
                                    <label class="ft-label" for="ft-email">{{ __('site.freeTrial.label_email') }} *</label>
                                    <input class="ft-input @error('email') is-invalid @enderror"
                                           type="email"
                                           id="ft-email"
                                           name="email"
                                           placeholder="{{ __('site.freeTrial.placeholder_email') }}"
                                           value="{{ old('email') }}"
                                           required>
                                </div>
                            </div>

                            <div class="ft-field" style="margin-bottom: 26px;">
                                <label class="ft-label" for="ft-company">{{ __('site.freeTrial.label_company') }} *</label>
                                <input class="ft-input @error('company') is-invalid @enderror"
                                       type="text"
                                       id="ft-company"
                                       name="company"
                                       placeholder="{{ __('site.freeTrial.placeholder_company') }}"
                                       value="{{ old('company') }}"
                                       required>
                            </div>

                            <button type="submit" class="ft-submit-btn submit-btn">
                                <span class="btn-text">{{ __('site.freeTrial.submit') }} &rarr;</span>
                                <i class="fas fa-spinner fa-spin"></i>
                            </button>

                            <input type="hidden" name="recaptcha_response" id="recaptchaResponse">

                            <div class="form-result alert">
                                <div class="content"></div>
                            </div>
                        </form>

                        <p class="ft-no-cc">
                            <i class="fas fa-lock"></i>
                            {{ __('site.freeTrial.no_cc') }}
                        </p>
                    </div>
                </div>
                <!-- /right -->

            </div>
        </div>
    </section>

    <!-- ============================
         SOCIAL PROOF - testimonials
         ============================ -->
    <section class="ft-proof">
        <div class="container">
            <p class="ft-section-label">{{ __('site.freeTrial.proof_label') }}</p>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="ft-testi">
                        <div class="ft-stars">★★★★★</div>
                        <p>{{ __('site.freeTrial.testi_1') }}</p>
                        <div class="ft-testi-author">
                            <div class="ft-avatar">{{ mb_strtoupper(mb_substr(__('site.freeTrial.testi_1_author'), 0, 1)) }}{{ mb_strtoupper(mb_substr(strstr(__('site.freeTrial.testi_1_author'), ' '), 1, 1)) }}</div>
                            <div class="ft-testi-info">
                                <strong>{{ __('site.freeTrial.testi_1_author') }}</strong>
                                <span>{{ __('site.freeTrial.testi_1_role') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ft-testi">
                        <div class="ft-stars">★★★★★</div>
                        <p>{{ __('site.freeTrial.testi_2') }}</p>
                        <div class="ft-testi-author">
                            <div class="ft-avatar">{{ mb_strtoupper(mb_substr(__('site.freeTrial.testi_2_author'), 0, 1)) }}{{ mb_strtoupper(mb_substr(strstr(__('site.freeTrial.testi_2_author'), ' '), 1, 1)) }}</div>
                            <div class="ft-testi-info">
                                <strong>{{ __('site.freeTrial.testi_2_author') }}</strong>
                                <span>{{ __('site.freeTrial.testi_2_role') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ft-testi">
                        <div class="ft-stars">★★★★★</div>
                        <p>{{ __('site.freeTrial.testi_3') }}</p>
                        <div class="ft-testi-author">
                            <div class="ft-avatar">{{ mb_strtoupper(mb_substr(__('site.freeTrial.testi_3_author'), 0, 1)) }}{{ mb_strtoupper(mb_substr(strstr(__('site.freeTrial.testi_3_author'), ' '), 1, 1)) }}</div>
                            <div class="ft-testi-info">
                                <strong>{{ __('site.freeTrial.testi_3_author') }}</strong>
                                <span>{{ __('site.freeTrial.testi_3_role') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection
