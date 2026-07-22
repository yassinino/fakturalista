<template>
  <div v-if="showAny" class="bb-wrap">

    <!-- Trial countdown: shown when ≤7 days remain and still trialing -->
    <div v-if="store.billing.showTrialBanner && !store.billing.isReadOnly" class="bb-banner bb-banner--trial" role="status">
      <i class="fa fa-hourglass-half bb-icon"></i>
      <div class="bb-body">
        <strong>{{ store.billing.trialDaysLeft }} day{{ store.billing.trialDaysLeft === 1 ? '' : 's' }} left in your free trial.</strong>
        Subscribe now to keep full access.
      </div>
      <router-link :to="{ name: 'backend-subscription' }" class="bb-cta">
        View plans <i class="fa fa-arrow-right ms-1"></i>
      </router-link>
    </div>

    <!-- Read-only / expired lockout -->
    <div v-if="store.billing.isReadOnly" class="bb-banner bb-banner--readonly" role="alert">
      <i class="fa fa-lock bb-icon"></i>
      <div class="bb-body">
        <strong>Your trial has ended.</strong>
        The app is in read-only mode — you can view data but cannot create or modify records.
      </div>
      <router-link :to="{ name: 'backend-subscription' }" class="bb-cta bb-cta--white">
        Subscribe to unlock <i class="fa fa-arrow-right ms-1"></i>
      </router-link>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useTemplateStore } from '@/stores/template';

const store   = useTemplateStore();
const showAny = computed(() => store.billing.showTrialBanner || store.billing.isReadOnly);
</script>

<style scoped>
.bb-wrap {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.bb-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 24px;
  font-size: 13.5px;
  line-height: 1.5;
}

.bb-banner--trial {
  background: linear-gradient(90deg, #f59e0b10, #f59e0b08);
  border-bottom: 1px solid rgba(245,158,11,0.25);
  color: #92400e;
}

.bb-banner--readonly {
  background: linear-gradient(90deg, #E91E6314, #c2185b0a);
  border-bottom: 1px solid rgba(233,30,99,0.2);
  color: #9d1c4a;
}

/* dark mode */
@media (prefers-color-scheme: dark) {
  .bb-banner--trial   { color: #fbbf24; background: rgba(245,158,11,0.08); }
  .bb-banner--readonly { color: #f472b6; background: rgba(233,30,99,0.08); }
}

:root[data-theme="dark"] .bb-banner--trial   { color: #fbbf24; background: rgba(245,158,11,0.08); }
:root[data-theme="dark"] .bb-banner--readonly { color: #f472b6; background: rgba(233,30,99,0.08); }

.bb-icon {
  font-size: 16px;
  flex-shrink: 0;
}

.bb-body { flex: 1; }

.bb-cta {
  flex-shrink: 0;
  white-space: nowrap;
  font-weight: 700;
  font-size: 13px;
  text-decoration: none;
  color: #E91E63;
  padding: 6px 14px;
  border: 1.5px solid rgba(233,30,99,0.35);
  border-radius: 8px;
  transition: background 0.15s, color 0.15s;
}

.bb-cta:hover {
  background: rgba(233,30,99,0.07);
}

.bb-cta--white {
  color: #E91E63;
}
</style>
