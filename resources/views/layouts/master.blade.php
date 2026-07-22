<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

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
    /* ── Language switcher — fully custom, position:fixed panel ── */
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

    /* Panel — always position:fixed so it escapes any overflow:hidden ancestor */
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

        /* Page Banner — mobile responsive override (About Us, Blog index, Blog detail) */
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
                            <span>Close</span>
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
                                <li><a href="{{ url('/pricing') }}">{{ __('site.nav.pricing') }}</a></li>
                                <li><a href="{{ url('/about') }}">{{ __('site.nav.about') }}</a></li>
                                <li><a href="{{ url('/blog') }}">{{ __('site.nav.blog') }}</a></li>
                                <li><a href="{{ url('/contact') }}">{{ __('site.nav.contact') }}</a></li>
                            </ul>

                            <div class="nav-right">
                                <a href="{{ url('/free-trial') }}" class="nav-btn">{{ __('site.nav.cta') }}</a>
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
                    <a href="{{ url('/free-trial') }}" class="fk-prefooter-btn">
                        {{ __('site.footer.cta_btn') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
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
                            <a href="{{ url('/free-trial') }}" class="fk-footer-brand-cta">{{ __('site.nav.cta') }}</a>
                        </div>

                        <!-- Product column -->
                        <div class="fk-footer-col">
                            <h4 class="fk-footer-col-title">{{ __('site.footer.product') }}</h4>
                            <ul class="fk-footer-links">
                                <li><a href="{{ url('/pricing') }}">{{ __('site.footer.product_pricing') }}</a></li>
                                <li><a href="#">{{ __('site.footer.product_security') }}</a></li>
                                <li><a href="#">{{ __('site.footer.product_integrations') }}</a></li>
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
                                <li><a href="#">{{ __('site.footer.resources_docs') }}</a></li>
                                <li><a href="#">{{ __('site.footer.resources_help') }}</a></li>
                                <li><a href="#">API</a></li>
                                <li><a href="#">{{ __('site.footer.resources_changelog') }}</a></li>
                            </ul>
                        </div>

                        <!-- Legal column -->
                        <div class="fk-footer-col">
                            <h4 class="fk-footer-col-title">{{ __('site.footer.legal_title') }}</h4>
                            <ul class="fk-footer-links">
                                <li><a href="#">{{ __('site.footer.privacy') }}</a></li>
                                <li><a href="#">{{ __('site.footer.terms') }}</a></li>
                                <li><a href="#">{{ __('site.footer.legal') }}</a></li>
                            </ul>
                        </div>

                    </div><!-- /.fk-footer-grid -->

                    <!-- Bottom bar -->
                    <div class="fk-footer-bar">
                        <div class="fk-footer-bar-left">
                            <span class="fk-footer-bar-copy">{{ __('site.footer.copyright') }}</span>
                        </div>

                        <div class="fk-footer-bar-right">
                            @php
                                $fkLangs   = ['es' => __('site.lang.es'), 'fr' => __('site.lang.fr'), 'en' => __('site.lang.en')];
                                $fkCurrent = app()->getLocale();
                                $fkLabel   = $fkLangs[$fkCurrent] ?? 'Español';
                            @endphp
                            <div class="fk-lang-switcher">
                                <button class="fk-lang-btn" type="button" id="fkLangBtn"
                                        aria-expanded="false" aria-haspopup="listbox"
                                        aria-label="{{ __('site.lang.label') }}">
                                    <svg class="fk-lang-globe" width="14" height="14" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                                    <span class="fk-lang-label">{{ $fkLabel }}</span>
                                    <svg class="fk-lang-chevron" width="12" height="12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                </button>
                                <div class="fk-lang-panel" id="fkLangPanel" role="listbox" aria-label="{{ __('site.lang.label') }}">
                                    @foreach($fkLangs as $fkCode => $fkName)
                                    <form method="POST" action="{{ url('/locale') }}" class="fk-lang-form">
                                        @csrf
                                        <input type="hidden" name="locale" value="{{ $fkCode }}">
                                        <button type="submit" class="fk-lang-item {{ $fkCurrent === $fkCode ? 'fk-active' : '' }}">
                                            @if($fkCurrent === $fkCode)
                                                <svg class="fk-lang-chk" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            @else
                                                <span class="fk-lang-chk-empty" aria-hidden="true"></span>
                                            @endif
                                            {{ $fkName }}
                                        </button>
                                    </form>
                                    @endforeach
                                </div>
                            </div>

                            <div class="fk-footer-socials">
                                <a href="#" class="fk-footer-social-icon" aria-label="Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="https://www.instagram.com/fakturalista" class="fk-footer-social-icon" aria-label="Instagram">
                                    <i class="fab fa-instagram"></i>
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
        function initFkLang() {
            var btn   = document.getElementById('fkLangBtn');
            var panel = document.getElementById('fkLangPanel');
            if (!btn || !panel) return;

            function position() {
                var r  = btn.getBoundingClientRect();
                var pw = panel.offsetWidth  || 164;
                var ph = panel.offsetHeight || 130;
                var vw = window.innerWidth;
                var gap = 6;

                // Prefer opening upward (footer placement); fall back to downward
                var top = r.top - ph - gap;
                if (top < 8) top = r.bottom + gap;

                // Align to button left; clamp so panel stays in viewport
                var left = r.left;
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

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initFkLang);
        } else {
            initFkLang();
        }
    })();
    </script>

</body>

</html>
