<template>
  <div class="inv-more">
    <button
      type="button"
      class="inv-more-btn"
      :aria-expanded="String(modelValue)"
      :aria-controls="panelId"
      data-test="more-options-toggle"
      @click="$emit('update:modelValue', !modelValue)"
    >
      <i class="fa" :class="modelValue ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true"></i>
      {{ label }}
      <span v-if="hasError && !modelValue" class="inv-more-dot" aria-hidden="true"></span>
    </button>

    <!-- v-show (not v-if): the fields stay mounted while collapsed, so
         nothing typed in them is ever lost. -->
    <div v-show="modelValue" :id="panelId" class="inv-more-panel" data-test="more-options-panel">
      <slot />
    </div>
  </div>
</template>

<script setup>
/**
 * "Plus d'options" - progressive disclosure for the optional fields of the
 * Create Invoice / Create Quote forms (status, notes, ...). Collapsed state
 * is owned by the parent (v-model). Whenever `hasError` becomes true (a
 * field inside fails validation) the section opens itself so the error is
 * never hidden.
 */
import { watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  label:      { type: String, required: true },
  hasError:   { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const panelId = 'inv-more-' + Math.random().toString(36).slice(2, 8);

watch(() => props.hasError, (error) => {
  if (error && !props.modelValue) emit('update:modelValue', true);
}, { immediate: true });
</script>

<style scoped>
/* Same subtle look as the forms' previous "more options" link + panel. */
.inv-more-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  font-size: 0.8rem;
  color: #6b7280;
  cursor: pointer;
  padding: 4px 0;
  transition: color 0.15s;
}
.inv-more-btn:hover,
.inv-more-btn:focus-visible { color: var(--brand-text); }
.inv-more-btn:focus-visible { outline: 2px solid var(--brand-primary); outline-offset: 2px; border-radius: 4px; }

.inv-more-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ef4444;
}

.inv-more-panel {
  margin-top: 10px;
  padding: 14px;
  background: #f8f9fc;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dark-mode .inv-more-btn { color: var(--dark-text-muted); }
.dark-mode .inv-more-btn:hover,
.dark-mode .inv-more-btn:focus-visible { color: var(--brand-text); }
.dark-mode .inv-more-panel { background: var(--dark-surface-elevated); }
</style>
