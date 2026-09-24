<template>
  <span class="tax-select-wrap">
    <select
      v-bind="$attrs"
      class="tax-select"
      :value="displayValue"
      :aria-label="taxName"
      :disabled="!presets.length"
      @change="selectTax"
    >
      <option v-if="line.vta == null" value="" disabled>—</option>
      <option v-else-if="isUnmatchedNonTaxable" :value="selectedKey" disabled>
        {{ taxLabel(line.vta, line.tax_treatment, taxName) }} {{ $t('common.savedValueSuffix') }}
      </option>
      <option v-for="preset in presets" :key="preset.code" :value="presetKey(preset)">
        {{ preset.label }}
      </option>
      <option value="__custom__">{{ $t('common.customTaxOption') }}</option>
    </select>
    <input
      v-if="showCustomInput"
      ref="customInputRef"
      v-model="customValue"
      v-decimal="{ decimals: 2 }"
      type="text"
      inputmode="decimal"
      class="tax-select-custom-input"
      :aria-label="$t('common.customTaxOption')"
      :placeholder="$t('common.customTaxPlaceholder')"
    />
  </span>
</template>

<script>
// defineOptions() needs Vue 3.3+ (this project pins ^3.2.31), so
// inheritAttrs is disabled the pre-3.3 way via a plain export instead.
export default { inheritAttrs: false };
</script>

<script setup>
import { computed, ref, watch } from 'vue';
import { taxKey, taxLabel } from '@/utils/tax.mjs';

const props = defineProps({
  line: { type: Object, required: true },
  presets: { type: Array, required: true },
  taxName: { type: String, default: 'Tax' },
});
const emit = defineEmits(['change']);

const presetKey = (preset) => taxKey({ vta: preset.rate, tax_treatment: preset.treatment });
const selectedKey = computed(() => props.line.vta == null ? '' : taxKey(props.line));
const hasPreset = computed(() => props.presets.some(p => presetKey(p) === selectedKey.value));

// A rate that matches no preset is either a legacy/persisted value or a
// user-entered custom percentage. Only "taxable" makes sense to edit as a
// free percentage - exempt/out-of-scope stay preset-only special cases.
const isCustomRate = computed(() =>
  props.line.vta != null && !hasPreset.value && (props.line.tax_treatment ?? 'taxable') === 'taxable'
);
const isUnmatchedNonTaxable = computed(() =>
  props.line.vta != null && !hasPreset.value && (props.line.tax_treatment ?? 'taxable') !== 'taxable'
);

// Whether the "Custom" option is currently what should show as selected -
// either because the line already carries a custom rate, or the user just
// clicked "Custom..." in the dropdown (manualCustomMode) before typing.
const manualCustomMode = ref(false);
const showCustomInput = computed(() => manualCustomMode.value || isCustomRate.value);

const displayValue = computed(() => {
  if (props.line.vta == null) return '';
  if (isUnmatchedNonTaxable.value) return selectedKey.value;
  if (showCustomInput.value) return '__custom__';
  return selectedKey.value;
});

const customInputRef = ref(null);
const customValue = ref(isCustomRate.value ? String(props.line.vta) : '');

// Keep the input in sync with external changes (loading a line, switching
// rows) without fighting the user while they're actively typing in it.
watch(() => [props.line.vta, props.line.tax_treatment], () => {
  if (!isCustomRate.value) return;
  if (customInputRef.value && document.activeElement === customInputRef.value) return;
  if (Number(customValue.value) === Number(props.line.vta)) return;
  customValue.value = String(props.line.vta);
});

watch(customValue, (val) => {
  if (!showCustomInput.value || val === '' || val == null) return;
  const num = Number(String(val).replace(',', '.'));
  if (!Number.isFinite(num) || num < 0 || num > 100) return;
  if (isCustomRate.value && num === Number(props.line.vta)) return;
  emit('change', { vta: num, tax_treatment: 'taxable' });
});

function selectTax(event) {
  const value = event.target.value;

  if (value === '__custom__') {
    manualCustomMode.value = true;
    if (!isCustomRate.value) customValue.value = '';
    return;
  }

  manualCustomMode.value = false;
  const preset = props.presets.find(p => presetKey(p) === value);
  if (preset) emit('change', { vta: preset.rate, tax_treatment: preset.treatment });
}
</script>

<style scoped>
/* Self-contained styling - deliberately not relying on the parent page's
   scoped .inv-select/.itc-select rules, which can't reach an element that
   isn't this component's own root (the wrapping span is the root, so a
   parent's scoped CSS attribute never lands on the <select> itself). */
.tax-select-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  max-width: 100%;
}

.tax-select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  display: block;
  width: 100%;
  min-width: 0;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  padding: 9px 28px 9px 12px;
  font-size: 0.875rem;
  font-weight: 400;
  line-height: 1.4;
  color: #0f172a;
  background-color: #fff;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 10px 6px;
  outline: none;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, background-image 0.15s;
  text-overflow: ellipsis;
}

.tax-select:hover:not(:disabled) {
  border-color: #f3a8c4;
}

.tax-select:focus {
  border-color: #E91E63;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%23E91E63' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}

.tax-select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  background-color: #f8fafc;
}

.tax-select option {
  color: #0f172a;
  background-color: #fff;
  font-size: 0.875rem;
  font-weight: 400;
}

.tax-select-custom-input {
  width: 64px;
  flex-shrink: 0;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  padding: 9px 8px;
  font-size: 0.875rem;
  font-weight: 400;
  line-height: 1.4;
  color: #0f172a;
  background-color: #fff;
  text-align: right;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.tax-select-custom-input:hover:not(:disabled) {
  border-color: #f3a8c4;
}

.tax-select-custom-input:focus {
  border-color: #E91E63;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
}

/* Dark mode - uses the app's real dark-mode toggle (Header/Sidebar ->
   Pinia store -> .dark-mode class on #page-container, see BaseLayout.vue),
   not prefers-color-scheme - that only reflects the OS, which can desync
   from the in-app toggle and leave this select light while everything
   else around it is dark (or vice versa). */
:global(.dark-mode) .tax-select {
  color: var(--dark-text-secondary);
  background-color: var(--dark-input);
  border-color: var(--dark-border);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2394a3b8' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}

:global(.dark-mode) .tax-select:hover:not(:disabled) {
  border-color: var(--brand-primary-active);
}

:global(.dark-mode) .tax-select:focus {
  border-color: var(--brand-primary);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%23E91E63' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}

:global(.dark-mode) .tax-select:disabled {
  background-color: var(--dark-surface);
  opacity: 0.5;
}

:global(.dark-mode) .tax-select option {
  color: var(--dark-text-secondary);
  background-color: var(--dark-surface-elevated);
}

:global(.dark-mode) .tax-select-custom-input {
  color: var(--dark-text-secondary);
  background-color: var(--dark-input);
  border-color: var(--dark-border);
}

:global(.dark-mode) .tax-select-custom-input:hover:not(:disabled) {
  border-color: var(--brand-primary-active);
}

:global(.dark-mode) .tax-select-custom-input:focus {
  border-color: var(--brand-primary);
}
</style>
