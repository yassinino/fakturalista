import { computed } from 'vue';
import { useTemplateStore } from '@/stores/template';
import { isSpain, isMorocco } from '@/utils/countryExperience.mjs';

export function useTenantCountry() {
  const store = useTemplateStore();
  return {
    isSpain: computed(() => store.companyLoaded && isSpain(store.company)),
    isMorocco: computed(() => store.companyLoaded && isMorocco(store.company)),
  };
}
