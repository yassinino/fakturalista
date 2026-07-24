<template>
  <div class="fl-root" :class="{ 'fl-dark': isDark }">

    <!-- ══ LEFT - Hero Panel ══════════════════════════════════ -->
    <div class="fl-hero" aria-hidden="true">

      <!-- Decorative blobs -->
      <div class="fl-blob fl-blob-1"></div>
      <div class="fl-blob fl-blob-2"></div>
      <div class="fl-blob fl-blob-3"></div>

      <!-- Dot grid overlay -->
      <div class="fl-dots"></div>

      <div class="fl-hero-inner">

        <!-- Logo -->
        <div class="fl-logo">
          <span class="fl-logo-icon">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
              <rect width="32" height="32" rx="8" fill="url(#logoGrad)"/>
              <path d="M8 10h16M8 16h10M8 22h13" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
              <defs>
                <linearGradient id="logoGrad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#E91E63"/>
                  <stop offset="1" stop-color="#c2185b"/>
                </linearGradient>
              </defs>
            </svg>
          </span>
          <span class="fl-logo-name">Fakturalista</span>
        </div>

        <!-- Main headline -->
        <div class="fl-hero-copy">
          <h1 class="fl-headline">
            Create invoices<br>
            <span class="fl-headline-accent">in 15 seconds</span>
          </h1>
          <p class="fl-subheadline">
            Send invoices, manage quotes, receive payments<br class="d-none d-xl-block">
            and grow your business.
          </p>
        </div>

        <!-- Feature cards -->
        <div class="fl-features">
          <div class="fl-feat fl-feat--delay-0">
            <span class="fl-feat-icon">⚡</span>
            <div>
              <div class="fl-feat-title">AI invoice generation</div>
              <div class="fl-feat-desc">Generate in seconds, not minutes</div>
            </div>
          </div>
          <div class="fl-feat fl-feat--delay-1">
            <span class="fl-feat-icon">💰</span>
            <div>
              <div class="fl-feat-title">Get paid faster</div>
              <div class="fl-feat-desc">Online payments & payment links</div>
            </div>
          </div>
          <div class="fl-feat fl-feat--delay-2">
            <span class="fl-feat-icon">📊</span>
            <div>
              <div class="fl-feat-title">Track your business</div>
              <div class="fl-feat-desc">Real-time revenue insights</div>
            </div>
          </div>
        </div>

        <!-- Social proof -->
        <div class="fl-proof">
          <div class="fl-proof-avatars">
            <div class="fl-av fl-av-1">Y</div>
            <div class="fl-av fl-av-2">M</div>
            <div class="fl-av fl-av-3">S</div>
            <div class="fl-av fl-av-4">A</div>
          </div>
          <span class="fl-proof-text">Trusted by <strong>Many</strong> freelancers &amp; businesses</span>
        </div>

      </div>
    </div>

    <!-- ══ RIGHT - Auth Panel ═════════════════════════════════ -->
    <div class="fl-panel">
      <div class="fl-card-wrap">

        <!-- Mobile logo (visible only on small screens) -->
        <div class="fl-mobile-logo">
          <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill="url(#logoGradM)"/>
            <path d="M8 10h16M8 16h10M8 22h13" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
            <defs>
              <linearGradient id="logoGradM" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                <stop stop-color="#E91E63"/>
                <stop offset="1" stop-color="#c2185b"/>
              </linearGradient>
            </defs>
          </svg>
          <span>Fakturalista</span>
        </div>

        <!-- Auth card -->
        <div class="fl-card">

          <div class="fl-card-header">
            <h2 class="fl-card-title">{{ $t('auth.signInTitle') }}</h2>
            <p class="fl-card-subtitle">Enter your credentials to access your workspace</p>
          </div>

          <!-- Error alert -->
          <div v-if="errorMessage" class="fl-alert" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i>{{ errorMessage }}
          </div>

          <!-- Form -->
          <form @submit.prevent="onSubmit" novalidate>

            <!-- Email -->
            <div class="fl-field" :class="{ 'fl-field--error': v$.email.$errors.length }">
              <label class="fl-label" for="login-email">{{ $t('auth.emailPlaceholder') }}</label>
              <div class="fl-input-wrap">
                <i class="fa fa-envelope fl-input-icon"></i>
                <input
                  id="login-email"
                  name="login-email"
                  type="email"
                  class="fl-input"
                  :placeholder="$t('auth.emailPlaceholder')"
                  v-model="state.email"
                  @blur="v$.email.$touch"
                  autocomplete="email"
                  inputmode="email"
                />
              </div>
              <div v-if="v$.email.$errors.length" class="fl-field-error">
                <i class="fa fa-circle-xmark me-1"></i>{{ $t('auth.required') }}
              </div>
            </div>

            <!-- Password -->
            <div class="fl-field" :class="{ 'fl-field--error': v$.password.$errors.length }">
              <label class="fl-label" for="login-password">{{ $t('auth.passwordPlaceholder') }}</label>
              <div class="fl-input-wrap">
                <i class="fa fa-lock fl-input-icon"></i>
                <input
                  id="login-password"
                  name="login-password"
                  :type="showPassword ? 'text' : 'password'"
                  class="fl-input fl-input--password"
                  :placeholder="$t('auth.passwordPlaceholder')"
                  v-model="state.password"
                  @blur="v$.password.$touch"
                  autocomplete="current-password"
                />
                <button
                  type="button"
                  class="fl-pw-toggle"
                  @click="showPassword = !showPassword"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  tabindex="-1"
                >
                  <i :class="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
                </button>
              </div>
              <div v-if="v$.password.$errors.length" class="fl-field-error">
                <i class="fa fa-circle-xmark me-1"></i>{{ $t('auth.required') }}
              </div>
            </div>

            <!-- Remember me + Forgot password -->
            <div class="fl-row-opts">
              <label class="fl-remember">
                <input type="checkbox" v-model="rememberMe" class="fl-checkbox">
                <span class="fl-remember-label">Remember me</span>
              </label>
              <router-link to="/admin/forgot-password" class="fl-forgot">{{ $t('auth.forgotPassword') }}</router-link>
            </div>

            <!-- Submit -->
            <button
              type="submit"
              class="fl-btn-submit"
              :class="{ 'fl-btn-submit--loading': isLoading }"
              :disabled="isLoading"
            >
              <span v-if="!isLoading" class="fl-btn-text">
                <i class="fa fa-sign-in-alt me-2"></i>{{ $t('auth.submit') }}
              </span>
              <span v-else class="fl-btn-text">
                <i class="fa fa-spinner fa-spin me-2"></i>Signing in…
              </span>
            </button>

          </form>

          <!-- Divider -->


        </div>
        <!-- END Auth card -->

      </div>
    </div>

  </div>
