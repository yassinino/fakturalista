@extends('layouts.auth')

@section('title', __('site.register.page_title'))

@section('styles')
<style>
    .ft-field-hint {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-top: 6px;
    }
</style>
@endsection

@section('content')
<div class="reg-page">
    <div class="reg-card">
        <a href="{{ url('/') }}" class="reg-logo-link">
            <img src="{{ url('assets/logo.svg') }}" alt="Fakturalista" class="reg-logo">
        </a>

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

            <div class="ft-field">
                <label class="ft-label" for="reg-name">{{ __('site.register.label_name') }} *</label>
                <input class="ft-input @error('name') is-invalid @enderror"
                       type="text" id="reg-name" name="name"
                       placeholder="{{ __('site.register.placeholder_name') }}"
                       value="{{ old('name') }}" required>
            </div>

            <div class="ft-field">
                <label class="ft-label" for="reg-email">{{ __('site.register.label_email') }} *</label>
                <input class="ft-input @error('email') is-invalid @enderror"
                       type="email" id="reg-email" name="email"
                       placeholder="{{ __('site.register.placeholder_email') }}"
                       value="{{ old('email') }}" required>
            </div>

            <div class="ft-field">
                <label class="ft-label" for="reg-phone">{{ __('site.register.label_phone') }}</label>
                <input class="ft-input @error('phone') is-invalid @enderror"
                       type="tel" id="reg-phone" name="phone"
                       placeholder="{{ __('site.register.placeholder_phone') }}"
                       value="{{ old('phone') }}">
            </div>

            <div class="ft-field">
                <label class="ft-label" for="reg-password">{{ __('site.register.label_password') }} *</label>
                <input class="ft-input @error('password') is-invalid @enderror"
                       type="password" id="reg-password" name="password"
                       autocomplete="new-password" minlength="8" required>
                <small class="ft-field-hint">{{ __('site.register.password_hint') }}</small>
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
