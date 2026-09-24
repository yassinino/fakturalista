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
            {{ $t('onboarding.headlinePrefix') }}<br>
            <span class="ob-headline-accent">{{ $t('onboarding.headlineAccent') }}</span>
          </h1>
          <p class="ob-subheadline">
            {{ $t('onboarding.subheadline') }}
          </p>
        </div>

        <div class="ob-steps">
          <div class="ob-step" :class="{ 'ob-step--done': currentStep > 1, 'ob-step--active': currentStep === 1 }">
            <div class="ob-step-dot"><i v-if="currentStep > 1" class="fa fa-check"></i><template v-else>1</template></div>
            <div>
              <div class="ob-step-title">{{ $t('onboarding.step1Title') }}</div>
              <div class="ob-step-desc">{{ $t('onboarding.step1Desc') }}</div>
            </div>
          </div>
          <div class="ob-step" :class="{ 'ob-step--done': currentStep > 2, 'ob-step--active': currentStep === 2 }">
            <div class="ob-step-dot"><i v-if="currentStep > 2" class="fa fa-check"></i><template v-else>2</template></div>
            <div>
              <div class="ob-step-title">{{ $t('onboarding.step2Title') }}</div>
              <div class="ob-step-desc">{{ $t('onboarding.step2Desc') }}</div>
            </div>
          </div>
          <div class="ob-step" :class="{ 'ob-step--active': currentStep === 3 }">
            <div class="ob-step-dot">3</div>
            <div>
              <div class="ob-step-title">{{ $t('onboarding.step3Title') }}</div>
              <div class="ob-step-desc">{{ $t('onboarding.step3Desc') }}</div>
            </div>
          </div>
        </div>

        <div class="ob-trial-badge">
          <i class="fa fa-gift me-2"></i>
          <span><strong>{{ $t('onboarding.trialBadgeStrong') }}</strong> - {{ $t('onboarding.trialBadgeText') }}</span>
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

          <!-- ═══ STEP 1 - Votre entreprise ═══ -->
          <template v-if="currentStep === 1">
            <div class="ob-card-header">
              <h2 class="ob-card-title">{{ $t('onboarding.step1Title') }}</h2>
              <p class="ob-card-subtitle">{{ $t('onboarding.cardSubtitle') }} <span class="ob-req">*</span></p>
            </div>

            <div v-if="errorMessage" class="ob-alert" role="alert">
              <i class="fa fa-exclamation-circle me-2"></i>{{ errorMessage }}
            </div>

            <form @submit.prevent="goToStep2" novalidate>
              <div class="ob-field" :class="{ 'ob-field--error': v$.trade_name.$errors.length }">
                <label class="ob-label" for="ob-trade-name">{{ $t('onboarding.fieldTradeName') }} <span class="ob-req">*</span></label>
                <input id="ob-trade-name" type="text" class="ob-input"
                  placeholder="My Business"
                  v-model="form.trade_name"
                  @blur="v$.trade_name.$touch"
                  autocomplete="organization"
                />
                <div v-if="v$.trade_name.$errors.length" class="ob-field-error">
                  <i class="fa fa-circle-xmark me-1"></i>{{ $t('validation.required') }}
                </div>
              </div>

              <div class="ob-field" :class="{ 'ob-field--error': v$.country.$error }">
                <label class="ob-label" for="ob-country">{{ $t('onboarding.fieldCountry') }} <span class="ob-req">*</span></label>
                <select id="ob-country" class="ob-input ob-select"
                  v-model="form.country" @change="selectCountry"
                  @blur="v$.country.$touch"
                  autocomplete="country-name"
                >
                  <option value="" disabled>{{ $t('onboarding.selectCountryPlaceholder') }}</option>
                  <option v-for="c in countries" :key="c.code" :value="c.name">{{ c.name }}</option>
                </select>
                <div v-if="v$.country.$errors.length" class="ob-field-error">
                  <i class="fa fa-circle-xmark me-1"></i>{{ $t('validation.required') }}
                </div>
              </div>

              <div class="ob-field">
                <label class="ob-label" for="ob-phone">{{ $t('onboarding.fieldPhone') }} <span class="ob-opt">({{ $t('onboarding.optional') }})</span></label>
                <input id="ob-phone" type="tel" class="ob-input"
                  :placeholder="isMorocco ? '+212 6XX XXX XXX' : '+34'"
                  v-model="form.phone"
                  autocomplete="tel"
                />
              </div>

              <button type="submit" class="ob-btn-submit">
                <span class="ob-btn-text">{{ $t('onboarding.continueBtn') }}</span>
              </button>
            </form>
          </template>

          <!-- ═══ STEP 2 - Informations de facturation ═══ -->
          <template v-else-if="currentStep === 2">
            <div class="ob-card-header">
              <h2 class="ob-card-title">{{ $t('onboarding.step2Title') }}</h2>
              <p class="ob-card-subtitle">{{ $t('onboarding.step2Note') }}</p>
            </div>

            <div v-if="errorMessage" class="ob-alert" role="alert">
              <i class="fa fa-exclamation-circle me-2"></i>{{ errorMessage }}
            </div>

            <form @submit.prevent="onSubmit" novalidate>
              <!-- Morocco: ICE / IF / RC. -->
              <template v-if="isMorocco">
                <div class="ob-row-2">
                  <div class="ob-field">
                    <label class="ob-label" for="ob-ice">{{ $t('onboarding.fieldIce') }} <span class="ob-opt">({{ $t('onboarding.optional') }})</span></label>
                    <input id="ob-ice" type="text" class="ob-input" placeholder="001234567000089" v-model="form.ice" />
                  </div>
                  <div class="ob-field">
                    <label class="ob-label" for="ob-if">{{ $t('onboarding.fieldIfNumber') }} <span class="ob-opt">({{ $t('onboarding.optional') }})</span></label>
                    <input id="ob-if" type="text" class="ob-input" placeholder="12345678" v-model="form.if_number" />
                  </div>
                </div>
                <div class="ob-field">
                  <label class="ob-label" for="ob-rc">{{ $t('onboarding.fieldRegistrationNumber') }} <span class="ob-opt">({{ $t('onboarding.optional') }})</span></label>
                  <input id="ob-rc" type="text" class="ob-input" placeholder="12345 - Casablanca" v-model="form.registration_number" />
                </div>
              </template>

              <!-- Spain / everyone else: unchanged fields. -->
              <div class="ob-row-2" v-else>
                <div class="ob-field">
                  <label class="ob-label" for="ob-taxid">{{ $t('onboarding.fieldTaxId') }} <span class="ob-opt">({{ $t('onboarding.optional') }})</span></label>
                  <input id="ob-taxid" type="text" class="ob-input" placeholder="B12345678" v-model="form.tax_id" />
                </div>
                <div class="ob-field">
                  <label class="ob-label" for="ob-vat">{{ $t('onboarding.fieldVatNumber') }} <span class="ob-opt">({{ $t('onboarding.optional') }})</span></label>
                  <input id="ob-vat" type="text" class="ob-input" placeholder="ES-B12345678" v-model="form.vat_number" />
                </div>
              </div>

              <div class="ob-field">
                <label class="ob-label" for="ob-address">{{ $t('onboarding.fieldStreetAddress') }} <span class="ob-opt">({{ $t('onboarding.optional') }})</span></label>
                <input id="ob-address" type="text" class="ob-input"
                  placeholder="123 Main Street"
                  v-model="form.address_line1"
                  autocomplete="street-address"
                />
              </div>

              <div class="ob-field">
                <label class="ob-label" for="ob-city">{{ $t('onboarding.fieldCity') }} <span class="ob-opt">({{ $t('onboarding.optional') }})</span></label>
                <input id="ob-city" type="text" class="ob-input"
                  :placeholder="isMorocco ? 'Casablanca' : 'Madrid'"
                  v-model="form.city"
                  autocomplete="address-level2"
                />
              </div>

              <div class="ob-btn-row">
                <button type="button" class="ob-btn-secondary" @click="goToStep1" :disabled="isLoading">
                  {{ $t('onboarding.backBtn') }}
                </button>
                <button
                  type="submit"
                  class="ob-btn-submit"
                  :class="{ 'ob-btn-submit--loading': isLoading }"
                  :disabled="isLoading"
                >
                  <span v-if="!isLoading" class="ob-btn-text">{{ $t('onboarding.continueBtn') }}</span>
                  <span v-else class="ob-btn-text">
                    <i class="fa fa-spinner fa-spin me-2"></i>{{ $t('onboarding.submitBtnLoading') }}
                  </span>
                </button>
              </div>
            </form>
          </template>

          <!-- ═══ STEP 3 - Ready ═══ -->
          <template v-else>
            <div class="ob-success">
              <div class="ob-success-icon"><i class="fa fa-circle-check"></i></div>
              <h2 class="ob-success-title">{{ $t('onboarding.step3Title') }}</h2>
              <p class="ob-success-subtitle">{{ $t('onboarding.step3Subtitle') }}</p>

              <button type="button" class="ob-btn-submit" @click="goToCreateInvoice">
                <span class="ob-btn-text"><i class="fa fa-file-invoice me-2"></i>{{ $t('onboarding.createFirstInvoiceBtn') }}</span>
              </button>
              <button type="button" class="ob-btn-link" @click="goToDashboard">
                {{ $t('onboarding.goToDashboardBtn') }}
              </button>
            </div>
          </template>

        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useTemplateStore } from '@/stores/template';
