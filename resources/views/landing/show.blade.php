@extends('layouts.master')

{{-- Market landing page (MarketLandingController). Content: resources/lang/{fr,ar}/landing.php --}}

@php
    $lpUrl = fn (string $path) => str_starts_with($path, '/blog') ? url($path) : Seo::url($path, $locale);
    $lpFaq = $content['faq'] ?? [];
    $lpArrow = $locale === 'ar' ? '←' : '→';
    // Amounts like "9 660,00" use a space as thousands separator, which the
    // bidi algorithm would reorder inside Arabic text ("660,00 9"): isolate
    // every number group left-to-right.
    $lpNum = fn (string $text) => preg_replace('/\d[\d\s.,]*\d|\d/u', '<bdi dir="ltr" class="fk-lp-num">$0</bdi>', e($text));
@endphp

@section('title', $content['meta_title'])
@section('html_lang', $page['hreflang'][$locale] ?? $locale)

@section('meta')
@include('partials.seo', [
    'title'       => $content['meta_title'],
    'description' => $content['meta_description'],
    'path'        => $page['path'],
    'locales'     => $page['locales'],
    'hreflang'    => $page['hreflang'],
    'breadcrumbs' => [[$content['nav_label'], $page['path']]],
    'schema'      => array_values(array_filter([
        [
            '@type'      => 'WebPage',
            '@id'        => Seo::canonical($page['path'], $locale) . '#webpage',
            'url'        => Seo::canonical($page['path'], $locale),
            'name'       => $content['meta_title'],
            'inLanguage' => $page['hreflang'][$locale] ?? $locale,
            'isPartOf'   => ['@id' => config('seo.canonical_url') . '/#website'],
            'about'      => ['@id' => config('seo.canonical_url') . '/#software'],
            'publisher'  => ['@id' => Seo::organizationId()],
        ],
        $lpFaq ? Seo::faqPage($lpFaq) : null,
    ])),
])
@endsection

