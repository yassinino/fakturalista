@extends('layouts.master')

@section('title', __('site.changelog.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.changelog.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.changelog.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.changelog.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/changelog') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    .cl-page { background: #f7f8fc; }

    .cl-content {
        max-width: 740px;
        margin: 0 auto;
        padding: 56px 24px 80px;
    }

    /* ── Placeholder notice ──────────────────────────────────── */
    .cl-notice {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f3f4f6;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: .82rem;
        color: #6b7280;
        margin-bottom: 40px;
    }
    .cl-notice i { color: #9ca3af; flex-shrink: 0; }

    /* ── Entry ───────────────────────────────────────────────── */
    .cl-entry {
        display: flex;
        gap: 32px;
        margin-bottom: 48px;
        position: relative;
    }
    .cl-entry:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 119px;
        top: 36px;
        bottom: -48px;
        width: 1px;
        background: #e5e7eb;
    }

    /* Date column */
    .cl-date-col {
        width: 88px;
        flex-shrink: 0;
        text-align: right;
        padding-top: 4px;
    }
    .cl-date {
        font-size: .78rem;
        font-weight: 600;
        color: #9ca3af;
        white-space: nowrap;
        letter-spacing: .02em;
    }

    /* Dot */
    .cl-dot-col {
        width: 20px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 4px;
    }
    .cl-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #fa7070;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #fa7070;
        flex-shrink: 0;
    }

    /* Content */
    .cl-body { flex: 1; min-width: 0; }

    .cl-version {
        display: inline-block;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        background: #f3f4f6;
        color: #6b7280;
        border-radius: 5px;
        padding: 2px 8px;
        margin-bottom: 8px;
    }
    .cl-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #2b2350;
        margin: 0 0 16px;
    }

    .cl-group { margin-bottom: 14px; }
    .cl-group:last-child { margin-bottom: 0; }

    .cl-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        border-radius: 5px;
        padding: 2px 8px;
        margin-bottom: 8px;
    }
    .cl-tag-added    { background: #d1fae5; color: #065f46; }
    .cl-tag-fixed    { background: #fee2e2; color: #991b1b; }
    .cl-tag-improved { background: #dbeafe; color: #1e40af; }

    .cl-items {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .cl-items li {
        font-size: .9rem;
        color: #374151;
        line-height: 1.65;
        padding: 4px 0 4px 18px;
        position: relative;
    }
    .cl-items li::before {
        content: '—';
        position: absolute;
        left: 0;
        color: #d1d5db;
        font-size: .8rem;
    }

    @media (max-width: 600px) {
        .cl-entry { gap: 16px; }
        .cl-entry:not(:last-child)::before { left: 103px; }
        .cl-date-col { width: 72px; }
        .cl-content { padding: 40px 16px 64px; }
    }
    @media (max-width: 480px) {
        .cl-entry { flex-direction: column; gap: 8px; }
        .cl-entry:not(:last-child)::before { display: none; }
        .cl-date-col { width: auto; text-align: left; }
        .cl-dot-col { display: none; }
    }
</style>

@php
/*
 * TODO: Replace these placeholder entries with real release notes before publishing.
 * To add a new entry, prepend an item to this array (newest first).
 * Keys: version (string), date (string), title (string),
 *       added (array), fixed (array), improved (array) — all optional.
 */
$entries = [
    [
        'version'  => 'v1.3.0',
        'date'     => 'Sep 2026',
        'title'    => 'Placeholder release — replace with real notes',
        'added'    => [
            'Placeholder: new feature A',
            'Placeholder: new feature B',
        ],
        'improved' => [
            'Placeholder: performance improvement example',
        ],
        'fixed'    => [],
    ],
    [
        'version'  => 'v1.2.0',
        'date'     => 'Aug 2026',
        'title'    => 'Placeholder release — replace with real notes',
        'added'    => [
            'Placeholder: new feature C',
        ],
        'fixed'    => [
            'Placeholder: bug fix example',
            'Placeholder: another bug fix',
        ],
        'improved' => [],
    ],
    [
        'version'  => 'v1.1.0',
        'date'     => 'Jul 2026',
        'title'    => 'Placeholder release — replace with real notes',
        'added'    => [],
        'fixed'    => [],
        'improved' => [
            'Placeholder: improvement A',
            'Placeholder: improvement B',
        ],
    ],
];
@endphp

@include('partials.page-hero', [
    'title'      => __('site.changelog.hero_title'),
    'paragraphs' => [__('site.changelog.hero_sub')],
])

<div class="cl-page">
    <div class="cl-content">

        {{-- Developer note — not shown in production, purely for reference --}}
        <div class="cl-notice">
            <i class="fas fa-info-circle"></i>
            {{-- TODO: remove this notice once real entries are in place --}}
            Entries below are placeholders. Edit the <code>$entries</code> array in <code>changelog.blade.php</code> to add real release notes.
        </div>

        @foreach ($entries as $entry)
            <article class="cl-entry">

                <div class="cl-date-col">
                    <span class="cl-date">{{ $entry['date'] }}</span>
                </div>

                <div class="cl-dot-col">
                    <span class="cl-dot" aria-hidden="true"></span>
                </div>

                <div class="cl-body">
                    <span class="cl-version">{{ $entry['version'] }}</span>
                    <h2 class="cl-title">{{ $entry['title'] }}</h2>

                    @if (!empty($entry['added']))
                        <div class="cl-group">
                            <span class="cl-tag cl-tag-added">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                {{ __('site.changelog.tag_added') }}
                            </span>
                            <ul class="cl-items">
                                @foreach ($entry['added'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($entry['improved']))
                        <div class="cl-group">
                            <span class="cl-tag cl-tag-improved">
                                <i class="fas fa-arrow-up" aria-hidden="true"></i>
                                {{ __('site.changelog.tag_improved') }}
                            </span>
                            <ul class="cl-items">
                                @foreach ($entry['improved'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($entry['fixed']))
                        <div class="cl-group">
                            <span class="cl-tag cl-tag-fixed">
                                <i class="fas fa-wrench" aria-hidden="true"></i>
                                {{ __('site.changelog.tag_fixed') }}
                            </span>
                            <ul class="cl-items">
                                @foreach ($entry['fixed'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                </div>
            </article>
        @endforeach

    </div>
</div>

@endsection
