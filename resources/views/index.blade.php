@extends('layouts.master')

@section('title', __('site.home.meta_title'))

@section('meta')
<meta name="description" content="{{ __('site.home.meta_description') }}" />
<meta name="keywords" content="software de facturación, facturación para autónomos, crear facturas, presupuestos, gestión de clientes" />
<meta property="og:title" content="{{ __('site.home.meta_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.home.meta_description') }}" />
<meta property="og:image" content="{{ url('assets/panel_fakturalista.png') }}" />
<meta property="og:url" content="{{ url('/') }}" />
<meta property="og:type" content="website" />
<meta property="og:site_name" content="Fakturalista" />
<meta name="twitter:card" content="summary_large_image" />
<link rel="canonical" href="{{ url('/') }}" />
@endsection

@section('content')

<style>
/* =========================================================================
   Fakturalista — Home page redesign
   Scoped entirely under .fk-home. Brand accent stays #E91E63 per guidelines.
   ========================================================================= */
.fk-home {
    --fk-pink:        #E91E63;
    --fk-pink-dark:   #C2185B;
    --fk-pink-soft:   #FDF0F5;
    --fk-pink-border: rgba(233, 30, 99, 0.18);
    --fk-ink:         #0F172A;
    --fk-muted:       #64748B;
    --fk-border:      #E7E9EE;
    --fk-bg-alt:      #FAFAFB;
    --fk-radius-lg:   28px;
    --fk-radius-md:   18px;
    --fk-radius-sm:   12px;
    --fk-shadow-sm:   0 2px 10px rgba(15, 23, 42, .05);
    --fk-shadow-md:   0 20px 60px rgba(15, 23, 42, .08);
    font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", sans-serif;
    color: var(--fk-ink);
    background: #fff;
    overflow-x: clip;
}
/* .site-header lives in the shared layout, outside .fk-home in the DOM -
   these rules are page-scoped simply because this <style> block only
   renders on the home page, not by CSS nesting under .fk-home. */
.site-header .site-main-menu li > a { color: #0F172A; }
.site-header.pix-header-fixed {
    background: rgba(255, 255, 255, 0.82);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    backdrop-filter: blur(14px) saturate(160%);
    box-shadow: 0 1px 0 rgba(15, 23, 42, .06), 0 12px 30px rgba(15, 23, 42, .05);
}

.fk-home h1, .fk-home h2, .fk-home h3 {
    font-family: 'Poppins', system-ui, sans-serif;
    color: var(--fk-ink);
    letter-spacing: -0.02em;
}
.fk-home p { color: var(--fk-muted); }
.fk-home a { text-decoration: none; }
.fk-home section { position: relative; }
.fk-home section[id] { scroll-margin-top: 96px; }
html { scroll-behavior: smooth; }
@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
}

.fk-container {
    max-width: 1180px;
    margin: 0 auto;
    padding: 0 24px;
}

/* ── Reveal-on-scroll ─────────────────────────────────────────────────
   Content is visible by default (no-JS, crawlers, disabled JS all see
   the full page). The hidden/animated state only applies once the
   inline script below confirms JS actually ran, so the animation is a
   pure enhancement and never a requirement for visibility. ─────────── */
.fk-reveal { opacity: 1; transform: none; }
.fk-js .fk-reveal {
    opacity: 0;
    transform: translateY(22px);
    transition: opacity .7s cubic-bezier(.16,.8,.3,1), transform .7s cubic-bezier(.16,.8,.3,1);
}
.fk-js .fk-reveal.fk-in {
    opacity: 1;
    transform: translateY(0);
}
@media (prefers-reduced-motion: reduce) {
    .fk-js .fk-reveal { opacity: 1; transform: none; transition: none; }
}

/* ── Buttons ──────────────────────────────────────────────────────────── */
.fk-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 28px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
    transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease, color .18s ease;
    border: 1.5px solid transparent;
}
.fk-btn-primary {
    background: var(--fk-pink);
    color: #fff !important;
    box-shadow: 0 10px 24px rgba(233, 30, 99, .22);
}
.fk-btn-primary:hover {
    background: var(--fk-pink-dark);
    transform: translateY(-2px);
    box-shadow: 0 14px 32px rgba(233, 30, 99, .3);
}
.fk-btn-secondary {
    background: #fff;
    color: var(--fk-ink) !important;
    border-color: var(--fk-border);
}
.fk-btn-secondary:hover {
    border-color: var(--fk-ink);
    transform: translateY(-2px);
}
.fk-btn-lg { padding: 16px 32px; font-size: 16px; }
@media (prefers-reduced-motion: reduce) {
    .fk-btn:hover { transform: none; }
}

