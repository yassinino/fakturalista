<template>
  <div class="ob-root" :class="{ 'ob-dark': isDark }">

    <!-- ══ LEFT - Setup Hero ══════════════════════════════════ -->
    <div class="ob-hero" aria-hidden="true">
      <div class="ob-blob ob-blob-1"></div>
      <div class="ob-blob ob-blob-2"></div>
      <div class="ob-dots"></div>

      <div class="ob-hero-inner">
        <div class="ob-logo">
          <span class="ob-logo-icon">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
              <rect width="32" height="32" rx="8" fill="url(#obLogoGrad)"/>
              <path d="M8 10h16M8 16h10M8 22h13" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
              <defs>
                <linearGradient id="obLogoGrad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#E91E63"/>
                  <stop offset="1" stop-color="#c2185b"/>
                </linearGradient>
              </defs>
            </svg>
          </span>
          <span class="ob-logo-name">Fakturalista</span>
        </div>

        <div class="ob-hero-copy">
          <h1 class="ob-headline">
            Set up your<br>
            <span class="ob-headline-accent">workspace</span>
          </h1>
          <p class="ob-subheadline">
            One-time setup to personalize your account.<br>
            Takes less than 2 minutes.
          </p>
        </div>

        <div class="ob-steps">
          <div class="ob-step ob-step--done">
            <div class="ob-step-dot"><i class="fa fa-check"></i></div>
            <div>
              <div class="ob-step-title">Account created</div>
              <div class="ob-step-desc">You're logged in and ready</div>
            </div>
          </div>
          <div class="ob-step ob-step--active">
            <div class="ob-step-dot">2</div>
            <div>
              <div class="ob-step-title">Company profile</div>
              <div class="ob-step-desc">Tell us about your business</div>
            </div>
          </div>
          <div class="ob-step">
            <div class="ob-step-dot">3</div>
            <div>
              <div class="ob-step-title">Free trial starts</div>
              <div class="ob-step-desc">3 months, no credit card needed</div>
            </div>
          </div>
        </div>

        <div class="ob-trial-badge">
          <i class="fa fa-gift me-2"></i>
          <span><strong>3-month free trial</strong> - no credit card required</span>
        </div>
      </div>
    </div>

    <!-- ══ RIGHT - Form Panel ═════════════════════════════════ -->
    <div class="ob-panel">
      <div class="ob-card-wrap">

        <div class="ob-mobile-logo">
          <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill="url(#obLogoGradM)"/>
            <path d="M8 10h16M8 16h10M8 22h13" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
            <defs>
              <linearGradient id="obLogoGradM" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                <stop stop-color="#E91E63"/>
                <stop offset="1" stop-color="#c2185b"/>
              </linearGradient>
            </defs>
          </svg>
          <span>Fakturalista</span>
        </div>

        <div class="ob-card">

          <div class="ob-card-header">
            <h2 class="ob-card-title">Company profile</h2>
            <p class="ob-card-subtitle">Required fields are marked with <span class="ob-req">*</span></p>
          </div>

          <div v-if="errorMessage" class="ob-alert" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i>{{ errorMessage }}
          </div>

          <form @submit.prevent="onSubmit" novalidate>

            <!-- Section: Personal -->
            <div class="ob-section-label">Your info</div>

            <div class="ob-field" :class="{ 'ob-field--error': v$.owner_name.$errors.length }">
              <label class="ob-label" for="ob-owner-name">Full name <span class="ob-req">*</span></label>
              <input id="ob-owner-name" type="text" class="ob-input"
                placeholder="Jane Doe"
                v-model="form.owner_name"
                @blur="v$.owner_name.$touch"
                autocomplete="name"
              />
              <div v-if="v$.owner_name.$errors.length" class="ob-field-error">
                <i class="fa fa-circle-xmark me-1"></i>Required
              </div>
            </div>

            <!-- Section: Company -->
            <div class="ob-section-label">Company</div>

            <div class="ob-field" :class="{ 'ob-field--error': v$.trade_name.$errors.length }">
              <label class="ob-label" for="ob-trade-name">Trade / business name <span class="ob-req">*</span></label>
              <input id="ob-trade-name" type="text" class="ob-input"
                placeholder="My Business"
                v-model="form.trade_name"
                @blur="v$.trade_name.$touch"
                autocomplete="organization"
              />
              <div v-if="v$.trade_name.$errors.length" class="ob-field-error">
                <i class="fa fa-circle-xmark me-1"></i>Required
              </div>
            </div>

            <div class="ob-field">
              <label class="ob-label" for="ob-legal-name">Legal / registered name <span class="ob-opt">(optional)</span></label>
              <input id="ob-legal-name" type="text" class="ob-input"
                placeholder="My Business S.L."
                v-model="form.legal_name"
                autocomplete="organization"
              />
            </div>

            <!-- Section: Address -->
            <div class="ob-section-label">Address</div>

            <div class="ob-field" :class="{ 'ob-field--error': v$.address_line1.$errors.length }">
              <label class="ob-label" for="ob-address">Street address <span class="ob-req">*</span></label>
              <input id="ob-address" type="text" class="ob-input"
                placeholder="123 Main Street"
                v-model="form.address_line1"
                @blur="v$.address_line1.$touch"
                autocomplete="street-address"
              />
              <div v-if="v$.address_line1.$errors.length" class="ob-field-error">
                <i class="fa fa-circle-xmark me-1"></i>Required
              </div>
            </div>

            <div class="ob-row-2">
              <div class="ob-field" :class="{ 'ob-field--error': v$.city.$errors.length }">
                <label class="ob-label" for="ob-city">City <span class="ob-req">*</span></label>
                <input id="ob-city" type="text" class="ob-input"
                  placeholder="Madrid"
                  v-model="form.city"
                  @blur="v$.city.$touch"
                  autocomplete="address-level2"
                />
                <div v-if="v$.city.$errors.length" class="ob-field-error">
                  <i class="fa fa-circle-xmark me-1"></i>Required
                </div>
              </div>

              <div class="ob-field" :class="{ 'ob-field--error': v$.postal_code.$errors.length }">
                <label class="ob-label" for="ob-postal">Postal code <span class="ob-req">*</span></label>
                <input id="ob-postal" type="text" class="ob-input"
                  placeholder="28001"
                  v-model="form.postal_code"
                  @blur="v$.postal_code.$touch"
                  autocomplete="postal-code"
                />
                <div v-if="v$.postal_code.$errors.length" class="ob-field-error">
                  <i class="fa fa-circle-xmark me-1"></i>Required
                </div>
              </div>
            </div>

            <div class="ob-field" :class="{ 'ob-field--error': v$.country.$error }">
              <label class="ob-label" for="ob-country">Country <span class="ob-req">*</span></label>
              <select id="ob-country" class="ob-input ob-select"
                v-model="form.country"
                @blur="v$.country.$touch"
                autocomplete="country-name"
              >
                <option value="" disabled>Select a country…</option>
                <option v-for="c in countries" :key="c.code" :value="c.name">{{ c.name }}</option>
              </select>
              <div v-if="v$.country.$errors.length" class="ob-field-error">
                <i class="fa fa-circle-xmark me-1"></i>Required
              </div>
            </div>

            <!-- Section: Business details -->
            <div class="ob-section-label">Business details</div>

            <div class="ob-row-2">
              <div class="ob-field">
                <label class="ob-label" for="ob-taxid">Tax ID / NIF <span class="ob-opt">(optional)</span></label>
                <input id="ob-taxid" type="text" class="ob-input"
                  placeholder="B12345678"
                  v-model="form.tax_id"
                />
              </div>

              <div class="ob-field">
                <label class="ob-label" for="ob-vat">VAT number <span class="ob-opt">(optional)</span></label>
                <input id="ob-vat" type="text" class="ob-input"
                  placeholder="ES-B12345678"
                  v-model="form.vat_number"
                />
              </div>
            </div>

            <div class="ob-row-2">
              <div class="ob-field">
                <label class="ob-label" for="ob-phone">Phone <span class="ob-opt">(optional)</span></label>
                <input id="ob-phone" type="tel" class="ob-input"
                  placeholder="+34 600 000 000"
                  v-model="form.phone"
                  autocomplete="tel"
                />
              </div>

              <div class="ob-field" :class="{ 'ob-field--error': v$.currency.$errors.length }">
                <label class="ob-label" for="ob-currency">Currency <span class="ob-req">*</span></label>
                <select id="ob-currency" class="ob-input ob-select"
                  v-model="form.currency"
                  @blur="v$.currency.$touch"
                >
                  <option value="" disabled>Select…</option>
                  <option value="EUR">EUR - Euro</option>
                  <option value="USD">USD - US Dollar</option>
                  <option value="GBP">GBP - British Pound</option>
                  <option value="CHF">CHF - Swiss Franc</option>
                  <option value="CAD">CAD - Canadian Dollar</option>
                  <option value="AUD">AUD - Australian Dollar</option>
                  <option value="JPY">JPY - Japanese Yen</option>
                  <option value="MAD">MAD - Moroccan Dirham</option>
                  <option value="TND">TND - Tunisian Dinar</option>
                  <option value="DZD">DZD - Algerian Dinar</option>
                  <option value="XOF">XOF - West African CFA Franc</option>
                  <option value="MXN">MXN - Mexican Peso</option>
                  <option value="BRL">BRL - Brazilian Real</option>
                  <option value="ARS">ARS - Argentine Peso</option>
                  <option value="COP">COP - Colombian Peso</option>
                </select>
                <div v-if="v$.currency.$errors.length" class="ob-field-error">
                  <i class="fa fa-circle-xmark me-1"></i>Required
                </div>
              </div>
            </div>

            <!-- Section: Logo -->
            <div class="ob-section-label">Logo <span class="ob-opt">(optional)</span></div>

            <div class="ob-field">
              <div class="ob-logo-upload" @click="$refs.logoInput.click()" :class="{ 'ob-logo-upload--has': logoPreview }">
                <img v-if="logoPreview" :src="logoPreview" class="ob-logo-preview" alt="Logo preview" />
                <div v-else class="ob-logo-placeholder">
                  <i class="fa fa-image"></i>
                  <span>Click to upload logo</span>
                  <small>PNG, JPG or SVG - max 2 MB</small>
                </div>
                <button v-if="logoPreview" type="button" class="ob-logo-remove" @click.stop="removeLogo">
                  <i class="fa fa-times"></i>
                </button>
              </div>
              <input ref="logoInput" type="file" accept="image/*" class="ob-file-hidden" @change="onLogoChange" />
            </div>

            <!-- Submit -->
            <button
              type="submit"
              class="ob-btn-submit"
              :class="{ 'ob-btn-submit--loading': isLoading }"
              :disabled="isLoading"
            >
              <span v-if="!isLoading" class="ob-btn-text">
                <i class="fa fa-rocket me-2"></i>Complete setup &amp; start trial
              </span>
              <span v-else class="ob-btn-text">
                <i class="fa fa-spinner fa-spin me-2"></i>Setting up your workspace…
              </span>
            </button>

          </form>

        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useTemplateStore } from '@/stores/template';
