{{-- First-party, cookieless audience measurement (App\Services\Analytics\AnalyticsTracker).
     Sends one signed beacon per page load once the page is actually shown
     (not for prerendered or back/forward-cache restores). No cookie or
     storage is written on the device. --}}
@php $fkAnalytics = app(\App\Services\Analytics\AnalyticsTracker::class)->pagePayload(request(), $title ?? null); @endphp
@if ($fkAnalytics)
<script>
(function () {
    if (navigator.webdriver) return;
    var data = @json($fkAnalytics), url = @json(url('/a/collect')), sent = false;
    function send() {
        if (sent) return; sent = true;
        try {
            var q = new URLSearchParams(location.search);
            data.r = document.referrer || '';
            data.us = q.get('utm_source'); data.um = q.get('utm_medium'); data.uc = q.get('utm_campaign');
            var body = JSON.stringify(data);
            if (!(navigator.sendBeacon && navigator.sendBeacon(url, body))) {
                fetch(url, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' }).catch(function () {});
            }
        } catch (e) {}
    }
    if (document.prerendering || document.visibilityState !== 'visible') {
        document.addEventListener(document.prerendering ? 'prerenderingchange' : 'visibilitychange', function onShow() {
            if (document.visibilityState === 'visible') { document.removeEventListener('visibilitychange', onShow); send(); }
        });
    } else {
        send();
    }
})();
</script>
@endif
