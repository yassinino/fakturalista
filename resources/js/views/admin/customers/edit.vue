<template>


    <!-- Page Content -->
    <div class="content">
      <form @submit.prevent="onSubmit">
  
  
            <!-- Floating Labels -->
      <BaseBlock class="customer-form-card" :title="$t('customers.editTitle')" content-full>
          <div class="row">
            <div class="col-lg-3">
              <p class="fs-sm text-muted">
                {{ $t("customers.overview") }}
              </p>
            </div>
            <div class="col-lg-9">
  
              <div class="row items-push">
                <div class="col-md-6">
                  <div class="form-check form-block">
                    <input
                      type="radio"
                      class="form-check-input"
                      id="type1"
                      name="type"
                      value="1"
                      v-model="state.type"
                      @blur="v$.type.$touch"
                    />
                    <label class="form-check-label" for="type1">
                      <span class="d-block p-1 fw-normal text-center my-1">
                        <span class="fs-4 fw-semibold">{{ $t("customers.types.company") }}</span>
                      </span>
                    </label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-check form-block">
                    <input
                      type="radio"
                      class="form-check-input"
                      id="type2"
                      name="type"
                      value="2"
                      v-model="state.type"
                      @blur="v$.type.$touch"
                    />
                    <label class="form-check-label" for="type2">
                      <span class="d-block p-1 fw-normal text-center my-1">
                        <span class="fs-4 fw-semibold">{{ $t("customers.types.individual") }}</span>
                      </span>
                    </label>
                  </div>
                </div>
  
              </div>
  
              <div class="form-floating mb-4" v-if="state.type == 1">
                <input
                  type="text"
                  id="val-name"
                  class="form-control"
                  :class="{
                    'is-invalid': v$.name.$errors.length,
                  }"
                  v-model="state.name"
                  @blur="v$.name.$touch"
                  :placeholder="$t('customers.fields.companyName')"
                />
                <div
                  v-if="v$.name.$errors.length"
                  class="invalid-feedback animated fadeIn"
                >
                {{ $t("validation.required") }}
                </div>
                <label for="val-name">
                  {{ $t("customers.fields.companyName") }}
                  <span class="text-danger">*</span>
                </label>
              </div>
  
              <div class="row g-3" v-if="state.type == 2">
                  <div class="form-floating col-4 mb-4">
                  <input
                    type="text"
                    id="val-first-name"
                    class="form-control"
                    v-model="state.first_name"
                    :placeholder="$t('customers.fields.firstName')"
  
                  />
                  <label for="val-first-name">{{ $t("customers.fields.firstName") }}</label>
                </div>
                <div class="form-floating col-4 mb-4">
                  <input
                    type="text"
                    id="val-first-name"
                    class="form-control"
                    :class="{
                      'is-invalid': v$.last_name.$errors.length,
                    }"
                    v-model="state.last_name"
                    @blur="v$.last_name.$touch"
                    :placeholder="$t('customers.fields.lastName')"
                  />
                  <div
                    v-if="v$.name.$errors.length"
                    class="invalid-feedback animated fadeIn"
                  >
                    {{ $t("validation.required") }}
                  </div>
                  <label for="val-last-name">
                    {{ $t("customers.fields.lastName") }}
                    <span class="text-danger">*</span>
                  </label>
                </div>
  
                <div class="form-floating col-4 mb-4">
                  <input
                    type="text"
                    id="val-middle-name"
                    class="form-control"
                    v-model="state.middle_name"
                    :placeholder="$t('customers.fields.middleName')"
                  />
                  <label for="val-middle-name">{{ $t("customers.fields.middleName") }}</label>
                </div>
              </div>
  
              <div class="form-floating mb-4">
                <input
                type="text"
                id="val-email"
                class="form-control"
                v-model="state.email"
                :placeholder="$t('customers.fields.email')"
              />
              <label for="val-email">{{ $t("customers.fields.email") }}</label>
            </div>
  
  
              <div class="row g-3">
                <div class="form-floating col-6 mb-4">
                  <input
                  type="text"
                  id="val-phone-name"
                  class="form-control"
                  v-model="state.phone"
                  :placeholder="$t('customers.fields.phone')"
                />
                <label for="val-phone-name">{{ $t("customers.fields.phone") }}</label>
              </div>
              <div class="form-floating col-6 mb-4">
                <input
                  type="text"
                  id="val-website"
                  class="form-control"
                  v-model="state.website"
                  :placeholder="$t('customers.fields.website')"
                />
                <label for="val-website">{{ $t("customers.fields.website") }}</label>
              </div>
  
              </div>

               <!-- Morocco: ICE (primary identifier) + IF + RC. No Spain/AEAT terminology.
                    Business-only (Morocco Phase 2A) - see customers/create.vue for the
                    same fix; ICE/IF/RC are company identifiers, meaningless for an
                    individual customer (state.type == 2). -->
               <template v-if="isMorocco && state.type == 1">
                 <div class="form-floating mb-4">
                   <input
                     type="text"
                     id="val-ice"
                     class="form-control"
                     v-model="state.ice"
                     :placeholder="$t('customers.fields.ice')"
                   />
                   <label for="val-ice">{{ $t("customers.fields.ice") }}</label>
                 </div>
                 <p class="ctc-edit-hint mb-3">{{ $t('customers.fields.iceHelp') }}</p>

                 <div class="form-floating mb-4">
                   <input
                     type="text"
                     id="val-if"
                     class="form-control"
                     v-model="state.if_number"
                     :placeholder="$t('customers.fields.ifNumber')"
                   />
                   <label for="val-if">{{ $t("customers.fields.ifNumber") }}</label>
                 </div>

                 <div class="form-floating mb-4">
                   <input
                     type="text"
                     id="val-rc"
                     class="form-control"
                     v-model="state.commercial_register"
                     :placeholder="$t('customers.fields.commercialRegister')"
                   />
                   <label for="val-rc">{{ $t("customers.fields.commercialRegister") }}</label>
                 </div>
               </template>

               <!-- Spain / everyone else: unchanged NIF + AEAT foreign-customer model.
                    Morocco Phase 2A: explicitly excludes a Moroccan individual customer
                    (isMorocco && state.type == 2) - they get neither block, matching
                    "do not force company identifiers on an individual" for either country. -->
               <template v-else-if="!isMorocco">
                 <div class="form-floating mb-1">
                  <input
                  type="text"
                  id="val-nif"
                  class="form-control"
                  v-model="state.tax_id"
                  :placeholder="$t('customers.fields.nif')"
                />
                <label for="val-nif">{{ $t("customers.fields.nif") }}</label>
              </div>
              <p class="ctc-edit-hint mb-3">{{ $t('customers.fields.nifHelp') }}</p>

              <div class="form-check mb-3">
                <input
                  type="checkbox"
                  id="val-is-foreign"
                  class="form-check-input"
                  v-model="isForeignCustomer"
                />
                <label class="form-check-label" for="val-is-foreign">
                  {{ $t('customers.fields.foreignCustomerToggle') }}
                </label>
              </div>

              <template v-if="isForeignCustomer">
                <div class="form-floating mb-4">
                  <select
                    id="val-foreign-id-type"
                    class="form-control"
                    v-model="state.foreign_tax_id_type"
                  >
                    <option value="">{{ $t('common.selectOption') }}</option>
                    <option value="02">{{ $t('customers.fields.foreignIdTypeOptions.02') }}</option>
                    <option value="03">{{ $t('customers.fields.foreignIdTypeOptions.03') }}</option>
                    <option value="04">{{ $t('customers.fields.foreignIdTypeOptions.04') }}</option>
                    <option value="05">{{ $t('customers.fields.foreignIdTypeOptions.05') }}</option>
                    <option value="06">{{ $t('customers.fields.foreignIdTypeOptions.06') }}</option>
                    <option value="07">{{ $t('customers.fields.foreignIdTypeOptions.07') }}</option>
                  </select>
                  <label for="val-foreign-id-type">{{ $t('customers.fields.foreignIdType') }}</label>
                </div>
                <div class="form-floating mb-1">
                  <input
                    type="text"
                    id="val-foreign-id"
                    class="form-control"
                    v-model="state.foreign_tax_id"
                  />
                  <label for="val-foreign-id">{{ $t('customers.fields.foreignIdNumber') }}</label>
                </div>
                <p class="ctc-edit-hint mb-3">{{ $t('customers.fields.foreignIdCountryHint') }}</p>
              </template>
               </template>


            </div>
          </div>
  
          <hr>
  
          <div class="row">
            <div class="col-lg-3">
              <p class="fs-sm text-muted">
                {{ $t("customers.addressSection") }}
              </p>
            </div>
            <div class="col-lg-9">
  
              <div class="form-floating mb-4">
                <input
                type="text"
                id="val-billing-address"
                class="form-control"
                v-model="state.address_billing"
                :placeholder="$t('customers.fields.address')"
              />
              <label for="val-billing-address">{{ $t("customers.fields.address") }}</label>
            </div>
  
              <div class="row g-3">
                  <div class="form-floating col-6 mb-4">
                  <input
                    type="text"
                  id="val-city"
                  class="form-control"
                  v-model="state.city"
                  :placeholder="$t('customers.fields.city')"
  
                  />
                  <label for="val-city">{{ $t("customers.fields.city") }}</label>
                </div>
  
  
                <div class="form-floating col-6 mb-4">
                  <input
                    type="text"
                    id="val-post-code"
                    class="form-control"
                    v-model="state.post_code"
                    :placeholder="$t('customers.fields.postalCode')"
                  />
                  <label for="val-post-code">{{ $t("customers.fields.postalCode") }}</label>
                </div>
              </div>
  
  
  
  
              <div class="row g-3">
  
                <div class="form-floating col-6 mb-4">
                <select
                  class="form-select"
                  id="example-select-floating"
                  name="example-select-floating"
                  v-model="state.billing_country_id"
                  aria-label="Floating label select example"
                  >
                  <option selected>{{ $t("common.selectOption") }}</option>
                  <option :value="country.id" v-for="country in countries">{{country.name}}</option>
                </select>
                <label for="example-select-floating">{{ $t("customers.fields.country") }}</label>
                </div>
  
              </div>
  
  
              <!-- <div class="form-check form-switch mb-4">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      id="is-same-address"
                      v-model="state.is_same_address"
                    />
                    <label class="form-check-label" for="is-same-address"
                      >Use same address for delivery</label
                    >
              </div>
  
              <div class="form-floating mb-4" v-if="!state.is_same_address">
                <input
                  type="text"
                  id="val-billing-address"
                  class="form-control"
                  v-model="state.address_billing"
                  placeholder="Your valid billing-address.."
                />
                <label for="val-billing-address">Billing Address</label>
              </div>
  
              <div class="row g-3"  v-if="!state.is_same_address">
                  <div class="form-floating col-6 mb-4">
                  <input
                    type="text"
                    id="val-city"
                    class="form-control"
                    v-model="state.city"
                    placeholder="Your City.."
  
                  />
                  <label for="val-city">City</label>
                </div>
  
  
                <div class="form-floating col-6 mb-4">
                  <input
                    type="text"
                    id="val-post-code"
                    class="form-control"
                    v-model="state.post_code"
                    placeholder="Your Post Code.."
                  />
                  <label for="val-post-code">Post Code</label>
                </div>
              </div>
  
  
              <div class="row g-3"  v-if="!state.is_same_address">
  
                <div class="form-floating col-6 mb-4">
                <select
                  class="form-select"
                  id="example-select-floating"
                  name="example-select-floating"
                  v-model="state.delivery_country_id"
                  aria-label="Floating label select example"
                  >
                  <option selected>Select an option</option>
                  <option :value="country.id" v-for="country in countries">{{country.name}}</option>
                </select>
                <label for="example-select-floating">Country</label>
                </div>
  
              </div>
   -->
  
            </div>
          </div>
  
          <hr>
  
          <!-- <div class="row">
            <div class="col-lg-3">
              <p class="fs-sm text-muted">
                Contacts
              </p>
            </div>
            <div class="col-lg-9">
  
              <div class="row g-3" v-for="(contact, index) in state.contacts" :key="contact.id">
                  <div class="form-floating col-2 mb-4">
                  <input
                    type="text"
                    id="val-contact-first-name"
                    class="form-control"
                    v-model="contact.first_name"
                    placeholder="First Name"
  
                  />
                  <label for="val-contact-first-name">First Name</label>
                </div>
  
  
                <div class="form-floating col-2 mb-4">
                  <input
                    type="text"
                    id="val-contact-last-name"
                    class="form-control"
                    v-model="contact.last_name"
                    :class="{
                      'is-invalid': v$.contacts.$each.$response.$errors[index].last_name.length ,
                    }"
                    @blur="v$.contacts.$each.$response.$errors[index].last_name.$touch"
                    placeholder="Last Name"
                  />
                  <div
                  class="invalid-feedback animated fadeIn"
                  v-for="error in v$.contacts.$each.$response.$errors[index].last_name"
                  :key="error"
                >
                  Required
                </div>
  
  
                  <label for="val-contact-last-name">Last Name<span class="text-danger">*</span></label>
                </div>
  
                <div class="form-floating col-2 mb-4">
                  <input
                    type="text"
                    id="val-contact-email"
                    class="form-control"
                    v-model="contact.email"
                    placeholder="Email"
                  />
                  <label for="val-contact-email">Email</label>
                </div>
  
                <div class="form-floating col-2 mb-4">
                  <input
                    type="text"
                    id="val-contact-phone"
                    class="form-control"
                    v-model="contact.work_phone"
                    placeholder="Phone"
                  />
                  <label for="val-contact-phone">Phone</label>
                </div>
  
                <div class="form-floating col-2 mb-4">
                  <button class="btn btn-danger" @click.prevent="deleteCustomer(index)"><i class="fa fa-fw fa-trash"></i></button>
                </div>
  
              </div>
  
              <button class="btn btn-primary" @click.prevent="newCustomer">New Customer Contact</button>
  
            </div>
          </div>
   -->
  
  
          <template class="text-right" #footer>
            <button type="submit" class="btn btn-lg btn-primary mb-3">
              {{ $t("common.save") }}
            </button>
  
          </template>
  
      </BaseBlock>
      <!-- END Floating Labels -->
  
      </form>
    </div>
    <!-- END Page Content -->
  </template>
  
  <script setup>
import { useTenantCountry } from '@/composables/useTenantCountry';
  import { reactive, ref, computed, onMounted, watch } from "vue";
  import axios from 'axios'
  import { createToaster } from '@meforma/vue-toaster';
  const toaster = createToaster({ /* options */ });
  import { useRoute } from 'vue-router'
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
  
  

<style scoped>
:global(.customer-form-card) {
  overflow: hidden;
  border-top: 3px solid #E91E63 !important;
}

.ctc-edit-hint {
  margin-top: -8px;
  font-size: 0.8rem;
  color: #6b7280;
}
</style>