/* ── Section heading helpers ──────────────────────────────────────────── */
.fk-kicker {
    display: inline-flex;
    align-items: center;
    background: var(--fk-pink-soft);
    color: var(--fk-pink);
    border: 1px solid var(--fk-pink-border);
    border-radius: 100px;
    padding: 6px 16px;
    font-size: 12.5px;
    font-weight: 600;
    letter-spacing: .02em;
    margin-bottom: 20px;
}
.fk-section-head { max-width: 640px; margin: 0 auto 56px; text-align: center; }
.fk-section-head h2 {
    font-size: clamp(28px, 3.6vw, 42px);
    font-weight: 700;
    line-height: 1.18;
    margin: 0 0 16px;
}
.fk-section-head p { font-size: 17px; line-height: 1.6; margin: 0; }

/* =========================================================================
   HERO
   ========================================================================= */
.fk-hero {
    padding: 176px 0 120px;
    background:
        radial-gradient(60% 55% at 82% 8%, rgba(233,30,99,.06), transparent 70%),
        #fff;
}
.fk-hero-inner { text-align: center; max-width: 760px; margin: 0 auto; }
.fk-hero-title {
    font-size: clamp(38px, 6vw, 64px);
    font-weight: 700;
    line-height: 1.1;
    margin: 0 0 24px;
}
.fk-hero-title span { display: block; }
.fk-hero-title .fk-accent { color: var(--fk-pink); }
.fk-hero-sub {
    font-size: 19px;
    line-height: 1.65;
    max-width: 620px;
    margin: 0 auto 36px;
}
.fk-hero-ctas {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.fk-hero-micro {
    font-size: 13.5px;
    color: var(--fk-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.fk-hero-micro svg { flex-shrink: 0; color: var(--fk-pink); }

.fk-hero-visual {
    position: relative;
    max-width: 980px;
    margin: 80px auto 0;
}
.fk-hero-frame {
    border-radius: var(--fk-radius-lg);
    overflow: hidden;
    box-shadow: var(--fk-shadow-md);
    background: #0f172a;
}
.fk-hero-shot { display: block; width: 100%; height: auto; }

.fk-float-card {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border-radius: 14px;
    padding: 14px 18px;
    box-shadow: 0 16px 40px rgba(15,23,42,.14);
    font-weight: 600;
    font-size: 14.5px;
    color: var(--fk-ink);
    animation: fk-float 5s ease-in-out infinite;
}
.fk-float-card--paid { top: 8%; left: -4%; }
.fk-float-card--amount {
    bottom: 10%;
    right: -3%;
    color: var(--fk-pink);
    font-size: 20px;
    font-weight: 700;
    animation-delay: .6s;
}
.fk-float-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #16a34a;
    color: #fff;
    font-size: 12px;
    flex-shrink: 0;
}
@keyframes fk-float {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-10px); }
}
@media (prefers-reduced-motion: reduce) {
    .fk-float-card { animation: none; }
}
@media (max-width: 767px) {
    .fk-float-card { display: none; }
}

/* =========================================================================
   SOLUTION / BENEFITS (problem → solution, merged with trust intro)
   ========================================================================= */
.fk-solution { padding: 110px 0; background: #fff; }
.fk-solution-head { text-align: center; max-width: 700px; margin: 0 auto 64px; }
.fk-solution-head h2 {
    font-size: clamp(30px, 4vw, 44px);
    font-weight: 700;
    line-height: 1.16;
    margin: 0 0 18px;
}
.fk-solution-head p { font-size: 17px; line-height: 1.65; margin: 0 auto; max-width: 560px; }

.fk-benefits {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 28px;
}
.fk-benefit-card {
    background: #fff;
    border: 1px solid var(--fk-border);
    border-radius: var(--fk-radius-md);
    padding: 32px 28px;
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
}
.fk-benefit-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--fk-shadow-md);
    border-color: transparent;
}
.fk-benefit-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--fk-pink-soft);
    color: var(--fk-pink);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}
.fk-benefit-card h3 { font-size: 19px; font-weight: 700; margin: 0 0 8px; }
.fk-benefit-card p { font-size: 15px; line-height: 1.6; margin: 0; }

/* =========================================================================
   PRODUCT SHOWCASE
   ========================================================================= */
.fk-showcase { padding: 40px 0; }
.fk-show-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 72px;
    align-items: center;
    padding: 88px 0;
    border-bottom: 1px solid var(--fk-border);
}
.fk-show-row:last-child { border-bottom: none; }
.fk-show-row--rev .fk-show-text { order: 2; }
.fk-show-row--rev .fk-show-visual { order: 1; }

.fk-show-text h3 {
    font-size: clamp(24px, 3vw, 32px);
    font-weight: 700;
    line-height: 1.2;
    margin: 0 0 18px;
}
.fk-show-text p { font-size: 16.5px; line-height: 1.7; margin: 0 0 24px; }
.fk-show-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
.fk-show-list li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 15px;
    color: var(--fk-ink);
    font-weight: 500;
}
.fk-show-list svg { flex-shrink: 0; margin-top: 2px; color: var(--fk-pink); }

.fk-show-visual { position: relative; }
.fk-shot-frame {
    border-radius: var(--fk-radius-lg);
    overflow: hidden;
    box-shadow: var(--fk-shadow-md);
    background: #0f172a;
}
.fk-shot-frame img { display: block; width: 100%; height: auto; }

