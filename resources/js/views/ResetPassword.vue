<template>
  <div class="rp-root" :class="{ 'rp-dark': isDark }">

    <!-- Background blobs -->
    <div class="rp-blob rp-blob-1"></div>
    <div class="rp-blob rp-blob-2"></div>
    <div class="rp-dots"></div>

    <div class="rp-wrap">

      <!-- Logo -->
      <div class="rp-logo">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="8" fill="url(#rpLogoGrad)"/>
          <path d="M8 10h16M8 16h10M8 22h13" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
          <defs>
            <linearGradient id="rpLogoGrad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
              <stop stop-color="#E91E63"/>
              <stop offset="1" stop-color="#c2185b"/>
            </linearGradient>
          </defs>
        </svg>
        <span>Fakturalista</span>
      </div>

      <!-- Invalid/missing token state -->
      <div v-if="!token || !email" class="rp-card">
        <div class="rp-card-header rp-text-center">
          <div class="rp-icon-wrap rp-icon-wrap--danger">
            <i class="fa fa-triangle-exclamation"></i>
          </div>
          <h2 class="rp-card-title">Invalid reset link</h2>
          <p class="rp-card-subtitle">
            This password reset link is missing required parameters.
            Please request a new one.
          </p>
        </div>
        <router-link to="/admin/forgot-password" class="rp-btn-submit" style="text-decoration:none;display:flex;">
          <i class="fa fa-arrow-rotate-left me-2"></i>Request a new link
        </router-link>
        <div class="rp-back">
          <router-link to="/admin/login" class="rp-back-link">
            <i class="fa fa-arrow-left me-1"></i>Back to sign in
          </router-link>
        </div>
      </div>

      <!-- Success state -->
      <div v-else-if="success" class="rp-card">
        <div class="rp-success">
          <div class="rp-success-icon">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="12" fill="rgba(34,197,94,0.12)"/>
              <path d="M7 12.5l3.5 3.5 6.5-7" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h2 class="rp-card-title">Password updated!</h2>
          <p class="rp-success-text">
            Your password has been reset successfully.
            You can now sign in with your new password.
          </p>
          <router-link to="/admin/login" class="rp-btn-submit" style="text-decoration:none;display:flex;margin-top:8px;">
            <i class="fa fa-sign-in-alt me-2"></i>Sign in now
          </router-link>
        </div>
      </div>

      <!-- Form state -->
      <div v-else class="rp-card">

        <div class="rp-card-header">
          <div class="rp-icon-wrap">
            <i class="fa fa-lock"></i>
          </div>
          <h2 class="rp-card-title">Set new password</h2>
          <p class="rp-card-subtitle">
            Choose a strong password for
            <strong class="rp-email-hint">{{ email }}</strong>
          </p>
        </div>

        <!-- Error alert -->
        <div v-if="errorMessage" class="rp-alert" role="alert">
          <i class="fa fa-exclamation-circle me-2"></i>{{ errorMessage }}
          <div v-if="tokenExpired" style="margin-top:8px;">
            <router-link to="/admin/forgot-password" class="rp-alert-link">
              Request a new reset link →
            </router-link>
          </div>
        </div>

        <form @submit.prevent="onSubmit" novalidate>

          <!-- New password -->
          <div class="rp-field" :class="{ 'rp-field--error': passwordError }">
            <label class="rp-label" for="rp-password">New password</label>
            <div class="rp-input-wrap">
              <i class="fa fa-lock rp-input-icon"></i>
              <input
                id="rp-password"
                :type="showPw ? 'text' : 'password'"
                class="rp-input rp-input--pw"
                placeholder="Min. 8 characters"
                v-model="password"
                @blur="touchPassword"
                autocomplete="new-password"
                :disabled="isLoading"
              />
              <button
                type="button"
                class="rp-pw-toggle"
                @click="showPw = !showPw"
                :aria-label="showPw ? 'Hide password' : 'Show password'"
                tabindex="-1"
              >
                <i :class="showPw ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
              </button>
            </div>
            <div v-if="passwordError" class="rp-field-error">
              <i class="fa fa-circle-xmark me-1"></i>{{ passwordError }}
            </div>
            <!-- Password strength -->
            <div v-if="password" class="rp-strength">
              <div class="rp-strength-bars">
                <span
                  v-for="i in 4"
                  :key="i"
                  class="rp-strength-bar"
                  :class="{ 'rp-strength-bar--active': passwordStrength >= i, ['rp-strength-bar--lvl-' + passwordStrength]: passwordStrength >= i }"
                ></span>
              </div>
              <span class="rp-strength-label" :class="'rp-strength-label--' + passwordStrength">
                {{ strengthLabel }}
              </span>
            </div>
          </div>

          <!-- Confirm password -->
          <div class="rp-field" :class="{ 'rp-field--error': confirmError }">
            <label class="rp-label" for="rp-confirm">Confirm password</label>
            <div class="rp-input-wrap">
              <i class="fa fa-lock rp-input-icon"></i>
              <input
                id="rp-confirm"
                :type="showConfirm ? 'text' : 'password'"
                class="rp-input rp-input--pw"
                placeholder="Repeat your password"
                v-model="passwordConfirmation"
                @blur="touchConfirm"
                autocomplete="new-password"
                :disabled="isLoading"
              />
              <button
                type="button"
                class="rp-pw-toggle"
                @click="showConfirm = !showConfirm"
                :aria-label="showConfirm ? 'Hide password' : 'Show password'"
                tabindex="-1"
              >
                <i :class="showConfirm ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
              </button>
            </div>
            <div v-if="confirmError" class="rp-field-error">
              <i class="fa fa-circle-xmark me-1"></i>{{ confirmError }}
            </div>
          </div>

          <button
            type="submit"
            class="rp-btn-submit"
            :disabled="isLoading"
          >
            <i v-if="isLoading" class="fa fa-spinner fa-spin me-2"></i>
            <i v-else class="fa fa-shield-halved me-2"></i>
            {{ isLoading ? 'Updating…' : 'Reset password' }}
          </button>

        </form>

        <div class="rp-back">
          <router-link to="/admin/login" class="rp-back-link">
            <i class="fa fa-arrow-left me-1"></i>Back to sign in
          </router-link>
        </div>

      </div>
      <!-- END Card -->

    </div>
  </div>
