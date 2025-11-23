<template>
    <!-- Extra Large Block Modal -->
    <Modal @close="toggleModal" :modalActive="modalActive" ref="myModal" style="font-size: 14px;">

        <BaseBlock>

              <template #content>
              <div class="block-content">
                
                <div class="row">
                    <div class="col-8 overflow-scroll" :style="'font-family :' + state.font_family + '!important;'
                     +  'font-size :' + state.font_size + '!important;'
                     +  'color :' + state.font_color + '!important;'" > 
                        
                        <div class="p-sm-4">
                            <!-- Invoice Info -->
                            <div class="row mb-4">
                            <!-- Company Info -->
                            <div class="col-6 fs-sm">
                                <p class="h3">Company</p>
                                <address>
                                Street Address<br />
                                State, City<br />
                                Region, Postal Code<br />
                                ltd@example.com
                                </address>
                            </div>
                            <!-- END Company Info -->

                            <!-- Client Info -->
                            <div class="col-6 text-end fs-sm">
                                <p class="h3">Client</p>
                                <address>
                                    {{ quote.customer }}
                                </address>
                            </div>
                            <!-- END Client Info -->
                            </div>
                            <!-- END Invoice Info -->

                            <!-- Table -->
                            <div class="table-responsive push">
                            <table class="table table-bordered" :style="'color :' + state.font_color + '!important;'">
                                <thead :style="'background :' + state.color ">
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end" style="width: 120px">Price</th>
                                    <th class="text-end" style="width: 120px">Qty.</th>
                                    <th class="text-end" style="width: 120px">Vat(%)</th>
                                    <th class="text-end" style="width: 120px">Total</th>
                                </tr>
                                </thead>
                                <tbody>

                                <tr v-for="(cart, index) in quote.carts" :key="index">
                                    <td>
                                    <p class="fw-semibold mb-1">{{  cart.description }}</p>
                                    
                                    </td>
                                    <td class="text-end">{{ cart.price }}</td>
                                    <td class="text-end">{{ cart.qty }}</td>
                                    <td class="text-end">{{ cart.vta }}</td>
                                    <td class="text-end">{{ cart.total }}</td>
                                </tr>

                                <tr>
                                    <td colspan="4" class="fw-semibold text-end">Subtotal</td>
                                    <td class="text-end">{{ quote.sub_total }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="fw-semibold text-end">Vat</td>
                                    <td class="text-end">{{ quote.vta }}</td>
                                </tr>
                                <tr>
                                    <td
                                    colspan="4"
                                    class="fw-bold text-uppercase text-end bg-body-light"
                                    >
                                    Total
                                    </td>
                                    <td class="fw-bold text-end bg-body-light">{{ quote.total }}</td>
                                </tr>
                                </tbody>
                            </table>

                            <template v-if="state.info_payment_on">
                                <p>
                                {{ state.info_payment_text }}
                                </p>

                                <p>
                                    {{ state.info_payment_method }}
                                </p>
                            </template>


                            <hr>

                            <p>
                                    {{ state.info_footer }}
                            </p>

                            
                            </div>

                        </div>



                    </div>


                    <div class="col-4" v-if="rightSide == 0">
                        
                        <div class="row">
                            <button 
                            @click.prevent="sendEmail"
                            type="button" 
                            class="btn btn-lg btn-outline-primary mb-3">
                            <i class="si si-paper-plane"></i> Email
                            </button>
                        </div>

                        <div class="row">
                            <button 
                            @click.prevent="printDocument"
                            type="button" 
                            class="btn btn-lg btn-outline-primary mb-3">
                            <i class="si si-printer"></i> Print
                            </button>
                        </div>

                        <div class="row">
                            <button 
                            @click.prevent="downloadDocument"
                            type="button" 
                            class="btn btn-lg btn-outline-primary mb-3">
                            <i class="si si-cloud-download"></i> Download
                            </button>
                        </div>
                       

                        <hr>
                        <div class="row">

                            <button
                            @click.prevent="changeSide(1)"
                            type="button" 
                            class="btn btn-lg btn-outline-primary mb-3">
                            <i class="si si-pencil"></i> Customize this document
                            </button>

                        </div>
                       

                    </div>

                    <div class="col-4" v-if="rightSide == 1">
                        <DocumentDesign @changeSide="changeSide" @sendData="sendData"
                        />
                    </div>

                </div>

              </div>

            </template>

        </BaseBlock>
    </Modal>

</template>

<script setup>
import Modal from "@/components/Modal.vue";
import DocumentDesign from "@/views/admin/layouts/documents/design.vue";
import VueformSlider from "@vueform/slider";
import { Document } from "postcss";
import { ref,reactive, onMounted } from "vue";
  
    const props = defineProps({
            quote: {
                type: Object,
            }
    });

    const modalActive = ref(false);
    const state = ref({
        color : '',
        font_family : 'arial',
        font_size : '10px',
        font_color : '',
        info_payment_text : 'Payment Information',
        info_payment_input_text : '',
        info_payment_on : '',
        info_payment_method : '',
        info_footer : ''
    });
    const rightSide = ref(0);

    const toggleModal = () => {
      modalActive.value = !modalActive.value;
    };


    onMounted(async () => {
            
        
    });


    const changeSide = (number) =>{
        rightSide.value = number
    }


    const sendData = (data) => {
      state.value = data;
    };



</script>


<style lang="scss">


@import "@vueform/slider/themes/default.css";
@import "@/assets/scss/vendor/vueform-slider";


</style>
