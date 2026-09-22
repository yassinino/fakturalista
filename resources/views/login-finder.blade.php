@extends('layouts.master')

@section('title', __('site.loginFinder.page_title'))

@section('content')
<style>
    .site-header .site-main-menu li > a{ color: #000000; }
    .reg-page {
        padding: 90px 0 110px;
        min-height: 60vh;
        display: flex;
        align-items: center;
    }
    .reg-card {
        max-width: 440px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(20, 20, 40, 0.08);
        padding: 44px 40px;
    }
    .reg-card-title { font-size: 26px; font-weight: 800; color: #1a1a2e; margin-bottom: 8px; }
    .reg-card-sub { font-size: 15px; color: #6b7280; margin-bottom: 28px; }
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
</style>

<div class="reg-page">
    <div class="container">
        <div class="reg-card">
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
</div>
@endsection
