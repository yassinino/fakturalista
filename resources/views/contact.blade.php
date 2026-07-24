@extends('layouts.master')

@section('title', __('site.contact.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.contact.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.contact.page_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.contact.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/contact') }}" />
@endsection

@section('content')
<style>
    .site-header .site-main-menu li > a{
        color: #000000;
    }
</style>
<div class="ct-page">

    <!-- ============================
         HEADER BAND
         ============================ -->
    <section class="ct-header">
        <div class="container">
            <span class="ct-header-tag">
                <i class="fas fa-headset"></i>
                {{ __('site.contact.header_tag') }}
            </span>
            <h1 class="ct-header-title">{{ __('site.contact.header_title') }}</h1>
            <p class="ct-header-sub">{!! __('site.contact.header_sub') !!}</p>
        </div>
    </section>

    <!-- ============================
         MAIN - INFO PANEL + FORM
         ============================ -->
    <section class="ct-main">
        <div class="container">
            <div class="row g-4 align-items-start">

                <!-- ── Left: contact info ── -->
                <div class="col-lg-4">
                    <div class="ct-info-panel">

                        <!-- Contact details card -->
                        <div class="ct-info-card">
                            <p class="ct-info-card-title">{{ __('site.contact.info_title') }}</p>

                            <div class="ct-info-block">
                                <div class="ct-info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="ct-info-text">
                                    <strong>{{ __('site.contact.label_email') }}</strong>
                                    <a href="mailto:contact@fakturalista.com">contact@fakturalista.com</a>
                                </div>
                            </div>

                            <div class="ct-info-block">
                                <div class="ct-info-icon">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                                <div class="ct-info-text">
                                    <strong>{{ __('site.contact.label_phone') }}</strong>
                                    <a href="tel:+34912345678">+34 912 345 678</a>
                                </div>
                            </div>

                            <div class="ct-info-block">
                                <div class="ct-info-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ct-info-text">
                                    <strong>{{ __('site.contact.label_hours') }}</strong>
                                    <span>{{ __('site.contact.hours_value') }}</span>
                                </div>
                            </div>

                            <div class="ct-info-block">
                                <div class="ct-info-icon">
                                    <i class="fab fa-instagram"></i>
                                </div>
                                <div class="ct-info-text">
                                    <strong>{{ __('site.contact.label_social') }}</strong>
                                    <div class="ct-social-row">
                                        <a href="https://www.instagram.com/fakturalista"
                                           class="ct-social-link"
                                           target="_blank" rel="noopener"
                                           aria-label="Instagram">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                        <a href="#" class="ct-social-link" aria-label="Facebook">
                                            <i class="fab fa-facebook-f"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reassurance note -->
                        <div class="ct-reassurance">
                            <i class="fas fa-shield-alt"></i>
                            <p>
                                <strong>{{ __('site.contact.privacy_title') }}</strong><br>
                                {{ __('site.contact.privacy_text') }}
                            </p>
                        </div>

                    </div>
                </div>
                <!-- /left -->

                <!-- ── Right: form ── -->
                <div class="col-lg-8">
                    <div class="ct-form-card">
                        <h2 class="ct-form-title">{{ __('site.contact.form_title') }}</h2>
                        <p class="ct-form-sub">{{ __('site.contact.form_sub') }}</p>

                        {{-- Alerts --}}
                        @if (session('status'))
                            <div class="ct-alert-success">
                                <i class="fas fa-check-circle"></i>
                                {{ session('status') }}
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="ct-alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                {{ __('site.contact.alert_error') }}
                            </div>
                        @endif

                        {{-- ★ Form - field names, action, data-* and JS hooks preserved ★ --}}
                        <form method="POST"
                              action="{{ route('contact.send') }}"
                              class="contact-form"
                              data-pixsaas="contact-froms">
                            @csrf

                            <div class="row g-3">
                                <div class="col-sm-6 ct-field">
                                    <label class="ct-label" for="ct-name">{{ __('site.contact.label_name') }}</label>
                                    <input class="ct-input @error('name') is-invalid @enderror"
                                           type="text"
                                           id="ct-name"
                                           name="name"
                                           placeholder="{{ __('site.contact.placeholder_name') }}"
                                           value="{{ old('name') }}"
                                           required>
                                </div>
                                <div class="col-sm-6 ct-field">
                                    <label class="ct-label" for="ct-email">{{ __('site.contact.label_email_f') }}</label>
                                    <input class="ct-input @error('email') is-invalid @enderror"
                                           type="email"
                                           id="ct-email"
                                           name="email"
                                           placeholder="{{ __('site.contact.placeholder_email') }}"
                                           value="{{ old('email') }}"
                                           required>
                                </div>
                            </div>

                            <div class="ct-field">
                                <label class="ct-label" for="ct-subject">{{ __('site.contact.label_subject') }}</label>
                                <input class="ct-input @error('subject') is-invalid @enderror"
                                       type="text"
                                       id="ct-subject"
                                       name="subject"
                                       placeholder="{{ __('site.contact.placeholder_subject') }}"
                                       value="{{ old('subject') }}">
                            </div>

                            <div class="ct-field" style="margin-bottom: 24px;">
                                <label class="ct-label" for="ct-content">{{ __('site.contact.label_message') }}</label>
                                <textarea class="ct-textarea @error('content') is-invalid @enderror"
                                          id="ct-content"
                                          name="content"
                                          placeholder="{{ __('site.contact.placeholder_message') }}"
                                          required>{{ old('content') }}</textarea>
                            </div>

                            <button type="submit" class="ct-submit-btn submit-btn">
                                <span class="btn-text">{{ __('site.contact.btn_submit') }} &rarr;</span>
                                <i class="fas fa-spinner fa-spin"></i>
                            </button>

                            <input type="hidden" name="recaptcha_response" id="recaptchaResponse">

                            <div class="ct-form-result form-result alert">
                                <div class="content"></div>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- /right -->

            </div>
        </div>
    </section>

</div>

@endsection