</template>


<script setup>
import { ref, computed } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import { useTemplateStore } from "@/stores/template";

const store  = useTemplateStore();
const route  = useRoute();
const isDark = computed(() => store.settings.darkMode);

// Pull token and email from the query string
const token = route.query.token ?? "";
const email = route.query.email ?? "";

const password             = ref("");
const passwordConfirmation = ref("");
const passwordTouched      = ref(false);
const confirmTouched       = ref(false);
const showPw               = ref(false);
const showConfirm          = ref(false);
const isLoading            = ref(false);
const errorMessage         = ref("");
const tokenExpired         = ref(false);
const success              = ref(false);

// ── Password strength ──────────────────────────────────────

const passwordStrength = computed(() => {
  const pw = password.value;
  if (!pw) return 0;
  let score = 0;
  if (pw.length >= 8)  score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/\d/.test(pw) || /[^A-Za-z0-9]/.test(pw)) score++;
  return Math.min(score, 4);
});

const strengthLabel = computed(() => {
  return ["", "Weak", "Fair", "Good", "Strong"][passwordStrength.value] ?? "";
});

// ── Validation ─────────────────────────────────────────────

const passwordError = computed(() => {
  if (!passwordTouched.value) return "";
  if (!password.value)        return "Password is required";
  if (password.value.length < 8) return "Password must be at least 8 characters";
  return "";
});

const confirmError = computed(() => {
  if (!confirmTouched.value) return "";
  if (!passwordConfirmation.value) return "Please confirm your password";
  if (password.value !== passwordConfirmation.value) return "Passwords do not match";
  return "";
});

function touchPassword() { passwordTouched.value = true; }
function touchConfirm()  { confirmTouched.value  = true; }

// ── Submit ─────────────────────────────────────────────────

async function onSubmit() {
  passwordTouched.value = true;
  confirmTouched.value  = true;

  if (passwordError.value || confirmError.value) return;

  errorMessage.value = "";
  tokenExpired.value = false;
  isLoading.value    = true;

  try {
    await axios.post("/reset-password", {
      token,
      email,
      password:              password.value,
      password_confirmation: passwordConfirmation.value,
    });

    success.value = true;
  } catch (e) {
    const msg = e.response?.data?.message ?? "Something went wrong. Please try again.";
    errorMessage.value = msg;
    tokenExpired.value = msg.toLowerCase().includes("expired") || msg.toLowerCase().includes("invalid");
  } finally {
    isLoading.value = false;
  }
}
</script>


