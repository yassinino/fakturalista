<template>
  <div class="content">
    <div class="ctc">

      <!-- ── PAGE HEADER ── -->
      <div class="ctc-header">
        <div class="ctc-header__left">
          <button type="button" class="ctc-back-btn" @click="route.push('/admin/customers')">
            <i class="fa fa-arrow-left"></i>
            {{ $t('customers.title') }}
          </button>
          <div class="ctc-header__title-row">
            <div class="ctc-header__icon">
              <i class="fa fa-user-plus"></i>
            </div>
            <div>
              <h1 class="ctc-header__title">{{ $t('customers.newTitle') }}</h1>
              <p class="ctc-header__sub">{{ $t('customers.overview') }}</p>
            </div>
          </div>
        </div>
        <div class="ctc-header__actions">
          <button type="button" class="ctc-btn ctc-btn--ghost" @click="route.push('/admin/customers')">
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

        <!-- ═══ CARD 4: Dirección ═══ -->
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

        <!-- ═══ CARD 5: Datos fiscales - country-aware (Morocco Phase 1B) ═══
             Business-only for Morocco (Morocco Phase 2A): ICE/IF/RC are company
             registration identifiers, not personal ones - showing this whole card
             for an individual customer (state.type == 2) made no sense and was
             never gated by type before this fix. Spain's NIF applies to
             individuals too (DNI/NIF), so that branch is untouched. -->
        <section class="ctc-card" v-if="!isMorocco || state.type == 1">
          <div class="ctc-card__head">
            <span class="ctc-card__icon"><i class="fa fa-file-invoice"></i></span>
            <span class="ctc-card__label">{{ $t('customers.fiscalSection') }}</span>
          </div>

          <!-- Morocco: ICE (primary identifier) + IF + RC. No Spain/AEAT terminology. -->
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

          <!-- Spain / everyone else: unchanged NIF + AEAT foreign-customer model -->
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
          <button type="button" class="ctc-btn ctc-btn--ghost" @click="route.push('/admin/customers')">
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
import { reactive,ref, computed, onMounted, watch } from "vue";
import axios from 'axios'
import { createToaster } from '@meforma/vue-toaster';
const toaster = createToaster({ /* options */ });
import { useRouter } from 'vue-router'
import { useI18n } from "vue-i18n";
import { useTemplateStore } from "@/stores/template";
const route = useRouter()
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
  decimal,
  integer,
  url,
  sameAs,
} from "@vuelidate/validators";

// Example options for select

// Input state variables
const state = reactive({
  type: 1,
  name: null,
  email: null,
  first_name : null,
  last_name : null,
  middle_name : null,
  phone_number : null,
  website : null,
  is_same_address : true,
  ice: null,
  if_number: null,
  commercial_register: null,
  tax_id: null,
  foreign_tax_id_type: null,
  foreign_tax_id: null,
  contacts : []
});

const isForeignCustomer = ref(false);
watch(isForeignCustomer, (checked) => {
  if (!checked) {
    state.foreign_tax_id_type = null;
    state.foreign_tax_id = null;
  }
});

const countries = ref()

onMounted(async () => {
        
        let response = await axios.get('/countries');
        countries.value = response.data.countries
});


// Validation rules
const rules = computed(() => {

  return {
      name: {
        required : requiredIf(function() {
                return state.type == 1;
        }),
        minLength: minLength(3),
      },
      last_name: {
        required : requiredIf(function() {
                return state.type == 2;
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
                return state.contacts.length > 0;
            })
          }
        })
      }
    };
});

// Use vuelidate
const v$ = useVuelidate(rules, state);

const newCustomer = () =>{
  state.contacts.push({
    email: null,
    first_name : null,
    last_name : null,
    work_phone : null,
  })
}

const deleteCustomer = (index) =>{
  if (confirm(t("documents.removeConfirm")))
        state.contacts.splice(index, 1);
}

// On form submission
async function onSubmit() {
  const result = await v$.value.$validate();

  if (!result) {
    // notify user form is invalid
    return;
  }

  axios.post('/customers',state).then(res => {
                              
    toaster.success(res.data.message);
    route.push('/admin/customers')

			})
}
</script>

<style scoped>
/* ─────────────────────────────────────────────
   DESIGN TOKENS  (mirrors items/create token system)
───────────────────────────────────────────── */
.ctc {
  --accent:       var(--brand-primary);
  --accent-bg:    rgba(233, 30, 99, 0.07);
  --accent-ring:  rgba(233, 30, 99, 0.15);
  --accent-hover: var(--brand-primary-hover);

  --bg:           #ffffff;
  --bg-subtle:    #f8fafc;
  --border:       #e2e8f0;

  --text:         #0f172a;
  --text-body:    #374151;
  --text-muted:   #64748b;
  --text-dim:     #94a3b8;

  --shadow-sm:    0 1px 2px rgba(0,0,0,0.06);
  --radius:       14px;
  --radius-sm:    8px;

  padding-bottom: 96px;
}

:global(.dark-mode) .ctc {
  --bg:         #1e2433;
  --bg-subtle:  #252d3d;
  --border:     rgba(255,255,255,0.09);
  --text:       #e2e8f0;
  --text-body:  #94a3b8;
  --text-muted: #64748b;
  --text-dim:   #3d4762;
  --shadow-sm:  0 1px 2px rgba(0,0,0,0.3);
}

