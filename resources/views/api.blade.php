@extends('layouts.master')

{{--
    NOTE: No public API currently exists. This is a "coming soon" placeholder page
    for a planned future feature. The notify form links to /contact — wire it to a
    real early-access backend when the API is ready.
--}}

@section('title', __('site.api.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.api.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.api.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.api.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/api-docs') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    .ap-page { background: #f7f8fc; }

    .ap-content {
        max-width: 820px;
        margin: 0 auto;
        padding: 64px 24px 80px;
    }

    /* ── Coming soon card ────────────────────────────────────── */
    .ap-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 2px 24px rgba(0,0,0,.06);
        padding: 48px 44px;
        margin-bottom: 32px;
    }

    .ap-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #fff0f0;
        border: 1px solid #ffd0d0;
        border-radius: 100px;
        padding: 5px 14px;
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #d94f4f;
        margin-bottom: 24px;
    }
    .ap-badge-dot {
        width: 7px;
        height: 7px;
        background: #fa7070;
        border-radius: 50%;
        animation: ap-pulse 1.8s ease-in-out infinite;
    }
    @keyframes ap-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: .4; transform: scale(.75); }
    }

    .ap-card h2 {
        font-size: clamp(1.5rem, 3.5vw, 2.1rem);
        font-weight: 700;
        color: #2b2350;
        margin: 0 0 14px;
        letter-spacing: -.02em;
    }
    .ap-card > p {
        font-size: 1rem;
        color: #4b5563;
        line-height: 1.75;
        margin: 0 0 28px;
    }

    /* ── Feature list ────────────────────────────────────────── */
    .ap-features {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .ap-features li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: .93rem;
        color: #374151;
        line-height: 1.5;
    }
    .ap-features li::before {
        content: '';
        display: inline-block;
        width: 18px;
        height: 18px;
        background: #fff0f0;
        border-radius: 50%;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23fa7070' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M5 13l4 4L19 7'/%3E%3C/svg%3E");
        background-size: 12px;
        background-position: center;
        background-repeat: no-repeat;
        flex-shrink: 0;
        margin-top: 1px;
    }

    /* ── Notify card ─────────────────────────────────────────── */
    .ap-notify {
        background: linear-gradient(135deg, #2b2350 0%, #3d346b 100%);
        border-radius: 20px;
        padding: 44px;
        color: #fff;
        text-align: center;
    }
    .ap-notify h3 {
        font-size: 1.4rem;
        font-weight: 700;
        margin: 0 0 10px;
        color: #fff;
    }
    .ap-notify > p {
        font-size: .95rem;
        color: rgba(255,255,255,.75);
        line-height: 1.7;
        margin: 0 0 28px;
        max-width: 480px;
        margin-left: auto;
        margin-right: auto;
    }
    .ap-notify-form {
        display: flex;
        gap: 0;
        max-width: 440px;
        margin: 0 auto 16px;
        border-radius: 10px;
        overflow: hidden;
        border: 1.5px solid rgba(255,255,255,.2);
        transition: border-color .2s;
    }
    .ap-notify-form:focus-within { border-color: #fa7070; }
    .ap-notify-input {
        flex: 1;
        border: none;
        outline: none;
        padding: 13px 18px;
        font-size: .95rem;
        background: rgba(255,255,255,.12);
        color: #fff;
        font-family: inherit;
    }
    .ap-notify-input::placeholder { color: rgba(255,255,255,.45); }
    .ap-notify-btn {
        background: #fa7070;
        border: none;
        padding: 0 22px;
        color: #fff;
        font-size: .9rem;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        font-family: inherit;
        transition: background .15s;
    }
    .ap-notify-btn:hover { background: #e85555; }
    .ap-notify-or {
        font-size: .82rem;
        color: rgba(255,255,255,.5);
        margin: 0;
    }
    .ap-notify-or a { color: rgba(255,255,255,.7); text-decoration: underline; }
    .ap-notify-or a:hover { color: #fff; }

    @media (max-width: 640px) {
        .ap-card { padding: 32px 24px; }
        .ap-notify { padding: 36px 24px; }
        .ap-features { grid-template-columns: 1fr; }
        .ap-notify-form { flex-direction: column; border-radius: 10px; overflow: visible; border: none; gap: 10px; }
        .ap-notify-input { border-radius: 10px; border: 1.5px solid rgba(255,255,255,.2); }
        .ap-notify-btn { border-radius: 10px; padding: 13px 22px; }
    }
</style>

@include('partials.page-hero', [
    'title'      => __('site.api.hero_title'),
    'paragraphs' => [__('site.api.hero_sub')],
])

<div class="ap-page">
    <div class="ap-content">

        {{-- Coming soon card --}}
        <div class="ap-card">
            <div class="ap-badge">
                <span class="ap-badge-dot" aria-hidden="true"></span>
                {{ __('site.api.coming_title') }}
            </div>
            <h2>{{ __('site.api.coming_title') }}</h2>
            <p>{{ __('site.api.coming_body') }}</p>
            <ul class="ap-features">
                <li>{{ __('site.api.feat_1') }}</li>
                <li>{{ __('site.api.feat_2') }}</li>
                <li>{{ __('site.api.feat_3') }}</li>
                <li>{{ __('site.api.feat_4') }}</li>
            </ul>
        </div>

        {{--
            Notify form — currently submits to /contact with a pre-filled subject.
            TODO: Replace with a real early-access signup endpoint when the API is ready.
        --}}
        <div class="ap-notify">
            <h3>{{ __('site.api.notify_title') }}</h3>
            <p>{{ __('site.api.notify_body') }}</p>
            <form class="ap-notify-form" action="{{ url('/contact') }}" method="GET">
                <input
                    type="email"
                    name="email"
                    class="ap-notify-input"
                    placeholder="{{ __('site.api.notify_placeholder') }}"
                    autocomplete="email"
                >
                <button type="submit" class="ap-notify-btn">
                    {{ __('site.api.notify_btn') }}
                </button>
            </form>
            <p class="ap-notify-or">
                {{ __('site.api.or_contact') }}
                <a href="mailto:contact@fakturalista.com">contact@fakturalista.com</a>
            </p>
        </div>

    </div>
</div>

@endsection