/* Recreated UI mockup card (used where no real screenshot exists) */
.fk-app-card {
    background: #fff;
    border: 1px solid var(--fk-border);
    border-radius: var(--fk-radius-lg);
    box-shadow: var(--fk-shadow-md);
    overflow: hidden;
}
.fk-app-bar {
    display: flex;
    gap: 6px;
    padding: 14px 18px;
    background: var(--fk-bg-alt);
    border-bottom: 1px solid var(--fk-border);
}
.fk-app-bar span { width: 10px; height: 10px; border-radius: 50%; background: #E2E4EA; }
.fk-app-body { padding: 30px; }

/* Quote → Invoice mockup */
.fk-mock-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--fk-bg-alt);
    border-radius: var(--fk-radius-sm);
    padding: 18px 20px;
}
.fk-mock-doc-num { font-size: 13px; color: var(--fk-muted); margin: 0 0 4px; font-weight: 600; }
.fk-mock-doc-client { font-size: 16px; font-weight: 700; margin: 0; }
.fk-mock-total { font-size: 15px; font-weight: 700; color: var(--fk-ink); }
.fk-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 100px;
    font-size: 12px;
    font-weight: 700;
}
.fk-badge--accepted { background: #ECFDF5; color: #15803D; }
.fk-badge--sent { background: var(--fk-pink-soft); color: var(--fk-pink); }
.fk-mock-arrow {
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: center;
    padding: 18px 0;
    color: var(--fk-muted);
    font-size: 13.5px;
    font-weight: 600;
}
.fk-mock-arrow svg { color: var(--fk-pink); }

/* Clients / services mockup */
.fk-mock-clients { display: grid; gap: 12px; margin-bottom: 20px; }
.fk-mock-client {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: var(--fk-radius-sm);
    background: var(--fk-bg-alt);
}
.fk-mock-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--fk-pink);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
}
.fk-mock-client-name { font-size: 14.5px; font-weight: 600; margin: 0; }
.fk-mock-client-sub { font-size: 12.5px; color: var(--fk-muted); margin: 0; }
.fk-mock-pay {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #ECFDF5;
    border-radius: var(--fk-radius-sm);
    padding: 16px 18px;
}
.fk-mock-pay-check {
    width: 30px; height: 30px; border-radius: 50%;
    background: #16A34A; color: #fff;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 14px;
}
.fk-mock-pay p { margin: 0; font-size: 14px; font-weight: 600; color: #166534; }

/* Reports mockup */
.fk-mock-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px; }
.fk-mock-stat { background: var(--fk-bg-alt); border-radius: var(--fk-radius-sm); padding: 16px 18px; }
.fk-mock-stat-label { font-size: 12px; color: var(--fk-muted); margin: 0 0 6px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
.fk-mock-stat-value { font-size: 21px; font-weight: 700; margin: 0; }
.fk-mock-chart {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    height: 120px;
    padding: 0 4px;
}
.fk-mock-bar { flex: 1; background: var(--fk-pink-soft); border-radius: 6px 6px 0 0; position: relative; }
.fk-mock-bar span {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: var(--fk-pink); border-radius: 6px 6px 0 0;
}

/* =========================================================================
   HOW IT WORKS
   ========================================================================= */
.fk-how { padding: 110px 0; background: var(--fk-bg-alt); }
.fk-steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 32px;
    position: relative;
}
.fk-steps:before {
    content: "";
    position: absolute;
    top: 27px;
    left: 12%;
    right: 12%;
    height: 1px;
    background: var(--fk-border);
}
.fk-step { position: relative; text-align: left; }
.fk-step-num {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #fff;
    border: 1.5px solid var(--fk-pink);
    color: var(--fk-pink);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 22px;
    position: relative;
    z-index: 1;
}
.fk-step h3 { font-size: 19px; font-weight: 700; margin: 0 0 8px; }
.fk-step p { font-size: 15px; line-height: 1.6; margin: 0; }
.fk-how-cta { text-align: center; margin-top: 56px; }

/* =========================================================================
   FEATURES GRID
   ========================================================================= */
.fk-features { padding: 110px 0; }
.fk-feature-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1px;
    background: var(--fk-border);
    border: 1px solid var(--fk-border);
    border-radius: var(--fk-radius-md);
    overflow: hidden;
}
.fk-feature-cell {
    background: #fff;
    padding: 30px 26px;
    transition: background .2s ease;
}
.fk-feature-cell:hover { background: var(--fk-pink-soft); }
.fk-feature-icon {
    width: 38px;
    height: 38px;
    color: var(--fk-pink);
    margin-bottom: 16px;
}
.fk-feature-cell h3 { font-size: 16px; font-weight: 700; margin: 0 0 6px; }
.fk-feature-cell p { font-size: 13.5px; line-height: 1.55; margin: 0; }

/* =========================================================================
   VALUE / PRODUCTIVITY
   ========================================================================= */