</template>


<script setup>
import { reactive, computed, ref } from "vue";
import { useTemplateStore } from "@/stores/template";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { setLocale } from "@/i18n";

import useVuelidate from "@vuelidate/core";
import { required, minLength, email } from "@vuelidate/validators";

const store        = useTemplateStore();
const { t }        = useI18n();
const errorMessage = ref("");
const isLoading    = ref(false);
const showPassword = ref(false);
const rememberMe   = ref(false);

const isDark = computed(() => store.settings.darkMode);

const state = reactive({
  email:    null,
  password: null,
});

const rules = computed(() => ({
  email:    { required, email, minLength: minLength(3) },
  password: { required, minLength: minLength(5) },
}));

const v$ = useVuelidate(rules, state);

async function onSubmit() {
  const result = await v$.value.$validate();
  if (!result) return;

  errorMessage.value = "";
  isLoading.value    = true;

  axios
    .post("/login", state)
    .then((res) => {
      store.loginUser(res.data.data);
      setLocale(res.data.data?.user?.locale || "es");
      window.location.href = "/admin/dashboard";
    })
    .catch((error) => {
      isLoading.value = false;
      if (error.response?.status === 401) {
        errorMessage.value = t("auth.invalidCredentials");
        return;
      }
      errorMessage.value = t("auth.failed");
    });
}
</script>


