<template>
  <div class="fp-root" :class="{ 'fp-dark': isDark }">

    <!-- Background blobs -->
    <div class="fp-blob fp-blob-1"></div>
    <div class="fp-blob fp-blob-2"></div>
    <div class="fp-dots"></div>

    <div class="fp-wrap">

      <!-- Logo -->
      <div class="fp-logo">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="8" fill="url(#fpLogoGrad)"/>
          <path d="M8 10h16M8 16h10M8 22h13" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
          <defs>
            <linearGradient id="fpLogoGrad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
              <stop stop-color="#E91E63"/>
              <stop offset="1" stop-color="#c2185b"/>
            </linearGradient>
          </defs>
        </svg>
        <span>Fakturalista</span>
      </div>

      <!-- Card -->
      <div class="fp-card">

        <!-- Success state -->
        <div v-if="sent" class="fp-success">
          <div class="fp-success-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="12" fill="rgba(34,197,94,0.12)"/>
              <path d="M7 12.5l3.5 3.5 6.5-7" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h2 class="fp-card-title">Check your inbox</h2>
          <p class="fp-success-text">
            We've sent a password reset link to <strong>{{ submittedEmail }}</strong>.
            It will expire in 60&nbsp;minutes.
          </p>
          <p class="fp-success-hint">
            Didn't receive it? Check your spam folder or
            <button type="button" class="fp-resend-btn" @click="sent = false">try again</button>.
          </p>
        </div>

        <!-- Form state -->
        <template v-else>
          <div class="fp-card-header">
            <div class="fp-lock-icon">
              <i class="fa fa-key"></i>
            </div>
            <h2 class="fp-card-title">Forgot your password?</h2>
            <p class="fp-card-subtitle">
              Enter your email and we'll send you a reset link.
            </p>
          </div>

          <!-- Error alert -->
          <div v-if="errorMessage" class="fp-alert" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i>{{ errorMessage }}
          </div>

          <form @submit.prevent="onSubmit" novalidate>

            <!-- Email -->
            <div class="fp-field" :class="{ 'fp-field--error': emailError }">
              <label class="fp-label" for="fp-email">Email address</label>
              <div class="fp-input-wrap">
                <i class="fa fa-envelope fp-input-icon"></i>
                <input
                  id="fp-email"
                  type="email"
                  class="fp-input"
                  placeholder="your@email.com"
                  v-model="email"
                  @blur="touchEmail"
                  autocomplete="email"
                  inputmode="email"
                  :disabled="isLoading"
                />
              </div>
              <div v-if="emailError" class="fp-field-error">
                <i class="fa fa-circle-xmark me-1"></i>{{ emailError }}
              </div>
            </div>

            <button
              type="submit"
              class="fp-btn-submit"
              :disabled="isLoading"
            >
              <i v-if="isLoading" class="fa fa-spinner fa-spin me-2"></i>
              <i v-else class="fa fa-paper-plane me-2"></i>
              {{ isLoading ? 'Sending…' : 'Send reset link' }}
            </button>

          </form>
        </template>

        <!-- Back to login -->
        <div class="fp-back">
          <router-link to="/admin/login" class="fp-back-link">
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
import axios from "axios";
import { useTemplateStore } from "@/stores/template";

const store = useTemplateStore();
const isDark = computed(() => store.settings.darkMode);

const email          = ref("");
const emailTouched   = ref(false);
const errorMessage   = ref("");
const isLoading      = ref(false);
const sent           = ref(false);
const submittedEmail = ref("");

// Simple local validation
const emailError = computed(() => {
  if (!emailTouched.value) return "";
  if (!email.value) return "Email is required";
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) return "Please enter a valid email";
  return "";
});

function touchEmail() {
  emailTouched.value = true;
}

async function onSubmit() {
  emailTouched.value = true;
  if (emailError.value) return;

  errorMessage.value = "";
  isLoading.value    = true;

  try {
    await axios.post("/forgot-password", { email: email.value });
    submittedEmail.value = email.value;
    sent.value           = true;
  } catch (e) {
    errorMessage.value =
      e.response?.data?.message ?? "Something went wrong. Please try again.";
  } finally {
    isLoading.value = false;
  }
}
</script>


<style scoped>
/* ══════════════════════════════════════════════════════════════
   ROOT
══════════════════════════════════════════════════════════════ */
.fp-root {
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
  --divider:      #e2e8f0;
}

.fp-dark {
  --card-bg:      #161b22;
  --card-border:  rgba(255,255,255,0.08);
  --text-primary: #f0f6fc;
  --text-muted:   #8b949e;
  --input-bg:     #0d1117;
  --input-border: rgba(255,255,255,0.1);
  --divider:      rgba(255,255,255,0.08);
}