import axios from 'axios';
import useVuelidate from '@vuelidate/core';
import { required } from '@vuelidate/validators';

const store  = useTemplateStore();
const router = useRouter();

const isDark       = computed(() => store.settings.darkMode);
const isLoading    = ref(false);
const errorMessage = ref('');
const logoPreview  = ref(null);
const logoFile     = ref(null);
const logoInput    = ref(null);
const countries    = ref([]);

const form = reactive({
  owner_name:    '',
  trade_name:    '',
  legal_name:    '',
  address_line1: '',
  city:          '',
  postal_code:   '',
  country:       '',
  tax_id:        '',
  vat_number:    '',
  phone:         '',
  currency:      '',
});

const rules = computed(() => ({
  owner_name:    { required },
  trade_name:    { required },
  address_line1: { required },
  city:          { required },
  postal_code:   { required },
  country:       { required },
  currency:      { required },
}));

const v$ = useVuelidate(rules, form);

onMounted(async () => {
  try {
    const res = await axios.get('countries');
    countries.value = res.data.countries ?? res.data;
  } catch {
    // Fallback: empty list - user can still type
  }
});

function onLogoChange(e) {
  const file = e.target.files[0];
  if (!file) return;
  logoFile.value = file;
  logoPreview.value = URL.createObjectURL(file);
}

