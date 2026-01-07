<template>


    <!-- Page Content -->
    <div class="content">
      <form @submit.prevent="onSubmit">
  
  
            <!-- Floating Labels -->
      <BaseBlock :title="$t('items.editTitle')" content-full>
          <div class="row">
            <div class="col-lg-12">
  
              <div class="row items-push">

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
                        <span class="fs-4 fw-semibold">{{ $t("items.types.product") }}</span>
                      </span>
                    </label>
                  </div>
                </div>

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
                        <span class="fs-4 fw-semibold">{{ $t("items.types.service") }}</span>
                      </span>
                    </label>
                  </div>
                </div>

  
              </div>
  

            </div>

            <div class="col-lg-9 offset-lg-3">
                <div class="row mb-2">
                  <label class="col-sm-3 col-form-label" for="item-name"
                    >{{ $t("items.fields.name") }}<span class="text-danger">*</span></label>
                  <div class="col-sm-9">
                    <input type="text" 
                    class="form-control" 
                    :class="{'is-invalid': v$.name.$errors.length}"
                    v-model="state.name" 
                    id="item-name"  
                    @blur="v$.name.$touch">
                  </div>
                </div>
                <div class="row mb-2">
                  <label class="col-sm-3 col-form-label" for="item-sales-price"
                    >{{ $t("items.fields.salesPrice") }}</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" v-model="state.sales_price" id="item-sales-price" 
                    v-decimal="{ decimals: 2 }"
                    inputmode="decimal"
                    autocomplete="off"
                    >
                  </div>
                </div>
                <div class="row mb-2" v-if="state.type == 2">
                  <label class="col-sm-3 col-form-label" for="item-purchase-price"
                    >{{ $t("items.fields.purchasePrice") }}</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" v-model="state.purchase_price" id="item-purchase-price" 
                    v-decimal="{ decimals: 2 }"
                    inputmode="decimal"
                    >
                  </div>
                </div>
                <div class="row mb-2" v-if="state.type == 2">
                  <label class="col-sm-3 col-form-label" for="item-purchase-price"
                    >{{ $t("items.fields.unit") }}</label>
                  <div class="col-sm-9">
                    <select
                      class="form-select"
                      id="cart-vta"
                      v-model="state.unite"
                      >
                      <option value="pc">{{ $t("units.piece") }}</option>
                      <option value="kg">{{ $t("units.kilogram") }}</option>
                    </select>
                  </div>
                </div>
            </div>

            <hr>

            <div class="col-lg-9 offset-lg-3">
                <div class="row mb-2">
                  <label class="col-sm-3 col-form-label" for="item-description"
                    >{{ $t("items.fields.description") }}</label>
                  <div class="col-sm-9">
                    <textarea class="form-control" rows="4" v-model="state.description" id="item-description" ></textarea>
                  </div>
                </div>


                <div class="row mb-2">
                  <label class="col-sm-3 col-form-label" for="item-family"
                    >{{ $t("items.fields.family") }}</label>
                  <div class="col-sm-9">
                    <VueSelect
                      id="quote-customer"
                      v-model="state.family_id"
                      :options="families"
                      label="name"
                      :reduce="(option) => option.id"
                      :placeholder="$t('items.selectFamilyPlaceholder')"
                    ></VueSelect>
                    <small>
                      <a href="" data-bs-toggle="modal" data-bs-target="#modal-block-vcenter">
                        {{ $t("items.createFamilyLink") }}
                      </a>
                    </small>
                  </div>
                </div>
            </div>
          </div>

            <template class="text-right" #footer>
            <button type="submit" class="btn btn-lg btn-primary mb-3">
              {{ $t("common.save") }}
            </button>
          </template>
  
      </BaseBlock>
      <!-- END Floating Labels -->
  
      </form>

      <div
      class="modal"
      id="modal-block-vcenter"
      tabindex="-1"
      role="dialog"
      aria-labelledby="modal-block-vcenter"
      aria-hidden="true"
    >
      <div class="modal-dialog modal-dialog-centered modal-lg" role="document" ref="modal_family"
>
        <div class="modal-content">
          <BaseBlock :title="$t('items.createFamilyTitle')" transparent class="mb-0">
            <template #options>
              <button
                type="button"
                class="btn-block-option"
                data-bs-dismiss="modal"
                aria-label="Close"
              >
                <i class="fa fa-fw fa-times"></i>
              </button>
            </template>

            <template #content>
                <form @submit.prevent="addFamily">

                    <div class="block-content fs-sm">
                            <div class="col-lg-9 offset-lg-3">
                                <div class="row mb-2">
                                    <label class="col-sm-3 col-form-label" for="item-name"
                                    >{{ $t("items.familyNameLabel") }}<span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" 
                                        class="form-control"                 
                                        :class="{
                                        'is-invalid': v$_fm.name.$errors.length,
                                        }" v-model="family.name" 
                                        id="item-name" 
                                        @blur="v$_fm.type.$touch">
                                    </div>
                                </div>
                            </div>
                    </div>
                    <div class="block-content block-content-full text-end bg-body">
                        <button
                        type="button"
                        class="btn btn-sm btn-alt-secondary me-1"
                        data-bs-dismiss="modal"
                        >
                        {{ $t("common.close") }}
                        </button>
                        <button
                        type="submit"
                        class="btn btn-sm btn-primary"
                        >
                        {{ $t("common.save") }}
                        </button>
                    </div>
                 </form>

            </template>
          </BaseBlock>
        </div>
      </div>
    </div>

    </div>
    <!-- END Page Content -->
  </template>
  
  <script setup>
  import { reactive,ref, computed, onMounted } from "vue";
  import axios from 'axios'
  import VueSelect from "vue-select";
  import { createToaster } from '@meforma/vue-toaster';
  const toaster = createToaster({ /* options */ });
  import { useRoute } from 'vue-router'
  const route = useRoute()
  
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
  const state = ref({});

    // Input state variables
    const family = reactive({
    name: null,
  });
  
  const modal_family = ref()
  const families = ref()
  const uuid = ref(route.params.id)



  onMounted(async () => {

          let res = await axios.get('/items/' + uuid.value + '/edit');
          state.value = res.data.item
          let response = await axios.get('/families');
          families.value = response.data.families
  });
  
  
  // Validation rules
  const rules = computed(() => {
    return {
        name: {
          required,
        },
        type: {
          required,
        },
      };
  });

  const rule_fm = computed(() => {
    return {
        name: {
          required,
        },
      };
  });
  
  // Use vuelidate
  const v$ = useVuelidate(rules, state);
  const v$_fm = useVuelidate(rule_fm, family);
  

  // On form submission
  async function addFamily() {
    const result = await v$_fm.value.$validate();
  
    if (!result) {
      // notify user form is invalid
      return;
    }
  
    axios.post('/families',family).then(res => {
                                
      families.value.push(res.data.family)
      family.name = ''
      console.log(modal_family.value)
      modal_family.value.hide()
      
     })
  }
  // On form submission
  async function onSubmit() {
     const result = await v$.value.$validate();
  
    if (!result) {
      // notify user form is invalid
      return;
    }
  
    axios.post('/items/' + state.value.uuid ,state.value, {
        params: {
        _method: "put",
      },
    }).then(res => {
                                
      toaster.success(res.data.message);
      route.push('/admin/items')
  
    })
  }
  </script>
  
  
