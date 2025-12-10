<template>
    
  <EditDocument 
  title="Edit Quote"
  :state="state"
  @saveDocument = "saveQuote"
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
            
            let res = await axios.get('/quotes/' + uuid.value + '/edit');
            state.value = res.data.quote

});


// On form submission
async function saveQuote(state) {

  axios.post('/quotes/' + state.value.uuid ,state.value, {
        params: {
        _method: "put",
      },
    }).then(res => {
                                
      toaster.success(res.data.message);
      router.push('/admin/quotes')
  
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