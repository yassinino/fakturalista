{{-- Standalone error page (404 / 410). Deliberately independent of
     layouts/master: error pages can render before the session, locale or
     tenancy middleware ran. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales', []), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, follow" />
    <title>{{ $heading }} - Fakturalista</title>
    <link rel="icon" href="{{ url('assets/icon.svg') }}" />
    <style>
        :root { --pink: #E91E63; --ink: #0F172A; --text: #475569; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f8fafc; color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        main { max-width: 520px; text-align: center; }
        .code { font-size: 72px; font-weight: 800; color: var(--pink); letter-spacing: -2px; line-height: 1; }
        h1 { color: var(--ink); font-size: 26px; margin: 16px 0 10px; }
        p { margin: 0 0 28px; line-height: 1.6; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
        a { display: inline-flex; align-items: center; min-height: 46px; padding: 0 22px; border-radius: 10px; font-weight: 600; text-decoration: none; }
        .primary { background: var(--pink); color: #fff; }
        .secondary { border: 1px solid #e2e8f0; color: var(--ink); background: #fff; }
        .logo { display: inline-block; margin-bottom: 28px; }
    </style>
</head>
<body>
    <main>
        <a class="logo" href="{{ url('/') }}" aria-label="Fakturalista"><img src="{{ url('assets/logo.svg') }}" alt="Fakturalista" height="32"></a>
        <div class="code">{{ $code }}</div>
        <h1>{{ $heading }}</h1>
        <p>{{ $text }}</p>
        <div class="actions">
            <a class="primary" href="{{ url('/') }}">{{ __('site.seo.back_home') }}</a>
            @unless (tenancy()->initialized)
                <a class="secondary" href="{{ url('/blog') }}">{{ __('site.seo.see_blog') }}</a>
            @endunless
        </div>
    </main>
</body>
</html>
