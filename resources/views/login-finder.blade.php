@extends('layouts.auth')

@section('title', __('site.loginFinder.page_title'))

@section('content')
<div class="reg-page">
    <div class="reg-card">
        <a href="{{ url('/') }}" class="reg-logo-link">
            <img src="{{ url('assets/logo.svg') }}" alt="Fakturalista" class="reg-logo">
        </a>

        <h1 class="reg-card-title">{{ __('site.loginFinder.title') }}</h1>
        <p class="reg-card-sub">{{ __('site.loginFinder.sub') }}</p>

        @if ($errors->any())
            <div class="ft-alert-error">
                <i class="fas fa-exclamation-circle"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ url('/login') }}" novalidate>
            @csrf
            <div class="ft-field">
                <label class="ft-label" for="lf-email">{{ __('site.loginFinder.label_email') }}</label>
                <input class="ft-input @error('email') is-invalid @enderror"
                       type="email" id="lf-email" name="email"
                       value="{{ old('email') }}" required autofocus>
            </div>

            <button type="submit" class="ft-submit-btn submit-btn" style="width:100%;">
                <span class="btn-text">{{ __('site.loginFinder.submit') }}</span>
            </button>
        </form>

        <p class="reg-signin">
            {{ __('site.loginFinder.register_cta') }}
            <a href="{{ url('/register') }}">{{ __('site.loginFinder.register_link') }}</a>
        </p>
    </div>
</div>
@endsection