<style scoped>
/* ══════════════════════════════════════════════════════════════
   ROOT
══════════════════════════════════════════════════════════════ */
.rp-root {
  min-height: 100vh;
  width: 100%;
  background: #0d1117;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  position: relative;
  overflow: hidden;
  --brand:        #E91E63;
  --card-bg:      #ffffff;
  --card-border:  rgba(255,255,255,0.06);
  --card-shadow:  0 4px 6px rgba(0,0,0,0.3), 0 24px 60px rgba(0,0,0,0.5);
  --text-primary: #0f172a;
  --text-muted:   #64748b;
  --input-bg:     #f8fafc;
  --input-border: #e2e8f0;
}

.rp-dark {
  --card-bg:      #161b22;
  --card-border:  rgba(255,255,255,0.08);
  --text-primary: #f0f6fc;
  --text-muted:   #8b949e;
  --input-bg:     #0d1117;
  --input-border: rgba(255,255,255,0.1);
}

/* Blobs */
.rp-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.14;
  pointer-events: none;
}

.rp-blob-1 {
  width: 480px; height: 480px;
  background: radial-gradient(circle, #E91E63, #9c27b0);
  bottom: -120px; right: -120px;
  animation: rpBlob 14s ease-in-out infinite alternate;
}

.rp-blob-2 {
  width: 340px; height: 340px;
  background: radial-gradient(circle, #3b82f6, #06b6d4);
  top: -80px; left: -80px;
  animation: rpBlob 18s ease-in-out infinite alternate-reverse;
}

@keyframes rpBlob {
  0%   { transform: scale(1); }
  100% { transform: translate(20px, -20px) scale(1.06); }
}

.rp-dots {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
}

/* ══════════════════════════════════════════════════════════════
   WRAP + LOGO
══════════════════════════════════════════════════════════════ */
.rp-wrap {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 440px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
}

.rp-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 17px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.3px;
  filter: drop-shadow(0 2px 8px rgba(233,30,99,0.3));
}

/* ══════════════════════════════════════════════════════════════
   CARD
══════════════════════════════════════════════════════════════ */
.rp-card {
  width: 100%;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 20px;
  box-shadow: var(--card-shadow);
  padding: 36px 36px 28px;
  animation: rpCardIn 0.4s ease both;
  transition: background 0.3s, border-color 0.3s;
}

@keyframes rpCardIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ══════════════════════════════════════════════════════════════
   CARD HEADER
══════════════════════════════════════════════════════════════ */
.rp-card-header {
  margin-bottom: 24px;
  text-align: center;
}

.rp-text-center { text-align: center; }

.rp-icon-wrap {
  width: 52px;
  height: 52px;
  background: linear-gradient(135deg, rgba(233,30,99,0.1), rgba(194,24,91,0.08));
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
  font-size: 20px;
  color: var(--brand);
}

.rp-icon-wrap--danger {
  background: rgba(239,68,68,0.08);
  color: #ef4444;
}

.rp-card-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 8px;
  letter-spacing: -0.4px;
}

.rp-card-subtitle {
  font-size: 14px;
  color: var(--text-muted);
  margin: 0;
  line-height: 1.6;
}

.rp-email-hint {
  color: var(--text-primary);
  font-weight: 700;
  word-break: break-all;
}

/* ══════════════════════════════════════════════════════════════
   ERROR ALERT
══════════════════════════════════════════════════════════════ */
.rp-alert {
  background: rgba(239,68,68,0.08);
  border: 1px solid rgba(239,68,68,0.2);
  color: #ef4444;
  border-radius: 10px;
  padding: 12px 14px;
  font-size: 13.5px;
  font-weight: 500;
  margin-bottom: 20px;
  animation: rpAlertIn 0.25s ease;
  line-height: 1.5;
}

@keyframes rpAlertIn {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: translateY(0); }
}

.rp-alert-link {
  color: var(--brand);
  font-weight: 700;
  font-size: 13px;
  text-decoration: none;
}

.rp-alert-link:hover { text-decoration: underline; }