.fk-value { padding: 110px 0; background: var(--fk-bg-alt); }
.fk-value-inner { text-align: center; max-width: 620px; margin: 0 auto 64px; }
.fk-value-inner h2 { font-size: clamp(28px, 3.6vw, 40px); font-weight: 700; margin: 0 0 16px; }
.fk-value-inner p { font-size: 17px; line-height: 1.65; margin: 0; }
.fk-value-visual { position: relative; max-width: 920px; margin: 0 auto; }
.fk-value-visual .fk-app-card { text-align: left; }
.fk-value-chip {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border-radius: 100px;
    padding: 10px 18px 10px 12px;
    box-shadow: var(--fk-shadow-md);
    font-size: 13px;
    font-weight: 600;
}
.fk-value-chip .fk-dot { width: 8px; height: 8px; border-radius: 50%; background: #16A34A; flex-shrink: 0; }
.fk-value-chip--1 { top: -6%; left: -4%; }
.fk-value-chip--2 { top: 34%; right: -6%; }
.fk-value-chip--3 { bottom: -6%; left: 18%; }
@media (max-width: 767px) { .fk-value-chip { display: none; } }

.fk-value-dash-grid { display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px; }
.fk-value-dash-list { display: grid; gap: 10px; }
.fk-value-dash-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: var(--fk-bg-alt);
    border-radius: 10px;
    font-size: 13.5px;
}
.fk-value-dash-row strong { font-weight: 700; }

/* =========================================================================
   PRICING PREVIEW
   ========================================================================= */
