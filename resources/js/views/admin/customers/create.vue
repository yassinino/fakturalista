<template>


  <!-- Page Content -->
  <div class="content">
    <form @submit.prevent="onSubmit">


          <!-- Floating Labels -->
    <BaseBlock :title="$t('customers.newTitle')" content-full>
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
                  v-if="v$.last_name.$errors.length"
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

              <div class="form-floating mb-4">
              <input
                type="text"
                id="val-ice"
                class="form-control"
                v-model="state.ice"
                :placeholder="$t('customers.fields.nif')"
              />
              <label for="val-email">{{ $t("customers.fields.nif") }}</label>
            </div>


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

            </div> -->


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
                <button class="btn btn-danger" @click.prevent="deleteCustomer(index)"><i class="fa fa-fw fa-minus"></i></button>
              </div>

            </div>

            <button class="btn btn-primary" @click.prevent="newCustomer">New Customer Contact</button>

          </div>
        </div> -->



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
import { reactive,ref, computed, onMounted } from "vue";
import axios from 'axios'
import { createToaster } from '@meforma/vue-toaster';
const toaster = createToaster({ /* options */ });
import { useRouter } from 'vue-router'
import { useI18n } from "vue-i18n";
const route = useRouter()
const { t } = useI18n();

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
  contacts : []
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
