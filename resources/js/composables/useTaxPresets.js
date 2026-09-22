import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { taxFields } from '@/utils/tax.mjs';

export function useTaxPresets(newLines = () => []) {
  const presets = ref([]);
  const country = ref('');
  const taxName = ref('Tax');
  const defaultCode = ref(null);
  const taxError = ref('');
  const taxReady = ref(false);
  const defaultPreset = computed(() => presets.value.find(p => p.code === defaultCode.value));
  const defaultTax = () => taxFields(defaultPreset.value && {
    vta: defaultPreset.value.rate, tax_treatment: defaultPreset.value.treatment,
  });

  async function loadTaxes() {
    taxReady.value = false;
    taxError.value = '';
    try {
      const { data } = await axios.get('/tax-presets');
      presets.value = data.presets;
      country.value = data.country;
      taxName.value = data.tax_name;
      defaultCode.value = data.default_code;
      // Only uninitialized NEW lines receive the default. Never infer a
      // persisted line's treatment from its numeric rate or today's presets.
      for (const line of newLines()) {
        if (line.vta == null) Object.assign(line, defaultTax());
      }
      taxReady.value = !!defaultPreset.value;
      if (!taxReady.value) taxError.value = 'No tax presets are available for this country.';
    } catch {
      taxError.value = 'Unable to load taxes. Please retry.';
    }
  }

  onMounted(loadTaxes);
  return { presets, country, taxName, defaultCode, defaultTax, taxReady, taxError, loadTaxes };
}