/* ══════════════════════════════════════════════════════════════
   FIELDS
══════════════════════════════════════════════════════════════ */
.rp-field {
  margin-bottom: 18px;
}

.rp-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 7px;
}

.rp-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.rp-input-icon {
  position: absolute;
  left: 13px;
  color: var(--text-muted);
  font-size: 13px;
  pointer-events: none;
  transition: color 0.2s;
}

.rp-input {
  width: 100%;
  padding: 12px 14px 12px 38px;
  border: 1.5px solid var(--input-border);
  border-radius: 10px;
  background: var(--input-bg);
  color: var(--text-primary);
  font-size: 14px;
  font-family: inherit;
  outline: none;
  transition: border-color 0.18s, box-shadow 0.18s;
  -webkit-appearance: none;
}

.rp-input--pw { padding-right: 42px; }
.rp-input::placeholder { color: var(--text-muted); opacity: 0.6; }

.rp-input:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(233,30,99,0.1);
}

.rp-input-wrap:focus-within .rp-input-icon { color: var(--brand); }

.rp-field--error .rp-input {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
}

.rp-field-error {
  font-size: 12px;
  color: #ef4444;
  margin-top: 5px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 4px;
  animation: rpErrIn 0.2s ease;
}

@keyframes rpErrIn {
  from { opacity: 0; transform: translateY(-3px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Password show/hide toggle */
.rp-pw-toggle {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  line-height: 1;
  font-size: 14px;
  border-radius: 4px;
  transition: color 0.15s;
}

.rp-pw-toggle:hover  { color: var(--text-primary); }
.rp-pw-toggle:focus  { outline: 2px solid rgba(233,30,99,0.3); }

/* ══════════════════════════════════════════════════════════════
   PASSWORD STRENGTH METER
══════════════════════════════════════════════════════════════ */
.rp-strength {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}

.rp-strength-bars {
  display: flex;
  gap: 4px;
  flex: 1;
}

.rp-strength-bar {
  flex: 1;
  height: 3px;
  border-radius: 99px;
  background: var(--input-border);
  transition: background 0.2s;
}

.rp-strength-bar--lvl-1 { background: #ef4444; }
.rp-strength-bar--lvl-2 { background: #f59e0b; }
.rp-strength-bar--lvl-3 { background: #3b82f6; }
.rp-strength-bar--lvl-4 { background: #22c55e; }

.rp-strength-label {
  font-size: 11px;
  font-weight: 600;
  min-width: 38px;
}

.rp-strength-label--1 { color: #ef4444; }
.rp-strength-label--2 { color: #f59e0b; }
.rp-strength-label--3 { color: #3b82f6; }
.rp-strength-label--4 { color: #22c55e; }

/* ══════════════════════════════════════════════════════════════
   SUBMIT BUTTON
══════════════════════════════════════════════════════════════ */
.rp-btn-submit {
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
  box-shadow: 0 4px 14px rgba(233,30,99,0.3);
  transition: transform 0.18s, box-shadow 0.18s, opacity 0.18s;
  letter-spacing: 0.01em;
}

.rp-btn-submit:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(233,30,99,0.4);
}

.rp-btn-submit:active:not(:disabled) {
  transform: none;
  box-shadow: 0 2px 8px rgba(233,30,99,0.25);
}

.rp-btn-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none !important;
}

.rp-btn-submit:focus-visible {
  outline: 3px solid rgba(233,30,99,0.4);
  outline-offset: 2px;
}

/* ══════════════════════════════════════════════════════════════
   BACK LINK + SUCCESS
══════════════════════════════════════════════════════════════ */
.rp-back {
  text-align: center;
  margin-top: 20px;
}

.rp-back-link {
  font-size: 13.5px;
  color: var(--text-muted);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.15s;
}

.rp-back-link:hover { color: var(--brand); }

.rp-success {
  text-align: center;
  padding: 8px 0;
  animation: rpSuccessIn 0.4s ease both;
}

@keyframes rpSuccessIn {
  from { opacity: 0; transform: scale(0.96); }
  to   { opacity: 1; transform: scale(1); }
}

.rp-success-icon {
  width: 72px;
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}

.rp-success-text {
  font-size: 14.5px;
  color: var(--text-muted);
  line-height: 1.7;
  margin: 0 0 24px;
}
</style>
