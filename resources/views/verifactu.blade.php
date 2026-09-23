@extends('layouts.master')

@section('title', __('site.verifactu.meta_title'))

@section('meta')
<meta name="description" content="{{ __('site.verifactu.meta_description') }}" />
<meta name="keywords" content="VERI*FACTU, VeriFactu autónomos, software facturación VeriFactu, facturación electrónica España" />
<meta property="og:title" content="{{ __('site.verifactu.meta_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.verifactu.meta_description') }}" />
<meta property="og:image" content="{{ url('assets/panel_fakturalista.png') }}" />
<meta property="og:url" content="{{ url('/verifactu') }}" />
<meta property="og:type" content="article" />
<meta property="og:site_name" content="Fakturalista" />
<meta name="twitter:card" content="summary_large_image" />
<link rel="canonical" href="{{ url('/verifactu') }}" />
@php
    $faqItems = collect(range(1, 6))->map(fn ($i) => [
        '@type' => 'Question',
        'name' => __('site.verifactu.faq_q' . $i),
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => __('site.verifactu.faq_a' . $i),
        ],
    ])->values()->all();

    $faqJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqItems,
    ];
@endphp
<script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@section('content')

<style>
/* =========================================================================
   Fakturalista — /verifactu page
   Same design system/tokens as the redesigned Home page, scoped under
   .fk-home so the two pages stay visually consistent without a shared
   stylesheet (matching this project's existing per-page <style> pattern).
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
.site-header .site-main-menu li > a { color: #0F172A; }
.site-header.pix-header-fixed {
    background: rgba(255, 255, 255, 0.82);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    backdrop-filter: blur(14px) saturate(160%);
    box-shadow: 0 1px 0 rgba(15, 23, 42, .06), 0 12px 30px rgba(15, 23, 42, .05);
}
.fk-home h1, .fk-home h2, .fk-home h3 { font-family: 'Poppins', system-ui, sans-serif; color: var(--fk-ink); letter-spacing: -0.02em; }
.fk-home p { color: var(--fk-muted); }
.fk-home a { text-decoration: none; }
.fk-home section { position: relative; }

.fk-container { max-width: 1180px; margin: 0 auto; padding: 0 24px; }

.fk-reveal { opacity: 1; transform: none; }
.fk-js .fk-reveal {
    opacity: 0;
    transform: translateY(22px);
    transition: opacity .7s cubic-bezier(.16,.8,.3,1), transform .7s cubic-bezier(.16,.8,.3,1);
}
.fk-js .fk-reveal.fk-in { opacity: 1; transform: translateY(0); }
@media (prefers-reduced-motion: reduce) {
    .fk-js .fk-reveal { opacity: 1; transform: none; transition: none; }
}

.fk-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 28px; border-radius: 12px; font-size: 15px; font-weight: 600;
    line-height: 1; white-space: nowrap; border: 1.5px solid transparent;
    transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease, color .18s ease;
}
.fk-btn-primary { background: var(--fk-pink); color: #fff !important; box-shadow: 0 10px 24px rgba(233,30,99,.22); }
.fk-btn-primary:hover { background: var(--fk-pink-dark); transform: translateY(-2px); box-shadow: 0 14px 32px rgba(233,30,99,.3); }
.fk-btn-lg { padding: 16px 32px; font-size: 16px; }
@media (prefers-reduced-motion: reduce) { .fk-btn:hover { transform: none; } }

.fk-kicker {
    display: inline-flex; align-items: center; background: var(--fk-pink-soft); color: var(--fk-pink);
    border: 1px solid var(--fk-pink-border); border-radius: 100px; padding: 6px 16px;
    font-size: 12.5px; font-weight: 600; letter-spacing: .02em; margin-bottom: 20px;
}
.fk-section-head { max-width: 680px; margin: 0 auto 52px; text-align: center; }
.fk-section-head h2 { font-size: clamp(26px, 3.4vw, 38px); font-weight: 700; line-height: 1.2; margin: 0 0 14px; }
.fk-section-head p { font-size: 16.5px; line-height: 1.6; margin: 0; }

/* ── Hero ── */
.fk-vf-hero {
    padding: 168px 0 90px;
    text-align: center;
    background: radial-gradient(55% 60% at 50% 0%, rgba(233,30,99,.06), transparent 70%), #fff;
}
.fk-vf-hero-inner { max-width: 780px; margin: 0 auto; }
.fk-vf-h1 { font-size: clamp(32px, 4.6vw, 48px); font-weight: 700; line-height: 1.18; margin: 0 0 22px; }
.fk-vf-hero-intro { font-size: 18px; line-height: 1.65; max-width: 620px; margin: 0 auto; }