import axios from 'axios';
import useVuelidate from '@vuelidate/core';
import { required } from '@vuelidate/validators';

const store  = useTemplateStore();
const router = useRouter();
const { t }  = useI18n();

const isDark       = computed(() => store.settings.darkMode);
const isLoading    = ref(false);
const errorMessage = ref('');
const countries    = ref([]);

// 3-step wizard, all in one page/component - no route change, no reload
// between steps (see the class-level task note). `form` is a single
// shared reactive object across every step, so values typed on step 1
// are still there if the user goes back from step 2.
const currentStep = ref(1);

const form = reactive({
  trade_name:    '',
  address_line1: '',
  city:          '',
  country:       '',
  country_code:  store.company.country || 'MA',
  tax_id:        '',
  vat_number:    '',
  ice:           '',
  if_number:     '',
  registration_number: '',
  phone:         '',
  // Morocco is now the default market (Morocco Phase 1A) - Spain and other
  // countries/currencies remain fully selectable via the country field;
  // currency itself is never a separate field in the wizard, it's always
  // derived from the chosen country (see selectCountry() below).
  currency:      store.company.currency,
});

// Morocco Phase 1B (docs/morocco-phase-1b-identity.md §6) - which fiscal
// field to show (ICE/IF/RC vs NIF/VAT) depends on the tenant's own
// provisioning-time country, fetched explicitly here rather than read
// from the Pinia store: this is the very first authenticated screen a
// new tenant sees, so the store's company context may not have loaded
// yet (see OnboardingController::show()).
const countryDefaults = ref({});
const isMorocco = computed(() => form.country_code === 'MA');

