<template>
  <div class="content">
    <div class="sub-wrap">

      <!-- ═══════════════════════════════════════════════════
           TRIAL-EXPIRED BANNER
           ═══════════════════════════════════════════════════ -->
      <div v-if="trialExpired && !hasActiveSub" class="sub-expired-banner" role="alert">
        <svg class="sub-alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
        </svg>
        <div class="sub-alert-body">
          <strong>{{ $t('subscription.trialExpired.banner') }}</strong>
          <span>{{ $t('subscription.trialExpired.bannerSub') }}</span>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════
           CURRENT PLAN / TRIAL STATUS CARD
           ═══════════════════════════════════════════════════ -->
      <div v-if="hasActiveSub || trialActive" class="sub-status-card">
        <div class="sub-status-left">
          <span class="sub-status-eyebrow">{{ $t('subscription.status.currentPlan') }}</span>
          <p class="sub-status-name">
            {{ trialActive ? $t('subscription.status.trialLabel') : currentPlanDisplayName }}
          </p>
        </div>
        <div class="sub-status-right">
          <template v-if="trialActive">
            <div class="sub-status-meta">
              <span class="sub-status-days">{{ store.billing.trialDaysLeft }}</span>
              <span class="sub-status-days-label">{{ $t('subscription.trial.daysLeft') }}</span>
            </div>
            <div class="sub-trial-ends" v-if="store.billing.trialEndsAt">
              {{ $t('subscription.trial.endsOn', { date: formatDate(store.billing.trialEndsAt) }) }}
            </div>
          </template>
          <template v-else-if="currentSub?.current_period_ends_at">
            <div class="sub-status-meta">
              <span class="sub-status-renews">{{ $t('subscription.status.renewsOn') }}</span>
              <span class="sub-status-date">{{ formatDate(currentSub.current_period_ends_at) }}</span>
            </div>
          </template>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════
           TRIAL ACTIVE NOTICE
           ═══════════════════════════════════════════════════ -->
      <div v-if="trialActive" class="sub-trial-notice">
        <div class="sub-trial-pill">
          <span class="sub-trial-dot"></span>
          {{ $t('subscription.trial.active') }}
        </div>
        <p class="sub-trial-subnote">{{ $t('subscription.trial.subnote') }}</p>
      </div>

      <!-- ═══════════════════════════════════════════════════
           PAGE HEADER
           ═══════════════════════════════════════════════════ -->
      <div class="sub-header">
        <h1 class="sub-heading">{{ $t('subscription.heading') }}</h1>
        <p class="sub-subheading">{{ $t('subscription.subheading') }}</p>
      </div>

      <!-- ═══════════════════════════════════════════════════
           BILLING CYCLE TOGGLE
           ═══════════════════════════════════════════════════ -->
      <div class="sub-toggle-wrap" role="group" :aria-label="$t('subscription.billing.monthly') + ' / ' + $t('subscription.billing.yearly')">
        <button
          class="sub-cycle-btn"
          :class="{ 'sub-cycle-btn--active': cycle === 'monthly' }"
          @click="cycle = 'monthly'"
        >{{ $t('subscription.billing.monthly') }}</button>

        <button
          class="sub-cycle-btn"
          :class="{ 'sub-cycle-btn--active': cycle === 'yearly' }"
          @click="cycle = 'yearly'"
        >
          {{ $t('subscription.billing.yearly') }}
          <span class="sub-save-badge">{{ $t('subscription.billing.savePercent') }}</span>
        </button>
      </div>

      <!-- ═══════════════════════════════════════════════════
           PLAN CARDS
           ═══════════════════════════════════════════════════ -->
      <div v-if="planError" class="sub-load-error">
        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
        {{ planError }}
      </div>

      <div v-if="plansLoading && !plans.length" class="sub-skeleton-row">
        <div v-for="i in 3" :key="i" class="sub-skeleton-card"></div>
      </div>

      <div v-else-if="plans.length" class="sub-cards">
        <div
          v-for="(plan, idx) in plans"
          :key="plan.id"
          class="sub-card"
          :class="{
            'sub-card--popular': plan.is_featured,
            'sub-card--current': isCurrentPlan(plan),
          }"
        >
          <!-- Popular badge -->
          <div v-if="plan.is_featured" class="sub-popular-badge">
            {{ $t('subscription.popular') }}
          </div>

          <!-- Card header -->
          <div class="sub-card-head">
            <div class="sub-card-icon" :class="`sub-card-icon--${plan.slug}`">
              <!-- Starter icon -->
              <svg v-if="idx === 0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
              </svg>
              <!-- Professional icon -->
              <svg v-else-if="idx === 1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
              </svg>
              <!-- Enterprise icon -->
              <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
              </svg>
            </div>
            <h2 class="sub-card-name">{{ plan.name }}</h2>
            <p class="sub-card-desc">{{ plan.short_description }}</p>
          </div>

          <!-- Price -->
          <div class="sub-card-price">
            <span class="sub-price-currency">€</span>
            <span class="sub-price-amount">{{ displayPrice(plan) }}</span>
            <span class="sub-price-period">{{ $t('subscription.billing.perMonth') }}</span>
          </div>
          <p v-if="cycle === 'yearly'" class="sub-price-billed">
            {{ $t('subscription.billing.billedYearly', { total: yearlyTotal(plan) }) }}
          </p>

          <!-- Divider -->
          <div class="sub-card-divider"></div>

          <!-- Features -->
          <ul class="sub-features" :aria-label="plan.name + ' features'">
            <li
              v-for="item in (plan.marketing_items || [])"
              :key="item.text"
              class="sub-feature"
            >
              <svg class="sub-check" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <circle cx="8" cy="8" r="8" fill="#dcfce7"/>
                <path d="M5 8l2.5 2.5L11 5.5" stroke="#16a34a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              {{ item.text }}
            </li>
          </ul>

          <!-- CTA -->
          <button
            class="sub-cta"
            :class="ctaClass(plan, idx)"
            :disabled="ctaDisabled(plan)"
            @click="onPlanClick(plan, idx)"
          >
            <svg v-if="trialActive" class="sub-cta-lock" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V6H3a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-1.5V4.5A3.5 3.5 0 0 0 8 1zm2 5V4.5a2 2 0 1 0-4 0V6h4z" clip-rule="evenodd"/>
            </svg>
            {{ ctaLabel(plan, idx) }}
          </button>
        </div>
      </div>

      <!-- Trust strip -->
      <div class="sub-trust-row">
        <div class="sub-trust-item">
          <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.403 12.652a3 3 0 000-5.304 3 3 0 00-3.75-3.751 3 3 0 00-5.305 0 3 3 0 00-3.751 3.75 3 3 0 000 5.305 3 3 0 003.75 3.751 3 3 0 005.305 0 3 3 0 003.751-3.75zm-2.546-4.46a.75.75 0 00-1.214-.883l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
          No credit card required for trial
        </div>
        <div class="sub-trust-item">
          <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
          Secure checkout · SSL encrypted
        </div>
        <div class="sub-trust-item">
          <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M10.75 10.818v2.614A3.13 3.13 0 0011.888 13c.482-.315.612-.648.612-.875 0-.227-.13-.56-.612-.875a3.13 3.13 0 00-1.138-.432zM8.33 8.62c.053.055.115.11.184.164.208.16.46.284.736.363V6.603a2.45 2.45 0 00-.35.13c-.14.065-.27.143-.386.233-.377.292-.514.627-.514.909 0 .184.058.39.33.785z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25v-.25a.75.75 0 00-1.5 0v.57a4.273 4.273 0 00-.625.186A3.75 3.75 0 007.14 9.315c-.28.44-.39.943-.39 1.435 0 1.05.57 1.824 1.277 2.296a5.018 5.018 0 001.723.723v2.197A3.125 3.125 0 018.88 15.5c-.5-.324-.63-.659-.63-.875a.75.75 0 00-1.5 0c0 .79.35 1.518 1.025 2.015a4.624 4.624 0 001.975.88v.23a.75.75 0 001.5 0v-.255a4.284 4.284 0 001.023-.367 3.621 3.621 0 001.48-1.392c.283-.463.422-.993.422-1.631 0-1.06-.582-1.83-1.298-2.3a5.015 5.015 0 00-1.627-.703V8.25c.21.062.408.15.585.26.274.176.415.38.415.49a.75.75 0 001.5 0c0-.73-.379-1.376-.934-1.762A4.296 4.296 0 0010.75 6.75z" clip-rule="evenodd"/></svg>
          Cancel anytime · No commitment
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════
           FAQ
           ═══════════════════════════════════════════════════ -->
      <div class="sub-faq">
        <h2 class="sub-faq-title">{{ $t('subscription.faq.title') }}</h2>
        <div class="sub-faq-list">
          <details
            v-for="n in 4"
            :key="n"
            class="sub-faq-item"
          >
            <summary class="sub-faq-q">
              {{ $t(`subscription.faq.q${n}`) }}
              <svg class="sub-faq-chevron" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 011.06 0L8 8.94l2.72-2.72a.75.75 0 111.06 1.06l-3.25 3.25a.75.75 0 01-1.06 0L4.22 7.28a.75.75 0 010-1.06z" clip-rule="evenodd"/>
              </svg>
            </summary>
            <p class="sub-faq-a">{{ $t(`subscription.faq.a${n}`) }}</p>
          </details>
        </div>
      </div>

    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════
       CONFIRMATION MODAL
       ═══════════════════════════════════════════════════ -->
  <div v-if="showConfirmModal" class="sub-modal-overlay" @click.self="closeModal">
    <div class="sub-modal" role="dialog" :aria-label="$t('subscription.confirm.title')">
      <button class="sub-modal-close" @click="closeModal" :aria-label="$t('subscription.confirm.cancel')">
        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
      </button>

      <div class="sub-modal-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </div>

      <h3 class="sub-modal-title">{{ $t('subscription.confirm.title') }}</h3>
      <p class="sub-modal-body">
        {{
          cycle === 'yearly'
            ? $t('subscription.confirm.bodyYearly', {
                plan:  pendingPlanName,
                price: pendingDisplayPrice,
              })
            : $t('subscription.confirm.body', {
                plan:  pendingPlanName,
                price: pendingDisplayPrice,
              })
        }}
      </p>

      <div class="sub-modal-meta">
        <div class="sub-modal-meta-row">
          <span>Payment</span>
          <span>
            <svg viewBox="0 0 32 12" height="12" fill="none" aria-label="Stripe">
              <text x="0" y="10" font-family="system-ui,sans-serif" font-size="12" font-weight="700" fill="#635bff">stripe</text>
            </svg>
          </span>
        </div>
        <div class="sub-modal-meta-row">
          <span>Billing</span>
          <span>{{ cycle === 'yearly' ? 'Yearly' : 'Monthly' }}</span>
        </div>
      </div>

      <div v-if="payError" class="sub-modal-error">{{ payError }}</div>

      <div class="sub-modal-actions">
        <button class="sub-modal-cancel" @click="closeModal" :disabled="payLoading">
          {{ $t('subscription.confirm.cancel') }}
        </button>
        <button class="sub-modal-proceed" @click="proceedPayment" :disabled="payLoading">
          <svg v-if="payLoading" class="sub-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
          </svg>
          {{ payLoading ? $t('subscription.processing') : $t('subscription.confirm.proceed') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useTemplateStore } from '@/stores/template';

const { t } = useI18n();
const store = useTemplateStore();

// ── State ─────────────────────────────────────────────────────────────────
const plans       = ref([]);
const plansLoading = ref(false);
const planError   = ref('');
const currentSub  = ref(null);
const cycle       = ref('monthly');

// Confirmation modal
const showConfirmModal = ref(false);
const pendingPlan      = ref(null);
const pendingPlanIdx   = ref(null);
const payLoading       = ref(false);
const payError         = ref('');

// ── Derived billing state ─────────────────────────────────────────────────
const trialActive  = computed(() =>
  store.billing.subscriptionStatus === 'trialing' && !store.billing.isReadOnly
);
const trialExpired = computed(() => store.billing.isReadOnly);
const hasActiveSub = computed(() =>
  ['active', 'past_due'].includes(String(store.billing.subscriptionStatus ?? '').toLowerCase())
);

// ── Current plan detection ────────────────────────────────────────────────
const currentPlanIdx = computed(() => {
  if (!currentSub.value?.plan) return null;
  const currentId = currentSub.value.plan_id ?? currentSub.value.plan?.id;
  const idx = plans.value.findIndex(p => Number(p.id) === Number(currentId));
  return idx >= 0 ? idx : null;
});

const currentPlanDisplayName = computed(() =>
  currentSub.value?.plan?.name ?? ''
);

const isCurrentPlan = (plan) => {
  if (!hasActiveSub.value) return false;
  const subPlanId = currentSub.value?.plan_id ?? currentSub.value?.plan?.id;
  return subPlanId && Number(subPlanId) === Number(plan.id);
};

// ── Price helpers ─────────────────────────────────────────────────────────
const displayPrice = (plan) => {
  const monthly = parseFloat(plan.price);
  if (cycle.value === 'yearly') return (monthly * 0.8).toFixed(0);
  return monthly.toFixed(0);
};

const yearlyTotal = (plan) => {
  return (parseFloat(plan.price) * 0.8 * 12).toFixed(0);
};

// ── CTA helpers ───────────────────────────────────────────────────────────
const ctaDisabled = (plan) => {
  if (trialActive.value) return true;
  if (isCurrentPlan(plan)) return true;
  return false;
};

const ctaClass = (plan, idx) => {
  if (trialActive.value) return 'sub-cta--ghost';
  if (isCurrentPlan(plan)) return 'sub-cta--current';
  if (plan.is_featured) return 'sub-cta--primary';
  return 'sub-cta--outline';
};

const ctaLabel = (plan, idx) => {
  if (trialActive.value) return t('subscription.buttons.trialPending');
  if (isCurrentPlan(plan)) return t('subscription.buttons.currentPlan');

  if (hasActiveSub.value && currentPlanIdx.value !== null) {
    if (idx > currentPlanIdx.value) return t('subscription.buttons.upgrade');
    if (idx < currentPlanIdx.value) return t('subscription.buttons.downgrade');
  }

  return t('subscription.buttons.subscribe');
};

// ── Pending plan info (for modal) ─────────────────────────────────────────
const pendingPlanName = computed(() => pendingPlan.value?.name ?? '');
const pendingDisplayPrice = computed(() =>
  pendingPlan.value ? displayPrice(pendingPlan.value) : ''
);

// ── Date formatting ───────────────────────────────────────────────────────
const formatDate = (iso) => {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
};

// ── API calls ─────────────────────────────────────────────────────────────
const loadPlans = async () => {
  plansLoading.value = true;
  planError.value    = '';
  try {
    const { data } = await axios.get('/plans');
    plans.value = (data?.plans ?? []).slice(0, 3); // only the first 3
  } catch {
    planError.value = t('subscription.errors.loadPlans');
  } finally {
    plansLoading.value = false;
  }
};

const loadCurrentSubscription = async () => {
  try {
    const { data } = await axios.get('/subscription');
    currentSub.value = data?.subscription ?? null;
  } catch {
    currentSub.value = null;
  }
};

onMounted(() => {
  loadPlans();
  loadCurrentSubscription();
});

// ── Plan click → open modal ────────────────────────────────────────────────
const onPlanClick = (plan, idx) => {
  if (ctaDisabled(plan)) return;
  pendingPlan.value    = plan;
  pendingPlanIdx.value = idx;
  payError.value       = '';
  showConfirmModal.value = true;
};

const closeModal = () => {
  if (payLoading.value) return;
  showConfirmModal.value = false;
  pendingPlan.value      = null;
  pendingPlanIdx.value   = null;
  payError.value         = '';
};

// ── Payment ───────────────────────────────────────────────────────────────
const proceedPayment = async () => {
  if (!pendingPlan.value || payLoading.value) return;
  payError.value   = '';
  payLoading.value = true;

  try {
    const { data } = await axios.post('/subscription/checkout', {
      plan_id: pendingPlan.value.id,
    });

    const url = data?.checkout_url;
    if (!url) throw new Error('No checkout URL');
    window.location.href = url;
  } catch (err) {
    payError.value =
      err.response?.data?.message ?? t('subscription.errors.stripeStart');
    payLoading.value = false;
  }
};
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════
   SUBSCRIPTION PAGE - scoped styles
   Brand: #fa7070 (coral), clean neutrals, ample whitespace
   ══════════════════════════════════════════════════════════════ */

.sub-wrap {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 16px 80px;
}

/* ── Trial-expired banner ─────────────────────────────────────────────── */
.sub-expired-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  background: #fff4f4;
  border: 1px solid #fca5a5;
  border-radius: 14px;
  padding: 16px 20px;
  margin-bottom: 28px;
  color: #991b1b;
}
.sub-alert-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  margin-top: 2px;
  fill: #ef4444;
}
.sub-alert-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 0.875rem;
  line-height: 1.5;
}
.sub-alert-body strong { font-weight: 700; }
.sub-alert-body span   { opacity: 0.8; }

