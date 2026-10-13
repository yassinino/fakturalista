{{--
    SEO <head> block for an indexable public page - used inside
    @section('meta'). Renders description, robots, canonical, hreflang,
    Open Graph, X/Twitter card and JSON-LD (BreadcrumbList + extras).

    Params:
      description (string, required)
      path        (string, required)  page path, e.g. '/pricing'
      title       (string)            defaults to @section('title')
      locales     (string[])          language versions; default config('seo.url_locales')
      hreflang    (array)             locale => hreflang code; default config('seo.hreflang')
      type        (string)            og:type, default 'website'
      image       (string)            absolute URL, default config('seo.default_image')
      robots      (string)            default 'index, follow, max-image-preview:large'
      breadcrumbs (array)             [[name, path], ...] (Home is prepended)
      schema      (array[])           extra JSON-LD objects
--}}
@php
    $seoLocales  = $locales ?? Seo::urlLocales();
    $seoLocale   = in_array(Seo::locale(), $seoLocales, true) ? Seo::locale() : $seoLocales[0];
    $seoHreflang = $hreflang ?? config('seo.hreflang', []);
    $seoTitle    = trim($title ?? $__env->yieldContent('title'));
    $seoTitle    = str_contains($seoTitle, 'Fakturalista') ? $seoTitle : $seoTitle . ' - Fakturalista';
    $seoCanon    = Seo::canonical($path, $seoLocale);
    $seoImage    = $image ?? Seo::asset(config('seo.default_image'));
    $seoOgLocale = ['fr' => 'fr_MA', 'ar' => 'ar_MA', 'en' => 'en_US'];
    if (isset($hreflang[$seoLocale]) && str_contains($hreflang[$seoLocale], '-')) {
        $seoOgLocale[$seoLocale] = str_replace('-', '_', $hreflang[$seoLocale]);
    }

    $seoGraph = [];
    if (! empty($breadcrumbs)) {
        $items = [['name' => __('site.seo.home'), 'path' => '/']];
        foreach ($breadcrumbs as [$crumbName, $crumbPath]) {
            $items[] = ['name' => trim(preg_replace('/\s*[—-]\s*Fakturalista$/u', '', $crumbName)), 'path' => $crumbPath];
        }
        $seoGraph[] = [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => Seo::canonical($item['path'], in_array($seoLocale, Seo::urlLocales(), true) ? $seoLocale : null),
            ])->all(),
        ];
    }
    foreach ($schema ?? [] as $node) {
        $seoGraph[] = $node;
    }
@endphp
<meta name="description" content="{{ $description }}" />
<meta name="robots" content="{{ $robots ?? 'index, follow, max-image-preview:large' }}" />
<link rel="canonical" href="{{ $seoCanon }}" />
@if (count($seoLocales) > 1)
@foreach (Seo::alternates($path, $seoLocales, $seoHreflang) as $seoCode => $seoHref)
<link rel="alternate" hreflang="{{ $seoCode }}" href="{{ $seoHref }}" />
@endforeach
@endif
<meta property="og:site_name" content="Fakturalista" />
<meta property="og:type" content="{{ $type ?? 'website' }}" />
<meta property="og:title" content="{{ $seoTitle }}" />
<meta property="og:description" content="{{ $description }}" />
<meta property="og:url" content="{{ $seoCanon }}" />
<meta property="og:image" content="{{ $seoImage }}" />
<meta property="og:locale" content="{{ $seoOgLocale[$seoLocale] ?? 'fr_MA' }}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $seoTitle }}" />
<meta name="twitter:description" content="{{ $description }}" />
<meta name="twitter:image" content="{{ $seoImage }}" />
@if (count($seoGraph))
<script type="application/ld+json">{!! Seo::jsonLd(['@context' => 'https://schema.org', '@graph' => $seoGraph]) !!}</script>
@endif
