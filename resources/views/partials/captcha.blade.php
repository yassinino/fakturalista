@php
    $captchaInputClass = $captchaInputClass ?? '';
    $captchaLabelClass = $captchaLabelClass ?? '';
    $captchaFieldClass = $captchaFieldClass ?? '';
@endphp
<div class="{{ $captchaFieldClass }} mc-captcha-field" data-mc-captcha>
    <label class="{{ $captchaLabelClass }}" for="mc-captcha-answer">
        {{ __('site.captcha.label') }}
    </label>

    <div class="mc-captcha-row" id="mc-captcha-equation">
        <span class="mc-captcha-num" data-mc-captcha-a>{{ $captcha['a'] }}</span>
        <span class="mc-captcha-op">+</span>
        <span class="mc-captcha-num" data-mc-captcha-b>{{ $captcha['b'] }}</span>
        <span class="mc-captcha-eq">=</span>
        <input
            type="text"
            inputmode="numeric"
            autocomplete="off"
            class="{{ $captchaInputClass }} mc-captcha-input @error('captcha_answer') is-invalid @enderror"
            id="mc-captcha-answer"
            name="captcha_answer"
            placeholder="{{ __('site.captcha.placeholder') }}"
            aria-describedby="mc-captcha-equation mc-captcha-error"
            aria-invalid="{{ $errors->has('captcha_answer') ? 'true' : 'false' }}"
            required
        >
    </div>

    <p id="mc-captcha-error"
       class="mc-captcha-error"
       role="alert"
       @if(!$errors->has('captcha_answer')) style="display:none" @endif>
        {{ $errors->first('captcha_answer') }}
    </p>
</div>

<style>
    .mc-captcha-field {
        margin-bottom: 20px;
    }
    .mc-captcha-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 6px;
    }
    .mc-captcha-num,
    .mc-captcha-op,
    .mc-captcha-eq {
        font-size: 15px;
        font-weight: 600;
        color: #1a1a2e;
    }
    .mc-captcha-input {
        width: 60%;
        flex: 0 0 auto;
    }
    .mc-captcha-error {
        margin: 6px 0 0;
        font-size: 13px;
        color: #d9534f;
    }
</style>

<script>
(function () {
    function updateCaptcha(root, data) {
        if (!data) return;

        if (data.captcha) {
            var aEl = root.querySelector('[data-mc-captcha-a]');
            var bEl = root.querySelector('[data-mc-captcha-b]');
            if (aEl) aEl.textContent = data.captcha.a;
            if (bEl) bEl.textContent = data.captcha.b;
        }

        var input   = root.querySelector('input[name="captcha_answer"]');
        var errorEl = root.querySelector('.mc-captcha-error');
        var message = (data.errors && data.errors.captcha_answer) ? data.errors.captcha_answer[0] : null;

        if (input) {
            input.value = '';
            input.classList.toggle('is-invalid', !!message);
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
        }
        if (errorEl) {
            errorEl.textContent = message || '';
            errorEl.style.display = message ? 'block' : 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var roots = document.querySelectorAll('[data-mc-captcha]');
        if (!roots.length || !window.jQuery) return;

        window.jQuery(document).ajaxComplete(function (event, xhr) {
            var data = xhr && xhr.responseJSON;
            if (!data || !('captcha' in data)) return;

            roots.forEach(function (root) {
                updateCaptcha(root, data);
            });
        });
    });
})();
</script>