/* ── Current plan / trial status card ────────────────────────────────── */
.sub-status-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: #fff;
  border: 1px solid #e4e4e7;
  border-radius: 16px;
  padding: 20px 28px;
  margin-bottom: 28px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
.sub-status-left { display: flex; flex-direction: column; gap: 4px; }
.sub-status-eyebrow {
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #6b7280;
}
.sub-status-name {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.sub-status-right  { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.sub-status-meta   { display: flex; align-items: baseline; gap: 6px; }
.sub-status-days   { font-size: 2rem; font-weight: 800; color: #fa7070; line-height: 1; }
.sub-status-days-label { font-size: 0.8rem; color: #6b7280; }
.sub-status-renews { font-size: 0.8rem; color: #6b7280; }
.sub-status-date   { font-size: 0.95rem; font-weight: 600; color: #374151; }
.sub-trial-ends    { font-size: 0.78rem; color: #9ca3af; }

/* ── Trial notice ─────────────────────────────────────────────────────── */
.sub-trial-notice {
  text-align: center;
  margin-bottom: 16px;
}
.sub-trial-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #fff0f0;
  border: 1px solid #fca5a5;
  border-radius: 999px;
  padding: 6px 18px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #b91c1c;
  margin-bottom: 8px;
}
.sub-trial-dot {
  width: 8px; height: 8px;
  background: #fa7070;
  border-radius: 50%;
  animation: sub-pulse 1.8s ease-in-out infinite;
}
@keyframes sub-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.5; transform: scale(0.7); }
}
.sub-trial-subnote {
  font-size: 0.82rem;
  color: #9ca3af;
  margin: 0;
}

/* ── Header ───────────────────────────────────────────────────────────── */
.sub-header {
  text-align: center;
  margin-bottom: 36px;
}
.sub-heading {
  font-size: 2rem;
  font-weight: 800;
  color: #111827;
  margin: 0 0 10px;
  letter-spacing: -0.03em;
}
.sub-subheading {
  font-size: 1rem;
  color: #6b7280;
  margin: 0;
}

/* ── Billing toggle ───────────────────────────────────────────────────── */
.sub-toggle-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  margin-bottom: 40px;
  background: #f3f4f6;
  border-radius: 12px;
  padding: 4px;
  width: fit-content;
  margin-left: auto;
  margin-right: auto;
}
.sub-cycle-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 22px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #6b7280;
  background: transparent;
  border: none;
  border-radius: 9px;
  cursor: pointer;
  transition: background 0.18s, color 0.18s, box-shadow 0.18s;
}
.sub-cycle-btn--active {
  background: #fff;
  color: #111827;
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(0,0,0,0.10);
}
.sub-save-badge {
  font-size: 0.7rem;
  font-weight: 700;
  background: #fa7070;
  color: #fff;
  border-radius: 999px;
  padding: 2px 8px;
}

/* ── Cards grid ───────────────────────────────────────────────────────── */
.sub-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  align-items: start;
  margin-bottom: 48px;
}
@media (max-width: 900px) {
  .sub-cards { grid-template-columns: 1fr; max-width: 440px; margin-left: auto; margin-right: auto; }
}

.sub-card {
  position: relative;
  background: #fff;
  border: 1.5px solid #e4e4e7;
  border-radius: 20px;
  padding: 28px 24px 24px;
  transition: box-shadow 0.22s, transform 0.22s, border-color 0.22s;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.sub-card:hover {
  box-shadow: 0 8px 30px rgba(0,0,0,0.09);
  transform: translateY(-2px);
}

/* Popular (middle) card */
.sub-card--popular {
  border-color: #fa7070;
  box-shadow: 0 6px 24px rgba(250,112,112,0.18);
  transform: translateY(-8px);
  padding-top: 36px;
}
.sub-card--popular:hover {
  box-shadow: 0 12px 36px rgba(250,112,112,0.24);
  transform: translateY(-12px);
}

/* Current plan card */
.sub-card--current {
  border-color: #a3a3a3;
  background: #fafafa;
}

/* ── Popular badge ────────────────────────────────────────────────────── */
.sub-popular-badge {
  position: absolute;
  top: -14px;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(90deg, #fa7070, #e05555);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: .04em;
  padding: 5px 18px;
  border-radius: 999px;
  white-space: nowrap;
  box-shadow: 0 2px 12px rgba(250,112,112,0.4);
}

/* ── Card head ────────────────────────────────────────────────────────── */
.sub-card-head { margin-bottom: 20px; }
.sub-card-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
}
.sub-card-icon svg { width: 22px; height: 22px; }
.sub-card-icon--starter      { background: #f0f9ff; color: #0369a1; }
.sub-card-icon--professional { background: #fff0f0; color: #fa7070; }
.sub-card-icon--enterprise   { background: #f5f3ff; color: #7c3aed; }

.sub-card-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 6px;
}
.sub-card-desc {
  font-size: 0.82rem;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

/* ── Price ────────────────────────────────────────────────────────────── */
.sub-card-price {
  display: flex;
  align-items: baseline;
  gap: 2px;
  margin-bottom: 4px;
}
.sub-price-currency {
  font-size: 1.25rem;
  font-weight: 700;
  color: #374151;
  margin-right: 1px;
}
.sub-price-amount {
  font-size: 3rem;
  font-weight: 800;
  color: #111827;
  line-height: 1;
  letter-spacing: -0.04em;
}
.sub-price-period {
  font-size: 0.85rem;
  color: #9ca3af;
  font-weight: 500;
  align-self: flex-end;
  margin-left: 2px;
  margin-bottom: 6px;
}
.sub-price-billed {
  font-size: 0.75rem;
  color: #9ca3af;
  margin: 0 0 8px;
}

/* ── Divider ──────────────────────────────────────────────────────────── */
.sub-card-divider {
  height: 1px;
  background: #f3f4f6;
  margin: 16px 0;
}

/* ── Features list ────────────────────────────────────────────────────── */
.sub-features {
  list-style: none;
  padding: 0;
  margin: 0 0 24px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
}
.sub-feature {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 0.875rem;
  color: #374151;
  line-height: 1.45;
}
.sub-check {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  margin-top: 1px;
}

/* ── CTA button ───────────────────────────────────────────────────────── */
.sub-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px 20px;
  border-radius: 12px;
  font-size: 0.9rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: background 0.18s, transform 0.15s, box-shadow 0.18s, opacity 0.15s;
  margin-top: auto;
}
.sub-cta:active { transform: scale(0.98); }
.sub-cta:disabled { cursor: not-allowed; opacity: 0.62; transform: none !important; }

.sub-cta--primary {
  background: #fa7070;
  color: #fff;
  box-shadow: 0 2px 14px rgba(250,112,112,0.38);
}
.sub-cta--primary:not(:disabled):hover {
  background: #e05555;
  box-shadow: 0 4px 18px rgba(250,112,112,0.48);
  transform: translateY(-1px);
}
.sub-cta--outline {
  background: #fff;
  color: #374151;
  border: 1.5px solid #d1d5db;
}
.sub-cta--outline:not(:disabled):hover {
  border-color: #9ca3af;
  background: #f9fafb;
}
.sub-cta--current {
  background: #f3f4f6;
  color: #6b7280;
  border: 1.5px solid #e5e7eb;
}
.sub-cta--ghost {
  background: #f9fafb;
  color: #9ca3af;
  border: 1.5px dashed #d1d5db;
}
.sub-cta-lock {
  width: 14px;
  height: 14px;
  opacity: 0.6;
}

/* ── Skeletons ────────────────────────────────────────────────────────── */
.sub-skeleton-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 48px;
}
@media (max-width: 900px) { .sub-skeleton-row { grid-template-columns: 1fr; } }
.sub-skeleton-card {
  height: 440px;
  border-radius: 20px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: sub-shimmer 1.5s infinite;
}
@keyframes sub-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Error / load error ───────────────────────────────────────────────── */
.sub-load-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 18px;
  background: #fef2f2;
  border: 1px solid #fca5a5;
  border-radius: 12px;
  color: #991b1b;
  font-size: 0.875rem;
  margin-bottom: 24px;
}

/* ── Trust strip ──────────────────────────────────────────────────────── */
.sub-trust-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 32px;
  flex-wrap: wrap;
  margin-bottom: 64px;
  padding: 20px 0;
  border-top: 1px solid #f3f4f6;
  border-bottom: 1px solid #f3f4f6;
}
.sub-trust-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem;
  color: #6b7280;
  font-weight: 500;
}
.sub-trust-item svg { color: #22c55e; flex-shrink: 0; }

/* ── FAQ ──────────────────────────────────────────────────────────────── */
.sub-faq { max-width: 680px; margin: 0 auto; }
.sub-faq-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #111827;
  text-align: center;
  margin: 0 0 32px;
  letter-spacing: -0.02em;
}
.sub-faq-list { display: flex; flex-direction: column; gap: 0; }

