<template>
  <div class="cs-root">
    <div class="cs-card">
      <div class="cs-logo">
        <span class="cs-logo-icon">
          <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill="url(#csLogoGrad)"/>
            <path d="M8 10h16M8 16h10M8 22h13" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
            <defs>
              <linearGradient id="csLogoGrad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                <stop stop-color="#E91E63"/>
                <stop offset="1" stop-color="#c2185b"/>
              </linearGradient>
            </defs>
          </svg>
        </span>
        <span class="cs-logo-name">Fakturalista</span>
      </div>

      <!-- Waiting for the webhook to confirm - never claim success from the URL alone -->
      <template v-if="state === 'processing'">
        <div class="cs-icon cs-icon--pending"><i class="fa fa-spinner fa-spin"></i></div>
        <h1 class="cs-title">{{ $t('checkoutSuccess.processingTitle') }}</h1>
        <p class="cs-subtitle">{{ $t('checkoutSuccess.processingSubtitle') }}</p>
      </template>

      <template v-else-if="state === 'success'">
        <div class="cs-icon cs-icon--success"><i class="fa fa-circle-check"></i></div>
        <h1 class="cs-title">{{ $t('checkoutSuccess.title') }}</h1>
        <p class="cs-subtitle">{{ $t('checkoutSuccess.subtitle') }}</p>

        <div class="cs-summary">
          <div class="cs-summary-row">
            <span class="cs-summary-label">{{ $t('checkoutSuccess.plan') }}</span>
            <span class="cs-summary-value">{{ planName }}</span>
          </div>
          <div class="cs-summary-row">
            <span class="cs-summary-label">{{ $t('checkoutSuccess.amount') }}</span>
            <span class="cs-summary-value">{{ amountLabel }}</span>
          </div>
          <div class="cs-summary-row">
            <span class="cs-summary-label">{{ $t('checkoutSuccess.status') }}</span>
            <span class="cs-summary-value cs-status-badge">{{ statusLabel }}</span>
          </div>
        </div>

        <button type="button" class="cs-btn-primary" @click="goToDashboard">
          {{ $t('checkoutSuccess.goToApp') }}
        </button>
        <button type="button" class="cs-btn-link" @click="goToSubscription">
          {{ $t('checkoutSuccess.manage') }}
        </button>
      </template>

      <template v-else>
        <div class="cs-icon cs-icon--pending"><i class="fa fa-clock"></i></div>
        <h1 class="cs-title">{{ $t('checkoutSuccess.timeoutTitle') }}</h1>
        <p class="cs-subtitle">{{ $t('checkoutSuccess.timeoutSubtitle') }}</p>

        <button type="button" class="cs-btn-primary" @click="checkAgain">
          {{ $t('checkoutSuccess.checkAgain') }}
        </button>
        <button type="button" class="cs-btn-link" @click="goToDashboard">
          {{ $t('checkoutSuccess.goToApp') }}
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useTemplateStore } from '@/stores/template';
import axios from 'axios';

const router = useRouter();
const { t }  = useI18n();
const store  = useTemplateStore();

// 'processing' | 'success' | 'timeout'
const state = ref('processing');
const subscription = ref(null);

const MAX_ATTEMPTS   = 10;
const POLL_INTERVAL  = 2000; // ms
let pollTimer = null;
let attempts  = 0;

const planName = computed(() => subscription.value?.plan?.name ?? '');

const amountLabel = computed(() => {
  const price = subscription.value?.price;
  if (!price) return '';
  const period = price.interval === 'yearly' ? t('checkoutSuccess.perYear') : t('checkoutSuccess.perMonth');
  return `${(price.amount / 100).toFixed(2)} ${price.currency}${period}`;
});

const statusLabel = computed(() => {
  const status = subscription.value?.status;
  if (status === 'trialing') return t('checkoutSuccess.statusTrialing');
  return t('checkoutSuccess.statusActive');
});

// A real, webhook-confirmed subscription always carries its plan - the
// pre-checkout "just trialing, no Subscription row yet" response never
// includes one (see SubscriptionController::index()). This is the only
// signal that decides "success", never the query string alone.
async function poll() {
  attempts += 1;
  try {
    const { data } = await axios.get('/subscription');
    if (data?.subscription?.plan) {
      subscription.value = data.subscription;
      state.value = 'success';

      // Refresh billing state so the rest of the app (router guard,
      // trial banners, disabled buttons) reflects the new plan immediately.
      try {
        const userRes = await axios.get('/user');
        if (userRes.data?.billing) store.setBillingStatus(userRes.data.billing);
        if (userRes.data?.company_context) store.setCompanyContext(userRes.data.company_context);
      } catch {
        // Non-fatal - the success screen itself already has what it needs.
      }
      return;
    }
  } catch {
    // Keep polling - a transient error here must not flip to "timeout" early.
  }

  if (attempts >= MAX_ATTEMPTS) {
    state.value = 'timeout';
    return;
  }

  pollTimer = setTimeout(poll, POLL_INTERVAL);
}

function checkAgain() {
  attempts = 0;
  state.value = 'processing';
  poll();
}

function goToDashboard() {
  router.push({ name: 'backend-dashboard' });
}

function goToSubscription() {
  router.push({ name: 'backend-subscription' });
}

onMounted(poll);
onBeforeUnmount(() => {
  if (pollTimer) clearTimeout(pollTimer);
});
</script>

<style scoped>
.cs-root {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f6fa;
  padding: 24px;
}

.cs-card {
  width: 100%;
  max-width: 420px;
  background: #ffffff;
  border: 1px solid rgba(0,0,0,0.07);
  border-radius: 20px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07), 0 24px 60px -12px rgba(0,0,0,0.12);
  padding: 36px 32px;
  text-align: center;
}

.cs-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 28px;
}
.cs-logo-icon { display: flex; filter: drop-shadow(0 4px 12px rgba(233,30,99,0.3)); }
.cs-logo-name { font-size: 16px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px; }

.cs-icon { font-size: 44px; margin-bottom: 16px; }
.cs-icon--success { color: #10b981; }
.cs-icon--pending { color: #E91E63; }

.cs-title {
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px;
  letter-spacing: -0.3px;
}
.cs-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 22px;
  line-height: 1.5;
}

.cs-summary {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 4px 16px;
  margin-bottom: 24px;
  text-align: left;
}
.cs-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 11px 0;
  border-bottom: 1px solid #e2e8f0;
}
.cs-summary-row:last-child { border-bottom: none; }
.cs-summary-label { font-size: 12.5px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.cs-summary-value { font-size: 14px; color: #0f172a; font-weight: 700; }
.cs-status-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  background: rgba(16,185,129,0.12);
  color: #10b981;
}

.cs-btn-primary {
  width: 100%;
  padding: 11px 20px;
  background: #E91E63;
  color: #fff;
  border: 1px solid #E91E63;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: background .15s, border-color .15s, transform .15s;
}
.cs-btn-primary:hover { background: #c2185b; border-color: #c2185b; transform: translateY(-1px); }

.cs-btn-link {
  display: block;
  width: 100%;
  margin-top: 12px;
  padding: 8px;
  background: none;
  border: none;
  color: #64748b;
  font-size: 0.85rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}
.cs-btn-link:hover { color: #E91E63; }
</style>
