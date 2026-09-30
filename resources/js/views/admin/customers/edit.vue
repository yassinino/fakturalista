<template>
  <div class="content">
    <div class="ctc">

      <!-- ── PAGE HEADER (same structure as customers/create.vue) ── -->
      <div class="ctc-header">
        <div class="ctc-header__left">
          <button type="button" class="ctc-back-btn" @click="router.push('/admin/customers')">
            <i class="fa fa-arrow-left"></i>
            {{ $t('customers.title') }}
          </button>
          <div class="ctc-header__title-row">
            <div class="ctc-header__icon">
              <i class="fa fa-user-pen"></i>
            </div>
            <div>
              <h1 class="ctc-header__title">{{ $t('customers.editTitle') }}</h1>
              <p class="ctc-header__sub">{{ $t('customers.overview') }}</p>
            </div>
          </div>
        </div>
        <div class="ctc-header__actions">
          <button type="button" class="ctc-btn ctc-btn--ghost" @click="router.push('/admin/customers')">
            {{ $t('common.cancel') }}
          </button>
          <button type="button" class="ctc-btn ctc-btn--primary" @click="onSubmit">
            <i class="fa fa-check"></i>
            {{ $t('common.save') }}
          </button>
        </div>
      </div>

      <!-- ── FORM ── -->
      <form @submit.prevent="onSubmit" novalidate>

        <!-- ═══ CARD 1: Customer type ═══ -->
        <section class="ctc-card ctc-card--accent-top">
          <div class="ctc-card__head">
            <span class="ctc-card__icon"><i class="fa fa-user-tag"></i></span>
            <span class="ctc-card__label">{{ $t('customers.typeSection') }}</span>
          </div>
          <div class="ctc-field">
            <div class="ctc-seg" role="radiogroup" :aria-label="$t('customers.typeSection')">
              <button
                type="button"
                class="ctc-seg__btn"
                :class="{ 'ctc-seg__btn--on': state.type == 1 }"
                role="radio"
                :aria-checked="String(state.type == 1)"
                @click="state.type = 1"
              >
                <i class="fa fa-building"></i>
                {{ $t('customers.types.company') }}
              </button>
              <button
                type="button"
                class="ctc-seg__btn"
                :class="{ 'ctc-seg__btn--on': state.type == 2 }"
                role="radio"
                :aria-checked="String(state.type == 2)"
                @click="state.type = 2"
              >
                <i class="fa fa-user"></i>
                {{ $t('customers.types.individual') }}
              </button>
            </div>
          </div>
        </section>

        <!-- ═══ CARD 2: Main information ═══ -->
        <section class="ctc-card">
          <div class="ctc-card__head">
            <span class="ctc-card__icon"><i class="fa fa-id-card"></i></span>
            <span class="ctc-card__label">{{ $t('customers.mainInfoSection') }}</span>
          </div>

          <!-- Company (type == 1) -->
          <div class="ctc-field" v-if="state.type == 1">
            <label class="ctc-label ctc-label--req" for="ctc-name">
              {{ $t('customers.fields.companyName') }}
            </label>
            <input
              id="ctc-name"
              type="text"
              class="ctc-input"
              :class="{ 'ctc-input--err': v$.name.$errors.length }"
              v-model="state.name"
              @blur="v$.name.$touch"
              :placeholder="$t('customers.fields.companyName')"
            />
            <p v-if="v$.name.$errors.length" class="ctc-err-msg">
              <i class="fa fa-circle-exclamation"></i>
              {{ $t('validation.required') }}
            </p>
          </div>

          <!-- Individual (type == 2) -->
          <div class="ctc-three-col" v-if="state.type == 2">
            <div class="ctc-field">
              <label class="ctc-label" for="ctc-first-name">
                {{ $t('customers.fields.firstName') }}
              </label>
              <input
                id="ctc-first-name"
                type="text"
                class="ctc-input"
                v-model="state.first_name"
                :placeholder="$t('customers.fields.firstName')"
              />
            </div>
            <div class="ctc-field">
              <label class="ctc-label ctc-label--req" for="ctc-last-name">
                {{ $t('customers.fields.lastName') }}
              </label>
              <input
                id="ctc-last-name"
                type="text"
                class="ctc-input"
                :class="{ 'ctc-input--err': v$.last_name.$errors.length }"
                v-model="state.last_name"
                @blur="v$.last_name.$touch"
                :placeholder="$t('customers.fields.lastName')"
              />
              <p v-if="v$.last_name.$errors.length" class="ctc-err-msg">
                <i class="fa fa-circle-exclamation"></i>
                {{ $t('validation.required') }}
              </p>
            </div>
            <div class="ctc-field">
              <label class="ctc-label" for="ctc-middle-name">
                {{ $t('customers.fields.middleName') }}
              </label>
              <input
                id="ctc-middle-name"
                type="text"
                class="ctc-input"
                v-model="state.middle_name"
                :placeholder="$t('customers.fields.middleName')"
              />
            </div>
          </div>
        </section>

        <!-- ═══ CARD 3: Contact details ═══ -->
        <section class="ctc-card">
          <div class="ctc-card__head">
            <span class="ctc-card__icon"><i class="fa fa-envelope"></i></span>
            <span class="ctc-card__label">{{ $t('customers.contactSection') }}</span>
          </div>

          <div class="ctc-field">
            <label class="ctc-label" for="ctc-email">{{ $t('customers.fields.email') }}</label>
            <input
              id="ctc-email"
              type="text"
              class="ctc-input"
              v-model="state.email"
              :placeholder="$t('customers.fields.email')"
            />
          </div>

          <div class="ctc-two-col ctc-field--mt">
            <div class="ctc-field">
              <label class="ctc-label" for="ctc-phone">{{ $t('customers.fields.phone') }}</label>
              <input
                id="ctc-phone"
                type="text"
                class="ctc-input"
                v-model="state.phone"
                :placeholder="$t('customers.fields.phone')"
              />
            </div>
            <div class="ctc-field">
              <label class="ctc-label" for="ctc-website">{{ $t('customers.fields.website') }}</label>
              <input
                id="ctc-website"
                type="text"
                class="ctc-input"
                v-model="state.website"
                :placeholder="$t('customers.fields.website')"
              />
            </div>
          </div>
        </section>

        <!-- ═══ CARD 4: Address ═══ -->
        <section class="ctc-card">
          <div class="ctc-card__head">
            <span class="ctc-card__icon"><i class="fa fa-map-pin"></i></span>
            <span class="ctc-card__label">{{ $t('customers.addressSection') }}</span>
          </div>

          <div class="ctc-field">
            <label class="ctc-label" for="ctc-address">{{ $t('customers.fields.address') }}</label>
            <input
              id="ctc-address"
              type="text"
              class="ctc-input"
              v-model="state.address_billing"
              :placeholder="$t('customers.fields.address')"
            />
          </div>

          <div class="ctc-two-col ctc-field--mt">
            <div class="ctc-field">
              <label class="ctc-label" for="ctc-city">{{ $t('customers.fields.city') }}</label>
              <input
                id="ctc-city"
                type="text"
                class="ctc-input"
                v-model="state.city"
                :placeholder="$t('customers.fields.city')"
              />
            </div>
            <div class="ctc-field">
              <label class="ctc-label" for="ctc-postcode">{{ $t('customers.fields.postalCode') }}</label>
              <input
                id="ctc-postcode"
                type="text"
                class="ctc-input"
                v-model="state.post_code"
                :placeholder="$t('customers.fields.postalCode')"
              />
            </div>
          </div>

          <div class="ctc-field ctc-field--mt">
            <label class="ctc-label" for="ctc-country">{{ $t('customers.fields.country') }}</label>
            <select
              id="ctc-country"
              class="ctc-select"
              v-model="state.billing_country_id"
            >
              <option value="">{{ $t('common.selectOption') }}</option>
              <option :value="country.id" v-for="country in countries" :key="country.id">
                {{ country.name }}
              </option>
            </select>
          </div>
        </section>

        <!-- ═══ CARD 5: Fiscal data - country-aware, same rules as create.vue:
             Morocco ICE/IF/RC for companies only (Phase 2A); Spain/others NIF
             + AEAT foreign-customer model for everyone. ═══ -->
        <section class="ctc-card" v-if="!isMorocco || state.type == 1">
          <div class="ctc-card__head">
            <span class="ctc-card__icon"><i class="fa fa-file-invoice"></i></span>
            <span class="ctc-card__label">{{ $t('customers.fiscalSection') }}</span>
          </div>

          <template v-if="isMorocco">
            <div class="ctc-field">
              <label class="ctc-label" for="ctc-ice">{{ $t('customers.fields.ice') }}</label>
              <p class="ctc-field-hint">{{ $t('customers.fields.iceHelp') }}</p>
              <input
                id="ctc-ice"
                type="text"
                class="ctc-input"
                v-model="state.ice"
                :placeholder="$t('customers.fields.ice')"
              />
            </div>
            <div class="ctc-field ctc-field--mt">
              <label class="ctc-label" for="ctc-if">{{ $t('customers.fields.ifNumber') }}</label>
              <input
                id="ctc-if"
                type="text"
                class="ctc-input"
                v-model="state.if_number"
                :placeholder="$t('customers.fields.ifNumber')"
              />
            </div>
            <div class="ctc-field ctc-field--mt">
              <label class="ctc-label" for="ctc-rc">{{ $t('customers.fields.commercialRegister') }}</label>
              <input
                id="ctc-rc"
                type="text"
                class="ctc-input"
                v-model="state.commercial_register"
                :placeholder="$t('customers.fields.commercialRegister')"
              />
            </div>
          </template>

          <template v-else>
            <div class="ctc-field">
              <label class="ctc-label" for="ctc-nif">{{ $t('customers.fields.nif') }}</label>
              <p class="ctc-field-hint">{{ $t('customers.fields.nifHelp') }}</p>
              <input
                id="ctc-nif"
                type="text"
                class="ctc-input"
                v-model="state.tax_id"
                :placeholder="$t('customers.fields.nif')"
              />
            </div>

            <div class="ctc-field ctc-field--mt">
              <label class="ctc-checkbox-label">
                <input type="checkbox" v-model="isForeignCustomer" />
                {{ $t('customers.fields.foreignCustomerToggle') }}
              </label>
            </div>

            <template v-if="isForeignCustomer">
              <div class="ctc-field ctc-field--mt">
                <label class="ctc-label" for="ctc-foreign-id-type">{{ $t('customers.fields.foreignIdType') }}</label>
                <select id="ctc-foreign-id-type" class="ctc-select" v-model="state.foreign_tax_id_type">
                  <option value="">{{ $t('common.selectOption') }}</option>
                  <option value="02">{{ $t('customers.fields.foreignIdTypeOptions.02') }}</option>
                  <option value="03">{{ $t('customers.fields.foreignIdTypeOptions.03') }}</option>
                  <option value="04">{{ $t('customers.fields.foreignIdTypeOptions.04') }}</option>
                  <option value="05">{{ $t('customers.fields.foreignIdTypeOptions.05') }}</option>
                  <option value="06">{{ $t('customers.fields.foreignIdTypeOptions.06') }}</option>
                  <option value="07">{{ $t('customers.fields.foreignIdTypeOptions.07') }}</option>
                </select>
              </div>
              <div class="ctc-field ctc-field--mt">
                <label class="ctc-label" for="ctc-foreign-id">{{ $t('customers.fields.foreignIdNumber') }}</label>
                <input
                  id="ctc-foreign-id"
                  type="text"
                  class="ctc-input"
                  v-model="state.foreign_tax_id"
                />
              </div>
              <p class="ctc-field-hint">{{ $t('customers.fields.foreignIdCountryHint') }}</p>
            </template>
          </template>
        </section>

      </form>
    </div>

    <!-- ── STICKY FOOTER ── -->
    <div class="ctc-footer">
      <div class="ctc-footer__inner">
        <p class="ctc-footer__note">
          <i class="fa fa-asterisk"></i>
          {{ $t('customers.requiredFieldsNote') }}
        </p>
        <div class="ctc-footer__btns">
          <button type="button" class="ctc-btn ctc-btn--ghost" @click="router.push('/admin/customers')">
            {{ $t('common.cancel') }}
          </button>
          <button type="button" class="ctc-btn ctc-btn--primary" @click="onSubmit">
            <i class="fa fa-check"></i>
            {{ $t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTenantCountry } from '@/composables/useTenantCountry';
  import { reactive, ref, computed, onMounted, watch } from "vue";
  import axios from 'axios'
  import { createToaster } from '@meforma/vue-toaster';
  const toaster = createToaster({ /* options */ });
  import { useRoute, useRouter } from 'vue-router'
  import { useI18n } from "vue-i18n";
  import { useTemplateStore } from "@/stores/template";
  const { t } = useI18n();
  const templateStore = useTemplateStore();
  // Morocco Phase 1B: country-aware fiscal identity fields (docs/morocco-phase-1b-identity.md).
  const { isMorocco } = useTenantCountry();
  
  // Vuelidate, for more info and examples you can check out https://github.com/vuelidate/vuelidate
  import useVuelidate from "@vuelidate/core";
  import {
    required,
    minLength,
    requiredIf,
    helpers,
    email,
  } from "@vuelidate/validators";
  
  // Example options for select
  
  // Input state variables
  const state = ref({});
  
  const countries = ref()
  const route = useRoute()
  const router = useRouter() // Back / Cancel buttons (same as customers/create.vue)
  const uuid = ref(route.params.id)
  
  const isForeignCustomer = ref(false);

  onMounted(async () => {
          let res = await axios.get('/customers/' + uuid.value + '/edit');
          state.value = res.data.customer
          state.value.is_same_address = state.value.is_same_address == 1 ? true : false;
          isForeignCustomer.value = !!state.value.foreign_tax_id_type;

          let response = await axios.get('/countries');
          countries.value = response.data.countries
  });

  watch(isForeignCustomer, (checked) => {
    if (!checked) {
      state.value.foreign_tax_id_type = null;
      state.value.foreign_tax_id = null;
    }
  });
  
  
  // Validation rules
  const rules = computed(() => {
  
    return {
        name: {
          required : requiredIf(function() {
                  return state.value.type == 1;
          }),
          minLength: minLength(3),
        },
        last_name: {
          required : requiredIf(function() {
                  return state.value.type == 2;
          }),
          minLength: minLength(3),
        },
        type: {
          required,
        },
        contacts: {
          $each: helpers.forEach({
            last_name: {
              required : requiredIf(function() {
                  return state.value.contacts.length > 0;
              })
            },
            email: {
              email
            }
          })
        }
      };
  });
  
  // Use vuelidate
  const v$ = useVuelidate(rules, state);
  
  const newCustomer = () =>{
    state.value.contacts.push({
      email: null,
      first_name : null,
      last_name : null,
      work_phone : null,
    })
  }
  
  const deleteCustomer = (index) =>{
    if (confirm(t("documents.removeConfirm")))
    state.value.contacts.splice(index, 1);
  }
  
  // On form submission
  async function onSubmit() {
    const result = await v$.value.$validate();
  
    if (!result) {
      // notify user form is invalid
      return;
    }
  
    axios.post('/customers/' + state.value.uuid ,state.value, {
        params: {
        _method: "put",
      },
    }).then(res => {
                                
      toaster.success(res.data.message);
      route.push('/admin/customers')
  
    })
  }
  </script>

<style scoped src="./customerForm.css"></style>