.sub-faq-item {
  border-bottom: 1px solid #f3f4f6;
}
.sub-faq-item:first-child { border-top: 1px solid #f3f4f6; }

.sub-faq-q {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  list-style: none;
  padding: 18px 4px;
  font-size: 0.95rem;
  font-weight: 600;
  color: #111827;
  cursor: pointer;
  user-select: none;
  transition: color 0.15s;
}
.sub-faq-q::-webkit-details-marker { display: none; }
.sub-faq-q:hover { color: #fa7070; }

.sub-faq-chevron {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  color: #9ca3af;
  transition: transform 0.2s;
}
details[open] .sub-faq-chevron { transform: rotate(180deg); }
details[open] .sub-faq-q { color: #fa7070; }

.sub-faq-a {
  font-size: 0.875rem;
  color: #6b7280;
  line-height: 1.7;
  margin: 0 0 18px 4px;
}

/* ── Confirmation modal ───────────────────────────────────────────────── */
.sub-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(17,24,39,0.55);
  backdrop-filter: blur(3px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.sub-modal {
  position: relative;
  background: #fff;
  border-radius: 22px;
  padding: 36px 32px 28px;
  max-width: 420px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0,0,0,0.18);
  animation: sub-modal-in 0.22s ease;
}
@keyframes sub-modal-in {
  from { opacity: 0; transform: translateY(16px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
.sub-modal-close {
  position: absolute;
  top: 14px; right: 14px;
  background: #f3f4f6;
  border: none;
  border-radius: 8px;
  width: 32px; height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: background 0.15s, color 0.15s;
}
.sub-modal-close:hover { background: #e5e7eb; color: #111827; }

.sub-modal-icon {
  width: 52px; height: 52px;
  background: #fff0f0;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  color: #fa7070;
}
.sub-modal-icon svg { width: 26px; height: 26px; }

.sub-modal-title {
  font-size: 1.2rem;
  font-weight: 800;
  color: #111827;
  text-align: center;
  margin: 0 0 12px;
}
.sub-modal-body {
  font-size: 0.9rem;
  color: #6b7280;
  text-align: center;
  line-height: 1.6;
  margin: 0 0 20px;
}

.sub-modal-meta {
  background: #f9fafb;
  border: 1px solid #f3f4f6;
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sub-modal-meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.82rem;
  color: #6b7280;
}
.sub-modal-meta-row span:last-child {
  font-weight: 600;
  color: #374151;
}

.sub-modal-error {
  background: #fef2f2;
  border: 1px solid #fca5a5;
  color: #991b1b;
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 0.82rem;
  margin-bottom: 16px;
  text-align: center;
}

.sub-modal-actions {
  display: flex;
  gap: 10px;
}
.sub-modal-cancel {
  flex: 1;
  padding: 12px;
  border-radius: 12px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.sub-modal-cancel:hover:not(:disabled) { background: #f9fafb; border-color: #d1d5db; }
.sub-modal-cancel:disabled { opacity: 0.5; cursor: not-allowed; }

.sub-modal-proceed {
  flex: 2;
  padding: 12px;
  border-radius: 12px;
  border: none;
  background: #fa7070;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 2px 12px rgba(250,112,112,0.38);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 0.15s, box-shadow 0.15s;
}
.sub-modal-proceed:hover:not(:disabled) {
  background: #e05555;
  box-shadow: 0 4px 18px rgba(250,112,112,0.48);
}
.sub-modal-proceed:disabled { opacity: 0.7; cursor: not-allowed; }

/* ── Spinner ──────────────────────────────────────────────────────────── */
.sub-spin {
  width: 16px; height: 16px;
  animation: sub-rotate 0.9s linear infinite;
  flex-shrink: 0;
}
@keyframes sub-rotate {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

/* ── Dark mode ────────────────────────────────────────────────────────── */
@media (prefers-color-scheme: dark) {
  .sub-heading, .sub-card-name, .sub-status-name, .sub-modal-title, .sub-faq-title { color: #f9fafb; }
  .sub-card, .sub-status-card, .sub-modal { background: #1f2937; border-color: #374151; }
  .sub-card--current { background: #111827; }
  .sub-subheading, .sub-card-desc, .sub-faq-a, .sub-modal-body { color: #9ca3af; }
  .sub-feature { color: #d1d5db; }
  .sub-price-currency, .sub-price-amount { color: #f9fafb; }
  .sub-price-period, .sub-price-billed { color: #6b7280; }
  .sub-card-divider { background: #374151; }
  .sub-toggle-wrap { background: #111827; }
  .sub-cycle-btn--active { background: #1f2937; color: #f9fafb; box-shadow: 0 1px 4px rgba(0,0,0,0.4); }
  .sub-faq-item, .sub-faq-item:first-child { border-color: #374151; }
  .sub-faq-q { color: #f9fafb; }
  .sub-trust-row { border-color: #374151; }
  .sub-modal-meta { background: #111827; border-color: #374151; }
  .sub-modal-meta-row span:last-child { color: #f9fafb; }
  .sub-modal-close { background: #374151; color: #9ca3af; }
  .sub-modal-close:hover { background: #4b5563; color: #f9fafb; }
  .sub-modal-cancel { background: #1f2937; border-color: #374151; color: #d1d5db; }
  .sub-modal-cancel:hover:not(:disabled) { background: #374151; }
  .sub-skeleton-card { background: linear-gradient(90deg, #1f2937 25%, #374151 50%, #1f2937 75%); }
}

:root[data-theme="dark"] .sub-heading,
:root[data-theme="dark"] .sub-card-name,
:root[data-theme="dark"] .sub-status-name,
:root[data-theme="dark"] .sub-modal-title,
:root[data-theme="dark"] .sub-faq-title { color: #f9fafb; }

:root[data-theme="dark"] .sub-card,
:root[data-theme="dark"] .sub-status-card,
:root[data-theme="dark"] .sub-modal { background: #1f2937; border-color: #374151; }

:root[data-theme="dark"] .sub-card--current { background: #111827; }

:root[data-theme="dark"] .sub-subheading,
:root[data-theme="dark"] .sub-card-desc,
:root[data-theme="dark"] .sub-faq-a,
:root[data-theme="dark"] .sub-modal-body { color: #9ca3af; }

:root[data-theme="dark"] .sub-feature    { color: #d1d5db; }
:root[data-theme="dark"] .sub-price-currency,
:root[data-theme="dark"] .sub-price-amount { color: #f9fafb; }
:root[data-theme="dark"] .sub-price-period,
:root[data-theme="dark"] .sub-price-billed { color: #6b7280; }
:root[data-theme="dark"] .sub-card-divider { background: #374151; }
:root[data-theme="dark"] .sub-toggle-wrap { background: #111827; }
:root[data-theme="dark"] .sub-cycle-btn--active { background: #1f2937; color: #f9fafb; box-shadow: 0 1px 4px rgba(0,0,0,0.4); }
:root[data-theme="dark"] .sub-faq-item,
:root[data-theme="dark"] .sub-faq-item:first-child { border-color: #374151; }
:root[data-theme="dark"] .sub-faq-q { color: #f9fafb; }
:root[data-theme="dark"] .sub-trust-row { border-color: #374151; }
:root[data-theme="dark"] .sub-modal-meta { background: #111827; border-color: #374151; }
:root[data-theme="dark"] .sub-modal-meta-row span:last-child { color: #f9fafb; }
:root[data-theme="dark"] .sub-modal-close { background: #374151; color: #9ca3af; }
:root[data-theme="dark"] .sub-modal-close:hover { background: #4b5563; color: #f9fafb; }
:root[data-theme="dark"] .sub-modal-cancel { background: #1f2937; border-color: #374151; color: #d1d5db; }
:root[data-theme="dark"] .sub-modal-cancel:hover:not(:disabled) { background: #374151; }
:root[data-theme="dark"] .sub-skeleton-card { background: linear-gradient(90deg, #1f2937 25%, #374151 50%, #1f2937 75%); }
</style>
