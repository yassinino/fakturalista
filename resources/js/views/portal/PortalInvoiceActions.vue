<template>
  <div :class="variant === 'card' ? 'pt-inv-actions pt-inv-actions--card' : 'pt-inv-actions'">
    <button
      v-if="invoice.payment?.payable"
      type="button"
      class="pt-pay-btn"
      :class="{ 'pt-pay-btn--card': variant === 'card' }"
      :disabled="payBusy"
      data-test="pay-now"
      @click="$emit('pay', invoice)"
    >
      <i class="fa fa-credit-card" aria-hidden="true"></i>
      <span>{{ t('portal.payment.payNow') }}</span>
    </button>

    <div class="pt-inv-downloads">
      <button
        type="button"
        class="pt-action-btn"
        :class="{ 'pt-action-btn--wide': variant === 'card' }"
        :disabled="downloadKey === 'invoice-pdf-' + invoice.uuid"
        :aria-label="t('portal.download.pdf')"
        :title="t('portal.download.pdf')"
        data-test="download-pdf"
        @click="$emit('download-pdf', invoice)"
      >
        <i v-if="downloadKey === 'invoice-pdf-' + invoice.uuid" class="fa fa-spinner fa-spin"></i>
        <i v-else class="fa fa-file-pdf"></i>
        <template v-if="variant === 'card'">{{ t('portal.download.pdf') }}</template>
      </button>
      <button
        type="button"
        class="pt-action-btn"
        :class="{ 'pt-action-btn--wide': variant === 'card' }"
        :disabled="downloadKey === 'invoice-ubl-' + invoice.uuid"
        :aria-label="t('portal.download.ubl')"
        :title="t('portal.download.ubl')"
        data-test="download-ubl"
        @click="$emit('download-ubl', invoice)"
      >
        <i v-if="downloadKey === 'invoice-ubl-' + invoice.uuid" class="fa fa-spinner fa-spin"></i>
        <i v-else class="fa fa-file-code"></i>
        <template v-if="variant === 'card'">{{ t('portal.download.ubl') }}</template>
      </button>
    </div>
  </div>
</template>

<script setup>
/**
 * Client Portal - one invoice's actions (Step 6B): "Pay now" when, and
 * only when, the server says `payment.payable`, plus the unchanged PDF/UBL
 * downloads. Rendered by ClientPortalView.vue both as a compact desktop
 * table cell (`row`) and as the mobile card footer (`card`). Presentational
 * only - the parent owns the download/payment requests.
 */
import { useI18n } from 'vue-i18n';

defineProps({
  invoice: { type: Object, required: true },
  variant: { type: String, default: 'row' }, // 'row' | 'card'
  downloadKey: { type: String, default: null },
  payBusy: { type: Boolean, default: false },
});
defineEmits(['pay', 'download-pdf', 'download-ubl']);

const { t } = useI18n();
</script>

<style scoped>
.pt-inv-actions { display: flex; align-items: center; gap: 6px; justify-content: flex-end; }
.pt-inv-actions--card { flex-direction: column; align-items: stretch; gap: 8px; margin-top: 14px; }
.pt-inv-downloads { display: flex; gap: 6px; }
.pt-inv-actions--card .pt-inv-downloads { gap: 8px; }

/* Shared look with ClientPortalView.vue's own .pt-action-btn */
.pt-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 40px;
  min-height: 40px;
  padding: 0 10px;
  border: 1px solid var(--pt-border);
  border-radius: 9px;
  background: var(--pt-surface);
  color: var(--pt-text-muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s ease, color .15s ease, border-color .15s ease;
}
.pt-action-btn:hover:not(:disabled) { background: var(--pt-bg); color: var(--pt-text); border-color: var(--pt-accent); }
.pt-action-btn:disabled { opacity: .6; cursor: not-allowed; }
.pt-action-btn--wide { flex: 1; min-height: 44px; }

/* Pay now - the one primary action, in the company's accent colour */
.pt-pay-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 40px;
  padding: 0 14px;
  border: none;
  border-radius: 9px;
  background: var(--pt-accent);
  color: var(--pt-on-accent, #fff);
  font-size: 13.5px;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(20, 20, 30, .12);
  transition: filter .15s ease, transform .05s ease;
}
.pt-pay-btn:hover:not(:disabled) { filter: brightness(1.06); }
.pt-pay-btn:active:not(:disabled) { transform: translateY(1px); }
.pt-pay-btn:focus-visible { outline: 3px solid var(--pt-accent); outline-offset: 2px; }
.pt-pay-btn:disabled { opacity: .6; cursor: not-allowed; }
.pt-pay-btn--card { width: 100%; min-height: 50px; font-size: 15.5px; border-radius: 12px; }
</style>
