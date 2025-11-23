<template>
    

                <button
                            @click.prevent="saveDocument()"
                            type="button" 
                            class="btn btn-lg btn-outline-primary mb-3 mr-3">Save
                            </button>

                            <button
                            @click.prevent="changeSide(0)"
                            type="button" 
                            class="btn btn-lg btn-primary mb-3 mr-2">Cancel
                            </button>
                        <div class="alert alert-warning alert-dismissible" role="alert">

                            <p class="mb-0">
                                <b>Heads up!</b> <br>
                                These changes will only be applied to this quote. <br>
                                To edit your template, go to Company Settings > Document Settings.
                            </p>
                        </div>

                        <p>Global documents settings</p>


                        <BaseBlock
                            title="Appearance"
                            btn-option-content
                            ref="testBlock"
                            >


                                    <div class="row mb-1">
                                        <label class="col-sm-8 col-form-label" for="example-hf-email"
                                            >Color scheme</label
                                        >
                                        <div class="col-sm-4">
                                            <input type="color" class="form-control" v-model="state.color" @input="changeData">
                                        </div>
 
                                    </div> 
                                    <div class="row mb-1">
                                        <label class="col-sm-6 col-form-label" for="example-hf-email"
                                            >Font
                                        </label>
                                        <div class="col-sm-6">
                                            <select name="" id="" class="form-control" v-model="state.font_family" @input="changeData">
                                                <option value="arial">Arial</option>
                                                <option value="Courier new">Courier new</option>
                                                <option value="Times new roman">Times New Roman</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-sm-6 col-form-label" for="example-hf-email"
                                            >Font size</label
                                        >
                                        <div class="col-sm-6">
                                            <select name="" id="" class="form-control" v-model="state.font_size" @input="changeData">
                                                <option value="10px">Small</option>
                                                <option value="12px">Medium</option>
                                                <option value="14px">Large</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-sm-8 col-form-label" for="example-hf-email"
                                            >Font color</label
                                        >
                                        <div class="col-sm-4">
                                            <input type="color" class="form-control" v-model="state.font_color" @input="changeData">
                                        </div>
 
                                    </div> 

                                    <div class="row mb-1">
                                        <label class="col-sm-6 col-form-label" for="example-hf-email"
                                            >Logo emplacement</label
                                        >
                                        <div class="col-sm-6">
                                            <div class="space-y-2">
                                                <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="radio"
                                                    id="example-radios-default1"
                                                    name="example-radios-default"
                                                    value="option1"
                                                    checked
                                                />
                                                <label class="form-check-label" for="example-radios-default1"
                                                    >Above</label
                                                >
                                                </div>
                                                <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="radio"
                                                    id="example-radios-default2"
                                                    name="example-radios-default"
                                                    value="option2"
                                                />
                                                <label class="form-check-label" for="example-radios-default2"
                                                    >Left</label
                                                >
                                                </div>

                                            </div>
                                        </div>
 
                                    </div> 

                                    <div class="row mb-1">
                                        <label class="col-sm-6 col-form-label" for="example-hf-email"
                                            >Logo Width</label
                                        >
                                        <div class="col-sm-6">

                                            <div class="py-4">
                                                <VueformSlider v-model="state.logo_width" />
                                                <div
                                                    class="d-flex justify-content-between align-items-center fs-sm text-muted py-2"
                                                >
                                                    <span>0</span>
                                                    <span>100</span>
                                                </div>
                                            </div>
                                            
                                        </div>
 
                                    </div>

                                    <div class="row mb-1">
                                        <label class="col-sm-6 col-form-label" for="example-hf-email"
                                            >Logo Height</label
                                        >
                                            <div class="col-sm-6">

                                            <div class="py-4">
                                                <VueformSlider v-model="state.logo_height" />
                                                <div
                                                    class="d-flex justify-content-between align-items-center fs-sm text-muted py-2"
                                                >
                                                    <span>0</span>
                                                    <span>100</span>
                                                </div>
                                            </div>
                                            
                                        </div>
 
                                    </div>

                        </BaseBlock>



                        <BaseBlock
                            title="Bottom"
                            ref="testBlock"
                            btn-option-content
                            >


                            <div class="row mb-1">
                                      
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" placeholder="Payment Information" v-model="state.info_payment_input_text" @input="changeData">
                                        </div>

                                        <div class="col-sm-2">
                                            <div class="form-check form-switch mb-4">
                                                    <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="is-same-address"
                                                    @input="changeData"
                                                    v-model="state.info_payment_on"
                                                    />
                                                   
                                            </div>
                                        </div>

                                        <div class="col-sm-12" v-if="state.info_payment_on">
                                            <label for="">Bank information & Payment Method</label>
                                            <textarea class="form-control" v-model="state.info_payment_method" @input="changeData"></textarea>
                                        </div>
 
                            </div> 

                            <div class="row mb-1">
                                <div class="col-sm-12">
                                            <label for="">Footer</label>
                                            <textarea class="form-control" v-model="state.info_footer" @input="changeData"></textarea>
                                </div>
                            </div>

                        </BaseBlock>

</template>


<script setup>
import Modal from "@/components/Modal.vue";
import axios from 'axios'
import VueformSlider from "@vueform/slider";
import { ref,reactive, onMounted } from "vue";
const emit = defineEmits(['changeSide'])

const props = defineProps({
                quote: {
                type: Object,
                }
});

const state = reactive({
    logo_width : 1,
    logo_height : 1,
    color : '',
    font_family : 'arial',
    font_size : '12px',
    font_color : '',
    info_payment_text : '',
    info_payment_input_text : '',
    info_payment_on : '',
    info_payment_method : '',
    info_footer : '',
  });


    const modalActive = ref(false);
    const testBlock = ref({});

    const toggleModal = () => {
      modalActive.value = !modalActive.value;
    };


    const changeSide = (nbr) => {
        emit('changeSide', nbr)
    }


    const changeData = () => {

        if(state.info_payment_input_text == ''){
            state.info_payment_text = 'Payment Information'
        }else{
            state.info_payment_text = state.info_payment_input_text
        }

        emit('sendData', state);
    };


    const saveDocument = () => {
        axios.post('/templates/save' , state).then(res => {
            toaster.success(res.data.message);
            emit('changeSide', 0)
        })
    }


onMounted(async () => {
        
    
});


</script>


<style lang="scss">


@import "@vueform/slider/themes/default.css";
@import "@/assets/scss/vendor/vueform-slider";


</style>
