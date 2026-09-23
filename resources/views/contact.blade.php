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
    .site-header .site-main-menu li > a { color: #000000; }

    .page-banner .page-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(233, 30, 99, 0.09);
        color: #E91E63;
        border-radius: 100px;
        padding: 5px 14px 5px 10px;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 20px;
    }

    .fk-highlight {
        position: relative;
        color: #E91E63;
        white-space: nowrap;
    }
    .fk-highlight:after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: 2px;
        height: 8px;
        background: rgba(233, 30, 99, 0.14);
        z-index: -1;
        border-radius: 4px;
    }

    .ct-page { background: #fff; }
    .ct-main { padding: 80px 0 100px; }

    /* ── Left: form ── */
    .ct-form-card {
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 18px;
        padding: 42px 40px;
        box-shadow: 0 20px 50px -20px rgba(15, 23, 42, 0.08);
    }
    .ct-form-title { font-size: 24px; font-weight: 700; color: #0F172A; margin-bottom: 8px; }
    .ct-form-sub { color: #667085; font-size: 15px; margin-bottom: 28px; }
    .ct-field { margin-bottom: 18px; }
    .ct-label { display: block; font-size: 13.5px; font-weight: 600; color: #344054; margin-bottom: 6px; }
    .ct-input, .ct-textarea, .ct-select {
        width: 100%;
        border: 1.5px solid #e4e7ec;
        border-radius: 10px;
        padding: 11px 14px;
        font-size: 14.5px;
        color: #0F172A;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .ct-input:focus, .ct-textarea:focus, .ct-select:focus {
        outline: none;
        border-color: #E91E63;
        box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.12);
    }
    .ct-input.is-invalid, .ct-textarea.is-invalid, .ct-select.is-invalid { border-color: #d92d20; }
    .ct-textarea { min-height: 130px; resize: vertical; }
    .ct-select { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3e%3cpath d='m6 9 6 6 6-6'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 12px center; background-size: 16px; padding-right: 38px; }

    /* Honeypot - never visible or reachable, but present in the DOM for bots that fill every field blindly */
    .ct-hp {
        position: absolute;
        left: -9999px;
        top: -9999px;
        width: 1px;
        height: 1px;
        overflow: hidden;
    }

    .ct-submit-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #E91E63;
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 13px 28px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s, transform .15s;
    }
    .ct-submit-btn:hover { background: #d0155a; }
    .ct-submit-btn i { display: none; }
    .ct-submit-btn.is-loading i { display: inline-block; }

    .ct-alert-success, .ct-alert-error {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        border-radius: 10px;
        padding: 13px 16px;
        font-size: 14px;
        margin-bottom: 22px;
    }
    .ct-alert-success { background: #ecfdf3; color: #067647; }
    .ct-alert-error { background: #fef3f2; color: #b42318; }

    /* ── Right: contact info + trust ── */
    .ct-info-col { padding-left: 12px; }
    .ct-info-title { font-size: 21px; font-weight: 700; color: #0F172A; margin-bottom: 8px; }
    .ct-info-sub { color: #667085; font-size: 14.5px; margin-bottom: 26px; }

    .ct-card {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 18px 0;
        border-bottom: 1px solid #f1f2f4;
    }
    .ct-card:last-of-type { border-bottom: none; }
    .ct-card-icon {
        flex-shrink: 0;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(233, 30, 99, 0.08);
        color: #E91E63;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }
    .ct-card-text strong { display: block; font-size: 13px; color: #667085; font-weight: 600; margin-bottom: 3px; }
    .ct-card-text a, .ct-card-text span { font-size: 15.5px; color: #0F172A; font-weight: 600; }
    .ct-card-text a:hover { color: #E91E63; }

    .ct-trust {
        margin-top: 28px;
        background: #fafafa;
        border: 1px solid #eef0f3;
        border-radius: 14px;
        padding: 22px 24px;
    }
    .ct-trust-title { font-size: 15.5px; font-weight: 700; color: #0F172A; margin-bottom: 8px; }
    .ct-trust-text { font-size: 13.8px; color: #667085; line-height: 1.6; margin: 0; }

    @media (max-width: 991px) {
        .ct-form-card { padding: 30px 22px; }
        .ct-info-col { padding-left: 0; margin-top: 40px; }
    }
</style>

@include('partials.page-hero', [
    'eyebrow'        => __('site.contact.header_tag'),
    'eyebrow_icon'   => 'fas fa-comment-dots',
    'title'          => __('site.contact.header_title'),
    'title_html'     => __('site.contact.header_title_html'),
    'paragraphs_html'=> [__('site.contact.header_sub')],
])

<div class="ct-page">
    <section class="ct-main">
        <div class="container">
            <div class="row g-4 g-lg-5 align-items-start">

                <!-- ── Left: form ── -->
                <div class="col-lg-7">
                    <div class="ct-form-card">
                        <h2 class="ct-form-title">{{ __('site.contact.form_title') }}</h2>
                        <p class="ct-form-sub">{{ __('site.contact.form_sub') }}</p>

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

                        <form method="POST"
                              action="{{ url('/contact') }}"
                              class="contact-form"
                              data-pixsaas="contact-froms">
                            @csrf

                            <!-- Honeypot field - left empty by real visitors, invisible to them -->
                            <div class="ct-hp" aria-hidden="true">
                                <label for="ct-company-website">Website</label>
                                <input type="text" id="ct-company-website" name="company_website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6 ct-field">
                                    <label class="ct-label" for="ct-first-name">{{ __('site.contact.label_first_name') }}</label>
                                    <input class="ct-input @error('first_name') is-invalid @enderror"
                                           type="text"
                                           id="ct-first-name"
                                           name="first_name"
                                           placeholder="{{ __('site.contact.placeholder_first_name') }}"
                                           value="{{ old('first_name') }}"
                                           maxlength="80"
                                           required>
                                </div>
                                <div class="col-sm-6 ct-field">
                                    <label class="ct-label" for="ct-last-name">{{ __('site.contact.label_last_name') }}</label>
                                    <input class="ct-input @error('last_name') is-invalid @enderror"
                                           type="text"
                                           id="ct-last-name"
                                           name="last_name"
                                           placeholder="{{ __('site.contact.placeholder_last_name') }}"
                                           value="{{ old('last_name') }}"
                                           maxlength="80"
                                           required>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6 ct-field">
                                    <label class="ct-label" for="ct-email">{{ __('site.contact.label_email_f') }}</label>
                                    <input class="ct-input @error('email') is-invalid @enderror"
                                           type="email"
                                           id="ct-email"
                                           name="email"
                                           placeholder="{{ __('site.contact.placeholder_email') }}"
                                           value="{{ old('email') }}"
                                           maxlength="255"
                                           required>
                                </div>
                                <div class="col-sm-6 ct-field">
                                    <label class="ct-label" for="ct-phone">{{ __('site.contact.label_phone') }}</label>
                                    <input class="ct-input @error('phone') is-invalid @enderror"
                                           type="tel"
                                           id="ct-phone"
                                           name="phone"
                                           placeholder="{{ __('site.contact.placeholder_phone') }}"
                                           value="{{ old('phone') }}"
                                           maxlength="30">
                                </div>
                            </div>

                            <div class="ct-field">
                                <label class="ct-label" for="ct-company">{{ __('site.contact.label_company') }}</label>
                                <input class="ct-input @error('company') is-invalid @enderror"
                                       type="text"
                                       id="ct-company"
                                       name="company"
                                       placeholder="{{ __('site.contact.placeholder_company') }}"
                                       value="{{ old('company') }}"
                                       maxlength="150">
                            </div>

                            <div class="ct-field">
                                <label class="ct-label" for="ct-subject">{{ __('site.contact.label_subject') }}</label>
                                <select class="ct-select @error('subject') is-invalid @enderror"
                                        id="ct-subject"
                                        name="subject"
                                        required>
                                    <option value="" disabled {{ old('subject') ? '' : 'selected' }}>{{ __('site.contact.placeholder_subject') }}</option>
                                    @foreach (['general', 'pricing', 'support', 'billing', 'partnership', 'other'] as $ctSubjectKey)
                                        <option value="{{ $ctSubjectKey }}" {{ old('subject') === $ctSubjectKey ? 'selected' : '' }}>
                                            {{ __('site.contact.subject_' . $ctSubjectKey) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="ct-field" style="margin-bottom: 24px;">
                                <label class="ct-label" for="ct-message">{{ __('site.contact.label_message') }}</label>
                                <textarea class="ct-textarea @error('message') is-invalid @enderror"
                                          id="ct-message"
                                          name="message"
                                          placeholder="{{ __('site.contact.placeholder_message') }}"
                                          maxlength="2000"
                                          required>{{ old('message') }}</textarea>
                            </div>

                            @include('partials.captcha', [
                                'captcha' => $captcha,
                                'captchaFieldClass' => 'ct-field',
                                'captchaLabelClass' => 'ct-label',
                                'captchaInputClass' => 'ct-input',
                            ])

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
                <!-- /left -->

                <!-- ── Right: contact info + trust ── -->
                <div class="col-lg-5">
                    <div class="ct-info-col">
                        <h2 class="ct-info-title">{{ __('site.contact.info_title') }}</h2>
                        <p class="ct-info-sub">{{ __('site.contact.info_sub') }}</p>

                        <div class="ct-card">
                            <div class="ct-card-icon"><i class="fas fa-phone-alt"></i></div>
                            <div class="ct-card-text">
                                <strong>{{ __('site.contact.label_phone_card') }}</strong>
                                <a href="{{ config('fakturalista.contact_phone_link') }}">{{ config('fakturalista.contact_phone_display') }}</a>
                            </div>
                        </div>

                        <div class="ct-card">
                            <div class="ct-card-icon"><i class="fas fa-envelope"></i></div>
                            <div class="ct-card-text">
                                <strong>{{ __('site.contact.label_email') }}</strong>
                                <a href="mailto:{{ config('fakturalista.contact_email') }}">{{ config('fakturalista.contact_email') }}</a>
                            </div>
                        </div>

                        <div class="ct-card">
                            <div class="ct-card-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="ct-card-text">
                                <strong>{{ __('site.contact.label_location') }}</strong>
                                <span>{{ __('site.contact.location_value') }}</span>
                            </div>
                        </div>

                        <div class="ct-trust">
                            <p class="ct-trust-title">{{ __('site.contact.trust_title') }}</p>
                            <p class="ct-trust-text">{{ __('site.contact.trust_text') }}</p>
                        </div>
                    </div>
                </div>
                <!-- /right -->

            </div>
        </div>
    </section>
</div>

@endsection
