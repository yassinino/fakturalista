{{-- Google Analytics 4 (config/seo.php 'ga4'). Disabled in tests and when
     GA4_ENABLED=false. Free-trial signups are also reported server-side as a
     `sign_up` event (App\Services\Analytics\GoogleAnalyticsConversion). --}}
@if (config('seo.ga4.enabled') && config('seo.ga4.measurement_id') && ! app()->environment('testing'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('seo.ga4.measurement_id') }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', @json(config('seo.ga4.measurement_id')));
</script>
@endif
