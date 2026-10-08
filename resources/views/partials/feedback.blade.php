{{--
    Feedback widget - public marketing site (included once from layouts/master).
    Side tab + modal; posts multipart to POST /feedback (FeedbackController::
    storePublic) and shows the confirmation in place, no reload. The tenant
    app has its own Vue twin: resources/js/components/FeedbackModal.vue.
--}}
@php
    $fbMaxMb = (int) (\App\Http\Requests\StoreFeedbackRequest::MAX_SCREENSHOT / 1024);
    $fbErrors = __('site.feedback.errors');
@endphp

<button type="button" class="fkfb-tab" id="fkfb-open" aria-haspopup="dialog" aria-controls="fkfb-dialog">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <span>{{ __('site.feedback.button') }}</span>
</button>

<div class="fkfb-backdrop" id="fkfb-backdrop" hidden>
    <div class="fkfb-dialog" id="fkfb-dialog" role="dialog" aria-modal="true" aria-labelledby="fkfb-title" aria-describedby="fkfb-subtitle">
        <button type="button" class="fkfb-close" data-fkfb-close aria-label="{{ __('site.feedback.close') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>

        <form id="fkfb-form" class="fkfb-form" novalidate enctype="multipart/form-data" action="{{ url('/feedback') }}" method="post">
            @csrf
            <div class="fkfb-head">
                <span class="fkfb-badge" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8M8 13h5"/></svg>
                </span>
                <h2 class="fkfb-title" id="fkfb-title">{{ __('site.feedback.title') }}</h2>
                <p class="fkfb-subtitle" id="fkfb-subtitle">{{ __('site.feedback.subtitle') }}</p>
            </div>

            <fieldset class="fkfb-field">
                <legend class="fkfb-label">{{ __('site.feedback.type_label') }}</legend>
                <div class="fkfb-types">
                    @foreach (['suggestion' => '<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/>',
                               'bug' => '<path d="M8 2l1.9 1.9M16 2l-1.9 1.9M9 7.1V6a3 3 0 0 1 6 0v1.1"/><path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6zM12 20v-9M6.5 9H3M6 13H2M6 17l-3 2M21 9h-3.5M22 13h-4M18 17l3 2"/>',
                               'improvement' => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3z"/>',
                               'other' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>'] as $fbType => $fbIcon)
                        <label class="fkfb-type">
                            <input type="radio" name="type" value="{{ $fbType }}" @checked($loop->first)>
                            <span class="fkfb-type-inner">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $fbIcon !!}</svg>
                                {{ __('site.feedback.types.' . $fbType) }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="fkfb-field">
                <label class="fkfb-label" for="fkfb-message">{{ __('site.feedback.message_label') }} <span class="fkfb-req" aria-hidden="true">*</span></label>
                <textarea id="fkfb-message" name="message" class="fkfb-input fkfb-textarea" rows="5" maxlength="5000" required
                          aria-describedby="fkfb-message-error" placeholder="{{ __('site.feedback.message_placeholder') }}"></textarea>
                <div class="fkfb-meta">
                    <p class="fkfb-error" id="fkfb-message-error" role="alert"></p>
                    <span class="fkfb-count" id="fkfb-count" aria-hidden="true">0 / 5000</span>
                </div>
            </div>

            <div class="fkfb-field">
                <label class="fkfb-label" for="fkfb-email">{{ __('site.feedback.email_label') }} <span class="fkfb-req" aria-hidden="true">*</span></label>
                <input id="fkfb-email" name="email" type="email" class="fkfb-input" maxlength="255" required autocomplete="email"
                       aria-describedby="fkfb-email-error" placeholder="{{ __('site.feedback.email_placeholder') }}">
                <p class="fkfb-error" id="fkfb-email-error" role="alert"></p>
            </div>

            <div class="fkfb-field">
                <span class="fkfb-label" id="fkfb-shot-label">{{ __('site.feedback.screenshot_label') }}</span>
                <label class="fkfb-drop" id="fkfb-drop" for="fkfb-file">
                    <input id="fkfb-file" name="screenshot" type="file" accept="image/png,image/jpeg,image/webp" class="fkfb-file"
                           aria-labelledby="fkfb-shot-label" aria-describedby="fkfb-shot-hint fkfb-screenshot-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                    <span><strong>{{ __('site.feedback.screenshot_add') }}</strong><small id="fkfb-shot-hint">{{ __('site.feedback.screenshot_hint') }}</small></span>
                </label>
                <div class="fkfb-preview" id="fkfb-preview" hidden>
                    <img id="fkfb-preview-img" alt="">
                    <span class="fkfb-preview-name" id="fkfb-preview-name"></span>
                    <button type="button" class="fkfb-preview-remove" id="fkfb-remove" aria-label="{{ __('site.feedback.screenshot_remove') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="fkfb-error" id="fkfb-screenshot-error" role="alert"></p>
            </div>

            {{-- Honeypot: hidden from people and assistive tech, bots fill it. --}}
            <div class="fkfb-hp" aria-hidden="true">
                <label for="fkfb-website">Website</label>
                <input id="fkfb-website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <p class="fkfb-alert" id="fkfb-alert" role="alert" hidden></p>

            <div class="fkfb-actions">
                <button type="button" class="fkfb-btn fkfb-btn--ghost" data-fkfb-close>{{ __('site.feedback.cancel') }}</button>
                <button type="submit" class="fkfb-btn fkfb-btn--primary" id="fkfb-submit">
                    <span class="fkfb-spinner" aria-hidden="true"></span>
                    <span class="fkfb-submit-label">{{ __('site.feedback.submit') }}</span>
                </button>
            </div>
        </form>

        <div class="fkfb-success" id="fkfb-success" hidden tabindex="-1">
            <span class="fkfb-success-icon" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </span>
            <h2 class="fkfb-title">{{ __('site.feedback.success_title') }}</h2>
            <p class="fkfb-subtitle" role="status">{{ __('site.feedback.success') }}</p>
            <button type="button" class="fkfb-btn fkfb-btn--primary" data-fkfb-close>{{ __('site.feedback.close') }}</button>
        </div>
    </div>
</div>

<style>
.fkfb-tab {
    /* Physical sides on purpose: logical ones would follow the tab's own
       vertical writing-mode (inline-end = bottom). */
    position: fixed;
    right: 0;
    left: auto;
    bottom: auto;
    top: 58%;
    z-index: 999990;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 14px 8px;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    background: #0F172A;
    color: #fff;
    border: 0;
    border-radius: 0 10px 10px 0;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    line-height: 1;
    letter-spacing: .02em;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(15, 23, 42, .18);
    transition: background .18s;
}
html[dir="rtl"] .fkfb-tab { right: auto; left: 0; transform: none; }
.fkfb-tab svg { transform: rotate(90deg); }
.fkfb-tab:hover { background: #E91E63; }
.fkfb-tab:focus-visible { outline: 3px solid rgba(233, 30, 99, .45); outline-offset: 2px; }

.fkfb-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000010;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, .5);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    opacity: 0;
    transition: opacity .22s ease;
}
.fkfb-backdrop[hidden] { display: none; }
.fkfb-backdrop.is-open { opacity: 1; }

.fkfb-dialog {
    position: relative;
    width: 100%;
    max-width: 480px;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
    overscroll-behavior: contain;
    background: #fff;
    color: #0F172A;
    border-radius: 18px;
    padding: 28px 28px 24px;
    box-shadow: 0 24px 60px -12px rgba(15, 23, 42, .35);
    transform: translateY(14px) scale(.98);
    transition: transform .26s cubic-bezier(.2, .8, .2, 1);
    text-align: start;
    font-size: 14px;
    line-height: 1.5;
}
.fkfb-backdrop.is-open .fkfb-dialog { transform: none; }

.fkfb-close {
    position: absolute;
    top: 14px;
    inset-inline-end: 14px;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: background .15s, color .15s;
}
.fkfb-close:hover { background: #f1f5f9; color: #0F172A; }

.fkfb-head { margin-bottom: 20px; padding-inline-end: 28px; }
.fkfb-badge {
    display: inline-grid;
    place-items: center;
    width: 40px;
    height: 40px;
    margin-bottom: 14px;
    border-radius: 12px;
    background: #fdf0f5;
    color: #E91E63;
}
.fkfb-title { margin: 0 0 6px; font-size: 20px; font-weight: 700; letter-spacing: -.01em; color: #0F172A; line-height: 1.3; }
.fkfb-subtitle { margin: 0; color: #475569; font-size: 14px; }

.fkfb-field { margin: 0 0 16px; padding: 0; border: 0; min-width: 0; }
.fkfb-label { display: block; margin-bottom: 7px; font-size: 13px; font-weight: 600; color: #1e293b; padding: 0; line-height: 1.4; }
legend.fkfb-label { float: none; width: auto; }
.fkfb-req { color: #E91E63; }

.fkfb-types { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.fkfb-type { position: relative; margin: 0; cursor: pointer; }
.fkfb-type input { position: absolute; opacity: 0; pointer-events: none; }
.fkfb-type-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 11px 4px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    text-align: center;
    transition: border-color .15s, background .15s, color .15s, box-shadow .15s;
}
.fkfb-type:hover .fkfb-type-inner { border-color: #cbd5e1; background: #f8fafc; }
.fkfb-type input:checked + .fkfb-type-inner { border-color: #E91E63; background: #fdf0f5; color: #c2185b; box-shadow: 0 0 0 1px #E91E63 inset; }
.fkfb-type input:focus-visible + .fkfb-type-inner { outline: 3px solid rgba(233, 30, 99, .35); outline-offset: 1px; }

.fkfb-input {
    display: block;
    width: 100%;
    height: auto;
    margin: 0;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    color: #0F172A;
    font: inherit;
    font-size: 14px;
    transition: border-color .15s, box-shadow .15s;
}
.fkfb-input::placeholder { color: #94a3b8; }
.fkfb-input:focus { outline: none; border-color: #E91E63; box-shadow: 0 0 0 3px rgba(233, 30, 99, .15); }
.fkfb-input[aria-invalid="true"] { border-color: #dc2626; }
.fkfb-input[aria-invalid="true"]:focus { box-shadow: 0 0 0 3px rgba(220, 38, 38, .15); }
.fkfb-textarea { resize: vertical; min-height: 110px; }

.fkfb-meta { display: flex; justify-content: space-between; gap: 12px; }
.fkfb-count { margin-top: 5px; margin-inline-start: auto; font-size: 12px; color: #94a3b8; white-space: nowrap; font-variant-numeric: tabular-nums; }
.fkfb-error { margin: 5px 0 0; font-size: 12.5px; color: #b91c1c; min-height: 0; }
.fkfb-error:empty { display: none; }

.fkfb-drop {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    padding: 12px 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    color: #475569;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.fkfb-drop[hidden] { display: none; }
.fkfb-drop:hover, .fkfb-drop.is-drag { border-color: #E91E63; background: #fdf0f5; }
.fkfb-drop:focus-within { border-color: #E91E63; box-shadow: 0 0 0 3px rgba(233, 30, 99, .15); }
.fkfb-drop strong { display: block; font-size: 13px; font-weight: 600; color: #1e293b; }
.fkfb-drop small { display: block; font-size: 12px; color: #64748b; }
.fkfb-file { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
.fkfb-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.fkfb-preview[hidden] { display: none; }
.fkfb-preview img { width: 56px; height: 42px; object-fit: cover; border-radius: 6px; background: #f1f5f9; }
.fkfb-preview-name { flex: 1; min-width: 0; font-size: 13px; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fkfb-preview-remove { width: 30px; height: 30px; display: grid; place-items: center; border: 0; border-radius: 8px; background: transparent; color: #64748b; cursor: pointer; }
.fkfb-preview-remove:hover { background: #fee2e2; color: #b91c1c; }

.fkfb-hp { position: absolute !important; left: -10000px; width: 1px; height: 1px; overflow: hidden; }

.fkfb-alert { margin: 0 0 14px; padding: 10px 12px; border-radius: 10px; background: #fef2f2; color: #b91c1c; font-size: 13px; }
.fkfb-alert[hidden] { display: none; }

.fkfb-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
.fkfb-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 20px;
    border-radius: 10px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s, border-color .15s, opacity .15s;
}
.fkfb-btn--ghost { background: #fff; border-color: #e2e8f0; color: #334155; }
.fkfb-btn--ghost:hover { background: #f8fafc; }
.fkfb-btn--primary { background: #E91E63; color: #fff; }
.fkfb-btn--primary:hover { background: #c2185b; }
.fkfb-btn:focus-visible { outline: 3px solid rgba(233, 30, 99, .4); outline-offset: 2px; }
.fkfb-btn[disabled] { opacity: .7; cursor: progress; }
.fkfb-spinner { display: none; width: 15px; height: 15px; border: 2px solid rgba(255, 255, 255, .45); border-top-color: #fff; border-radius: 50%; animation: fkfb-spin .7s linear infinite; }
.fkfb-btn.is-loading .fkfb-spinner { display: inline-block; }
@keyframes fkfb-spin { to { transform: rotate(360deg); } }

.fkfb-success { padding: 18px 4px 6px; text-align: center; }
.fkfb-success[hidden] { display: none; }
.fkfb-success:focus { outline: none; }
.fkfb-success-icon { display: inline-grid; place-items: center; width: 60px; height: 60px; margin-bottom: 16px; border-radius: 50%; background: #dcfce7; color: #15803d; animation: fkfb-pop .35s cubic-bezier(.2, .8, .2, 1.3); }
.fkfb-success .fkfb-subtitle { margin: 0 auto 22px; max-width: 340px; }
@keyframes fkfb-pop { from { transform: scale(.6); opacity: 0; } to { transform: none; opacity: 1; } }

html.fkfb-lock, html.fkfb-lock body { overflow: hidden; }

@media (max-width: 575px) {
    .fkfb-tab { padding: 11px 6px; font-size: 12px; top: 64%; }
    .fkfb-backdrop { align-items: flex-end; padding: 0; }
    .fkfb-dialog { max-width: none; max-height: 92vh; border-radius: 18px 18px 0 0; padding: 22px 18px 18px; transform: translateY(40px); }
    .fkfb-types { grid-template-columns: repeat(2, 1fr); }
    .fkfb-type-inner { flex-direction: row; justify-content: center; padding: 10px 6px; }
    .fkfb-actions .fkfb-btn { flex: 1; }
}
@media (prefers-reduced-motion: reduce) {
    .fkfb-backdrop, .fkfb-dialog, .fkfb-tab { transition: none; }
    .fkfb-success-icon, .fkfb-spinner { animation: none; }
}
</style>

<script>
(function () {
    var MAX_BYTES = {{ $fbMaxMb }} * 1024 * 1024;
    var TYPES = ['image/png', 'image/jpeg', 'image/webp'];
    var MSG = @json($fbErrors);
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    var opener   = document.getElementById('fkfb-open');
    var backdrop = document.getElementById('fkfb-backdrop');
    if (!opener || !backdrop) return;

    var dialog   = document.getElementById('fkfb-dialog');
    var form     = document.getElementById('fkfb-form');
    var success  = document.getElementById('fkfb-success');
    var message  = document.getElementById('fkfb-message');
    var email    = document.getElementById('fkfb-email');
    var count    = document.getElementById('fkfb-count');
    var fileIn   = document.getElementById('fkfb-file');
    var drop     = document.getElementById('fkfb-drop');
    var preview  = document.getElementById('fkfb-preview');
    var prevImg  = document.getElementById('fkfb-preview-img');
    var prevName = document.getElementById('fkfb-preview-name');
    var alertBox = document.getElementById('fkfb-alert');
    var submit   = document.getElementById('fkfb-submit');
    var file = null, previewUrl = null, lastFocus = null, closeTimer = null;

    function setError(field, text) {
        var el = document.getElementById('fkfb-' + field + '-error');
        if (el) el.textContent = text || '';
        var input = field === 'screenshot' ? fileIn : document.getElementById('fkfb-' + field);
        if (input) input.setAttribute('aria-invalid', text ? 'true' : 'false');
    }
    function clearErrors() {
        ['message', 'email', 'screenshot'].forEach(function (f) { setError(f, ''); });
        alertBox.hidden = true;
        alertBox.textContent = '';
    }

    function setFile(f) {
        setError('screenshot', '');
        if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
        file = null;
        if (f) {
            if (TYPES.indexOf(f.type) === -1) { setError('screenshot', MSG.screenshot_type); }
            else if (f.size > MAX_BYTES) { setError('screenshot', MSG.screenshot_size); }
            else { file = f; }
        }
        if (file) {
            previewUrl = URL.createObjectURL(file);
            prevImg.src = previewUrl;
            prevName.textContent = file.name || 'capture.png';
        }
        preview.hidden = !file;
        drop.hidden = !!file;
        fileIn.value = '';
    }

    function validate() {
        var errors = {};
        var m = message.value.trim();
        if (!m) errors.message = MSG.message_required;
        else if (m.length < 5) errors.message = MSG.message_min;
        else if (m.length > 5000) errors.message = MSG.message_max;
        var e = email.value.trim();
        if (!e) errors.email = MSG.email_required;
        else if (!EMAIL_RE.test(e)) errors.email = MSG.email_invalid;
        return errors;
    }

    function showErrors(errors) {
        ['message', 'email', 'screenshot'].forEach(function (f) { setError(f, errors[f] || ''); });
        var first = ['message', 'email'].filter(function (f) { return errors[f]; })[0];
        if (first) document.getElementById('fkfb-' + first).focus();
    }

    function serverErrors(bag) {
        var out = {};
        if (bag.message) { var m = message.value.trim(); out.message = !m ? MSG.message_required : (m.length < 5 ? MSG.message_min : MSG.message_max); }
        if (bag.email) out.email = email.value.trim() ? MSG.email_invalid : MSG.email_required;
        if (bag.screenshot) out.screenshot = file && file.size > MAX_BYTES ? MSG.screenshot_size : MSG.screenshot_type;
        return out;
    }

    function setLoading(on) {
        submit.disabled = on;
        submit.classList.toggle('is-loading', on);
        submit.setAttribute('aria-busy', on ? 'true' : 'false');
    }

    function focusables() {
        return Array.prototype.filter.call(
            dialog.querySelectorAll('button, [href], input:not([tabindex="-1"]), textarea, select, [tabindex]:not([tabindex="-1"])'),
            function (el) { return !el.disabled && el.offsetParent !== null; }
        );
    }

    function open() {
        clearTimeout(closeTimer);
        lastFocus = document.activeElement;
        if (!success.hidden) reset();
        backdrop.hidden = false;
        document.documentElement.classList.add('fkfb-lock');
        opener.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(function () { backdrop.classList.add('is-open'); });
        setTimeout(function () { message.focus(); }, 60);
    }

    function close() {
        backdrop.classList.remove('is-open');
        opener.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('fkfb-lock');
        closeTimer = setTimeout(function () {
            backdrop.hidden = true;
            if (!success.hidden) reset();
        }, 220);
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    function reset() {
        form.reset();
        setFile(null);
        clearErrors();
        count.textContent = '0 / 5000';
        form.hidden = false;
        success.hidden = true;
    }

    opener.addEventListener('click', open);
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop || e.target.closest('[data-fkfb-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
        if (backdrop.hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); close(); return; }
        if (e.key === 'Tab') {
            var list = focusables();
            if (!list.length) return;
            var first = list[0], last = list[list.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    message.addEventListener('input', function () {
        count.textContent = message.value.length + ' / 5000';
        if (message.getAttribute('aria-invalid') === 'true') setError('message', '');
    });
    email.addEventListener('input', function () {
        if (email.getAttribute('aria-invalid') === 'true') setError('email', '');
    });

    fileIn.addEventListener('change', function () { setFile(fileIn.files && fileIn.files[0]); });
    document.getElementById('fkfb-remove').addEventListener('click', function () { setFile(null); fileIn.focus(); });
    ['dragenter', 'dragover'].forEach(function (t) {
        drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-drag'); });
    });
    ['dragleave', 'drop'].forEach(function (t) {
        drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); });
    });
    drop.addEventListener('drop', function (e) { setFile(e.dataTransfer.files && e.dataTransfer.files[0]); });
    // Pasting an image anywhere in the dialog attaches it as the screenshot.
    dialog.addEventListener('paste', function (e) {
        var items = (e.clipboardData && e.clipboardData.files) || [];
        if (items.length && items[0].type.indexOf('image/') === 0) { e.preventDefault(); setFile(items[0]); }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (submit.disabled) return;
        clearErrors();

        var errors = validate();
        if (Object.keys(errors).length) { showErrors(errors); return; }

        var data = new FormData(form);
        data.delete('screenshot');
        if (file) data.append('screenshot', file, file.name || 'capture.png');
        data.append('page_url', window.location.href);

        setLoading(true);
        fetch(form.action, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 201) {
                form.hidden = true;
                success.hidden = false;
                success.focus();
                return;
            }
            if (res.status === 422) {
                return res.json().then(function (body) { showErrors(serverErrors((body && body.errors) || {})); });
            }
            alertBox.textContent = res.status === 429 ? MSG.rate_limited : MSG.generic;
            alertBox.hidden = false;
        }).catch(function () {
            alertBox.textContent = MSG.generic;
            alertBox.hidden = false;
        }).then(function () { setLoading(false); });
    });
})();
</script>