const rules = computed(() => ({
  trade_name: { required },
  country:    { required },
}));

const v$ = useVuelidate(rules, form);

onMounted(async () => {
  try {
    const [countryResponse, onboardingResponse] = await Promise.all([
      axios.get('countries'), axios.get('onboarding'),
    ]);
    countries.value = countryResponse.data.countries ?? countryResponse.data;
    const data = onboardingResponse.data;
    const context = data.company_context;
    store.setCompanyContext(context);
    countryDefaults.value = data.country_defaults ?? {};
    form.country_code = context.country;
    form.country = data.profile?.country || countries.value.find(c => c.code === context.country)?.name || context.country_name;
    form.currency = data.profile?.currency || context.currency;
    for (const field of ['trade_name', 'address_line1', 'city', 'tax_id', 'vat_number', 'ice', 'if_number', 'registration_number', 'phone']) {
      if (data.profile?.[field] != null) form[field] = data.profile[field];
    }
    // Step 1's phone is optional and prefilled from registration when
    // available (tenants.company_phone) - only if the company profile
    // itself doesn't already have a (later, more specific) value.
    if (!form.phone && data.tenant_phone) {
      form.phone = data.tenant_phone;
    }
  } catch {
    errorMessage.value = t('onboarding.loadError');
  }
});

function selectCountry() {
  const country = countries.value.find(c => c.name === form.country);
  if (!country) return;
  form.country_code = country.code;
  const defaults = countryDefaults.value[country.code];
  if (defaults) form.currency = defaults.currency;
}

async function goToStep2() {
  v$.value.trade_name.$touch();
  v$.value.country.$touch();
  if (v$.value.trade_name.$invalid || v$.value.country.$invalid) return;
  errorMessage.value = '';
  currentStep.value = 2;
}

