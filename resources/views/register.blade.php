@extends('layouts.master')

@section('title', __('site.register.page_title'))

@section('content')
<style>
    .site-header .site-main-menu li > a{
        color: #000000;
    }
    .reg-page {
        padding: 70px 0 90px;
        min-height: 70vh;
        display: flex;
        align-items: center;
    }
    .reg-card {
        max-width: 520px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(20, 20, 40, 0.08);
        padding: 44px 40px;
    }
    .reg-card-tag {
        display: inline-block;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #E91E63;
        background: rgba(233, 30, 99, 0.08);
        padding: 5px 12px;
        border-radius: 20px;
        margin-bottom: 16px;
    }
    .reg-card-title {
        font-size: 28px;
        font-weight: 800;
        color: #1a1a2e;
        margin-bottom: 8px;
    }
    .reg-card-sub {
        font-size: 15px;
        color: #6b7280;
        margin-bottom: 28px;
    }
    .reg-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .reg-note {
        text-align: center;
        font-size: 13px;
        color: #6b7280;
        margin-top: 18px;
    }
    .reg-note i { color: #16a34a; margin-right: 4px; }
    .reg-signin {
        text-align: center;
        font-size: 14px;
        color: #6b7280;
        margin-top: 22px;
        padding-top: 22px;
        border-top: 1px solid #eef0f3;
    }
    .reg-signin a { color: #E91E63; font-weight: 600; text-decoration: none; }
    .reg-signin a:hover { text-decoration: underline; }
    @media (max-width: 576px) {
        .reg-row-2 { grid-template-columns: 1fr; }
        .reg-card { padding: 32px 22px; }
    }
</style>

<div class="reg-page">
    <div class="container">
        <div class="reg-card">
            <span class="reg-card-tag">{{ __('site.register.card_tag') }}</span>
            <h1 class="reg-card-title">{{ __('site.register.card_title') }}</h1>
            <p class="reg-card-sub">{{ __('site.register.card_sub') }}</p>

            @if ($errors->any())
                <div class="ft-alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ url('/register') }}" id="reg-form" novalidate>
                @csrf

                <div class="reg-row-2">
                    <div class="ft-field">
                        <label class="ft-label" for="reg-first-name">{{ __('site.register.label_first_name') }} *</label>
                        <input class="ft-input @error('first_name') is-invalid @enderror"
                               type="text" id="reg-first-name" name="first_name"
                               placeholder="{{ __('site.register.placeholder_first_name') }}"
                               value="{{ old('first_name') }}" required>
                    </div>
                    <div class="ft-field">
                        <label class="ft-label" for="reg-last-name">{{ __('site.register.label_last_name') }}</label>
                        <input class="ft-input @error('last_name') is-invalid @enderror"
                               type="text" id="reg-last-name" name="last_name"
                               placeholder="{{ __('site.register.placeholder_last_name') }}"
                               value="{{ old('last_name') }}">
                    </div>
                </div>

                <div class="ft-field">
                    <label class="ft-label" for="reg-email">{{ __('site.register.label_email') }} *</label>
                    <input class="ft-input @error('email') is-invalid @enderror"
                           type="email" id="reg-email" name="email"
                           placeholder="{{ __('site.register.placeholder_email') }}"
                           value="{{ old('email') }}" required>
                </div>

                <div class="reg-row-2">
                    <div class="ft-field">
                        <label class="ft-label" for="reg-password">{{ __('site.register.label_password') }} *</label>
                        <input class="ft-input @error('password') is-invalid @enderror"
                               type="password" id="reg-password" name="password"
                               autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="ft-field">
                        <label class="ft-label" for="reg-password-confirmation">{{ __('site.register.label_password_confirmation') }} *</label>
                        <input class="ft-input"
                               type="password" id="reg-password-confirmation" name="password_confirmation"
                               autocomplete="new-password" minlength="8" required>
                    </div>
                </div>

                <div class="ft-field">
                    <label class="ft-label" for="reg-company">{{ __('site.register.label_company') }} *</label>
                    <input class="ft-input @error('company_name') is-invalid @enderror"
                           type="text" id="reg-company" name="company_name"
                           placeholder="{{ __('site.register.placeholder_company') }}"
                           value="{{ old('company_name') }}" required>
                </div>

                <div class="reg-row-2">
                    <div class="ft-field">
                        <label class="ft-label" for="reg-tax-id">{{ __('site.register.label_tax_id') }}</label>
                        <input class="ft-input @error('tax_id') is-invalid @enderror"
                               type="text" id="reg-tax-id" name="tax_id" value="{{ old('tax_id') }}">
                    </div>
                    <div class="ft-field">
                        <label class="ft-label" for="reg-country">{{ __('site.register.label_country') }}</label>
                        <select class="ft-input @error('country') is-invalid @enderror" id="reg-country" name="country">
                            <option value="">—</option>
                            @foreach ($countries as $code => $label)
                                <option value="{{ $code }}" @selected(old('country') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @include('partials.captcha', [
                    'captcha' => $captcha,
                    'captchaFieldClass' => 'ft-field',
                    'captchaLabelClass' => 'ft-label',
                    'captchaInputClass' => 'ft-input',
                ])

                <button type="submit" class="ft-submit-btn submit-btn" id="reg-submit-btn" style="width:100%;">
                    <span class="btn-text">{{ __('site.register.submit') }}</span>
                </button>
            </form>

            <p class="reg-note"><i class="fas fa-lock"></i>{{ __('site.register.trial_note') }}</p>

            <p class="reg-signin">
                {{ __('site.register.already_have_account') }}
                <a href="{{ url('/login') }}">{{ __('site.register.sign_in') }}</a>
            </p>
        </div>
    </div>
</div>

<script>
document.getElementById('reg-form').addEventListener('submit', function () {
    var btn = document.getElementById('reg-submit-btn');
    if (btn.disabled) {
        // Prevent a second submission if the user double-clicks - the
        // browser already queued the first submit, so this just stops a
        // second network request from firing (server-side protection -
        // the register-trial cache lock/domain uniqueness - is what
        // actually guarantees no duplicate tenant, this is only UX).
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '<span class="btn-text">{{ __('site.register.submitting') }}</span>';
});
</script>
@endsection