<style scoped>
/* ══════════════════════════════════════════════════════════════
   ROOT
══════════════════════════════════════════════════════════════ */
.fl-root {
  display: flex;
  min-height: 100vh;
  width: 100%;
  --brand:        #E91E63;
  --brand-dark:   #c2185b;
  --hero-bg:      #0d1117;
  --hero-text:    rgba(255,255,255,0.85);
  --hero-muted:   rgba(255,255,255,0.5);
  --panel-bg:     #f5f6fa;
  --card-bg:      #ffffff;
  --card-border:  rgba(0,0,0,0.07);
  --card-shadow:  0 4px 6px -1px rgba(0,0,0,0.07), 0 24px 60px -12px rgba(0,0,0,0.12);
  --text-primary: #0f172a;
  --text-muted:   #64748b;
  --input-bg:     #f8fafc;
  --input-border: #e2e8f0;
  --input-focus:  #E91E63;
  --divider:      #e2e8f0;
}

/* Dark mode overrides */
.fl-dark {
  --panel-bg:     #0d1117;
  --card-bg:      #161b22;
  --card-border:  rgba(255,255,255,0.06);
  --card-shadow:  0 4px 6px -1px rgba(0,0,0,0.4), 0 24px 60px -12px rgba(0,0,0,0.5);
  --text-primary: #f0f6fc;
  --text-muted:   #8b949e;
  --input-bg:     #0d1117;
  --input-border: rgba(255,255,255,0.1);
  --divider:      rgba(255,255,255,0.08);
}

/* ══════════════════════════════════════════════════════════════
   HERO (LEFT PANEL)
══════════════════════════════════════════════════════════════ */
.fl-hero {
  flex: 0 0 55%;
  background: var(--hero-bg);
  position: relative;
  overflow: hidden;
  display: none; /* hidden on mobile */
}

@media (min-width: 1024px) {
  .fl-hero { display: flex; align-items: center; justify-content: center; }
}

/* Animated gradient blobs */
.fl-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.18;
  animation: blobFloat 12s ease-in-out infinite alternate;
}

