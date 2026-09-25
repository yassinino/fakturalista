@php
    // Only visible_locales are offered in the picker - es stays fully
    // supported server-side (routes, session, __() files) but is hidden
    // from selection so it can be re-enabled later without any data loss.
    $fkLangs   = ['fr' => __('site.lang.fr'), 'ar' => __('site.lang.ar'), 'en' => __('site.lang.en')];
    $fkCurrent = app()->getLocale();
    $fkLabel   = $fkLangs[$fkCurrent] ?? $fkLangs['fr'];
@endphp
<div class="fk-lang-switcher">
    <button class="fk-lang-btn" type="button"
            aria-expanded="false" aria-haspopup="listbox"
            aria-label="{{ __('site.lang.label') }}">
        <svg class="fk-lang-globe" width="14" height="14" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/></svg>
        <span class="fk-lang-label">{{ $fkLabel }}</span>
        <svg class="fk-lang-chevron" width="12" height="12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
    </button>
    <div class="fk-lang-panel" role="listbox" aria-label="{{ __('site.lang.label') }}">
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
