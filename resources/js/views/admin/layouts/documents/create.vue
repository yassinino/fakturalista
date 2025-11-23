<template>


    <!-- Page Content -->
    <div class="content">
      <form>
  
  
            <!-- Floating Labels -->
      <BaseBlock :title="title" content-full>
          <div class="row">
            <div class="col-lg-6">
                <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-customer"
                    >Cliente<span class="text-danger">*</span></label>
                  <div class="col-sm-8">
                    <VueSelect
                      id="document-customer"
                      v-model="state.customer_id"
                      :options="customers"
                      label="name"
                      :reduce="(option) => option.uuid"
                      @option:selected="showAddress(index, $event)"
                      @blur="v$.customer_id.$touch"
                      :class="{
                        'is-invalid': v$.customer_id.$errors.length,
                      }"
                      placeholder="Selecciona un cliente"
                    ></VueSelect>
                    <div
                        v-if="v$.customer_id.$errors.length"
                        class="invalid-feedback animated fadeIn"
                      >
                      Requerida
                      </div>

                  </div>
                </div>
                <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-address"
                    >Dirección </label>
                  <div class="col-sm-8">
                    <textarea class="form-control" v-model="state.address" rows="5" id="document-address" disabled></textarea>
                  </div>
                </div>
                <hr>
            </div>
            <div class="col-lg-6">
                <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-date"
                    >Fecha<span class="text-danger">*</span></label>
                  <div class="col-sm-8">
                    <FlatPickr
                      id="document-date"
                      class="form-control"
                      placeholder="Y-m-d"
                      v-model="state.date"
                    />
                  </div>
                </div>
                <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-status"
                    >Estado</label>
                  <div class="col-sm-8">
                    <select
                      class="form-select"
                      id="document-status"
                      v-model="state.status"
                      >
                      <option selected>Seleccione un estado</option>
                      <option value="1">Pagado</option>
                      <option value="0">No Pagado</option>
                    </select>
                  </div>
                </div>
                <!-- <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-expiration-date"
                    >Fecha de expiración<span class="text-danger">*</span></label>
                  <div class="col-sm-8">
                    <FlatPickr
                      id="document-expiration-date"
                      class="form-control"
                      placeholder="Y-m-d"
                      v-model="state.expiration_date"
                    />
                  </div>
                </div>
                <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-terms"
                    >Condiciones de pago</label>
                  <div class="col-sm-8">
                    <select
                      class="form-select"
                      id="document-terms"
                      v-model="state.payment_terms"
                      >
                      <option selected>Seleccione una opción</option>
                      <option value="30days">A los 30 días</option>
                      <option value="60days">A los 60 días</option>
                      <option value="90days">A los 90 días</option>
                      <option value="due_on_receipt">Vencimiento al recibirlo</option>
                    </select>
                  </div>
                </div> -->
                <hr>
            </div>
          </div>
  
          <div class="row">
            <div class="col-lg-12">
              <table class="table table-vcenter">
                <thead>
                  <tr>
                    <th class="d-none d-sm-table-cell" style="width: 35%;">
                      Descripción
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Cantidad
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Unidad
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Precio unitario
                    </th>
                    <th class="d-none d-sm-table-cell">
                      IMPORTE
                    </th>
                    <th class="text-center">
                      IVA%
                    </th>
                    <th> </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(cart, index) in state.carts" :key="index">
                    <th>

                      <div class="row">
                        <div class="col-12">

                          <VueSelect
                              id="quote-customer"
                              v-model="cart.item_id"
                              @option:selected="selectProduct(index, $event)"
                              :options="items"
                              label="name"
                              :reduce="(option) => option.id"
                              placeholder="Seleccionar producto"
                            ></VueSelect>

                          <textarea class="form-control mt-2" rows="2" v-model="cart.description"></textarea>
                        </div>
                      </div>
                    </th>
                    <th>
                      <input type="text" 
                      class="form-control" 
                      v-model="cart.qty" 
                      id="cart-qty"
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal">
                    </th>
                     <th style="width: 10%;">
                      <select
                      class="form-select"
                      id="cart-vta"
                      v-model="cart.unite"
                      >
                      <option value="pc">pieza</option>
                      <option value="kg">kg</option>
                    </select>
                    </th>
                    <th>
                      <input type="text" 
                      class="form-control" 
                      v-model="cart.price" 
                      id="cart-price"
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal"
                      >
                    </th>
                    <!-- <th>
                      <input type="text" 
                      class="form-control" 
                      v-model="cart.discount" 
                      id="cart-discount">
                    </th> -->
                    <th>
                      {{ $toComma(totalRow[index]) }}
                    </th>
                    <th style="width: 10%;">
                      <select
                      class="form-select"
                      id="cart-vta"
                      v-model="cart.vta"
                      >
                      <option value="0" selected>0</option>
                      <option value="4">4</option>
                      <option value="10">10</option>
                      <option value="21">21</option>
                    </select>
                    </th>
                    <th>
                      <button class="btn btn-danger" @click.prevent="removeCart(cart)"><i class="fa fa-fw fa-minus"></i></button>
                    </th>
                  </tr>
                </tbody>
              </table>
             <button 
                class="btn btn-primary" @click.prevent="addNewItem()">
                <i class="fa fa-fw fa-plus"></i> Agregar nuevo artículo
              </button>

            </div>
          </div>
          <div class="row mt-4">
            <div class="col-lg-6">
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-note"
                    >Nota</label>
                  <div class="col-sm-8">
                    <textarea class="form-control" v-model="state.note" rows="3" id="document-note"></textarea>
                  </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-subtotal"
                    >SUMA</label>
                  <div class="col-sm-8">
                    <input type="text" 
                      class="form-control" 
                      v-model="subTotal" 
                      id="document-subtotal"
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal"
                      disabled>
                  </div>
              </div>
              <!-- <hr>
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-discount-rate"
                    >Descuento%</label>
                  <div class="col-sm-8">
                    <input type="text" 
                      class="form-control" 
                      v-model="state.discount_rate" 
                      id="document-discount-rate">
                  </div>
              </div>
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-discount-amount"
                    >Importe del descuento</label>
                  <div class="col-sm-8">
                    <input type="text" 
                      class="form-control" 
                      v-model="discountTotal" 
                      id="document-discount-amount" disabled>
                  </div>
              </div> -->
              <hr>
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="quote-vta"
                    >IVA(4%)</label>
                  <div class="col-sm-8">
                    <input type="text" 
                      class="form-control" 
                      v-model="vtaTotal4" 
                      id="quote-vta" 
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal"
                      disabled>
                  </div>
              </div>
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="quote-vta"
                    >IVA(10%)</label>
                  <div class="col-sm-8">
                    <input type="text" 
                      class="form-control" 
                      v-model="vtaTotal10" 
                      id="quote-vta" 
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal"
                      disabled>
                  </div>
              </div>
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="quote-vta"
                    >IVA(21%)</label>
                  <div class="col-sm-8">
                    <input type="text" 
                      class="form-control" 
                      v-model="vtaTotal21"
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal"
                      id="quote-vta" disabled>
                  </div>
              </div>
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-total"
                    >Total</label>
                  <div class="col-sm-8">
                    <input type="text" 
                      class="form-control" 
                      v-model="total" 
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal"
                      id="document-total" disabled>
                  </div>
              </div>
            </div>
            </div>
            <!-- <div class="row mt-4">
            <div class="col-lg-12">
              <div class="row mb-2">
                  <label class="col-sm-4 col-form-label" for="document-note"
                    >Attachments</label>
                  <div class="col-sm-12">
                    <div class="row push">
                      <div class="col-lg-12 col-xl-12">
                        <form id="dropzoneForm" class="dropzone"></form>
                      </div>
                    </div>
                  </div>
              </div>
            </div>
            </div> -->
  
          <template class="text-right" #footer>
            <button 
            @click.prevent="saveQuote"
            type="button" 
            class="btn btn-lg btn-primary mb-3">
            Guardar</button>
  
          </template>
  
      </BaseBlock>
      
  
      </form>
      <!-- <showListProduct @sendCarts="getcarts"/> -->

    
    </div>


  </template>
  
  <script setup>
  import { reactive, ref, computed, onMounted } from "vue";
  import VueSelect from "vue-select";
  import FlatPickr from "vue-flatpickr-component";
  import axios from 'axios'

  
  import useVuelidate from "@vuelidate/core";
  import {
    required,
  } from "@vuelidate/validators";
  
  const props = defineProps({
        title: {
        type: String,
        },
    });

  const emit = defineEmits(['saveDocument'])


  // Example options for select
  const current = new Date();

  // Input state variables
  const state = reactive({
    customer_id: '',
    address : '',
    discount_rate : 0,
    discount_amount : 0,
    date : `${current.getFullYear()}-${current.getMonth() + 1}-${current.getDate()}`,
    status : '',
    expiration_date : `${current.getFullYear()}-${current.getMonth() + 2}-${current.getDate()}`,
    carts : []
  });

  const customers = ref([])
  const items = ref([])
    
  onMounted(async () => {
            
            let response = await axios.get('/customers');
            customers.value = response.data.customers

            let res = await axios.get('/items');
            items.value = res.data.items

    });

  const selectProduct = (index, value) => {
    state.carts[index] = {
      'item_id' : value.id,
      'description' : value.description,
      'qty' : 1,
      'unite' : value.unite,
      'price' : value.sales_price,
      'discount' : 0,
      'vta' : 0,
      'total' : value.sales_price
    }
    
  }

  const addNewItem = () => {
    state.carts.push({
      'item_id' : '',
      'name' : '',
      'description' : '',
      'qty' : 1,
      'unite' : 'pc',
      'price' : 0,
      'discount' : 0,
      'vta' : 0,
      'total' : 0
    })
  }

  const showAddress = (index, value) => {
    state.address = value.address_billing
  }

  const total = computed(() => {

    const final = ((subTotal.value + vtaTotal.value) - discountTotal.value)
    return Number(final.toFixed(2)); // ✅ format 2 décimales
  });

  const vtaTotal = computed(() => {

  var vta = state.carts.reduce((total, cart) => {
          return ( total + ((cart.vta * cart.total) / 100 ) )
      }, 0);
  var t_tva = vta - ((vta * state.discount_rate) / 100 )
  return t_tva;
  });


  const calcVta = (rate) => {
    const raw = state.carts.reduce((total, cart) => {
      if (Number(cart.vta) === rate) {
        const row = (cart.qty * cart.price) - ((cart.qty * cart.price) * cart.discount) / 100;
        return total + (row * rate) / 100;
      }
      return total;
    }, 0);

    const final = raw - (raw * Number(state.discount_rate || 0)) / 100;

    return Number(final.toFixed(2)); // ✅ format 2 décimales
  };

  const vtaTotal4  = computed(() => calcVta(4));
  const vtaTotal10 = computed(() => calcVta(10));
  const vtaTotal21 = computed(() => calcVta(21));


  const discountTotal = computed(() => {
    return (subTotal.value * state.discount_rate) / 100
  });


  const subTotal = computed(() => {
    const final = state.carts.reduce((total, cart) => {
            return total + (Number(cart.qty * cart.price) - (( Number(cart.qty * cart.price) * cart.discount ) / 100));
          }, 0);

    return Number(final.toFixed(2)); 
  });

  const totalRow = computed(() => {
    return state.carts.map((cart) => {
            cart.total =  Number(cart.qty * cart.price) - (( Number(cart.qty * cart.price) * cart.discount ) / 100)
            return Number(cart.total.toFixed(2)); 
        })
  });

  const removeCart = (cart) => {
    if (confirm("¿Estás seguro de que quieres eliminar este elemento?"))
            state.carts = state.carts.filter(ct => ct != cart)
  }
  
  // Validation rules
  const rules = computed(() => {
  
    return {
        customer_id: {
          required,
        },
        date: {
          required,
        },
        expiration_date: {
          required,
        },
      };
  });
  
  // Use vuelidate
  const v$ = useVuelidate(rules, state);

  
  // On form submission
  async function saveQuote() {
    const result = await v$.value.$validate();
  
    if (!result) {
      // notify user form is invalid
      return;
    }

    state.total = total
    state.sub_total = subTotal
    state.vta = vtaTotal
    state.vta4 = vtaTotal4
    state.vta10 = vtaTotal10
    state.vta21 = vtaTotal21
    state.discount_amount = discountTotal

    emit('saveDocument', state)

  }
  </script>
  
  
  <style lang="scss">
// Flatpickr + Custom overrides
@import "flatpickr/dist/flatpickr.css";
@import "@/assets/scss/vendor/flatpickr";

// Dropzone + Custom overrides
@import "dropzone/dist/dropzone.css";
@import "@/assets/scss/vendor/dropzone";
</style>