function removeLogo() {
  logoFile.value    = null;
  logoPreview.value = null;
  if (logoInput.value) logoInput.value.value = '';
}

async function onSubmit() {
  const valid = await v$.value.$validate();
  if (!valid) return;

  isLoading.value    = true;
  errorMessage.value = '';

  try {
    const payload = new FormData();
    Object.entries(form).forEach(([k, v]) => { if (v) payload.append(k, v); });
    if (logoFile.value) payload.append('logo', logoFile.value);

    const res = await axios.post('onboarding', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    // Refresh billing state so the guard doesn't re-redirect
    const userRes = await axios.get('user');
    if (userRes.data.billing) {
      store.setBillingStatus(userRes.data.billing);
    }

    router.push({ name: 'backend-dashboard' });
  } catch (err) {
    const msg = err.response?.data?.message || 'Something went wrong. Please try again.';
    errorMessage.value = msg;
    isLoading.value = false;
  }
}
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════
   ROOT
══════════════════════════════════════════════════════════════ */
.ob-root {
  display: flex;
  min-height: 100vh;
  width: 100%;
  --brand:        #E91E63;
  --brand-dark:   #c2185b;
  --hero-bg:      #0d1117;
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
  --section-lbl:  #94a3b8;
  --divider:      #e2e8f0;
}

.ob-dark {
  --panel-bg:     #0d1117;
  --card-bg:      #161b22;
  --card-border:  rgba(255,255,255,0.06);
  --card-shadow:  0 4px 6px -1px rgba(0,0,0,0.4), 0 24px 60px -12px rgba(0,0,0,0.5);
  --text-primary: #f0f6fc;
  --text-muted:   #8b949e;
  --input-bg:     #0d1117;
  --input-border: rgba(255,255,255,0.1);
  --section-lbl:  #4d5968;
  --divider:      rgba(255,255,255,0.08);
}

/* ══════════════════════════════════════════════════════════════
   HERO (LEFT)
══════════════════════════════════════════════════════════════ */
.ob-hero {
  flex: 0 0 42%;
  background: var(--hero-bg);
  position: relative;
  overflow: hidden;
  display: none;
}

@media (min-width: 1024px) {
  .ob-hero { display: flex; align-items: center; justify-content: center; }
}

.ob-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.16;
}

