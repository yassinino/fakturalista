@extends('layouts.master')

@section('title', 'Espacio no encontrado')

@section('meta')
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="Este espacio de cliente no existe en Fakturalista.">
@endsection

@section('content')
<style>
    /* ── Tenant not-found page ─────────────────────────────── */
    .tnf-wrap {
        min-height: 72vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 80px 20px;
        background: #f8f9fc;
    }

    .tnf-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 4px 32px rgba(0, 0, 0, .07);
        max-width: 560px;
        width: 100%;
        padding: 56px 48px 52px;
        text-align: center;
    }

    .tnf-icon-wrap {
        width: 88px;
        height: 88px;
        border-radius: 50%;
        background: #fff0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 28px;
    }

    .tnf-icon-wrap svg {
        width: 44px;
        height: 44px;
        color: #fa7070;
    }

    .tnf-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff0f0;
        border: 1px solid #ffd0d0;
        border-radius: 100px;
        padding: 4px 14px;
        font-size: 12px;
        font-weight: 600;
        color: #d94f4f;
        letter-spacing: .04em;
        text-transform: uppercase;
        margin-bottom: 20px;
    }

    .tnf-title {
        font-size: 26px;
        font-weight: 700;
        color: #1a1a2e;
        line-height: 1.25;
        margin-bottom: 14px;
    }

    .tnf-subtitle {
        font-size: 15px;
        color: #6b7280;
        line-height: 1.7;
        margin-bottom: 0;
    }

    .tnf-domain-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        font-family: 'Courier New', Courier, monospace;
        margin-top: 20px;
        word-break: break-all;
    }

    .tnf-domain-chip-label {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-weight: 400;
        color: #9ca3af;
        font-size: 12px;
        white-space: nowrap;
    }

    .tnf-divider {
        border: none;
        border-top: 1px solid #f1f3f5;
        margin: 32px 0;
    }

    .tnf-cta-title {
        font-size: 15px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }

    .tnf-cta-sub {
        font-size: 13px;
        color: #9ca3af;
        margin-bottom: 24px;
    }

    .tnf-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fa7070;
        color: #ffffff !important;
        text-decoration: none !important;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: .01em;
        padding: 14px 32px;
        border-radius: 12px;
        transition: background .15s, transform .1s, box-shadow .15s;
        box-shadow: 0 4px 16px rgba(250, 112, 112, .32);
    }

    .tnf-btn:hover {
        background: #e85555;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(250, 112, 112, .42);
        color: #fff !important;
    }

    .tnf-btn:active { transform: translateY(0); }

    .tnf-back-link {
        display: block;
        margin-top: 20px;
        font-size: 13px;
        color: #9ca3af;
        text-decoration: none;
        transition: color .15s;
    }

    .tnf-back-link:hover { color: #fa7070; text-decoration: none; }

    @media (max-width: 600px) {
        .tnf-card { padding: 40px 28px 36px; }
        .tnf-title { font-size: 22px; }
    }
</style>

<section class="tnf-wrap">
    <div class="tnf-card">

        {{-- Icon --}}
        <div class="tnf-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/>
                <path d="M9 21V12h6v9"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <circle cx="12" cy="14.5" r=".5" fill="currentColor" stroke="none"/>
            </svg>
        </div>

        {{-- Badge --}}
        <div class="tnf-eyebrow">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <circle cx="12" cy="16" r=".5" fill="currentColor" stroke="none"/>
            </svg>
            Espacio no encontrado
        </div>

        {{-- Heading --}}
        <h1 class="tnf-title">Este espacio de cliente no existe</h1>

        <p class="tnf-subtitle">
            Puede que el enlace sea incorrecto o que este espacio<br class="d-none d-sm-block">
            haya sido eliminado o nunca haya existido.
        </p>

        {{-- Attempted domain chip --}}
        @if($domain)
        <div>
            <div class="tnf-domain-chip">
                <span class="tnf-domain-chip-label">Has intentado acceder a:</span>
                {{ $domain }}
            </div>
        </div>
        @endif

        <hr class="tnf-divider">

        {{-- CTA --}}
        <p class="tnf-cta-title">¿Quieres tu propio espacio en Fakturalista?</p>
        <p class="tnf-cta-sub">Crea tu cuenta gratis en menos de 2 minutos. Sin tarjeta de crédito.</p>

        <a href="{{ route('free-trial') }}" class="tnf-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12l7 7 7-7"/>
            </svg>
            Crear cuenta gratis
        </a>

        <a href="{{ url('/') }}" class="tnf-back-link">
            ← Volver a fakturalista.com
        </a>

    </div>
</section>
@endsection