.fk-pricing { padding: 110px 0; }
.fk-pricing-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    max-width: 1020px;
    margin: 0 auto 44px;
    align-items: stretch;
}
.fk-plan-card {
    background: #fff;
    border: 1.5px solid var(--fk-border);
    border-radius: var(--fk-radius-md);
    padding: 32px 28px;
    display: flex;
    flex-direction: column;
    position: relative;
    transition: transform .25s ease, box-shadow .25s ease;
}
.fk-plan-card:hover { transform: translateY(-4px); box-shadow: var(--fk-shadow-md); }
.fk-plan-card--featured { border-color: var(--fk-pink); box-shadow: 0 16px 44px rgba(233,30,99,.14); }
.fk-plan-badge {
    position: absolute;
    top: -13px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--fk-pink);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: 5px 14px;
    border-radius: 100px;
}
.fk-plan-name { font-size: 18px; font-weight: 700; margin: 0 0 6px; }
.fk-plan-desc { font-size: 13.5px; margin: 0 0 20px; min-height: 36px; }
.fk-plan-price { display: flex; align-items: baseline; gap: 4px; margin-bottom: 4px; }
.fk-plan-price .amount { font-size: 34px; font-weight: 700; color: var(--fk-ink); }
.fk-plan-price .period { font-size: 14px; color: var(--fk-muted); }
.fk-plan-trial { font-size: 12.5px; color: var(--fk-muted); margin: 0 0 24px; }
.fk-plan-items { list-style: none; margin: 0 0 28px; padding: 0; display: grid; gap: 10px; flex: 1; }
.fk-plan-items li { display: flex; align-items: flex-start; gap: 8px; font-size: 13.5px; color: var(--fk-ink); }
.fk-plan-items svg { flex-shrink: 0; color: var(--fk-pink); margin-top: 3px; }
.fk-plan-btn {
    display: block;
    text-align: center;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14.5px;
    font-weight: 600;
    border: 1.5px solid var(--fk-border);
    color: var(--fk-ink) !important;
    transition: all .18s ease;
}
.fk-plan-btn:hover { border-color: var(--fk-ink); }
.fk-plan-btn--primary { background: var(--fk-pink); border-color: var(--fk-pink); color: #fff !important; }
.fk-plan-btn--primary:hover { background: var(--fk-pink-dark); border-color: var(--fk-pink-dark); }
.fk-pricing-foot { text-align: center; }

/* =========================================================================
   FAQ
   ========================================================================= */
.fk-faq { padding: 110px 0; background: var(--fk-bg-alt); }
.fk-faq-list { max-width: 780px; margin: 0 auto; display: grid; gap: 12px; }
.fk-faq-item {
    background: #fff;
    border: 1px solid var(--fk-border);
    border-radius: var(--fk-radius-sm);
    overflow: hidden;
}
.fk-faq-q {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    background: none;
    border: none;
    text-align: left;
    padding: 20px 24px;
    font-size: 16px;
    font-weight: 600;
    color: var(--fk-ink);
    cursor: pointer;
}
.fk-faq-q svg { flex-shrink: 0; color: var(--fk-pink); transition: transform .25s ease; }
.fk-faq-item[data-open="true"] .fk-faq-q svg { transform: rotate(45deg); }
.fk-faq-a {
    max-height: 0;
    overflow: hidden;
    transition: max-height .3s ease;
}
.fk-faq-item[data-open="true"] .fk-faq-a { max-height: 240px; }
.fk-faq-a p { margin: 0 24px 22px; font-size: 15px; line-height: 1.65; }
@media (prefers-reduced-motion: reduce) {
    .fk-faq-a, .fk-faq-q svg { transition: none; }
}

/* =========================================================================
   RESPONSIVE
   ========================================================================= */
@media (max-width: 991px) {
    .fk-hero { padding: 150px 0 90px; }
    .fk-benefits { grid-template-columns: 1fr; }
    .fk-show-row { grid-template-columns: 1fr; gap: 40px; padding: 60px 0; }
    .fk-show-row--rev .fk-show-text,
    .fk-show-row--rev .fk-show-visual { order: initial; }
    .fk-steps { grid-template-columns: 1fr; gap: 36px; }
    .fk-steps:before { display: none; }
    .fk-feature-grid { grid-template-columns: repeat(2, 1fr); }
    .fk-value-dash-grid { grid-template-columns: 1fr; }
    .fk-pricing-grid { grid-template-columns: 1fr; max-width: 460px; }
}
@media (max-width: 767px) {
    .fk-hero { padding: 130px 0 70px; }
    .fk-hero-sub { font-size: 17px; }
    .fk-hero-ctas .fk-btn { width: 100%; }
    .fk-hero-ctas { flex-direction: column; }
    .fk-hero-visual { margin-top: 56px; }
    .fk-solution, .fk-how, .fk-features, .fk-value, .fk-pricing, .fk-faq { padding: 72px 0; }
    .fk-feature-grid { grid-template-columns: 1fr; }
    .fk-section-head, .fk-solution-head, .fk-value-inner { margin-bottom: 40px; }
}
</style>

<script>
document.documentElement.classList.add('fk-js');

// The shared site-wide nav-scroll handler (front/assets/js/app.js) animates
// same-page anchor links to their raw offset, ignoring this page's sticky
// header height - it would land section headings partly under the header.
// Intercept in the capture phase (runs before that later-bound handler)
// and let the native anchor jump take over, which respects the
// scroll-margin-top set on these sections below.
document.addEventListener('click', function (e) {
    var link = e.target.closest('a[href*="#funciones"], a[href*="#como-funciona"]');
    if (link) { e.stopImmediatePropagation(); }
}, true);
</script>

<main class="fk-home">

    {{-- ============================ HERO ============================ --}}
    <section class="fk-hero">
        <div class="fk-container fk-hero-inner">
            <span class="fk-kicker fk-reveal">{{ __('site.home.hero_badge') }}</span>
            <h1 class="fk-hero-title fk-reveal">
                <span>{{ __('site.home.hero_title_l1') }}</span>
                <span>{{ __('site.home.hero_title_l2') }}</span>
                <span class="fk-accent">{{ __('site.home.hero_title_l3') }}</span>
            </h1>
            <p class="fk-hero-sub fk-reveal">{{ __('site.home.hero_sub') }}</p>
            <div class="fk-hero-ctas fk-reveal">
                <a href="{{ url('/free-trial') }}" class="fk-btn fk-btn-primary fk-btn-lg">{{ __('site.nav.cta') }}</a>
                <a href="{{ url('/') }}#como-funciona" class="fk-btn fk-btn-secondary fk-btn-lg">{{ __('site.home.hero_cta_secondary') }}</a>
            </div>
            <p class="fk-hero-micro fk-reveal">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ __('site.home.hero_microcopy') }}
            </p>
        </div>

        <div class="fk-container">
            <div class="fk-hero-visual fk-reveal">
                <div class="fk-hero-frame">
                    <img
                        src="{{ url('assets/panel_fakturalista.png') }}"
                        alt="{{ __('site.home.hero_image_alt') }}"
                        class="fk-hero-shot"
                        width="1500" height="1125"
                        loading="eager"
                        fetchpriority="high"
                    >
                </div>
                <div class="fk-float-card fk-float-card--paid" aria-hidden="true">
                    <span class="fk-float-check">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    {{ __('site.home.hero_float_paid') }}
                </div>
                <div class="fk-float-card fk-float-card--amount" aria-hidden="true">
                    {{ __('site.home.hero_float_amount') }}
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ SOLUTION / BENEFITS ============================ --}}
    <section class="fk-solution" id="beneficios">
        <div class="fk-container">
            <div class="fk-solution-head fk-reveal">
                <span class="fk-kicker">{{ __('site.home.solution_kicker') }}</span>
                <h2>
                    {{ __('site.home.solution_title_l1') }}<br>
                    {{ __('site.home.solution_title_l2') }}
                </h2>
                <p>{{ __('site.home.solution_text') }}</p>
            </div>

            <div class="fk-benefits">
                <div class="fk-benefit-card fk-reveal">
                    <div class="fk-benefit-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    </div>
                    <h3>{{ __('site.home.benefit_1_title') }}</h3>
                    <p>{{ __('site.home.benefit_1_text') }}</p>
                </div>
                <div class="fk-benefit-card fk-reveal">
                    <div class="fk-benefit-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-3.13a4 4 0 100-8 4 4 0 000 8zm6 1a4 4 0 00-2-7.46" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h3>{{ __('site.home.benefit_2_title') }}</h3>
                    <p>{{ __('site.home.benefit_2_text') }}</p>
                </div>
                <div class="fk-benefit-card fk-reveal">
                    <div class="fk-benefit-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h3>{{ __('site.home.benefit_3_title') }}</h3>
                    <p>{{ __('site.home.benefit_3_text') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ PRODUCT SHOWCASE ============================ --}}
    <section class="fk-showcase" id="funciones">
        <div class="fk-container">

            {{-- 1. Facturas — real screenshot --}}
            <div class="fk-show-row">
                <div class="fk-show-text fk-reveal">
                    <h3>{{ __('site.home.show1_title') }}</h3>
                    <p>{{ __('site.home.show1_text') }}</p>
                    <ul class="fk-show-list">
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show1_bullet_1') }}</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show1_bullet_2') }}</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show1_bullet_3') }}</li>
                    </ul>
                </div>
                <div class="fk-show-visual fk-reveal">
                    <div class="fk-shot-frame">
                        <img src="{{ url('assets/panel_fakturalista.png') }}" alt="{{ __('site.home.show1_image_alt') }}" loading="lazy" width="1500" height="1125">
                    </div>
                </div>
            </div>

            {{-- 2. Presupuestos — recreated UI --}}
            <div class="fk-show-row fk-show-row--rev">
                <div class="fk-show-text fk-reveal">
                    <h3>{{ __('site.home.show2_title') }}</h3>
                    <p>{{ __('site.home.show2_text') }}</p>
                    <ul class="fk-show-list">
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show2_bullet_1') }}</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show2_bullet_2') }}</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show2_bullet_3') }}</li>
                    </ul>
                </div>
                <div class="fk-show-visual fk-reveal">
                    <div class="fk-app-card" role="img" aria-label="{{ __('site.home.show2_image_alt') }}">
                        <div class="fk-app-bar"><span></span><span></span><span></span></div>
                        <div class="fk-app-body">
                            <div class="fk-mock-row">
                                <div>
                                    <p class="fk-mock-doc-num">Presupuesto #0032</p>
                                    <p class="fk-mock-doc-client">Estudio Creativo S.L.</p>
                                </div>
                                <span class="fk-badge fk-badge--accepted">Aceptado</span>
                            </div>
                            <div class="fk-mock-arrow">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div class="fk-mock-row">
                                <div>
                                    <p class="fk-mock-doc-num">Factura #0045</p>
                                    <p class="fk-mock-doc-client">Estudio Creativo S.L.</p>
                                </div>
                                <span class="fk-badge fk-badge--sent">Enviada</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Clientes / pagos / servicios — recreated UI --}}
            <div class="fk-show-row">
                <div class="fk-show-text fk-reveal">
                    <h3>{{ __('site.home.show3_title') }}</h3>
                    <p>{{ __('site.home.show3_text') }}</p>
                    <ul class="fk-show-list">
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show3_bullet_1') }}</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show3_bullet_2') }}</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show3_bullet_3') }}</li>
                    </ul>
                </div>
                <div class="fk-show-visual fk-reveal">
                    <div class="fk-app-card" role="img" aria-label="{{ __('site.home.show3_image_alt') }}">
                        <div class="fk-app-bar"><span></span><span></span><span></span></div>
                        <div class="fk-app-body">
                            <div class="fk-mock-clients">
                                <div class="fk-mock-client">
                                    <span class="fk-mock-avatar">MG</span>
                                    <div>
                                        <p class="fk-mock-client-name">María García</p>
                                        <p class="fk-mock-client-sub">8 facturas</p>
                                    </div>
                                </div>
                                <div class="fk-mock-client">
                                    <span class="fk-mock-avatar">TC</span>
                                    <div>
                                        <p class="fk-mock-client-name">Taller Creativo S.L.</p>
                                        <p class="fk-mock-client-sub">3 presupuestos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="fk-mock-pay">
                                <span class="fk-mock-pay-check">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <p>Pago recibido — Factura #0041</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Informes — recreated UI --}}
            <div class="fk-show-row fk-show-row--rev">
                <div class="fk-show-text fk-reveal">
                    <h3>{{ __('site.home.show4_title') }}</h3>
                    <p>{{ __('site.home.show4_text') }}</p>
                    <ul class="fk-show-list">
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show4_bullet_1') }}</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show4_bullet_2') }}</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('site.home.show4_bullet_3') }}</li>
                    </ul>
                </div>
                <div class="fk-show-visual fk-reveal">
                    <div class="fk-app-card" role="img" aria-label="{{ __('site.home.show4_image_alt') }}">
                        <div class="fk-app-bar"><span></span><span></span><span></span></div>
                        <div class="fk-app-body">
                            <div class="fk-mock-stats">
                                <div class="fk-mock-stat">
                                    <p class="fk-mock-stat-label">Cobrado este mes</p>
                                    <p class="fk-mock-stat-value">3.240,00&nbsp;€</p>
                                </div>
                                <div class="fk-mock-stat">
                                    <p class="fk-mock-stat-label">Pendiente</p>
                                    <p class="fk-mock-stat-value">860,00&nbsp;€</p>
                                </div>
                            </div>
                            <div class="fk-mock-chart" aria-hidden="true">
                                <div class="fk-mock-bar" style="height:100%"><span style="height:55%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:70%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:40%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:85%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:65%"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- ============================ HOW IT WORKS ============================ --}}
    <section class="fk-how" id="como-funciona">
        <div class="fk-container">
            <div class="fk-section-head fk-reveal">
                <h2>{{ __('site.home.how_title') }}</h2>
            </div>
            <div class="fk-steps">
                <div class="fk-step fk-reveal">
                    <div class="fk-step-num">01</div>
                    <h3>{{ __('site.home.how_step1_title') }}</h3>
                    <p>{{ __('site.home.how_step1_text') }}</p>
                </div>
                <div class="fk-step fk-reveal">
                    <div class="fk-step-num">02</div>
                    <h3>{{ __('site.home.how_step2_title') }}</h3>
                    <p>{{ __('site.home.how_step2_text') }}</p>
                </div>
                <div class="fk-step fk-reveal">
                    <div class="fk-step-num">03</div>
                    <h3>{{ __('site.home.how_step3_title') }}</h3>
                    <p>{{ __('site.home.how_step3_text') }}</p>
                </div>
            </div>
            <div class="fk-how-cta fk-reveal">
                <a href="{{ url('/free-trial') }}" class="fk-btn fk-btn-primary fk-btn-lg">{{ __('site.nav.cta') }}</a>
            </div>
        </div>
    </section>

    {{-- ============================ FEATURES GRID ============================ --}}
    <section class="fk-features">
        <div class="fk-container">
            <div class="fk-section-head fk-reveal">
                <h2>{{ __('site.home.features_title') }}</h2>
            </div>
            <div class="fk-feature-grid fk-reveal">
                <div class="fk-feature-cell">
                    <svg class="fk-feature-icon" viewBox="0 0 24 24" fill="none"><path d="M7 3h10a1 1 0 011 1v16l-3-2-2 2-2-2-2 2-3-2V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 8h6M9 12h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <h3>{{ __('site.home.feature_invoices_title') }}</h3>
                    <p>{{ __('site.home.feature_invoices_text') }}</p>
                </div>
                <div class="fk-feature-cell">
                    <svg class="fk-feature-icon" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 3h10a1 1 0 011 1v16l-3-2-2 2-2-2-2 2-3-2V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    <h3>{{ __('site.home.feature_quotes_title') }}</h3>
                    <p>{{ __('site.home.feature_quotes_text') }}</p>
                </div>
                <div class="fk-feature-cell">
                    <svg class="fk-feature-icon" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M5 20a7 7 0 0114 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <h3>{{ __('site.home.feature_clients_title') }}</h3>
                    <p>{{ __('site.home.feature_clients_text') }}</p>
                </div>
                <div class="fk-feature-cell">
                    <svg class="fk-feature-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 10h18" stroke="currentColor" stroke-width="1.6"/></svg>
                    <h3>{{ __('site.home.feature_payments_title') }}</h3>
                    <p>{{ __('site.home.feature_payments_text') }}</p>
                </div>
                <div class="fk-feature-cell">
                    <svg class="fk-feature-icon" viewBox="0 0 24 24" fill="none"><path d="M20.5 12.5l-8 4.5-8-4.5V7l8-4.5L20.5 7v5.5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M4.5 7l8 4.5 8-4.5M12.5 11.5V21" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    <h3>{{ __('site.home.feature_services_title') }}</h3>
                    <p>{{ __('site.home.feature_services_text') }}</p>
                </div>
                <div class="fk-feature-cell">
                    <svg class="fk-feature-icon" viewBox="0 0 24 24" fill="none"><path d="M4 19V9m6 10V5m6 14v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <h3>{{ __('site.home.feature_reports_title') }}</h3>
                    <p>{{ __('site.home.feature_reports_text') }}</p>
                </div>
                <div class="fk-feature-cell">
                    <svg class="fk-feature-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 9h18M8 4v5" stroke="currentColor" stroke-width="1.6"/></svg>
                    <h3>{{ __('site.home.feature_templates_title') }}</h3>
                    <p>{{ __('site.home.feature_templates_text') }}</p>
                </div>
                <div class="fk-feature-cell">
                    <svg class="fk-feature-icon" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M3 12h18M12 3a14 14 0 010 18 14 14 0 010-18z" stroke="currentColor" stroke-width="1.6"/></svg>
                    <h3>{{ __('site.home.feature_multilang_title') }}</h3>
                    <p>{{ __('site.home.feature_multilang_text') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ VALUE / PRODUCTIVITY ============================ --}}
    <section class="fk-value">
        <div class="fk-container">
            <div class="fk-value-inner fk-reveal">
                <h2>{{ __('site.home.value_title') }}</h2>
                <p>{{ __('site.home.value_text') }}</p>
            </div>
            <div class="fk-value-visual fk-reveal">
                <div class="fk-app-card">
                    <div class="fk-app-bar"><span></span><span></span><span></span></div>
                    <div class="fk-app-body">
                        <div class="fk-value-dash-grid">
                            <div class="fk-mock-chart" aria-hidden="true" style="height:150px;">
                                <div class="fk-mock-bar" style="height:100%"><span style="height:35%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:60%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:48%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:78%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:66%"></span></div>
                                <div class="fk-mock-bar" style="height:100%"><span style="height:90%"></span></div>
                            </div>
                            <div class="fk-value-dash-list">
                                <div class="fk-value-dash-row"><span>Facturas</span><strong>24</strong></div>
                                <div class="fk-value-dash-row"><span>Clientes</span><strong>12</strong></div>
                                <div class="fk-value-dash-row"><span>Presupuestos</span><strong>6</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fk-value-chip fk-value-chip--1"><span class="fk-dot"></span>{{ __('site.home.value_chip_1') }}</div>
                <div class="fk-value-chip fk-value-chip--2"><span class="fk-dot"></span>{{ __('site.home.value_chip_2') }}</div>
                <div class="fk-value-chip fk-value-chip--3"><span class="fk-dot"></span>{{ __('site.home.value_chip_3') }}</div>
            </div>
        </div>
    </section>

    {{-- ============================ PRICING PREVIEW ============================ --}}
    <section class="fk-pricing">
        <div class="fk-container">
            <div class="fk-section-head fk-reveal">
                <h2>{{ __('site.home.pricing_title') }}</h2>
                <p>{{ __('site.home.pricing_text') }}</p>
            </div>

            <div class="fk-pricing-grid fk-reveal">
                @foreach ($plans as $plan)
                    @php
                        $planName  = $plan->translate('name', $locale);
                        $planDesc  = $plan->translate('short_description', $locale);
                        $planBadge = $plan->translate('badge', $locale);
                        $items     = $plan->marketingItems->take(4);
                    @endphp
                    <div class="fk-plan-card {{ $plan->is_featured ? 'fk-plan-card--featured' : '' }}">
                        @if ($planBadge)
                            <span class="fk-plan-badge">{{ $planBadge }}</span>
                        @endif
                        <p class="fk-plan-name">{{ $planName }}</p>
                        <p class="fk-plan-desc">{{ $planDesc }}</p>
                        <div class="fk-plan-price">
                            <span class="amount">{{ $plan->formattedPrice() }}&nbsp;€</span>
                            <span class="period">{{ __('site.pricing.period') }}</span>
                        </div>
                        @if ($plan->trial_days)
                            <p class="fk-plan-trial">{{ __('site.home.pricing_trial_note') }}</p>
                        @endif
                        <ul class="fk-plan-items">
                            @foreach ($items as $item)
                                <li>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $item->text($locale) }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ url('/free-trial') }}" class="fk-plan-btn {{ $plan->is_featured ? 'fk-plan-btn--primary' : '' }}">
                            {{ __('site.nav.cta') }}
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="fk-pricing-foot fk-reveal">
                <a href="{{ url('/pricing') }}" class="fk-btn fk-btn-secondary">{{ __('site.home.pricing_cta_secondary') }}</a>
            </div>
        </div>
    </section>

    {{-- ============================ FAQ ============================ --}}
    <section class="fk-faq">
        <div class="fk-container">
            <div class="fk-section-head fk-reveal">
                <h2>{{ __('site.home.faq_title') }}</h2>
            </div>
            <div class="fk-faq-list fk-reveal" id="fk-faq-list">
                @foreach ([1,2,3,4,5,6] as $i)
                    <div class="fk-faq-item" data-open="false">
                        <button type="button" class="fk-faq-q" aria-expanded="false">
                            <span>{{ __('site.home.faq_q' . $i) }}</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        </button>
                        <div class="fk-faq-a">
                            <p>{{ __('site.home.faq_a' . $i) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

</main>

<script>
(function () {
    // Reveal-on-scroll
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var revealEls = document.querySelectorAll('.fk-reveal');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealEls.forEach(function (el) { el.classList.add('fk-in'); });
    } else {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fk-in');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        revealEls.forEach(function (el) { observer.observe(el); });

        // Safety net: never leave content permanently hidden (e.g. a
        // renderer that doesn't fire real scroll/intersection events).
        setTimeout(function () {
            revealEls.forEach(function (el) { el.classList.add('fk-in'); });
        }, 4000);
    }

    // FAQ accordion
    var faqList = document.getElementById('fk-faq-list');
    if (faqList) {
        faqList.addEventListener('click', function (e) {
            var btn = e.target.closest('.fk-faq-q');
            if (!btn) return;
            var item = btn.closest('.fk-faq-item');
            var isOpen = item.getAttribute('data-open') === 'true';

            faqList.querySelectorAll('.fk-faq-item').forEach(function (el) {
                el.setAttribute('data-open', 'false');
                el.querySelector('.fk-faq-q').setAttribute('aria-expanded', 'false');
            });

            if (!isOpen) {
                item.setAttribute('data-open', 'true');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    }
})();
</script>

@endsection
