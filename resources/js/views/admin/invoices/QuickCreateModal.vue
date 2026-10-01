<template>
  <!-- Rendered in place (not teleported) so it stays inside #page-container
       and picks up the app's .dark-mode styles. -->
  <div class="qc-overlay" @mousedown.self="!loading && $emit('cancel')">
    <div class="qc-modal" role="dialog" aria-modal="true" :aria-labelledby="titleId" @keydown.esc="!loading && $emit('cancel')">
      <form novalidate @submit.prevent="$emit('submit')">
        <div class="qc-head">
          <span class="qc-icon"><i :class="icon"></i></span>
          <h2 :id="titleId" class="qc-title">{{ title }}</h2>
        </div>

        <div class="qc-body">
          <slot />
        </div>

        <p v-if="error" class="qc-error" role="alert"><i class="fa fa-exclamation-circle me-1"></i>{{ error }}</p>
        <p v-if="hint" class="qc-hint">{{ hint }}</p>

        <div class="qc-actions">
          <button type="button" class="qc-btn qc-btn--ghost" :disabled="loading" @click="$emit('cancel')">
            {{ $t('common.cancel') }}
          </button>
          <button type="submit" class="qc-btn qc-btn--primary" :disabled="loading || disabled" data-test="quick-create-submit">
            <i v-if="loading" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa fa-check"></i>
            {{ loading ? $t('invoices.form.quickCreate.creating') : $t('invoices.form.quickCreate.createAndSelect') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
/**
 * Small shared shell for the invoice page's quick-create dialogs
 * (QuickCustomerModal / QuickItemModal): title, body slot, error/hint,
 * Cancel + "Create and select". Purely presentational.
 */
defineProps({
  title:    { type: String, required: true },
  icon:     { type: String, default: 'fa fa-plus' },
  loading:  { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error:    { type: String, default: '' },
  hint:     { type: String, default: '' },
});
defineEmits(['cancel', 'submit']);

const titleId = 'qc-title-' + Math.random().toString(36).slice(2, 8);
</script>

<style scoped>
.qc-overlay {
  --qc-accent:   var(--brand-primary);
  --qc-accent-h: var(--brand-primary-hover);
  --qc-ring:     rgba(233, 30, 99, 0.15);
  --qc-bg:       #ffffff;
  --qc-subtle:   #f8fafc;
  --qc-border:   #e2e8f0;
  --qc-text:     #0f172a;
  --qc-body:     #374151;
  --qc-muted:    #64748b;

  position: fixed;
  inset: 0;
  z-index: 1060;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.45);
}
:global(.dark-mode) .qc-overlay {
  --qc-bg:     var(--dark-surface);
  --qc-subtle: var(--dark-surface-elevated);
  --qc-border: var(--dark-border-subtle);
  --qc-text:   var(--dark-text);
  --qc-body:   var(--dark-text-secondary);
  --qc-muted:  var(--dark-text-muted);
  background: rgba(0, 0, 0, 0.6);
}

.qc-modal {
  width: 100%;
  max-width: 440px;
  max-height: calc(100dvh - 32px);
  overflow-y: auto;
  background: var(--qc-bg);
  color: var(--qc-text);
  border: 1px solid var(--qc-border);
  border-top: 3px solid var(--qc-accent);
  border-radius: 14px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
  padding: 20px 22px;
}

.qc-head { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.qc-icon {
  width: 32px; height: 32px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  border-radius: 9px;
  background: rgba(233, 30, 99, 0.08);
  color: var(--qc-accent);
  font-size: 0.85rem;
}
.qc-title { font-size: 1.05rem; font-weight: 700; margin: 0; color: var(--qc-text); }

.qc-body { display: flex; flex-direction: column; gap: 14px; }

.qc-error { margin: 14px 0 0; font-size: 0.8rem; color: #dc2626; }
:global(.dark-mode) .qc-error { color: #f87171; }
.qc-hint  { margin: 12px 0 0; font-size: 0.75rem; color: var(--qc-muted); }

.qc-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
.qc-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  padding: 9px 16px;
  border-radius: 8px;
  font-size: 0.875rem; font-weight: 600;
  border: 1.5px solid transparent;
  cursor: pointer;
  line-height: 1;
  white-space: nowrap;
  transition: background .15s, border-color .15s;
}
.qc-btn:disabled { opacity: .6; cursor: not-allowed; }
.qc-btn--primary { background: var(--qc-accent); border-color: var(--qc-accent); color: #fff; }
.qc-btn--primary:hover:not(:disabled) { background: var(--qc-accent-h); border-color: var(--qc-accent-h); }
.qc-btn--ghost { background: transparent; border-color: var(--qc-border); color: var(--qc-body); }
.qc-btn--ghost:hover:not(:disabled) { background: var(--qc-subtle); }

/* Shared field styles for the slotted forms (scoped data-v doesn't reach
   slot content defined by the parent, so they're exposed via :deep). */
.qc-modal :deep(.qc-field) { display: flex; flex-direction: column; }
.qc-modal :deep(.qc-label) { font-size: 0.8rem; font-weight: 600; color: var(--qc-body); margin: 0 0 6px; }
.qc-modal :deep(.qc-label--req::after) { content: " *"; color: var(--qc-accent); }
.qc-modal :deep(.qc-input),
.qc-modal :deep(.qc-select) {
  width: 100%;
  border: 1.5px solid var(--qc-border);
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 0.875rem;
  color: var(--qc-text);
  background: var(--qc-bg);
  outline: none;
  line-height: 1.4;
}
.qc-modal :deep(.qc-input:focus),
.qc-modal :deep(.qc-select:focus) { border-color: var(--qc-accent); box-shadow: 0 0 0 3px var(--qc-ring); }
.qc-modal :deep(.qc-input--err) { border-color: #ef4444 !important; }
.qc-modal :deep(.qc-err-msg) { font-size: 0.75rem; color: #ef4444; margin: 5px 0 0; }
.qc-modal :deep(.qc-two-col) { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.qc-modal :deep(.qc-seg) {
  display: inline-flex;
  border: 1.5px solid var(--qc-border);
  border-radius: 8px;
  overflow: hidden;
  background: var(--qc-subtle);
  align-self: flex-start;
}
.qc-modal :deep(.qc-seg-btn) {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px;
  font-size: 0.85rem; font-weight: 500;
  color: var(--qc-muted);
  background: transparent;
  border: none;
  cursor: pointer;
}
.qc-modal :deep(.qc-seg-btn + .qc-seg-btn) { border-inline-start: 1.5px solid var(--qc-border); }
.qc-modal :deep(.qc-seg-btn--on) { background: var(--qc-accent); color: #fff; }

@media (max-width: 480px) {
  .qc-overlay { align-items: flex-end; padding: 0; }
  .qc-modal {
    max-width: none;
    border-radius: 16px 16px 0 0;
    padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px));
  }
  .qc-actions { flex-direction: column-reverse; }
  .qc-actions .qc-btn { width: 100%; }
  .qc-modal :deep(.qc-two-col) { grid-template-columns: 1fr; }
}
</style>