.fl-blob-1 {
  width: 500px; height: 500px;
  background: radial-gradient(circle, #E91E63, #9c27b0);
  top: -120px; left: -100px;
  animation-delay: 0s;
}

.fl-blob-2 {
  width: 380px; height: 380px;
  background: radial-gradient(circle, #3b82f6, #8b5cf6);
  bottom: -80px; right: -60px;
  animation-delay: -4s;
}

.fl-blob-3 {
  width: 260px; height: 260px;
  background: radial-gradient(circle, #06b6d4, #3b82f6);
  top: 50%; left: 55%;
  transform: translate(-50%, -50%);
  animation-delay: -8s;
}

@keyframes blobFloat {
  0%   { transform: translate(0, 0) scale(1); }
  50%  { transform: translate(20px, -20px) scale(1.05); }
  100% { transform: translate(-10px, 10px) scale(0.97); }
}

/* Dot grid */
.fl-dots {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
}

.fl-hero-inner {
  position: relative;
  z-index: 2;
  padding: 48px 52px;
  max-width: 560px;
  width: 100%;
}

/* Logo */
.fl-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 52px;
}

.fl-logo-icon {
  display: flex;
  flex-shrink: 0;
  filter: drop-shadow(0 4px 12px rgba(233,30,99,0.4));
}

.fl-logo-name {
  font-size: 18px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.3px;
}

/* Headline */
.fl-hero-copy {
  margin-bottom: 40px;
}

.fl-headline {
  font-size: clamp(28px, 3.2vw, 42px);
  font-weight: 800;
  color: #fff;
  line-height: 1.15;
  letter-spacing: -1px;
  margin: 0 0 14px;
}

.fl-headline-accent {
  background: linear-gradient(135deg, #E91E63, #f472b6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.fl-subheadline {
  font-size: 15px;
  color: var(--hero-muted);
  line-height: 1.7;
  margin: 0;
}

/* Feature cards */
.fl-features {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 40px;
}

.fl-feat {
  display: flex;
  align-items: center;
  gap: 14px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 14px 18px;
  backdrop-filter: blur(8px);
  animation: featIn 0.5s ease both;
  transition: border-color 0.2s, background 0.2s;
}

.fl-feat:hover {
  background: rgba(255,255,255,0.08);
  border-color: rgba(233,30,99,0.3);
}

.fl-feat--delay-0 { animation-delay: 0.1s; }
.fl-feat--delay-1 { animation-delay: 0.2s; }
.fl-feat--delay-2 { animation-delay: 0.3s; }

@keyframes featIn {
  from { opacity: 0; transform: translateX(-16px); }
  to   { opacity: 1; transform: translateX(0); }
}

.fl-feat-icon {
  font-size: 22px;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.07);
  border-radius: 10px;
}

.fl-feat-title {
  font-size: 13.5px;
  font-weight: 700;
  color: #fff;
  margin-bottom: 2px;
}

.fl-feat-desc {
  font-size: 12px;
  color: var(--hero-muted);
}

/* Social proof */
.fl-proof {
  display: flex;
  align-items: center;
  gap: 12px;
}

.fl-proof-avatars {
  display: flex;
}

.fl-av {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: 2px solid #0d1117;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  color: #fff;
  margin-left: -8px;
}

.fl-av:first-child { margin-left: 0; }
.fl-av-1 { background: linear-gradient(135deg, #E91E63, #c2185b); }
.fl-av-2 { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.fl-av-3 { background: linear-gradient(135deg, #10b981, #059669); }
.fl-av-4 { background: linear-gradient(135deg, #f59e0b, #d97706); }

.fl-proof-text {
  font-size: 12.5px;
  color: var(--hero-muted);
}

.fl-proof-text strong {
  color: #fff;
  font-weight: 700;
}

/* ══════════════════════════════════════════════════════════════
   RIGHT PANEL
══════════════════════════════════════════════════════════════ */
.fl-panel {
  flex: 1;
  background: var(--panel-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 20px;
  min-height: 100vh;
  transition: background 0.3s;
}

.fl-card-wrap {
  width: 100%;
  max-width: 420px;
}

/* Mobile logo */
.fl-mobile-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  margin-bottom: 28px;
  font-size: 16px;
  font-weight: 800;
  color: var(--text-primary);
}

@media (min-width: 1024px) {
  .fl-mobile-logo { display: none; }
}

/* ══════════════════════════════════════════════════════════════
   AUTH CARD
══════════════════════════════════════════════════════════════ */
.fl-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 20px;
  box-shadow: var(--card-shadow);
  padding: 36px 36px 28px;
  transition: background 0.3s, border-color 0.3s;
  animation: cardIn 0.45s ease both;
}

@keyframes cardIn {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.fl-card-header {
  margin-bottom: 24px;
}

.fl-card-title {
  font-size: 22px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 6px;
  letter-spacing: -0.4px;
}

.fl-card-subtitle {
  font-size: 13.5px;
  color: var(--text-muted);
  margin: 0;
  line-height: 1.5;
}

/* ══════════════════════════════════════════════════════════════
   ERROR ALERT
══════════════════════════════════════════════════════════════ */
.fl-alert {
  background: rgba(239,68,68,0.08);
  border: 1px solid rgba(239,68,68,0.2);
  color: #ef4444;
  border-radius: 10px;
  padding: 11px 14px;
  font-size: 13.5px;
  font-weight: 500;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  animation: alertIn 0.25s ease;
}

@keyframes alertIn {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ══════════════════════════════════════════════════════════════
   FORM FIELDS
══════════════════════════════════════════════════════════════ */
.fl-field {
  margin-bottom: 18px;
}

.fl-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 7px;
}

.fl-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.fl-input-icon {
  position: absolute;
  left: 13px;
  color: var(--text-muted);
  font-size: 13px;
  pointer-events: none;
  transition: color 0.2s;
}

.fl-input {
  width: 100%;
  padding: 11px 14px 11px 38px;
  border: 1.5px solid var(--input-border);
  border-radius: 10px;
  background: var(--input-bg);
  color: var(--text-primary);
  font-size: 14px;
  font-family: inherit;
  outline: none;
  transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
  -webkit-appearance: none;
}

.fl-input--password {
  padding-right: 42px;
}

.fl-input::placeholder {
  color: var(--text-muted);
  opacity: 0.6;
}

.fl-input:focus {
  border-color: var(--input-focus);
  box-shadow: 0 0 0 3px rgba(233,30,99,0.1);
  background: var(--card-bg);
}

.fl-input:focus ~ .fl-input-icon,
.fl-input-wrap:focus-within .fl-input-icon {
  color: var(--input-focus);
}

/* Error state */
.fl-field--error .fl-input {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
}

.fl-field-error {
  font-size: 12px;
  color: #ef4444;
  margin-top: 5px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 4px;
  animation: errIn 0.2s ease;
}

@keyframes errIn {
  from { opacity: 0; transform: translateY(-3px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Password toggle */
.fl-pw-toggle {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  line-height: 1;
  font-size: 14px;
  transition: color 0.15s;
  border-radius: 4px;
}

.fl-pw-toggle:hover  { color: var(--text-primary); }
.fl-pw-toggle:focus  { outline: 2px solid rgba(233,30,99,0.3); }

/* ══════════════════════════════════════════════════════════════
   ROW - REMEMBER ME + FORGOT PASSWORD
══════════════════════════════════════════════════════════════ */
.fl-row-opts {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
  margin-top: -4px;
}

.fl-remember {
  display: flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
  user-select: none;
}

.fl-checkbox {
  width: 15px;
  height: 15px;
  accent-color: var(--brand);
  cursor: pointer;
  border-radius: 4px;
  flex-shrink: 0;
}

.fl-remember-label {
  font-size: 13px;
  color: var(--text-muted);
}

.fl-forgot {
  font-size: 13px;
  color: var(--brand);
  font-weight: 600;
  text-decoration: none;
  transition: opacity 0.15s;
}

.fl-forgot:hover { opacity: 0.75; }

/* ══════════════════════════════════════════════════════════════
   SUBMIT BUTTON
══════════════════════════════════════════════════════════════ */
.fl-btn-submit {
  width: 100%;
  padding: 13px 24px;
  background: linear-gradient(135deg, #E91E63 0%, #c2185b 100%);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s;
  box-shadow: 0 4px 14px rgba(233,30,99,0.3);
  letter-spacing: 0.01em;
}

.fl-btn-text {
  display: flex;
  align-items: center;
  gap: 8px;
}

.fl-btn-submit:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(233,30,99,0.4);
}

.fl-btn-submit:active:not(:disabled) {
  transform: translateY(0);
  box-shadow: 0 2px 8px rgba(233,30,99,0.25);
}

.fl-btn-submit:disabled,
.fl-btn-submit--loading {
  opacity: 0.75;
  cursor: not-allowed;
  transform: none !important;
}

.fl-btn-submit:focus-visible {
  outline: 3px solid rgba(233,30,99,0.4);
  outline-offset: 2px;
}

/* ══════════════════════════════════════════════════════════════
   DIVIDER
══════════════════════════════════════════════════════════════ */
.fl-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 22px 0 16px;
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.fl-divider::before,
.fl-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--divider);
}

/* ══════════════════════════════════════════════════════════════
   SOCIAL BUTTONS
══════════════════════════════════════════════════════════════ */
.fl-socials {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 22px;
}

.fl-social-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  background: var(--input-bg);
  border: 1.5px solid var(--input-border);
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text-primary);
  font-family: inherit;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, transform 0.15s;
  opacity: 0.6;
}

.fl-social-btn:not(:disabled):hover {
  background: var(--card-bg);
  border-color: var(--brand);
  transform: translateY(-1px);
  opacity: 1;
}

.fl-social-btn:disabled {
  cursor: not-allowed;
}

/* ══════════════════════════════════════════════════════════════
   CARD FOOTER
══════════════════════════════════════════════════════════════ */
.fl-card-footer {
  text-align: center;
  font-size: 13.5px;
  color: var(--text-muted);
  margin: 0;
}

.fl-signup-link {
  color: var(--brand);
  font-weight: 700;
  text-decoration: none;
  margin-left: 4px;
  transition: opacity 0.15s;
}

.fl-signup-link:hover { opacity: 0.75; }
</style>