/* ── What is VERI*FACTU ── */
.fk-vf-what { padding: 90px 0; }
.fk-vf-what-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 64px; align-items: center; }
.fk-vf-what-text h2 { font-size: clamp(24px, 3vw, 32px); font-weight: 700; margin: 0 0 20px; }
.fk-vf-what-text p { font-size: 16px; line-height: 1.75; margin: 0 0 16px; }

.fk-vf-flow { display: grid; gap: 0; position: relative; padding-left: 8px; }
.fk-vf-flow-step { display: flex; align-items: center; gap: 16px; padding: 8px 0; }
.fk-vf-flow-icon {
    width: 52px; height: 52px; border-radius: 14px; background: #fff; border: 1.5px solid var(--fk-border);
    color: var(--fk-pink); display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    box-shadow: var(--fk-shadow-sm); position: relative; z-index: 1;
}
.fk-vf-flow-step--aeat .fk-vf-flow-icon { background: var(--fk-ink); border-color: var(--fk-ink); color: #fff; }
.fk-vf-flow-label { font-size: 15px; font-weight: 700; color: var(--fk-ink); margin: 0; }
.fk-vf-flow-sub { font-size: 12.5px; color: var(--fk-muted); margin: 2px 0 0; }
.fk-vf-flow-connector { width: 1.5px; height: 28px; background: var(--fk-border); margin-left: 26px; }

/* ── Why is it changing (concept cards) ── */
.fk-vf-why { padding: 90px 0; background: var(--fk-bg-alt); }
.fk-vf-why-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; }
.fk-vf-why-card {
    background: #fff; border: 1px solid var(--fk-border); border-radius: var(--fk-radius-md);
    padding: 26px 22px; transition: transform .22s ease, box-shadow .22s ease;
}
.fk-vf-why-card:hover { transform: translateY(-4px); box-shadow: var(--fk-shadow-md); }
.fk-vf-why-icon { width: 40px; height: 40px; color: var(--fk-pink); margin-bottom: 14px; }
.fk-vf-why-card h3 { font-size: 15.5px; font-weight: 700; margin: 0 0 8px; }
.fk-vf-why-card p { font-size: 13.5px; line-height: 1.55; margin: 0; }

