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

    <!-- Optional details - collapsed by default so the quick form stays tiny -->
    <button type="button" class="qc-more-btn" :aria-expanded="String(showMore)" aria-controls="qc-cust-more"
      data-test="quick-customer-more" @click="showMore = !showMore">
      <i class="fa" :class="showMore ? 'fa-minus' : 'fa-plus'"></i>
      {{ showMore ? $t('invoices.form.quickCreate.lessInfo') : $t('invoices.form.quickCreate.moreInfo') }}
    </button>

    <div v-if="showMore" id="qc-cust-more" class="qc-more">
      <div class="qc-two-col">
        <div class="qc-field">
          <label class="qc-label" for="qc-cust-email">{{ $t('customers.fields.email') }}</label>
          <input id="qc-cust-email" v-model.trim="form.email" type="email" class="qc-input" :class="{ 'qc-input--err': v$.email.$error }"
            autocomplete="email" inputmode="email" />
          <p v-if="v$.email.$error" class="qc-err-msg">{{ $t('invoices.form.quickCreate.invalidEmail') }}</p>
        </div>
        <div class="qc-field">
          <label class="qc-label" for="qc-cust-phone">{{ $t('customers.fields.phone') }}</label>
          <input id="qc-cust-phone" v-model="form.phone" type="tel" class="qc-input" autocomplete="tel" />
        </div>
      </div>

      <div class="qc-field">
        <label class="qc-label" for="qc-cust-address">{{ $t('customers.fields.address') }}</label>
        <input id="qc-cust-address" v-model="form.address_billing" type="text" class="qc-input" autocomplete="street-address" />
      </div>

      <div class="qc-two-col">
        <div class="qc-field">
          <label class="qc-label" for="qc-cust-city">{{ $t('customers.fields.city') }}</label>
          <input id="qc-cust-city" v-model="form.city_billing" type="text" class="qc-input" autocomplete="address-level2" />
        </div>
        <div class="qc-field">
          <label class="qc-label" for="qc-cust-postcode">{{ $t('customers.fields.postalCode') }}</label>
          <input id="qc-cust-postcode" v-model="form.post_code_billing" type="text" class="qc-input" autocomplete="postal-code" />
        </div>
      </div>

      <!-- Same country rule as the full Customers page: ICE for Moroccan
           companies only, NIF for everyone else. -->
      <div v-if="isMorocco && form.type === 1" class="qc-field">
        <label class="qc-label" for="qc-cust-ice">{{ $t('customers.fields.ice') }}</label>
        <input id="qc-cust-ice" v-model="form.ice" type="text" class="qc-input" maxlength="32" />
      </div>
      <div v-else-if="!isMorocco" class="qc-field">
        <label class="qc-label" for="qc-cust-nif">{{ $t('customers.fields.nif') }}</label>
        <input id="qc-cust-nif" v-model="form.tax_id" type="text" class="qc-input" maxlength="20" />
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
import { requiredIf, email } from '@vuelidate/validators';
import { useTenantCountry } from '@/composables/useTenantCountry';
import { buildQuickCustomerPayload } from './quickCustomerPayload.mjs';
import QuickCreateModal from './QuickCreateModal.vue';

const props = defineProps({
  initialName: { type: String, default: '' },
});
const emit = defineEmits(['cancel', 'created']);
const { t } = useI18n();

const form = reactive({
  type: 1, name: props.initialName.trim(), first_name: '', last_name: '',
  // Optional details (existing customer fields, saved by POST /customers).
  email: '', phone: '', address_billing: '', city_billing: '', post_code_billing: '', ice: '', tax_id: '',
});
const showMore = ref(false);
const { isMorocco } = useTenantCountry();
const saving = ref(false);
const error = ref('');
const firstInput = ref(null);

const rules = computed(() => ({
  name:      { required: requiredIf(() => form.type === 1 && !form.name.trim()) },
  last_name: { required: requiredIf(() => form.type === 2 && !form.last_name.trim()) },
  email:     { email },
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
    const payload = buildQuickCustomerPayload(form, { isMorocco: isMorocco.value });

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

<style scoped>
.qc-more-btn {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 2px 0;
  border: none;
  background: none;
  color: var(--qc-accent);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}
.qc-more-btn:hover { text-decoration: underline; }
.qc-more-btn:focus-visible { outline: 2px solid var(--qc-accent); outline-offset: 2px; border-radius: 4px; }
.qc-more {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 12px;
  border-top: 1px dashed var(--qc-border);
}
</style>