function goToStep1() {
  currentStep.value = 1;
}

function goToDashboard() {
  router.push({ name: 'backend-dashboard' });
}

function goToCreateInvoice() {
  router.push({ name: 'backend-create-invoice' });
}

async function onSubmit() {
  const valid = await v$.value.$validate();
  if (!valid) return;

  isLoading.value    = true;
  errorMessage.value = '';

  try {
    const payload = new FormData();
    Object.entries(form).forEach(([k, v]) => { if (v) payload.append(k, v); });

    const res = await axios.post('onboarding', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    // Refresh billing state so the guard doesn't re-redirect
    const userRes = await axios.get('user');
    if (userRes.data.billing) {
      store.setBillingStatus(userRes.data.billing);
    }
    if (userRes.data.company_context) {
      store.setCompanyContext(userRes.data.company_context);
    }

    // Onboarding is already marked complete on the backend at this point
    // (see OnboardingController::store()) - just show the step 3 success
    // screen in place, no navigation/reload needed.
    currentStep.value = 3;
  } catch (err) {
    const msg = err.response?.data?.message || t('onboarding.genericError');
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
  --brand:        var(--brand-primary);
  --brand-dark:   var(--brand-primary-hover);
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
  --input-focus:  var(--brand-primary);
  --section-lbl:  #94a3b8;
  --divider:      #e2e8f0;
}

.ob-dark {
  --panel-bg:     var(--dark-bg);
  --card-bg:      var(--dark-surface);
  --card-border:  var(--dark-border-subtle);
  --card-shadow:  0 4px 6px -1px rgba(0,0,0,0.4), 0 24px 60px -12px rgba(0,0,0,0.5);
  --text-primary: var(--dark-text);
  --text-muted:   var(--dark-text-muted);
  --input-bg:     var(--dark-input);
  --input-border: var(--dark-border);
  --section-lbl:  var(--dark-text-disabled);
  --divider:      var(--dark-border-subtle);
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
  background: radial-gradient(circle, var(--brand-primary), #9c27b0);
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
  background: linear-gradient(135deg, var(--brand-primary), #f472b6);
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
  background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-hover));
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
  background: var(--brand-primary);
  color: #fff;
  border: 1px solid var(--brand-primary);
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
  background: var(--brand-primary-hover);
  border-color: var(--brand-primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
}

.ob-btn-submit:active:not(:disabled) {
  background: var(--brand-primary-active);
  border-color: var(--brand-primary-active);
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

/* ══════════════════════════════════════════════════════════════
   STEP NAVIGATION (Retour / Continuer)
══════════════════════════════════════════════════════════════ */
.ob-btn-row {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}

.ob-btn-row .ob-btn-submit { margin-top: 0; flex: 1; }

.ob-btn-secondary {
  flex: 0 0 auto;
  padding: 10px 22px;
  background: transparent;
  color: var(--text-primary);
  border: 1.5px solid var(--input-border);
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: border-color .15s ease-in-out, background .15s ease-in-out;
}

.ob-btn-secondary:hover:not(:disabled) {
  border-color: var(--brand);
  background: rgba(233,30,99,0.04);
}

.ob-btn-secondary:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.ob-btn-link {
  display: block;
  width: 100%;
  margin-top: 14px;
  padding: 8px;
  background: none;
  border: none;
  color: var(--text-muted);
  font-size: 0.875rem;
  font-weight: 600;
  font-family: inherit;
  text-align: center;
  cursor: pointer;
  transition: color .15s ease-in-out;
}

.ob-btn-link:hover { color: var(--brand); }

/* ══════════════════════════════════════════════════════════════
   STEP 3 - SUCCESS
══════════════════════════════════════════════════════════════ */
.ob-success {
  text-align: center;
  padding: 12px 0 4px;
}

.ob-success-icon {
  font-size: 52px;
  color: #10b981;
  margin-bottom: 18px;
}

.ob-success-title {
  font-size: 22px;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 10px;
  letter-spacing: -0.4px;
}

.ob-success-subtitle {
  font-size: 14.5px;
  color: var(--text-muted);
  line-height: 1.6;
  margin: 0 0 8px;
}

@media (max-width: 480px) {
  .ob-row-2 { grid-template-columns: 1fr; }
  .ob-btn-row { flex-direction: column; }
}
</style>
