<template>
  <QuickCreateModal
    :title="$t('invoices.form.quickCreate.customerTitle')"
    icon="fa fa-user-plus"
    :loading="saving"
    :error="error"
    :hint="$t('invoices.form.quickCreate.customerHint')"
    @cancel="$emit('cancel')"
    @submit="submit"
  >
    <div class="qc-field">
      <span class="qc-label">{{ $t('customers.typeSection') }}</span>
      <div class="qc-seg" role="radiogroup" :aria-label="$t('customers.typeSection')">
        <button type="button" class="qc-seg-btn" :class="{ 'qc-seg-btn--on': form.type === 1 }" role="radio" :aria-checked="String(form.type === 1)" @click="form.type = 1">
          <i class="fa fa-building"></i>{{ $t('customers.types.company') }}
        </button>
        <button type="button" class="qc-seg-btn" :class="{ 'qc-seg-btn--on': form.type === 2 }" role="radio" :aria-checked="String(form.type === 2)" @click="form.type = 2">
          <i class="fa fa-user"></i>{{ $t('customers.types.individual') }}
        </button>
      </div>
    </div>

    <div v-if="form.type === 1" class="qc-field">
      <label class="qc-label qc-label--req" for="qc-cust-name">{{ $t('customers.fields.companyName') }}</label>
      <input id="qc-cust-name" ref="firstInput" v-model="form.name" type="text" class="qc-input"
        :class="{ 'qc-input--err': v$.name.$error }" autocomplete="organization" data-test="quick-customer-name" />
      <p v-if="v$.name.$error" class="qc-err-msg">{{ $t('validation.required') }}</p>
    </div>

    <div v-else class="qc-two-col">
      <div class="qc-field">
        <label class="qc-label" for="qc-cust-first">{{ $t('customers.fields.firstName') }}</label>
        <input id="qc-cust-first" ref="firstInput" v-model="form.first_name" type="text" class="qc-input" autocomplete="given-name" />
      </div>
      <div class="qc-field">
        <label class="qc-label qc-label--req" for="qc-cust-last">{{ $t('customers.fields.lastName') }}</label>
        <input id="qc-cust-last" v-model="form.last_name" type="text" class="qc-input"
          :class="{ 'qc-input--err': v$.last_name.$error }" autocomplete="family-name" />
        <p v-if="v$.last_name.$error" class="qc-err-msg">{{ $t('validation.required') }}</p>
      </div>
    </div>
  </QuickCreateModal>
</template>

<script setup>
/**
 * Invoice page - quick "+ Nouveau client". Only what's needed to invoice
 * someone: company name, or first/last name for an individual (the same
 * requirement as the full Customers > New page). Posts to the existing
 * POST /customers (same backend validation / plan limits) and emits the
 * new customer's uuid; everything else is completed later in Customers.
 */
import { reactive, ref, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import useVuelidate from '@vuelidate/core';
import { requiredIf } from '@vuelidate/validators';
import QuickCreateModal from './QuickCreateModal.vue';

const props = defineProps({
  initialName: { type: String, default: '' },
});
const emit = defineEmits(['cancel', 'created']);
const { t } = useI18n();

const form = reactive({ type: 1, name: props.initialName.trim(), first_name: '', last_name: '' });
const saving = ref(false);
const error = ref('');
const firstInput = ref(null);

const rules = computed(() => ({
  name:      { required: requiredIf(() => form.type === 1 && !form.name.trim()) },
  last_name: { required: requiredIf(() => form.type === 2 && !form.last_name.trim()) },
}));
const v$ = useVuelidate(rules, form);

onMounted(async () => {
  await nextTick();
  firstInput.value?.focus();
});

async function submit() {
  if (saving.value) return;
  error.value = '';
  if (!(await v$.value.$validate())) return;

  saving.value = true;
  try {
    const payload = form.type === 1
      ? { type: 1, name: form.name.trim(), contacts: [] }
      : { type: 2, first_name: form.first_name.trim() || null, last_name: form.last_name.trim(), contacts: [] };

    const res = await axios.post('/customers', payload);
    emit('created', res.data?.customer?.uuid ?? null);
  } catch (err) {
    error.value = err.response?.status === 402
      ? t('invoices.form.quickCreate.planLimit')
      : (err.response?.data?.message || t('items.form.genericError'));
  } finally {
    saving.value = false;
  }
}
</script>
