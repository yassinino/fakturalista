<template>
  <select :value="selectedKey" :aria-label="taxName" :disabled="!presets.length" @change="selectTax">
    <option v-if="line.vta == null" value="" disabled>—</option>
    <option v-else-if="!hasPreset" :value="selectedKey" disabled>
      {{ taxLabel(line.vta, line.tax_treatment, taxName) }} {{ $t('common.savedValueSuffix') }}
    </option>
    <option v-for="preset in presets" :key="preset.code" :value="presetKey(preset)">
      {{ preset.label }}
    </option>
  </select>
</template>

<script setup>
import { computed } from 'vue';
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
function selectTax(event) {
  const preset = props.presets.find(p => presetKey(p) === event.target.value);
  if (preset) emit('change', { vta: preset.rate, tax_treatment: preset.treatment });
}
</script>