/* ─────────────────────────────────────────────
   PAGE HEADER
───────────────────────────────────────────── */
.ctc-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.ctc-header__left {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ctc-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--bg);
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 6px 12px;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-muted);
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s;
  align-self: flex-start;
}
.ctc-back-btn:hover { border-color: var(--accent); color: var(--accent); }

.ctc-header__title-row {
  display: flex;
  align-items: center;
  gap: 14px;
}
.ctc-header__icon {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--accent-bg);
  color: var(--accent);
  border-radius: 12px;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.ctc-header__title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 2px;
  line-height: 1.2;
}
.ctc-header__sub {
  font-size: 0.8rem;
  color: var(--text-dim);
  margin: 0;
}
.ctc-header__actions { display: flex; align-items: center; gap: 8px; }

/* ─────────────────────────────────────────────
   CARDS
───────────────────────────────────────────── */
.ctc-card {
  background: var(--bg);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  padding: 20px 24px;
  margin-bottom: 14px;
  box-shadow: var(--shadow-sm);
}
.ctc-card--accent-top {
  border-top: 3px solid var(--accent);
}
.ctc-card__head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--border);
}
.ctc-card__icon {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--accent-bg);
  color: var(--accent);
  border-radius: 7px;
  font-size: 0.8rem;
  flex-shrink: 0;
}
.ctc-card__label {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text-muted);
}

/* ─────────────────────────────────────────────
   SEGMENTED CONTROL  (Empresa / Individual)
───────────────────────────────────────────── */
.ctc-seg {
  display: inline-flex;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  overflow: hidden;
  background: var(--bg-subtle);
}
.ctc-seg__btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 24px;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-muted);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: color 0.15s, background 0.15s;
  white-space: nowrap;
  line-height: 1;
}
.ctc-seg__btn + .ctc-seg__btn {
  border-left: 1.5px solid var(--border);
}
.ctc-seg__btn:hover:not(.ctc-seg__btn--on) {
  color: var(--text-body);
  background: var(--bg);
}
.ctc-seg__btn--on {
  background: var(--accent);
  color: #fff;
}

/* ─────────────────────────────────────────────
   FIELDS & LAYOUT GRIDS
───────────────────────────────────────────── */
.ctc-field         { display: flex; flex-direction: column; }
.ctc-field--mt     { margin-top: 18px; }
.ctc-two-col   { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.ctc-three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

.ctc-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-body);
  margin: 0 0 6px;
}
.ctc-label--req::after { content: " *"; color: var(--accent); }

.ctc-field-hint {
  margin: -2px 0 8px;
  font-size: 0.75rem;
  color: var(--text-muted, #6b7280);
}

.ctc-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-body);
  cursor: pointer;
}

.ctc-input,
.ctc-select {
  display: block;
  width: 100%;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 9px 12px;
  font-size: 0.875rem;
  color: var(--text);
  background: var(--bg);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  line-height: 1.4;
}
.ctc-input:focus,
.ctc-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}
.ctc-input--err { border-color: #ef4444 !important; }
.ctc-select     { cursor: pointer; }

.ctc-err-msg {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.75rem;
  color: #ef4444;
  margin: 5px 0 0;
}

/* ─────────────────────────────────────────────
   BUTTONS
───────────────────────────────────────────── */
.ctc-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 9px 18px;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  border: 1.5px solid transparent;
  transition: background 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.12s;
  white-space: nowrap;
  line-height: 1;
}
.ctc-btn--primary {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}
.ctc-btn--primary:hover:not(:disabled) {
  background: var(--accent-hover);
  border-color: var(--accent-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(233,30,99,0.25);
}
.ctc-btn--ghost {
  background: transparent;
  border-color: var(--border);
  color: var(--text-body);
}
.ctc-btn--ghost:hover { background: var(--bg-subtle); border-color: var(--text-muted); }

/* ─────────────────────────────────────────────
   STICKY FOOTER
───────────────────────────────────────────── */
.ctc-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 200;
  background: rgba(255,255,255,0.93);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-top: 1px solid var(--border);
  padding: 12px 0;
}
:global(.dark-mode) .ctc-footer { background: rgba(30,36,51,0.93); }
.ctc-footer__inner {
  max-width: 1320px;
  margin: 0 auto;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.ctc-footer__note {
  font-size: 0.78rem;
  color: var(--text-dim);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 5px;
}
.ctc-footer__note i { font-size: 0.55rem; }
.ctc-footer__btns { display: flex; align-items: center; gap: 8px; }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 768px) {
  .ctc-header          { flex-direction: column; align-items: flex-start; }
  .ctc-header__actions { width: 100%; justify-content: flex-end; }
  .ctc-two-col         { grid-template-columns: 1fr; }
  .ctc-three-col       { grid-template-columns: 1fr; }
  .ctc-card            { padding: 16px; }
  .ctc-seg__btn        { padding: 9px 16px; }
  .ctc-footer__note    { display: none; }
  .ctc-footer__btns    { width: 100%; }
  .ctc-footer__btns .ctc-btn { flex: 1; justify-content: center; }
}
</style>
