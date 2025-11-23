<template>
    
  <EditDocument 
  :title="'Factura #' + state.reference"
  :state="state"
  @saveDocument = "saveInvoice"
  >

  </EditDocument>

</template>

<script setup>
  import { reactive, ref, computed, onMounted } from "vue";
import axios from 'axios'
import { createToaster } from '@meforma/vue-toaster';
const toaster = createToaster({ /* options */ });
import { useRoute, useRouter } from 'vue-router'
const router = useRouter()
const route = useRoute()
const uuid = ref(route.params.id)

const state = ref({
    customer_id: '',
    address : '',
    discount_rate : 0,
    discount_amount : 0,
    date : '',
    status : '',
    expiration_date : '',
    carts : [],
  });

onMounted(async () => {
            
            let res = await axios.get('/invoices/' + uuid.value + '/edit');
            state.value = res.data.invoice

});


// On form submission
async function saveInvoice(state) {

  axios.post('/invoices/' + state.value.uuid ,state.value, {
        params: {
        _method: "put",
      },
    }).then(res => {
                                
      toaster.success(res.data.message);
      router.push('/backend/invoices')
  
    })
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