.ob-blob-1 {
  width: 420px; height: 420px;
  background: radial-gradient(circle, #E91E63, #9c27b0);
  top: -80px; left: -80px;
}

.ob-blob-2 {
  width: 300px; height: 300px;
  background: radial-gradient(circle, #3b82f6, #06b6d4);
  bottom: -60px; right: -40px;
}

.ob-dots {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
}

.ob-hero-inner {
  position: relative;
  z-index: 2;
  padding: 48px 44px;
  max-width: 480px;
  width: 100%;
}

.ob-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 48px;
}

.ob-logo-icon {
  display: flex;
  flex-shrink: 0;
  filter: drop-shadow(0 4px 12px rgba(233,30,99,0.4));
}

.ob-logo-name {
  font-size: 18px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.3px;
}

.ob-hero-copy { margin-bottom: 40px; }

.ob-headline {
  font-size: clamp(26px, 3vw, 38px);
  font-weight: 800;
  color: #fff;
  line-height: 1.15;
  letter-spacing: -1px;
  margin: 0 0 14px;
}

.ob-headline-accent {
  background: linear-gradient(135deg, #E91E63, #f472b6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.ob-subheadline {
  font-size: 14.5px;
  color: var(--hero-muted);
  line-height: 1.7;
  margin: 0;
}

/* Steps */
.ob-steps {
  display: flex;
  flex-direction: column;
  gap: 0;
  margin-bottom: 36px;
  position: relative;
}

.ob-steps::before {
  content: '';
  position: absolute;
  left: 15px;
  top: 0;
  bottom: 0;
  width: 1px;
  background: rgba(255,255,255,0.1);
  z-index: 0;
}

.ob-step {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 14px 0;
  position: relative;
  z-index: 1;
}

.ob-step-dot {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.45);
}

.ob-step--done .ob-step-dot {
  background: rgba(16,185,129,0.2);
  border-color: rgba(16,185,129,0.4);
  color: #10b981;
}

.ob-step--active .ob-step-dot {
  background: linear-gradient(135deg, #E91E63, #c2185b);
  border-color: transparent;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(233,30,99,0.2);
}

.ob-step-title {
  font-size: 13.5px;
  font-weight: 700;
  color: rgba(255,255,255,0.4);
  margin-bottom: 2px;
  line-height: 30px;
}

.ob-step--done .ob-step-title  { color: rgba(255,255,255,0.65); }
.ob-step--active .ob-step-title { color: #fff; }

.ob-step-desc {
  font-size: 12px;
  color: rgba(255,255,255,0.3);
  display: none;
}

.ob-step--active .ob-step-desc { display: block; }

/* Trial badge */
.ob-trial-badge {
  display: flex;
  align-items: center;
  background: rgba(233,30,99,0.1);
  border: 1px solid rgba(233,30,99,0.25);
  border-radius: 12px;
  padding: 12px 18px;
  font-size: 13px;
  color: rgba(255,255,255,0.75);
}

.ob-trial-badge strong { color: #fff; }

/* ══════════════════════════════════════════════════════════════
   RIGHT PANEL
══════════════════════════════════════════════════════════════ */
.ob-panel {
  flex: 1;
  background: var(--panel-bg);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 32px 20px 48px;
  min-height: 100vh;
  overflow-y: auto;
  transition: background 0.3s;
}

.ob-card-wrap {
  width: 100%;
  max-width: 560px;
  padding-top: 8px;
}

.ob-mobile-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  margin-bottom: 28px;
  font-size: 16px;
  font-weight: 800;
  color: var(--text-primary);
}

@media (min-width: 1024px) { .ob-mobile-logo { display: none; } }

/* ══════════════════════════════════════════════════════════════
   CARD
══════════════════════════════════════════════════════════════ */
.ob-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 20px;
  box-shadow: var(--card-shadow);
  padding: 36px 36px 28px;
  transition: background 0.3s, border-color 0.3s;
  animation: obCardIn 0.4s ease both;
}

@keyframes obCardIn {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.ob-card-header { margin-bottom: 24px; }

.ob-card-title {
  font-size: 22px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 6px;
  letter-spacing: -0.4px;
}

.ob-card-subtitle {
  font-size: 13.5px;
  color: var(--text-muted);
  margin: 0;
}

.ob-req { color: var(--brand); font-weight: 700; }
.ob-opt { color: var(--text-muted); font-weight: 400; font-size: 11px; }

/* ══════════════════════════════════════════════════════════════
   ERROR ALERT
══════════════════════════════════════════════════════════════ */
.ob-alert {
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
}

/* ══════════════════════════════════════════════════════════════
   SECTION LABELS
══════════════════════════════════════════════════════════════ */
.ob-section-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--section-lbl);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin: 20px 0 12px;
  border-bottom: 1px solid var(--divider);
  padding-bottom: 7px;
}

/* ══════════════════════════════════════════════════════════════
   FORM FIELDS
══════════════════════════════════════════════════════════════ */
.ob-field { margin-bottom: 14px; }

.ob-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 6px;
}

