<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales', []), true) ? 'rtl' : 'ltr' }}">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
    <title>@yield('title', 'Fakturalista') - Fakturalista</title>
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('assets/icon.svg') }}" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ url('assets/icon.svg') }}" />
    <link rel="icon" type="image/png" sizes="16x16" href="{{ url('assets/icon.svg') }}" />
    <link rel="mask-icon" href="assets/img/fav/safari-pinned-tab.svg" color="#fa7070" />

    <meta name="msapplication-TileColor" content="#fa7070" />
    <meta name="theme-color" content="#fa7070" />
    @hasSection('meta')
        @yield('meta')
    @else
        <meta name="description" content="Fakturalista es una plataforma moderna de facturación online que te permite crear, enviar y gestionar facturas profesionales en segundos.">
        <meta name="keywords" content="facturación, facturas online, SaaS, gestión de gastos, pagos instantáneos, software de facturación, Fakturalista">
        <meta property="og:title" content="Fakturalista – Software de facturación online" />
        <meta property="og:description" content="Crea y envía facturas profesionales, controla tus gastos y acepta pagos instantáneos con Fakturalista." />
        <meta property="og:image" content="{{ asset('images/og-image.jpg') }}" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="Fakturalista" />
        <link rel="canonical" href="{{ url()->current() }}" />
    @endif
    <meta name="author" content="Fakturalista">
    <!-- Dependency Styles -->
    <link rel="stylesheet" href="{{ url('dependencies/bootstrap/css/bootstrap.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/fontawesome/css/all.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/swiper/css/swiper.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/wow/css/animate.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/magnific-popup/css/magnific-popup.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/components-elegant-icons/css/elegant-icons.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/simple-line-icons/css/simple-line-icons.css') }}" type="text/css" />

    <!-- Site Stylesheet -->
    <link rel="stylesheet" href="{{ url('front/assets/css/app.css') }}" type="text/css" />

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat+Alternates:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Satisfy&display=swap" rel="stylesheet" />

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-YY0S2MP872"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-YY0S2MP872');
    </script>

    <style>
    /* ── Top sales bar - slim, above the main nav, same on every page ──
       z-index is deliberately higher than .site-header (9999) and its
       scrolled .pix-header-fixed state (999999, see app.css). .fk-topbar
       establishes its own stacking context (position:relative + z-index),
       so its position:fixed .fk-lang-panel child is stacked WITHIN that
       context, not against the page root - a z-index set on the panel
       itself can't let it escape and out-rank .site-header, which is a
       sibling further down in the DOM. Without this, the main nav painted
       over the top of the topbar's language dropdown when it opened,
       leaving only its last row visible. */
    .fk-topbar {
        background: #fff;
        border-bottom: 1px solid #eef0f3;
        position: relative;
        z-index: 1000000;
    }
    .fk-topbar-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        min-height: 36px;
        font-size: 13px;
    }
    .fk-topbar-phone {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: #4b5563;
        font-weight: 500;
        white-space: nowrap;
        transition: color .15s;
    }
    .fk-topbar-phone i { color: #E91E63; font-size: 11px; }
    .fk-topbar-phone:hover,
    .fk-topbar-phone:hover .fk-topbar-phone-num { color: #E91E63; }
    .fk-topbar-phone-num { font-weight: 700; color: #0F172A; transition: color .15s; }
    .fk-topbar-right { display: flex; align-items: center; gap: 12px; }
    .fk-topbar-market {
        font-weight: 600;
        color: #6b7280;
        letter-spacing: .02em;
        white-space: nowrap;
    }
    .fk-topbar-sep { color: #d1d5db; }
    .fk-topbar-lang .fk-lang-btn {
        background: transparent;
        border-color: rgba(15,23,42,.14);
        color: #4b5563;
        padding: 3px 9px 3px 7px;
        font-size: 12.5px;
    }
    .fk-topbar-lang .fk-lang-btn:hover,
    .fk-topbar-lang .fk-lang-btn[aria-expanded="true"] {
        background: #f9fafb;
        border-color: rgba(15,23,42,.28);
        color: #0F172A;
    }
    @media (max-width: 575px) {
        .fk-topbar-help-label,
        .fk-topbar-market,
        .fk-topbar-sep { display: none; }
        .fk-topbar-inner { min-height: 34px; font-size: 12.5px; }
    }
    @media (max-width: 400px) {
        .fk-topbar-lang .fk-lang-label { display: none; }
    }

    /* ── Main navbar - compact SaaS layout ───────────────────────────
       The stock template's .site-header is `position: absolute` (meant
       to float, transparent, over a photo hero) and its .nav-btn is a
       white floating pill with a huge shadow (meant to stand out over
       that photo). Fakturalista never uses that overlay look - every
       public page already forces the nav links dark - so the absolute
       header was instead overlapping the new top bar (both anchored at
       top:0), which is the root cause of the "broken/oversized" header.
       Below: the header becomes a normal in-flow bar (still switching to
       `position:fixed` on scroll via the existing .pix-header-fixed
       class/JS), sized and spaced for a compact, one-line desktop nav. */
    .site-header.header_trans-fixed {
        position: relative;
        background: #fff;
        border-bottom: 1px solid #eef0f3;
    }
    .site-header.header_trans-fixed.pix-header-fixed {
        position: fixed;
        top: 0;
    }
    .site-header .site-main-menu li > a { color: #0F172A; }

    /* Primary CTA - a normal compact button, not a floating card, in
       both the resting and scrolled (.pix-header-fixed) states. */
    .site-header .header-inner .site-nav .nav-right .nav-btn,
    .pix-header-fixed .header-inner .site-nav .nav-right .nav-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 44px;
        padding: 0 22px;
        background: #E91E63;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        box-shadow: none;
        transition: background .15s, transform .15s;
    }
    .site-header .header-inner .site-nav .nav-right .nav-btn:hover,
    .pix-header-fixed .header-inner .site-nav .nav-right .nav-btn:hover {
        background: #C2185B;
        color: #fff;
        transform: translateY(-1px);
    }

    /* Secondary "Connexion" - a plain text link, not a boxed button. */
    .site-header .header-inner .site-nav .nav-right .nav-login {
        color: #0F172A;
        font-weight: 600;
        font-size: 14px;
        white-space: nowrap;
        transition: color .15s;
    }
    .site-header .header-inner .site-nav .nav-right .nav-login:hover { color: #E91E63; }

    @media (min-width: 1100px) {
        .site-header .header-inner {
            display: flex;
            align-items: center;
            min-height: 72px;
        }
        .site-header .header-inner .site-logo a { max-width: 172px; }
        .site-header .header-inner .site-nav .menu-wrapper {
            flex: 1;
            justify-content: space-between;
            margin-left: 44px;
        }
        .site-header .site-main-menu li {
            margin: 0 14px;
            padding: 0;
        }
        .site-header .site-main-menu li > a {
            font-size: 15px;
            font-weight: 600;
            white-space: nowrap;
        }
        .site-header .header-inner .site-nav .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-left: 24px;
        }
    }

    @media (max-width: 1099px) {
        .site-header .toggle-menu {
            position: absolute;
            left: 0;
            width: 26px;
            top: 50%;
            transform: translateY(-50%);
            display: block;
            height: 24px;
        }
        .site-header .toggle-menu .bar {
            width: 18px;
            height: 2px;
            display: block;
            float: left;
            margin: 3px auto;
            background: #0F172A;
        }
        .site-header .toggle-menu .bar:nth-child(2) { width: 24px; }
        .site-header .header-inner { text-align: center; padding: 14px 0; }
        .site-header .site-mobile-logo { display: block; }
        .site-header .site-mobile-logo img { max-height: 30px; width: auto; }
        .site-header .site-logo { display: none; }
        .site-header .header-inner .site-nav .menu-wrapper { display: block; }
        .site-header .site-nav {
            position: fixed;
            width: 320px !important;
            height: 100vh;
            background: #fff;
            top: 0;
            left: -100%;
            display: block !important;
            transition: all .5s ease-in-out;
            overflow: scroll;
            box-shadow: 0 20px 30px rgba(0,0,0,.1);
        }
        .site-header .site-nav .site-main-menu {
            display: block;
            width: 100%;
            padding-bottom: 20px;
            text-align: left;
        }
        .site-header .site-nav .site-main-menu li {
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #f1f2f3;
        }
        .site-header .site-nav .site-main-menu li:first-child { border-top: 1px solid #f1f2f3; }
        .site-header .site-nav .site-main-menu li > a {
            display: block;
            padding: 14px 24px;
            font-weight: 500;
            color: #344054;
        }
        .site-header .site-nav .site-main-menu li > a:after { display: none; }
        .site-header .header-inner .site-nav .nav-right {
            display: block;
            margin: 16px 24px 20px;
            padding: 0;
        }
        .site-header .header-inner .site-nav .nav-right .nav-login {
            display: block;
            padding: 10px 0;
            text-align: left;
        }
        .site-header .header-inner .site-nav .nav-right .nav-btn {
            display: block;
            width: 100%;
            margin-top: 8px;
        }
    }

    /* ── Language switcher - fully custom, position:fixed panel ── */
    .fk-lang-switcher { display: inline-flex; align-items: center; }

    .fk-lang-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.20);
        color: #c9cdd4;
        border-radius: 100px;
        padding: 5px 10px 5px 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background .15s, border-color .15s, color .15s;
        line-height: 1.4;
        white-space: nowrap;
        user-select: none;
    }
    .fk-lang-btn:hover,
    .fk-lang-btn[aria-expanded="true"] {
        background: rgba(255,255,255,0.15);
        color: #fff;
        border-color: rgba(255,255,255,0.38);
        outline: none;
    }
    .fk-lang-globe { flex-shrink: 0; }
    .fk-lang-chevron {
        flex-shrink: 0;
        opacity: .65;
        transition: transform .2s ease;
    }
    .fk-lang-btn[aria-expanded="true"] .fk-lang-chevron { transform: rotate(180deg); }

    /* Panel - always position:fixed so it escapes any overflow:hidden ancestor */
    .fk-lang-panel {
        position: fixed;
        min-width: 164px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 8px 32px rgba(0,0,0,.14), 0 2px 8px rgba(0,0,0,.06);
        padding: 5px;
        z-index: 999999;
        visibility: hidden;
        opacity: 0;
        transform: translateY(6px) scale(0.97);
        transform-origin: bottom center;
        pointer-events: none;
        transition: opacity .14s ease, transform .14s ease, visibility 0s .14s;
    }
    .fk-lang-panel.fk-open {
        visibility: visible;
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
        transition: opacity .14s ease, transform .14s ease, visibility 0s 0s;
    }

    .fk-lang-form { margin: 0; padding: 0; }
    .fk-lang-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 0 10px;
        height: 40px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        background: none;
        border: none;
        width: 100%;
        text-align: left;
        cursor: pointer;
        transition: background .1s;
        white-space: nowrap;
        line-height: 1;
    }
    .fk-lang-item:hover { background: #f3f4f6; color: #111827; }
    .fk-lang-item.fk-active { color: #E91E63; font-weight: 600; background: #fff0f6; }
    .fk-lang-item.fk-active:hover { background: #fce7f3; }
    .fk-lang-chk { width: 14px; height: 14px; flex-shrink: 0; }
    .fk-lang-chk-empty { display: inline-block; width: 14px; flex-shrink: 0; }


    /* ── Pre-footer CTA ────────────────────────────────────────────── */
    .fk-prefooter {
        padding: 100px 0;
        text-align: center;
        background: #fff;
    }
    .fk-prefooter-inner { max-width: 580px; margin: 0 auto; }
    .fk-prefooter-badge {
        display: inline-flex;
        align-items: center;
        background: #fff0f6;
        color: #E91E63;
        border: 1px solid rgba(233,30,99,.16);
        border-radius: 100px;
        padding: 4px 14px;
        font-size: 11.5px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 24px;
    }
    .fk-prefooter-title {
        font-size: clamp(28px, 4.5vw, 48px);
        font-weight: 700;
        color: #0f172a;
        line-height: 1.15;
        letter-spacing: -0.022em;
        margin: 0 0 16px;
    }
    .fk-prefooter-sub {
        font-size: 17px;
        color: #64748b;
        margin: 0 0 36px;
        line-height: 1.65;
    }
    .fk-prefooter-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #E91E63;
        color: #fff !important;
        border-radius: 10px;
        padding: 13px 28px;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none !important;
        transition: background .18s, transform .18s, box-shadow .18s;
    }
    .fk-prefooter-btn:hover {
        background: #c2185b;
        transform: translateY(-2px);
        box-shadow: 0 10px 28px rgba(233,30,99,.28);
        color: #fff !important;
    }
    .fk-prefooter-btn svg { flex-shrink: 0; }
    .fk-prefooter-note {
        margin: 16px 0 0;
        font-size: 13px;
        color: #94a3b8;
    }

    /* ── Footer shell ────────────────────────────────────────────────── */
    #footer.fk-footer {
        background: #0d1117;
        border-top: none;
        padding: 0;
        margin: 0;
    }
    .fk-footer-body { padding: 72px 0 0; }

    /* ── Grid ────────────────────────────────────────────────────────── */
    .fk-footer-grid {
        display: grid;
        grid-template-columns: 2.2fr 1fr 1fr 1fr 1fr;
        gap: 48px 40px;
        padding-bottom: 56px;
        border-bottom: 1px solid rgba(255,255,255,0.07);
    }

    /* Brand column */
    .fk-footer-logo-link { display: inline-block; margin-bottom: 16px; }
    .fk-footer-logo {
        height: 30px;
        width: auto;
        filter: brightness(0) invert(1);
        opacity: .88;
    }
    .fk-footer-desc {
        font-size: 14px;
        color: #6b7280;
        line-height: 1.7;
        max-width: 260px;
        margin: 0 0 24px;
    }
    .fk-footer-brand-cta {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #E91E63;
        color: #fff !important;
        border-radius: 8px;
        padding: 9px 20px;
        font-size: 13.5px;
        font-weight: 600;
        text-decoration: none !important;
        transition: background .15s, transform .15s, box-shadow .15s;
        white-space: nowrap;
    }
    .fk-footer-brand-cta:hover {
        background: #c2185b;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(233,30,99,.28);
    }

    /* Column headings */
    .fk-footer-col-title {
        font-size: 11px;
        font-weight: 600;
        color: #ffffff;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin: 0 0 18px;
    }

    /* Links */
    .fk-footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 11px;
    }
    .fk-footer-links a {
        font-size: 14px;
        font-weight: 400;
        color: #6b7280;
        text-decoration: none;
        line-height: 1;
        transition: color .15s ease;
    }
    .fk-footer-links a:hover {
        color: #c9d1d9;
        text-decoration: none;
    }

    /* ── Bottom bar ──────────────────────────────────────────────────── */
    .fk-footer-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px 24px;
        padding: 22px 0;
    }
    .fk-footer-bar-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .fk-footer-bar-copy { font-size: 13px; color: #4b5563; white-space: nowrap; }
    .fk-footer-bar-right { display: flex; align-items: center; gap: 12px; }
    .fk-footer-socials { display: flex; align-items: center; gap: 6px; }
    .fk-footer-social-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        color: #6b7280 !important;
        font-size: 13px;
        text-decoration: none !important;
        transition: background .15s, color .15s, transform .15s, border-color .15s;
    }
    .fk-footer-social-icon:hover {
        background: rgba(255,255,255,0.10);
        border-color: rgba(255,255,255,0.14);
        color: #c9d1d9 !important;
        transform: translateY(-1px);
    }

    /* ── Responsive ──────────────────────────────────────────────────── */
    @media (max-width: 1199px) {
        .fk-footer-grid {
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 40px 32px;
        }
        .fk-footer-brand { grid-column: 1 / -1; }
        .fk-footer-desc { max-width: none; }
    }
    @media (max-width: 767px) {
        .fk-footer-grid {
            grid-template-columns: 1fr 1fr;
            gap: 32px 24px;
        }
        .fk-footer-brand { grid-column: 1 / -1; }
        .fk-footer-body { padding: 56px 0 0; }
        .fk-footer-bar { flex-direction: column; align-items: flex-start; gap: 14px; }
        .fk-footer-bar-right { flex-wrap: wrap; gap: 10px; }
        .fk-prefooter { padding: 72px 0; }
    }
    @media (max-width: 479px) {
        .fk-footer-grid { grid-template-columns: 1fr; gap: 32px; }
        .fk-prefooter-sub { font-size: 15px; }
    }

        /* Page Banner - mobile responsive override (About Us, Blog index, Blog detail) */
    @media (max-width: 767px) {
        .page-banner {
            height: auto;
            padding: 90px 0 60px;
        }
        .page-banner .page-title {
            font-size: 28px;
            line-height: 1.3;
            margin: 10px auto 12px;
        }
        .page-banner.blog-details-banner {
            height: auto;
            padding: 90px 0 48px;
        }
        .page-banner.blog-details-banner .page-title-wrapper {
            margin-top: 0;
        }
        .page-banner.blog-details-banner .page-title {
            font-size: 24px;
            line-height: 1.4;
        }
    }
    @media (max-width: 479px) {
        .page-banner .page-title {
            font-size: 22px;
        }
        .page-banner.blog-details-banner .page-title {
            font-size: 20px;
        }
    }

    /* ══════════════════════════════════════════════════════════════
       RTL (Arabic) - public site
       This <style> block loads after bootstrap.min.css and app.css
       (see the <link> tags above), so same-specificity rules here
       win in the cascade without needing !important. Scope is the
       "pragmatic global" mirror agreed for this task: shared layout
       (nav, mobile drawer, lang switcher, footer) plus the common
       Bootstrap utility classes used across the marketing pages -
       not a per-page pixel audit.
       ══════════════════════════════════════════════════════════════ */
    html[dir="rtl"] body {
        direction: rtl;
        text-align: right;
    }

    /* Desktop nav */
    html[dir="rtl"] .site-header .header-inner .site-nav .menu-wrapper {
        margin-left: 0;
        margin-right: 44px;
    }
    html[dir="rtl"] .site-header .header-inner .site-nav .nav-right {
        margin-left: 0;
        margin-right: 24px;
    }

    /* Mobile hamburger + off-canvas drawer */
    html[dir="rtl"] .site-header .toggle-menu {
        left: auto;
        right: 0;
    }
    html[dir="rtl"] .site-header .toggle-menu .bar {
        float: right;
    }
    html[dir="rtl"] .site-header .site-nav {
        left: auto;
        right: -100%;
    }
    html[dir="rtl"] .sidebar-open .site-header .site-nav {
        left: auto;
        right: 0;
    }
    html[dir="rtl"] .site-header .site-nav .site-main-menu {
        text-align: right;
    }
    html[dir="rtl"] .site-header .header-inner .site-nav .nav-right .nav-login {
        text-align: right;
    }

    /* Language switcher */
    html[dir="rtl"] .fk-lang-btn {
        padding: 5px 8px 5px 10px;
    }
    html[dir="rtl"] .fk-topbar-lang .fk-lang-btn {
        padding: 3px 7px 3px 9px;
    }
    html[dir="rtl"] .fk-lang-item {
        text-align: right;
    }

    /* Bootstrap utilities (bootstrap.min.css ships LTR-only; no rtlcss
       step in this project's build, so the common ones are mirrored
       by hand here). Values match Bootstrap 5's default $spacers map. */
    html[dir="rtl"] .ms-0 { margin-left: 0 !important; margin-right: 0 !important; }
    html[dir="rtl"] .ms-1 { margin-left: 0 !important; margin-right: 0.25rem !important; }
    html[dir="rtl"] .ms-2 { margin-left: 0 !important; margin-right: 0.5rem !important; }
    html[dir="rtl"] .ms-3 { margin-left: 0 !important; margin-right: 1rem !important; }
    html[dir="rtl"] .ms-4 { margin-left: 0 !important; margin-right: 1.5rem !important; }
    html[dir="rtl"] .ms-5 { margin-left: 0 !important; margin-right: 3rem !important; }
    html[dir="rtl"] .ms-auto { margin-left: 0 !important; margin-right: auto !important; }
    html[dir="rtl"] .me-0 { margin-right: 0 !important; margin-left: 0 !important; }
    html[dir="rtl"] .me-1 { margin-right: 0 !important; margin-left: 0.25rem !important; }
    html[dir="rtl"] .me-2 { margin-right: 0 !important; margin-left: 0.5rem !important; }
    html[dir="rtl"] .me-3 { margin-right: 0 !important; margin-left: 1rem !important; }
    html[dir="rtl"] .me-4 { margin-right: 0 !important; margin-left: 1.5rem !important; }
    html[dir="rtl"] .me-5 { margin-right: 0 !important; margin-left: 3rem !important; }
    html[dir="rtl"] .me-auto { margin-right: 0 !important; margin-left: auto !important; }
    html[dir="rtl"] .ps-0 { padding-left: 0 !important; padding-right: 0 !important; }
    html[dir="rtl"] .ps-1 { padding-left: 0 !important; padding-right: 0.25rem !important; }
    html[dir="rtl"] .ps-2 { padding-left: 0 !important; padding-right: 0.5rem !important; }
    html[dir="rtl"] .ps-3 { padding-left: 0 !important; padding-right: 1rem !important; }
    html[dir="rtl"] .ps-4 { padding-left: 0 !important; padding-right: 1.5rem !important; }
    html[dir="rtl"] .ps-5 { padding-left: 0 !important; padding-right: 3rem !important; }
    html[dir="rtl"] .pe-0 { padding-right: 0 !important; padding-left: 0 !important; }
    html[dir="rtl"] .pe-1 { padding-right: 0 !important; padding-left: 0.25rem !important; }
    html[dir="rtl"] .pe-2 { padding-right: 0 !important; padding-left: 0.5rem !important; }
    html[dir="rtl"] .pe-3 { padding-right: 0 !important; padding-left: 1rem !important; }
    html[dir="rtl"] .pe-4 { padding-right: 0 !important; padding-left: 1.5rem !important; }
    html[dir="rtl"] .pe-5 { padding-right: 0 !important; padding-left: 3rem !important; }
    html[dir="rtl"] .text-start { text-align: right !important; }
    html[dir="rtl"] .text-end   { text-align: left !important; }
    html[dir="rtl"] .float-start { float: right !important; }
    html[dir="rtl"] .float-end   { float: left !important; }
    html[dir="rtl"] .dropdown-menu { text-align: right; left: auto; right: 0; }
    html[dir="rtl"] .dropdown-menu-end { right: auto; left: 0; }
    html[dir="rtl"] .form-check { padding-left: 0; padding-right: 1.5em; }
    html[dir="rtl"] .form-check .form-check-input { margin-left: 0; margin-right: -1.5em; }
    html[dir="rtl"] .modal-header .btn-close { margin: -0.5rem auto -0.5rem -0.5rem; }
    html[dir="rtl"] .alert-dismissible { padding-right: 1rem; padding-left: 3rem; }
    html[dir="rtl"] .alert-dismissible .btn-close { right: auto; left: 0; }

    /* Keep naturally-LTR content readable inside the RTL interface */
    html[dir="rtl"] .fk-ltr,
    html[dir="rtl"] a[href^="mailto:"],
    html[dir="rtl"] a[href^="tel:"] {
        direction: ltr;
        unicode-bidi: isolate;
    }
    </style>

</head>

<body id="home-version-1" class="home-version-4" data-style="default">
    <a href="#main_content" data-type="section-switch" class="return-to-top">
        <i class="fa fa-chevron-up"></i>
    </a>

    <div class="page-loader">
        <div class="loader">
            <!-- Loader -->
            <div class="blobs">
                <div class="blob-center"></div>
                <div class="blob"></div>
                <div class="blob"></div>
                <div class="blob"></div>
                <div class="blob"></div>
                <div class="blob"></div>
                <div class="blob"></div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" version="1.1">
                <defs>
                    <filter id="goo">
                        <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur" />
                        <feColorMatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 18 -7" result="goo" />
                        <feBlend in="SourceGraphic" in2="goo" />
                    </filter>
                </defs>
            </svg>
        </div>
    </div>
    <!-- /.page-loader -->

    <div id="main_content">

        <!--=========================-->
        <!--=       Top bar         =-->
        <!--=========================-->
        @php
            $fkContactPhoneDisplay = config('fakturalista.contact_phone_display');
            $fkContactPhoneLink    = config('fakturalista.contact_phone_link');
            $fkMarketCurrency      = session('public_market', 'MA') === 'ES' ? 'EUR' : 'MAD';
        @endphp
        <div class="fk-topbar">
            <div class="container">
                <div class="fk-topbar-inner">
                    <a href="{{ $fkContactPhoneLink }}" class="fk-topbar-phone">
                        <i class="fas fa-phone-alt" aria-hidden="true"></i>
                        <span class="fk-topbar-help-label">{{ __('site.topbar.help') }}</span>
                        <span class="fk-topbar-phone-num">{{ $fkContactPhoneDisplay }}</span>
                    </a>
                    <div class="fk-topbar-right">
                        <span class="fk-topbar-market">{{ $fkMarketCurrency }}</span>
                        <span class="fk-topbar-sep" aria-hidden="true">&middot;</span>
                        <div class="fk-topbar-lang">
                            @include('partials.lang-switcher')
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!--=========================-->
        <!--=        Navbar         =-->
        <!--=========================-->
        <header class="site-header header_trans-fixed" data-top="992">
            <div class="container">
                <div class="header-inner">
                    <div class="toggle-menu">
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                    </div>
                    <!-- /.toggle-menu -->

                    <div class="site-mobile-logo">
                        <a href="index.html" class="logo">
                            <img src="{{ url('assets/icon.svg') }}" alt="site logo" class="main-logo" />
                            <img src="{{ url('assets/icon.svg') }}" alt="site logo" class="sticky-logo" />
                        </a>
                    </div>

                    <nav class="site-nav">
                        <div class="close-menu">
                            <span>{{ __('site.nav.close') }}</span>
                            <i class="ei ei-icon_close"></i>
                        </div>

                        <div class="site-logo">
                            <a href="{{ url('/') }}" class="logo">
                                <img src="{{ url('assets/logo.svg') }}" alt="site logo" class="main-logo" />
                                <img src="{{ url('assets/logo.svg') }}" alt="site logo" class="sticky-logo" />
                            </a>
                        </div>
                        <!-- /.site-logo -->

                        <div class="menu-wrapper" data-top="992">
                            <ul class="site-main-menu">
                                <li><a href="{{ url('/') }}#funciones">{{ __('site.nav.features') }}</a></li>
                                <li><a href="{{ url('/pricing') }}">{{ __('site.nav.pricing') }}</a></li>
                                <li><a href="{{ url('/') }}#como-funciona">{{ __('site.nav.how_it_works') }}</a></li>
                                <li><a href="{{ url('/help-center') }}">{{ __('site.nav.resources') }}</a></li>
                                <li><a href="{{ url('/contact') }}">{{ __('site.nav.contact') }}</a></li>
                            </ul>

                            <div class="nav-right">
                                <a href="{{ url('/login') }}" class="nav-login">{{ __('site.nav.login') }}</a>
                                <a href="{{ url('/register') }}" class="nav-btn">{{ __('site.nav.cta') }}</a>
                            </div>
                        </div>
                        <!-- /.menu-wrapper -->
                    </nav>
                    <!-- /.site-nav -->
                </div>
                <!-- /.header-inner -->
            </div>
            <!-- /.container -->
        </header>
        <!-- /.site-header -->

        @yield('content')

        <!-- ===========================
             Pre-footer CTA
             =========================== -->
        <section class="fk-prefooter">
            <div class="container">
                <div class="fk-prefooter-inner">
                    <div class="fk-prefooter-badge">{{ __('site.footer.cta_badge') }}</div>
                    <h2 class="fk-prefooter-title">{{ __('site.footer.cta_title') }}</h2>
                    <p class="fk-prefooter-sub">{{ __('site.footer.cta_sub') }}</p>
                    <a href="{{ url('/register') }}" class="fk-prefooter-btn">
                        {{ __('site.footer.cta_btn') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <p class="fk-prefooter-note">{{ __('site.footer.cta_note') }}</p>
                </div>
            </div>
        </section>

        <!--=========================-->
        <!--=        Footer         =-->
        <!--=========================-->
        <footer id="footer" class="fk-footer">
            <div class="container">
                <div class="fk-footer-body">

                    <!-- Top grid: brand + link columns -->
                    <div class="fk-footer-grid">

                        <!-- Brand column -->
                        <div class="fk-footer-brand">
                            <a href="{{ url('/') }}" class="fk-footer-logo-link">
                                <img src="{{ url('assets/logo.svg') }}" alt="Fakturalista" class="fk-footer-logo" />
                            </a>
                            <p class="fk-footer-desc">{{ __('site.footer.tagline') }}</p>
                            <a href="{{ url('/register') }}" class="fk-footer-brand-cta">{{ __('site.nav.cta') }}</a>
                        </div>

                        <!-- Product column -->
                        <div class="fk-footer-col">
                            <h4 class="fk-footer-col-title">{{ __('site.footer.product') }}</h4>
                            <ul class="fk-footer-links">
                                <li><a href="{{ url('/pricing') }}">{{ __('site.footer.product_pricing') }}</a></li>
                                <li><a href="{{ url('/security') }}">{{ __('site.footer.product_security') }}</a></li>
                                <li><a href="{{ url('/integrations') }}">{{ __('site.footer.product_integrations') }}</a></li>
                                <li><a href="{{ url('/faq') }}">{{ __('site.footer.product_faq') }}</a></li>
                            </ul>
                        </div>

                        <!-- Company column -->
                        <div class="fk-footer-col">
                            <h4 class="fk-footer-col-title">{{ __('site.footer.company') }}</h4>
                            <ul class="fk-footer-links">
                                <li><a href="{{ url('/about') }}">{{ __('site.footer.company_about') }}</a></li>
                                <li><a href="{{ url('/contact') }}">{{ __('site.footer.company_contact') }}</a></li>
                                <li><a href="{{ url('/blog') }}">{{ __('site.footer.company_blog') }}</a></li>
                            </ul>
                        </div>

                        <!-- Resources column -->
                        <div class="fk-footer-col">
                            <h4 class="fk-footer-col-title">{{ __('site.footer.resources') }}</h4>
                            <ul class="fk-footer-links">
                                <li><a href="{{ url('/documentation') }}">{{ __('site.footer.resources_docs') }}</a></li>
                                <li><a href="{{ url('/help-center') }}">{{ __('site.footer.resources_help') }}</a></li>
                                <li><a href="{{ url('/api-docs') }}">{{ __('site.footer.resources_api') }}</a></li>
                                <li><a href="{{ url('/changelog') }}">{{ __('site.footer.resources_changelog') }}</a></li>
                                @if(app()->getLocale() === 'es')
                                    <li><a href="{{ url('/verifactu') }}">{{ __('site.footer.resources_verifactu') }}</a></li>
                                @endif
                            </ul>
                        </div>

                        <!-- Legal column -->
                        <div class="fk-footer-col">
                            <h4 class="fk-footer-col-title">{{ __('site.footer.legal_title') }}</h4>
                            <ul class="fk-footer-links">
                                <li><a href="{{ url('/privacy-policy') }}">{{ __('site.footer.privacy') }}</a></li>
                                <li><a href="{{ url('/terms') }}">{{ __('site.footer.terms') }}</a></li>
                                <li><a href="{{ url('/legal-notice') }}">{{ __('site.footer.legal') }}</a></li>
                                <li><a href="{{ url('/cookie-policy') }}">{{ __('site.footer.cookies') }}</a></li>
                            </ul>
                        </div>

                    </div><!-- /.fk-footer-grid -->

                    <!-- Bottom bar -->
                    <div class="fk-footer-bar">
                        <div class="fk-footer-bar-left">
                            <span class="fk-footer-bar-copy">{{ __('site.footer.copyright') }}</span>
                        </div>

                        <div class="fk-footer-bar-right">
                            @include('partials.lang-switcher')

                            <div class="fk-footer-socials">
                                <a href="https://www.facebook.com/fakturalista" class="fk-footer-social-icon" aria-label="Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="https://www.instagram.com/fakturalista" class="fk-footer-social-icon" aria-label="Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="https://www.tiktok.com/@fakturalista" class="fk-footer-social-icon" aria-label="TikTok">
                                    <i class="fab fa-tiktok"></i>
                                </a>
                            </div>
                        </div>
                    </div><!-- /.fk-footer-bar -->

                </div><!-- /.fk-footer-body -->
            </div><!-- /.container -->
        </footer><!-- /#footer -->

    </div>
    <!-- /#site -->

    <!-- Dependency Scripts -->
    <script src="{{ url('dependencies/jquery/jquery.min.js') }}"></script>
    <script src="{{ url('dependencies/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ url('dependencies/swiper/js/swiper.min.js') }}"></script>
    <script src="{{ url('dependencies/jquery.appear/jquery.appear.js') }}"></script>
    <script src="{{ url('dependencies/wow/js/wow.min.js') }}"></script>
    <script src="{{ url('dependencies/countUp.js/countUp.min.js') }}"></script>
    <script src="{{ url('dependencies/isotope-layout/isotope.pkgd.min.js') }}"></script>
    <script src="{{ url('dependencies/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
    <script src="{{ url('dependencies/jquery.parallax-scroll/js/jquery.parallax-scroll.js') }}"></script>
    <script src="{{ url('dependencies/magnific-popup/js/jquery.magnific-popup.min.js') }}"></script>
    <script src="{{ url('dependencies/gmap3/js/gmap3.min.js') }}"></script>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDk2HrmqE4sWSei0XdKGbOMOHN3Mm2Bf-M&#038;ver=2.1.6"></script>

    <!-- Site Scripts -->
    <script src="{{ url('front/assets/js/header.js') }}"></script>
    <script src="{{ url('front/assets/js/app.js') }}"></script>

    <script>
    (function () {
        // Scoped per-instance (not a single fixed id) so the same
        // switcher can appear twice on one page - the top bar and the
        // footer - without colliding.
        function initFkLangInstance(root) {
            var btn   = root.querySelector('.fk-lang-btn');
            var panel = root.querySelector('.fk-lang-panel');
            if (!btn || !panel) return;

            function position() {
                var r  = btn.getBoundingClientRect();
                var pw = panel.offsetWidth  || 164;
                var ph = panel.offsetHeight || 130;
                var vw = window.innerWidth;
                var gap = 6;
                var isRtl = document.documentElement.getAttribute('dir') === 'rtl';

                // Prefer opening downward (top-bar placement); fall back
                // upward if there isn't room below (footer placement).
                var top = r.bottom + gap;
                if (top + ph > window.innerHeight - 8) top = r.top - ph - gap;

                // Align to the button's leading edge - left edge in LTR,
                // right edge in RTL - then clamp so the panel stays in
                // the viewport either way.
                var left = isRtl ? (r.right - pw) : r.left;
                if (left + pw > vw - 8) left = vw - pw - 8;
                if (left < 8) left = 8;

                panel.style.top  = top  + 'px';
                panel.style.left = left + 'px';
            }

            function open() {
                position();
                panel.classList.add('fk-open');
                btn.setAttribute('aria-expanded', 'true');
            }
            function close() {
                panel.classList.remove('fk-open');
                btn.setAttribute('aria-expanded', 'false');
            }

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                panel.classList.contains('fk-open') ? close() : open();
            });
            document.addEventListener('click', function (e) {
                if (!panel.contains(e.target) && e.target !== btn) close();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') close();
            });
            window.addEventListener('resize', function () {
                if (panel.classList.contains('fk-open')) position();
            });
            window.addEventListener('scroll', function () {
                if (panel.classList.contains('fk-open')) position();
            }, { passive: true });
        }

        function initFkLang() {
            document.querySelectorAll('.fk-lang-switcher').forEach(initFkLangInstance);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initFkLang);
        } else {
            initFkLang();
        }
    })();
    </script>

</body>

</html>