@section('content')
<style>
.fk-lp { --lp-pink: #E91E63; --lp-pink-dark: #C2185B; --lp-ink: #0F172A; --lp-text: #475569; --lp-muted: #64748b; --lp-border: #e2e8f0; --lp-soft: #fdf2f6; --lp-bg: #f8fafc; color: var(--lp-text); font-size: 16px; line-height: 1.7; }
.fk-lp h1, .fk-lp h2, .fk-lp h3 { color: var(--lp-ink); }
.fk-lp-container { max-width: 1080px; margin: 0 auto; padding: 0 20px; }
.fk-lp-narrow { max-width: 820px; }

.fk-lp-hero { padding: 150px 0 64px; background: linear-gradient(180deg, #fff 0%, var(--lp-soft) 100%); }
.fk-lp-crumbs { display: flex; flex-wrap: wrap; gap: 6px; list-style: none; margin: 0 0 22px; padding: 0; font-size: 13.5px; color: var(--lp-muted); }
.fk-lp-crumbs li + li::before { content: "/"; margin-inline-end: 6px; color: #cbd5e1; }
.fk-lp-crumbs a { color: var(--lp-muted); }
.fk-lp-crumbs a:hover { color: var(--lp-pink-dark); text-decoration: none; }
.fk-lp-eyebrow { display: inline-block; padding: 5px 12px; margin-bottom: 18px; border-radius: 999px; background: #fff; border: 1px solid #fbcfe0; color: var(--lp-pink-dark); font-size: 13px; font-weight: 600; }
.fk-lp-hero h1 { font-size: clamp(1.9rem, 4.2vw, 3rem); line-height: 1.15; font-weight: 700; letter-spacing: -.02em; margin: 0 0 18px; max-width: 860px; }
.fk-lp-intro { font-size: 18px; max-width: 760px; margin: 0 0 30px; }
.fk-lp-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 14px 22px; }
.fk-lp-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 50px; padding: 0 26px; border-radius: 10px; background: var(--lp-pink); color: #fff; font-weight: 600; font-size: 16px; transition: background .15s; }
.fk-lp-btn:hover { background: var(--lp-pink-dark); color: #fff; text-decoration: none; }
.fk-lp-link { color: var(--lp-pink-dark); font-weight: 600; }
.fk-lp-link:hover { color: var(--lp-pink); }
.fk-lp-micro { display: block; width: 100%; font-size: 13.5px; color: var(--lp-muted); }

.fk-lp-highlights { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 44px; }
.fk-lp-highlight { background: #fff; border: 1px solid var(--lp-border); border-radius: 14px; padding: 20px; }
.fk-lp-highlight h2 { font-size: 16px; margin: 0 0 6px; }
.fk-lp-highlight p { margin: 0; font-size: 14.5px; line-height: 1.6; }

.fk-lp-section { padding: 64px 0 0; }
.fk-lp-section h2 { font-size: clamp(1.45rem, 2.6vw, 1.9rem); line-height: 1.25; margin: 0 0 16px; font-weight: 700; }
.fk-lp-section p { margin: 0 0 14px; }
.fk-lp-items { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-top: 22px; }
.fk-lp-item { padding: 22px; border-radius: 14px; background: var(--lp-bg); border: 1px solid var(--lp-border); }
.fk-lp-item h3 { font-size: 17px; margin: 0 0 6px; }
.fk-lp-item p { margin: 0; font-size: 15px; line-height: 1.6; }
.fk-lp-list { margin: 8px 0 14px; padding-inline-start: 0; list-style: none; }
.fk-lp-list li { position: relative; padding-inline-start: 28px; margin-bottom: 10px; }
.fk-lp-list li::before { content: ""; position: absolute; inset-inline-start: 0; top: .55em; width: 14px; height: 8px; border-inline-start: 2.5px solid var(--lp-pink); border-bottom: 2.5px solid var(--lp-pink); transform: rotate(-45deg); }
html[dir="rtl"] .fk-lp-list li::before { transform: rotate(45deg) scaleX(-1); }
.fk-lp-steps { counter-reset: lp-step; list-style: none; padding: 0; margin: 22px 0 0; display: grid; gap: 14px; }
.fk-lp-steps li { counter-increment: lp-step; position: relative; padding: 18px 20px; padding-inline-start: 68px; border: 1px solid var(--lp-border); border-radius: 14px; background: #fff; }
.fk-lp-steps li::before { content: counter(lp-step); position: absolute; inset-inline-start: 20px; top: 18px; width: 32px; height: 32px; border-radius: 50%; background: var(--lp-soft); color: var(--lp-pink-dark); font-weight: 700; display: grid; place-items: center; }
.fk-lp-steps h3 { font-size: 17px; margin: 0 0 4px; }
.fk-lp-steps p { margin: 0; font-size: 15px; }
.fk-lp-more { display: inline-block; margin-top: 6px; }

.fk-lp-example { margin-top: 64px; padding: 28px; border-radius: 18px; background: #fff; border: 1px solid var(--lp-border); box-shadow: 0 10px 30px -18px rgba(15, 23, 42, .25); }
.fk-lp-example h2 { font-size: 1.3rem; margin: 0 0 16px; }
.fk-lp-table-wrap { overflow-x: auto; }
.fk-lp-table { width: 100%; border-collapse: collapse; font-size: 15px; min-width: 520px; }
.fk-lp-table th, .fk-lp-table td { padding: 10px 12px; border-bottom: 1px solid var(--lp-border); text-align: start; }
.fk-lp-table th { font-size: 13px; text-transform: uppercase; letter-spacing: .03em; color: var(--lp-muted); background: var(--lp-bg); }
.fk-lp-table td:not(:first-child), .fk-lp-table th:not(:first-child) { text-align: end; white-space: nowrap; }
.fk-lp-table tfoot td { font-weight: 600; color: var(--lp-ink); border-bottom: 0; }
.fk-lp-table tfoot tr:last-child td { font-size: 16.5px; color: var(--lp-pink-dark); }
.fk-lp-num { white-space: nowrap; unicode-bidi: isolate; }
.fk-lp-example figcaption { margin-top: 14px; font-size: 14px; color: var(--lp-muted); }

.fk-lp-faq { margin-top: 18px; }
.fk-lp-faq details { border-bottom: 1px solid var(--lp-border); }
.fk-lp-faq summary { cursor: pointer; list-style: none; padding: 18px 0; padding-inline-end: 32px; position: relative; font-weight: 600; color: var(--lp-ink); font-size: 17px; }
.fk-lp-faq summary::-webkit-details-marker { display: none; }
.fk-lp-faq summary::after { content: "+"; position: absolute; inset-inline-end: 4px; top: 14px; font-size: 24px; color: var(--lp-pink); }
.fk-lp-faq details[open] summary::after { content: "–"; }
.fk-lp-faq details p { margin: 0 0 18px; }

.fk-lp-related { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; list-style: none; padding: 0; margin: 18px 0 0; }
.fk-lp-related a { display: block; height: 100%; padding: 16px 18px; border: 1px solid var(--lp-border); border-radius: 12px; color: var(--lp-ink); font-weight: 600; }
.fk-lp-related a:hover { border-color: var(--lp-pink); color: var(--lp-pink-dark); text-decoration: none; }
.fk-lp-related small { display: block; font-weight: 500; color: var(--lp-muted); font-size: 12.5px; margin-bottom: 4px; }
.fk-lp-sources { margin-top: 18px; font-size: 14px; }
.fk-lp-sources ul { padding-inline-start: 18px; margin: 8px 0 0; }
.fk-lp-sources a { color: var(--lp-text); text-decoration: underline; overflow-wrap: anywhere; }

.fk-lp-cta { margin: 72px 0 96px; padding: 44px 32px; border-radius: 22px; background: var(--lp-ink); color: #cbd5e1; text-align: center; }
.fk-lp-cta h2 { color: #fff; font-size: clamp(1.5rem, 3vw, 2rem); margin: 0 0 10px; }
.fk-lp-cta p { margin: 0 auto 24px; max-width: 560px; }
.fk-lp-cta .fk-lp-actions { justify-content: center; }
.fk-lp-cta .fk-lp-link { color: #f9a8c9; }

@media (max-width: 767px) {
    .fk-lp-hero { padding: 120px 0 48px; }
    .fk-lp-intro { font-size: 16.5px; }
    .fk-lp-section { padding-top: 48px; }
    .fk-lp-example { padding: 20px 16px; }
    .fk-lp-cta { padding: 32px 20px; margin-bottom: 64px; }
    .fk-lp-actions .fk-lp-btn { width: 100%; }
    /* Fit the example invoice on a phone instead of scrolling sideways. */
    .fk-lp-table { min-width: 0; font-size: 13px; }
    .fk-lp-table th, .fk-lp-table td { padding: 8px 5px; }
    .fk-lp-table td:not(:first-child), .fk-lp-table th:not(:first-child) { white-space: normal; }
    .fk-lp-table th { font-size: 11px; letter-spacing: 0; }
}
</style>

<main class="fk-lp">
    <section class="fk-lp-hero">
        <div class="fk-lp-container">
            <nav aria-label="breadcrumb">
                <ol class="fk-lp-crumbs">
                    <li><a href="{{ Seo::url('/', $locale) }}">{{ __('site.seo.home') }}</a></li>
                    <li aria-current="page">{{ $content['nav_label'] }}</li>
                </ol>
            </nav>
            <span class="fk-lp-eyebrow">{{ $content['eyebrow'] }}</span>
            <h1>{{ $content['h1'] }}</h1>
            <p class="fk-lp-intro">{{ $content['intro'] }}</p>
            <div class="fk-lp-actions">
                <a href="{{ url('/register') }}" class="fk-lp-btn">{{ __('site.seo.cta_primary') }}</a>
                <a href="{{ Seo::url('/', $locale) }}#funciones" class="fk-lp-link">{{ __('site.seo.cta_features') }} {{ $lpArrow }}</a>
                <span class="fk-lp-micro">{{ __('site.seo.cta_microcopy', ['days' => $trialDays]) }}</span>
            </div>

            @if (! empty($content['highlights']))
                <div class="fk-lp-highlights">
                    @foreach ($content['highlights'] as [$hlTitle, $hlText])
                        <div class="fk-lp-highlight">
                            <h2>{{ $hlTitle }}</h2>
                            <p>{{ $hlText }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="fk-lp-container">
        @foreach ($content['sections'] as $section)
            <section class="fk-lp-section fk-lp-narrow">
                <h2>{{ $section['h2'] }}</h2>

                @foreach ($section['paragraphs'] ?? [] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach

                @if (! empty($section['list']))
                    <ul class="fk-lp-list">
                        @foreach ($section['list'] as $listItem)
                            <li>{{ $listItem }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (! empty($section['steps']))
                    <ol class="fk-lp-steps">
                        @foreach ($section['steps'] as [$stepTitle, $stepText])
                            <li>
                                <h3>{{ $stepTitle }}</h3>
                                <p>{{ $stepText }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif

                @if (! empty($section['items']))
                    <div class="fk-lp-items">
                        @foreach ($section['items'] as [$itemTitle, $itemText])
                            <div class="fk-lp-item">
                                <h3>{{ $itemTitle }}</h3>
                                <p>{{ $itemText }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (! empty($section['link']))
                    <a href="{{ $lpUrl($section['link'][1]) }}" class="fk-lp-link fk-lp-more">{{ $section['link'][0] }} {{ $lpArrow }}</a>
                @endif
            </section>
        @endforeach

        @if (! empty($content['example']))
            @php $example = $content['example']; @endphp
            <figure class="fk-lp-example fk-lp-narrow">
                <h2>{{ $example['title'] }}</h2>
                <div class="fk-lp-table-wrap">
                    <table class="fk-lp-table">
                        <thead>
                            <tr>
                                @foreach ($example['head'] as $headCell)
                                    <th scope="col">{{ $headCell }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($example['rows'] as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td>{!! $lpNum($cell) !!}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @foreach ($example['totals'] as [$totalLabel, $totalValue])
                                <tr>
                                    <td colspan="{{ count($example['head']) - 1 }}">{{ $totalLabel }}</td>
                                    <td>{!! $lpNum($totalValue) !!}</td>
                                </tr>
                            @endforeach
                        </tfoot>
                    </table>
                </div>
                <figcaption>{{ $example['caption'] }} {{ $common['example_note'] }}</figcaption>
            </figure>
        @endif

        @if ($lpFaq)
            <section class="fk-lp-section fk-lp-narrow" aria-labelledby="lp-faq">
                <h2 id="lp-faq">{{ __('site.seo.faq_title') }}</h2>
                <div class="fk-lp-faq">
                    @foreach ($lpFaq as [$question, $answer])
                        <details @if ($loop->first) open @endif>
                            <summary>{{ $question }}</summary>
                            <p>{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($content['related']))
            <section class="fk-lp-section" aria-labelledby="lp-related">
                <h2 id="lp-related">{{ __('site.seo.related_title') }}</h2>
                <ul class="fk-lp-related">
                    @foreach ($content['related'] as [$relatedLabel, $relatedPath])
                        <li>
                            <a href="{{ $lpUrl($relatedPath) }}" @if (str_starts_with($relatedPath, '/blog') && $locale !== 'fr') hreflang="fr" @endif>
                                @if (str_starts_with($relatedPath, '/blog'))<small>{{ $common['related_blog'] }}</small>@endif
                                {{ $relatedLabel }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($content['sources']))
            <section class="fk-lp-sources fk-lp-narrow" aria-labelledby="lp-sources">
                <h2 id="lp-sources" class="h6">{{ $common['sources_title'] }}</h2>
                <ul>
                    @foreach ($content['sources'] as [$sourceLabel, $sourceUrl])
                        <li><a href="{{ $sourceUrl }}" rel="noopener" target="_blank">{{ $sourceLabel }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="fk-lp-cta">
            <h2>{{ $content['cta_title'] }}</h2>
            <p>{{ $content['cta_text'] }}</p>
            <div class="fk-lp-actions">
                <a href="{{ url('/register') }}" class="fk-lp-btn">{{ __('site.seo.cta_first_invoice') }}</a>
                <a href="{{ Seo::url('/pricing', $locale) }}" class="fk-lp-link">{{ __('site.seo.cta_pricing') }} {{ $lpArrow }}</a>
            </div>
        </section>
    </div>
</main>
@endsection
