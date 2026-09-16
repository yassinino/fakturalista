@extends('layouts.master')

@section('title', __('site.cookie_policy.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.cookie_policy.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.cookie_policy.hero_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.cookie_policy.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/cookie-policy') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a { color: #000000; }

    .lp-page {
        background: #f7f8fc;
    }
    .lp-content {
        max-width: 820px;
        margin: 0 auto;
        padding: 48px 24px 80px;
    }
    .lp-updated {
        font-size: 13px;
        color: #9ca3af;
        margin-bottom: 40px;
    }
    .lp-section {
        margin-bottom: 40px;
    }
    .lp-heading {
        font-size: 1.15rem;
        font-weight: 700;
        color: #2b2350;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #fde8e8;
    }
    .lp-body {
        font-size: 15.5px;
        line-height: 1.85;
        color: #374151;
        margin: 0 0 16px;
    }
    .lp-body:last-child { margin-bottom: 0; }

    /* Cookie table */
    .lp-table-wrap {
        overflow-x: auto;
        margin-top: 16px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
    }
    .lp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        background: #fff;
    }
    .lp-table thead th {
        background: #2b2350;
        color: #fff;
        font-weight: 600;
        padding: 11px 14px;
        text-align: left;
        white-space: nowrap;
    }
    .lp-table tbody tr:nth-child(even) { background: #fafafa; }
    .lp-table tbody tr:hover { background: #fdf2f2; }
    .lp-table td {
        padding: 10px 14px;
        border-top: 1px solid #f0f0f0;
        color: #374151;
        line-height: 1.6;
        vertical-align: top;
    }
    .lp-table td code {
        background: #f3f4f6;
        border-radius: 4px;
        padding: 2px 6px;
        font-size: 12.5px;
        color: #374151;
        font-family: 'Courier New', monospace;
        white-space: nowrap;
    }
</style>

@include('partials.page-hero', [
    'title'      => __('site.cookie_policy.hero_title'),
    'paragraphs' => [__('site.cookie_policy.hero_sub')],
])

<section class="lp-page">
    <div class="lp-content">

        <p class="lp-updated">{{ __('site.cookie_policy.updated') }}</p>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.cookie_policy.intro_title') }}</h2>
            <p class="lp-body">{{ __('site.cookie_policy.intro_body') }}</p>
        </div>

        {{-- Necessary cookies --}}
        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.cookie_policy.necessary_title') }}</h2>
            <p class="lp-body">{{ __('site.cookie_policy.necessary_body') }}</p>
            <div class="lp-table-wrap">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>{{ __('site.cookie_policy.table_col_name') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_provider') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_purpose') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>laravel_session</code></td>
                            <td>Fakturalista</td>
                            <td>{{ __('site.cookie_policy.cookie_session_purpose') }}</td>
                            <td>{{ __('site.cookie_policy.cookie_session_duration') }}</td>
                        </tr>
                        <tr>
                            <td><code>XSRF-TOKEN</code></td>
                            <td>Fakturalista</td>
                            <td>{{ __('site.cookie_policy.cookie_csrf_purpose') }}</td>
                            <td>{{ __('site.cookie_policy.cookie_csrf_duration') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Preference cookies --}}
        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.cookie_policy.pref_title') }}</h2>
            <p class="lp-body">{{ __('site.cookie_policy.pref_body') }}</p>
            <div class="lp-table-wrap">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>{{ __('site.cookie_policy.table_col_name') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_provider') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_purpose') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>locale</code></td>
                            <td>Fakturalista</td>
                            <td>{{ __('site.cookie_policy.cookie_locale_purpose') }}</td>
                            <td>{{ __('site.cookie_policy.cookie_locale_duration') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Analytics cookies --}}
        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.cookie_policy.analytics_title') }}</h2>
            <p class="lp-body">{{ __('site.cookie_policy.analytics_body') }}</p>
            <div class="lp-table-wrap">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>{{ __('site.cookie_policy.table_col_name') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_provider') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_purpose') }}</th>
                            <th>{{ __('site.cookie_policy.table_col_duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>_ga</code></td>
                            <td>Google</td>
                            <td>{{ __('site.cookie_policy.cookie_ga_purpose') }}</td>
                            <td>{{ __('site.cookie_policy.cookie_ga_duration') }}</td>
                        </tr>
                        <tr>
                            <td><code>_ga_*</code></td>
                            <td>Google</td>
                            <td>{{ __('site.cookie_policy.cookie_ga_purpose') }}</td>
                            <td>{{ __('site.cookie_policy.cookie_ga_duration') }}</td>
                        </tr>
                        <tr>
                            <td><code>_gid</code></td>
                            <td>Google</td>
                            <td>{{ __('site.cookie_policy.cookie_gid_purpose') }}</td>
                            <td>{{ __('site.cookie_policy.cookie_gid_duration') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.cookie_policy.manage_title') }}</h2>
            <p class="lp-body">{{ __('site.cookie_policy.manage_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.cookie_policy.optout_title') }}</h2>
            <p class="lp-body">{{ __('site.cookie_policy.optout_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.cookie_policy.changes_title') }}</h2>
            <p class="lp-body">{{ __('site.cookie_policy.changes_body') }}</p>
        </div>

        <div class="lp-section">
            <h2 class="lp-heading">{{ __('site.cookie_policy.contact_title') }}</h2>
            <p class="lp-body">{{ __('site.cookie_policy.contact_body') }}</p>
        </div>

    </div>
</section>

@endsection
