<template>
  <div class="dc-banner" role="status" aria-live="polite" data-test="document-created-banner">
    <span class="dc-icon" aria-hidden="true"><i class="fa fa-check-circle"></i></span>
    <div class="dc-body">
      <p class="dc-title">{{ title }}</p>
      <p class="dc-text">{{ text }}</p>
    </div>
    <button type="button" class="dc-close" :aria-label="$t('common.close')" data-test="document-created-dismiss" @click="$emit('dismiss')">
      <i class="fa fa-times" aria-hidden="true"></i>
    </button>
  </div>
</template>

<script setup>
/**
 * Shown once on an invoice/quote page right after it was created
 * ("...created → here it is → here's what you can do next"). Purely
 * informational: it never issues, sends or changes anything.
 */
defineProps({
  title: { type: String, required: true },
  text:  { type: String, required: true },
});
defineEmits(['dismiss']);
</script>

<style scoped>
.dc-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin: 0 0 18px;
  padding: 12px 14px;
  border: 1px solid #a7f3d0;
  border-radius: 12px;
  background: #ecfdf5;
  color: #14532d;
}
.dc-icon { font-size: 1.1rem; line-height: 1.35; color: #16a34a; flex-shrink: 0; }
.dc-body { flex: 1; min-width: 0; }
.dc-title { margin: 0 0 2px; font-size: 0.9rem; font-weight: 700; }
.dc-text  { margin: 0; font-size: 0.82rem; line-height: 1.5; }
.dc-close {
  flex-shrink: 0;
  width: 30px; height: 30px;
  margin: -4px -6px -4px 0;
  border: none; border-radius: 8px;
  background: transparent; color: inherit; opacity: .7;
  cursor: pointer;
}
.dc-close:hover { opacity: 1; }
.dc-close:focus-visible { outline: 2px solid #16a34a; outline-offset: 2px; }
.rtl-support .dc-close, [dir="rtl"] .dc-close { margin: -4px 0 -4px -6px; }

.dark-mode .dc-banner { background: rgba(22, 163, 74, 0.12); border-color: rgba(22, 163, 74, 0.35); color: #bbf7d0; }
.dark-mode .dc-icon { color: #4ade80; }
</style>
