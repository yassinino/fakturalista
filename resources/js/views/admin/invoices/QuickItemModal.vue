<template>
  <QuickCreateModal
    :title="$t('invoices.form.quickCreate.itemTitle')"
    icon="fa fa-box"
    :loading="saving"
    :disabled="!taxReady"
    :error="error || taxError"
    :hint="$t('invoices.form.quickCreate.itemHint')"
    @cancel="$emit('cancel')"
    @submit="submit"
  >
    <div class="qc-field">
      <label class="qc-label qc-label--req" for="qc-item-name">{{ $t('items.fields.name') }}</label>
      <input id="qc-item-name" ref="firstInput" v-model="form.name" type="text" class="qc-input"
        :class="{ 'qc-input--err': v$.name.$error }" data-test="quick-item-name" />
      <p v-if="v$.name.$error" class="qc-err-msg">{{ $t('validation.required') }}</p>
    </div>

    <div class="qc-field">
      <span class="qc-label">{{ $t('invoices.form.quickCreate.itemType') }}</span>
      <div class="qc-seg" role="radiogroup" :aria-label="$t('invoices.form.quickCreate.itemType')">
        <button type="button" class="qc-seg-btn" :class="{ 'qc-seg-btn--on': form.type === 2 }" role="radio" :aria-checked="String(form.type === 2)" @click="form.type = 2">
          <i class="fa fa-box"></i>{{ $t('items.types.product') }}
        </button>
        <button type="button" class="qc-seg-btn" :class="{ 'qc-seg-btn--on': form.type === 1 }" role="radio" :aria-checked="String(form.type === 1)" @click="form.type = 1">
          <i class="fa fa-briefcase"></i>{{ $t('items.types.service') }}
        </button>
      </div>
    </div>

    <div class="qc-two-col">
      <div class="qc-field">
        <label class="qc-label" for="qc-item-price">{{ $t('items.fields.salesPrice') }}</label>
        <input id="qc-item-price" v-model="form.sales_price" type="text" inputmode="decimal" class="qc-input"
          v-decimal="{ decimals: 2 }" placeholder="0,00" />
      </div>
      <div class="qc-field">
        <label class="qc-label" for="qc-item-tax">{{ taxName }}</label>
        <TaxSelect id="qc-item-tax" class="qc-select" :class="{ 'qc-input--err': v$.vta.$error }" :line="form" :presets="presets" :tax-name="taxName"
          @change="Object.assign(form, $event)" />
        <p v-if="v$.vta.$error" class="qc-err-msg">{{ $t('validation.required') }}</p>
      </div>
    </div>
  </QuickCreateModal>
</template>

<script setup>
/**
 * Invoice page - quick "+ Nouveau produit ou service". Only what's needed
 * to put it on an invoice line: name, product/service, price and tax (the
 * tenant's default preselected, same TaxSelect + useTaxPresets as the full
 * Products page). No category - it's optional and can be set later from
 * Products & services. Posts to the existing POST /items (same ItemRequest
 * validation / plan limits) and emits the new item's id.
 */
import { reactive, ref, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import useVuelidate from '@vuelidate/core';
import { required, requiredIf } from '@vuelidate/validators';
import TaxSelect from '@/components/TaxSelect.vue';
import { useTaxPresets } from '@/composables/useTaxPresets';
import { useTemplateStore } from '@/stores/template';
import QuickCreateModal from './QuickCreateModal.vue';

const props = defineProps({
  initialName: { type: String, default: '' },
});
const emit = defineEmits(['cancel', 'created']);
const { t } = useI18n();
const templateStore = useTemplateStore();

const form = reactive({
  type: 2, // same default as the full Products page
  name: props.initialName.trim(),
  sales_price: '',
  vta: null,
  tax_treatment: 'taxable',
  unite: 'pc',
});
const { presets, taxName, taxReady, taxError } = useTaxPresets(() => [form]);

const saving = ref(false);
const error = ref('');
const firstInput = ref(null);

// vta must be explicit: the items table stores it NOT NULL. The tenant's
// default tax is preselected (useTaxPresets); if there is none, the user
// picks one - never a silently invented rate.
const rules = computed(() => ({
  name: { required },
  vta:  { required: requiredIf(() => form.vta === null || form.vta === undefined) },
}));
const v$ = useVuelidate(rules, form);

onMounted(async () => {
  await nextTick();
  firstInput.value?.focus();
});

async function submit() {
  if (saving.value || !taxReady.value) return;
  error.value = '';
  if (!(await v$.value.$validate())) return;

  saving.value = true;
  try {
    const price = String(form.sales_price ?? '').replace(',', '.').trim();
    const res = await axios.post('/items', {
      name:          form.name.trim(),
      type:          form.type,
      sales_price:   price === '' ? null : Number(price),
      vta:           form.vta,
      tax_treatment: form.tax_treatment,
      unite:         form.unite,
      currency:      templateStore.company.currency?.toUpperCase() ?? null,
      active:        true,
    });
    emit('created', res.data?.item?.id ?? null);
  } catch (err) {
    error.value = err.response?.status === 402
      ? t('invoices.form.quickCreate.planLimit')
      : (err.response?.data?.message || t('items.form.genericError'));
  } finally {
    saving.value = false;
  }
}
</script>