/* Blobs */
.fp-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.14;
  pointer-events: none;
}

.fp-blob-1 {
  width: 480px; height: 480px;
  background: radial-gradient(circle, #E91E63, #9c27b0);
  top: -160px; left: -140px;
  animation: fpBlob 14s ease-in-out infinite alternate;
}

.fp-blob-2 {
  width: 360px; height: 360px;
  background: radial-gradient(circle, #3b82f6, #8b5cf6);
  bottom: -100px; right: -80px;
  animation: fpBlob 18s ease-in-out infinite alternate-reverse;
}

@keyframes fpBlob {
  0%   { transform: translate(0, 0) scale(1); }
  100% { transform: translate(24px, -20px) scale(1.06); }
}

.fp-dots {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
}

/* ══════════════════════════════════════════════════════════════
   WRAP + LOGO
══════════════════════════════════════════════════════════════ */
.fp-wrap {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 420px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
}

.fp-logo {
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
.fp-card {
  width: 100%;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 20px;
  box-shadow: var(--card-shadow);
  padding: 36px 36px 28px;
  animation: fpCardIn 0.4s ease both;
  transition: background 0.3s, border-color 0.3s;
}

@keyframes fpCardIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ══════════════════════════════════════════════════════════════
   HEADER
══════════════════════════════════════════════════════════════ */
.fp-card-header {
  margin-bottom: 24px;
  text-align: center;
}

.fp-lock-icon {
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

.fp-card-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 8px;
  letter-spacing: -0.4px;
}

.fp-card-subtitle {
  font-size: 14px;
  color: var(--text-muted);
  margin: 0;
  line-height: 1.6;
}

/* ══════════════════════════════════════════════════════════════
   ERROR ALERT
══════════════════════════════════════════════════════════════ */
.fp-alert {
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
  animation: fpAlertIn 0.25s ease;
}

@keyframes fpAlertIn {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ══════════════════════════════════════════════════════════════
   FIELD
══════════════════════════════════════════════════════════════ */
.fp-field {
  margin-bottom: 20px;
}

.fp-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 7px;
}

.fp-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.fp-input-icon {
  position: absolute;
  left: 13px;
  color: var(--text-muted);
  font-size: 13px;
  pointer-events: none;
  transition: color 0.2s;
}

.fp-input {
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

.fp-input::placeholder { color: var(--text-muted); opacity: 0.6; }

.fp-input:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(233,30,99,0.1);
}

.fp-input-wrap:focus-within .fp-input-icon {
  color: var(--brand);
}

.fp-field--error .fp-input {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
}

.fp-field-error {
  font-size: 12px;
  color: #ef4444;
  margin-top: 5px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 4px;
  animation: fpErrIn 0.2s ease;
}

@keyframes fpErrIn {
  from { opacity: 0; transform: translateY(-3px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ══════════════════════════════════════════════════════════════
   SUBMIT BUTTON
══════════════════════════════════════════════════════════════ */
.fp-btn-submit {
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

.fp-btn-submit:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(233,30,99,0.4);
}

.fp-btn-submit:active:not(:disabled) {
  transform: none;
  box-shadow: 0 2px 8px rgba(233,30,99,0.25);
}

.fp-btn-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none !important;
}

.fp-btn-submit:focus-visible {
  outline: 3px solid rgba(233,30,99,0.4);
  outline-offset: 2px;
}

/* ══════════════════════════════════════════════════════════════
   BACK LINK
══════════════════════════════════════════════════════════════ */
.fp-back {
  text-align: center;
  margin-top: 20px;
}

.fp-back-link {
  font-size: 13.5px;
  color: var(--text-muted);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.15s;
}

.fp-back-link:hover { color: var(--brand); }

/* ══════════════════════════════════════════════════════════════
   SUCCESS STATE
══════════════════════════════════════════════════════════════ */
.fp-success {
  text-align: center;
  padding: 8px 0;
  animation: fpSuccessIn 0.4s ease both;
}

@keyframes fpSuccessIn {
  from { opacity: 0; transform: scale(0.96); }
  to   { opacity: 1; transform: scale(1); }
}

.fp-success-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  background: rgba(34,197,94,0.1);
}

.fp-success-icon svg {
  width: 36px;
  height: 36px;
}

.fp-success-text {
  font-size: 14.5px;
  color: var(--text-muted);
  line-height: 1.7;
  margin: 0 0 16px;
}

.fp-success-text strong {
  color: var(--text-primary);
  font-weight: 700;
}

.fp-success-hint {
  font-size: 13px;
  color: var(--text-muted);
  margin: 0;
}

.fp-resend-btn {
  background: none;
  border: none;
  color: var(--brand);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
  font-family: inherit;
}

.fp-resend-btn:hover { opacity: 0.75; }
</style>