.ob-input {
  width: 100%;
  padding: 10px 14px;
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

.ob-input::placeholder { color: var(--text-muted); opacity: 0.6; }

.ob-input:focus {
  border-color: var(--input-focus);
  box-shadow: 0 0 0 3px rgba(233,30,99,0.1);
  background: var(--card-bg);
}

.ob-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2394a3b8' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  padding-right: 38px;
  cursor: pointer;
}

.ob-field--error .ob-input {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
}

.ob-field-error {
  font-size: 12px;
  color: #ef4444;
  margin-top: 5px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 4px;
}

.ob-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

/* ══════════════════════════════════════════════════════════════
   LOGO UPLOAD
══════════════════════════════════════════════════════════════ */
.ob-logo-upload {
  border: 2px dashed var(--input-border);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  min-height: 90px;
  transition: border-color 0.2s, background 0.2s;
  position: relative;
  background: var(--input-bg);
}

.ob-logo-upload:hover {
  border-color: var(--brand);
  background: rgba(233,30,99,0.03);
}

.ob-logo-upload--has {
  padding: 12px;
  border-style: solid;
  border-color: var(--input-border);
}

.ob-logo-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  color: var(--text-muted);
}

.ob-logo-placeholder i {
  font-size: 24px;
  opacity: 0.5;
}

.ob-logo-placeholder span {
  font-size: 13.5px;
  font-weight: 600;
}

.ob-logo-placeholder small {
  font-size: 11.5px;
  opacity: 0.65;
}

.ob-logo-preview {
  max-height: 64px;
  max-width: 180px;
  object-fit: contain;
  border-radius: 6px;
}

.ob-logo-remove {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: rgba(239,68,68,0.12);
  border: none;
  color: #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  cursor: pointer;
  transition: background 0.15s;
}

.ob-logo-remove:hover { background: rgba(239,68,68,0.22); }

.ob-file-hidden {
  position: absolute;
  width: 0;
  height: 0;
  opacity: 0;
  overflow: hidden;
}

/* ══════════════════════════════════════════════════════════════
   SUBMIT BUTTON
══════════════════════════════════════════════════════════════ */
.ob-btn-submit {
  width: 100%;
  margin-top: 24px;
  padding: 10px 24px;
  background: #E91E63;
  color: #fff;
  border: 1px solid #E91E63;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background .15s ease-in-out, border-color .15s ease-in-out,
              box-shadow .15s ease-in-out, transform .15s ease-in-out;
  line-height: 1;
}

.ob-btn-text {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ob-btn-submit:hover:not(:disabled) {
  background: #c2185b;
  border-color: #c2185b;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
}

.ob-btn-submit:active:not(:disabled) {
  background: #ad1457;
  border-color: #ad1457;
  transform: none;
  box-shadow: none;
}

.ob-btn-submit:disabled,
.ob-btn-submit--loading {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}
</style>