/* ── Dates timeline ── */
.fk-vf-dates { padding: 90px 0; }
.fk-vf-timeline { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; max-width: 900px; margin: 0 auto; position: relative; }
.fk-vf-timeline:before { content: ""; position: absolute; top: 27px; left: 10%; right: 10%; height: 1px; background: var(--fk-border); }
.fk-vf-milestone { position: relative; background: #fff; border: 1px solid var(--fk-border); border-radius: var(--fk-radius-md); padding: 28px 26px; }
.fk-vf-milestone-dot {
    width: 56px; height: 56px; border-radius: 50%; background: #fff; border: 1.5px solid var(--fk-pink); color: var(--fk-pink);
    display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; text-align: center;
    margin-bottom: 20px; position: relative; z-index: 1; line-height: 1.2;
}
.fk-vf-milestone h3 { font-size: 16.5px; font-weight: 700; margin: 0 0 8px; }
.fk-vf-milestone p { font-size: 14px; line-height: 1.6; margin: 0; }
.fk-vf-dates-note {
    max-width: 780px; margin: 36px auto 0; background: var(--fk-pink-soft); border: 1px solid var(--fk-pink-border);
    border-radius: var(--fk-radius-sm); padding: 16px 20px; font-size: 13.5px; line-height: 1.6; color: var(--fk-ink);
    display: flex; gap: 10px; align-items: flex-start;
}
.fk-vf-dates-note svg { flex-shrink: 0; color: var(--fk-pink); margin-top: 2px; }

/* ── Autónomo: before / after ── */
.fk-vf-auto { padding: 90px 0; background: var(--fk-bg-alt); }
.fk-vf-auto-intro { max-width: 700px; margin: 0 auto 48px; text-align: center; font-size: 16px; line-height: 1.7; }
.fk-vf-compare { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 880px; margin: 0 auto; }
.fk-vf-compare-card { background: #fff; border-radius: var(--fk-radius-md); padding: 30px 28px; border: 1.5px solid var(--fk-border); }
.fk-vf-compare-card--after { border-color: var(--fk-pink); box-shadow: 0 16px 40px rgba(233,30,99,.1); }
.fk-vf-compare-label {
    display: inline-flex; font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--fk-muted); background: var(--fk-bg-alt); border-radius: 100px; padding: 5px 12px; margin-bottom: 16px;
}
.fk-vf-compare-card--after .fk-vf-compare-label { color: var(--fk-pink); background: var(--fk-pink-soft); }
.fk-vf-compare-card p { font-size: 15px; line-height: 1.65; color: var(--fk-ink); margin: 0; }
.fk-vf-auto-note { max-width: 780px; margin: 28px auto 0; text-align: center; font-size: 13.5px; line-height: 1.6; color: var(--fk-muted); }

/* ── Fakturalista + VERI*FACTU ── */
.fk-vf-brand { padding: 90px 0; }
.fk-vf-brand-panel {
    max-width: 880px; margin: 0 auto; text-align: center;
    background: linear-gradient(135deg, #fff 0%, var(--fk-pink-soft) 130%);
    border: 1px solid var(--fk-pink-border); border-radius: var(--fk-radius-lg); padding: 64px 56px;
}
.fk-vf-brand-panel h2 { font-size: clamp(24px, 3.2vw, 34px); font-weight: 700; line-height: 1.25; margin: 0 0 18px; }
.fk-vf-brand-panel p { font-size: 16px; line-height: 1.7; margin: 0 auto 12px; max-width: 620px; }
.fk-vf-brand-quote { font-weight: 600; color: var(--fk-ink) !important; margin-top: 20px !important; }
.fk-vf-status {
    display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--fk-border);
    border-radius: 100px; padding: 8px 16px; font-size: 13px; font-weight: 600; color: var(--fk-ink);
    margin: 26px 0 30px;
}
.fk-vf-status-dot { width: 8px; height: 8px; border-radius: 50%; background: #F59E0B; flex-shrink: 0; }

/* ── FAQ (same pattern as home) ── */
.fk-vf-faq { padding: 90px 0; background: var(--fk-bg-alt); }
.fk-faq-list { max-width: 780px; margin: 0 auto; display: grid; gap: 12px; }
.fk-faq-item { background: #fff; border: 1px solid var(--fk-border); border-radius: var(--fk-radius-sm); overflow: hidden; }
.fk-faq-q {
    width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 16px;
    background: none; border: none; text-align: left; padding: 20px 24px; font-size: 16px; font-weight: 600;
    color: var(--fk-ink); cursor: pointer;
}
.fk-faq-q svg { flex-shrink: 0; color: var(--fk-pink); transition: transform .25s ease; }
.fk-faq-item[data-open="true"] .fk-faq-q svg { transform: rotate(45deg); }
.fk-faq-a { max-height: 0; overflow: hidden; transition: max-height .3s ease; }
.fk-faq-item[data-open="true"] .fk-faq-a { max-height: 280px; }
.fk-faq-a p { margin: 0 24px 22px; font-size: 15px; line-height: 1.65; }
@media (prefers-reduced-motion: reduce) { .fk-faq-a, .fk-faq-q svg { transition: none; } }

/* ── Official sources ── */
.fk-vf-sources { padding: 80px 0 110px; }
.fk-vf-sources-inner { max-width: 780px; margin: 0 auto; }
.fk-vf-sources h2 { font-size: 22px; font-weight: 700; margin: 0 0 8px; }
.fk-vf-sources-note { font-size: 13.5px; margin: 0 0 24px; }
.fk-vf-sources-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.fk-vf-sources-list a {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    background: var(--fk-bg-alt); border: 1px solid var(--fk-border); border-radius: var(--fk-radius-sm);
    padding: 16px 20px; font-size: 14.5px; font-weight: 600; color: var(--fk-ink) !important;
    transition: border-color .18s ease, background .18s ease;
}
.fk-vf-sources-list a:hover { border-color: var(--fk-pink); background: #fff; }
.fk-vf-sources-list svg { flex-shrink: 0; color: var(--fk-muted); }

/* ── Responsive ── */
@media (max-width: 991px) {
    .fk-vf-hero { padding: 140px 0 70px; }
    .fk-vf-what-grid { grid-template-columns: 1fr; gap: 40px; }
    .fk-vf-why-grid { grid-template-columns: repeat(2, 1fr); }
    .fk-vf-timeline { grid-template-columns: 1fr; gap: 20px; }
    .fk-vf-timeline:before { display: none; }
    .fk-vf-compare { grid-template-columns: 1fr; }
    .fk-vf-brand-panel { padding: 44px 28px; }
}
@media (max-width: 767px) {
    .fk-vf-hero, .fk-vf-what, .fk-vf-why, .fk-vf-dates, .fk-vf-auto, .fk-vf-brand, .fk-vf-faq { padding: 64px 0; }
    .fk-vf-sources { padding: 56px 0 80px; }
    .fk-vf-why-grid { grid-template-columns: 1fr; }
    .fk-section-head { margin-bottom: 36px; }
}
</style>

<script>document.documentElement.classList.add('fk-js');</script>

<main class="fk-home">

    {{-- ============================ HERO ============================ --}}
    <section class="fk-vf-hero">
        <div class="fk-container fk-vf-hero-inner">
            <span class="fk-kicker fk-reveal">{{ __('site.verifactu.hero_badge') }}</span>
            <h1 class="fk-vf-h1 fk-reveal">{{ __('site.verifactu.hero_title') }}</h1>
            <p class="fk-vf-hero-intro fk-reveal">{{ __('site.verifactu.hero_intro') }}</p>
        </div>
    </section>

    {{-- ============================ WHAT IS VERI*FACTU ============================ --}}
    <section class="fk-vf-what">
        <div class="fk-container fk-vf-what-grid">
            <div class="fk-vf-what-text fk-reveal">
                <h2>{{ __('site.verifactu.what_title') }}</h2>
                <p>{{ __('site.verifactu.what_p1') }}</p>
                <p>{{ __('site.verifactu.what_p2') }}</p>
                <p>{{ __('site.verifactu.what_p3') }}</p>
            </div>
            <div class="fk-vf-flow fk-reveal" role="img" aria-label="{{ __('site.verifactu.flow_aria_label') }}">
                <div class="fk-vf-flow-step">
                    <div class="fk-vf-flow-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M7 3h10a1 1 0 011 1v16l-3-2-2 2-2-2-2 2-3-2V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <p class="fk-vf-flow-label">{{ __('site.verifactu.flow_invoice_label') }}</p>
                        <p class="fk-vf-flow-sub">{{ __('site.verifactu.flow_invoice_sub') }}</p>
                    </div>
                </div>
                <div class="fk-vf-flow-connector"></div>
                <div class="fk-vf-flow-step">
                    <div class="fk-vf-flow-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <p class="fk-vf-flow-label">{{ __('site.verifactu.flow_record_label') }}</p>
                        <p class="fk-vf-flow-sub">{{ __('site.verifactu.flow_record_sub') }}</p>
                    </div>
                </div>
                <div class="fk-vf-flow-connector"></div>
                <div class="fk-vf-flow-step fk-vf-flow-step--aeat">
                    <div class="fk-vf-flow-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <p class="fk-vf-flow-label">{{ __('site.verifactu.flow_aeat_label') }}</p>
                        <p class="fk-vf-flow-sub">{{ __('site.verifactu.flow_aeat_sub') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ WHY IS IT CHANGING ============================ --}}
    <section class="fk-vf-why">
        <div class="fk-container">
            <div class="fk-section-head fk-reveal">
                <h2>{{ __('site.verifactu.why_title') }}</h2>
                <p>{{ __('site.verifactu.why_intro') }}</p>
            </div>
            <div class="fk-vf-why-grid fk-reveal">
                <div class="fk-vf-why-card">
                    <svg class="fk-vf-why-icon" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <h3>{{ __('site.verifactu.why_1_title') }}</h3>
                    <p>{{ __('site.verifactu.why_1_text') }}</p>
                </div>
                <div class="fk-vf-why-card">
                    <svg class="fk-vf-why-icon" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <h3>{{ __('site.verifactu.why_2_title') }}</h3>
                    <p>{{ __('site.verifactu.why_2_text') }}</p>
                </div>
                <div class="fk-vf-why-card">
                    <svg class="fk-vf-why-icon" viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    <h3>{{ __('site.verifactu.why_3_title') }}</h3>
                    <p>{{ __('site.verifactu.why_3_text') }}</p>
                </div>
                <div class="fk-vf-why-card">
                    <svg class="fk-vf-why-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 9h18M8 4v5" stroke="currentColor" stroke-width="1.6"/></svg>
                    <h3>{{ __('site.verifactu.why_4_title') }}</h3>
                    <p>{{ __('site.verifactu.why_4_text') }}</p>
                </div>
                <div class="fk-vf-why-card">
                    <svg class="fk-vf-why-icon" viewBox="0 0 24 24" fill="none"><path d="M12 15a4 4 0 004-4V6a4 4 0 10-8 0v5a4 4 0 004 4z" stroke="currentColor" stroke-width="1.6"/><path d="M19 11a7 7 0 01-14 0M12 18v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <h3>{{ __('site.verifactu.why_5_title') }}</h3>
                    <p>{{ __('site.verifactu.why_5_text') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ DATES TIMELINE ============================ --}}
    <section class="fk-vf-dates">
        <div class="fk-container">
            <div class="fk-section-head fk-reveal">
                <h2>{{ __('site.verifactu.dates_title') }}</h2>
                <p>{{ __('site.verifactu.dates_intro') }}</p>
            </div>
            <div class="fk-vf-timeline fk-reveal">
                <div class="fk-vf-milestone">
                    <div class="fk-vf-milestone-dot">{!! __('site.verifactu.date_1_badge') !!}</div>
                    <h3>{{ __('site.verifactu.date_1_label') }}</h3>
                    <p>{{ __('site.verifactu.date_1_text') }}</p>
                </div>
                <div class="fk-vf-milestone">
                    <div class="fk-vf-milestone-dot">{!! __('site.verifactu.date_2_badge') !!}</div>
                    <h3>{{ __('site.verifactu.date_2_label') }}</h3>
                    <p>{{ __('site.verifactu.date_2_text') }}</p>
                </div>
            </div>
            <div class="fk-vf-dates-note fk-reveal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5M12 16.5v.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <span>{{ __('site.verifactu.dates_note') }}</span>
            </div>
        </div>
    </section>

    {{-- ============================ WHAT CHANGES FOR AN AUTÓNOMO ============================ --}}
    <section class="fk-vf-auto">
        <div class="fk-container">
            <div class="fk-section-head fk-reveal">
                <h2>{{ __('site.verifactu.auto_title') }}</h2>
            </div>
            <p class="fk-vf-auto-intro fk-reveal">{{ __('site.verifactu.auto_intro') }}</p>
            <div class="fk-vf-compare fk-reveal">
                <div class="fk-vf-compare-card">
                    <span class="fk-vf-compare-label">{{ __('site.verifactu.auto_before_label') }}</span>
                    <p>{{ __('site.verifactu.auto_before_text') }}</p>
                </div>
                <div class="fk-vf-compare-card fk-vf-compare-card--after">
                    <span class="fk-vf-compare-label">{{ __('site.verifactu.auto_after_label') }}</span>
                    <p>{{ __('site.verifactu.auto_after_text') }}</p>
                </div>
            </div>
            <p class="fk-vf-auto-note fk-reveal">{{ __('site.verifactu.auto_note') }}</p>
        </div>
    </section>

    {{-- ============================ FAKTURALISTA + VERI*FACTU ============================ --}}
    <section class="fk-vf-brand">
        <div class="fk-container">
            <div class="fk-vf-brand-panel fk-reveal">
                <h2>{{ __('site.verifactu.brand_title') }}</h2>
                <p>{{ __('site.verifactu.brand_text') }}</p>
                <p class="fk-vf-brand-quote">{{ __('site.verifactu.brand_line_1') }}<br>{{ __('site.verifactu.brand_line_2') }}</p>
                <div>
                    <span class="fk-vf-status">
                        <span class="fk-vf-status-dot"></span>
                        {{ __('site.verifactu.brand_status') }}
                    </span>
                </div>
                <div>
                    <a href="{{ url('/register') }}" class="fk-btn fk-btn-primary fk-btn-lg">{{ __('site.verifactu.cta_final') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ FAQ ============================ --}}
    <section class="fk-vf-faq">
        <div class="fk-container">
            <div class="fk-section-head fk-reveal">
                <h2>{{ __('site.verifactu.faq_title') }}</h2>
            </div>
            <div class="fk-faq-list fk-reveal" id="fk-vf-faq-list">
                @foreach ([1,2,3,4,5,6] as $i)
                    <div class="fk-faq-item" data-open="false">
                        <button type="button" class="fk-faq-q" aria-expanded="false">
                            <span>{{ __('site.verifactu.faq_q' . $i) }}</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        </button>
                        <div class="fk-faq-a">
                            <p>{{ __('site.verifactu.faq_a' . $i) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================ OFFICIAL SOURCES ============================ --}}
    <section class="fk-vf-sources">
        <div class="fk-container fk-vf-sources-inner fk-reveal">
            <h2>{{ __('site.verifactu.sources_title') }}</h2>
            <p class="fk-vf-sources-note">{{ __('site.verifactu.sources_note') }}</p>
            <ul class="fk-vf-sources-list">
                <li>
                    <a href="https://www.agenciatributaria.es" target="_blank" rel="noopener noreferrer">
                        {{ __('site.verifactu.source_aeat') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 17L17 7M9 7h8v8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </li>
                <li>
                    <a href="https://www.boe.es" target="_blank" rel="noopener noreferrer">
                        {{ __('site.verifactu.source_rd1007') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 17L17 7M9 7h8v8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </li>
                <li>
                    <a href="https://www.boe.es" target="_blank" rel="noopener noreferrer">
                        {{ __('site.verifactu.source_orden') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 17L17 7M9 7h8v8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </li>
                <li>
                    <a href="https://www.boe.es" target="_blank" rel="noopener noreferrer">
                        {{ __('site.verifactu.source_rdley15') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 17L17 7M9 7h8v8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </li>
            </ul>
        </div>
    </section>

</main>

<script>
(function () {
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

        setTimeout(function () {
            revealEls.forEach(function (el) { el.classList.add('fk-in'); });
        }, 4000);
    }

    var faqList = document.getElementById('fk-vf-faq-list');
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
