<template>
    
    <CreateDocument 
    title="Nueva factura"
    @saveDocument = "saveInvoice"
    >

    </CreateDocument>

</template>
  
  <script setup>
//   import { reactive, ref, computed, onMounted } from "vue";
  import axios from 'axios'
  import { createToaster } from '@meforma/vue-toaster';
  const toaster = createToaster({ /* options */ });
  import { useRouter } from 'vue-router'
  const route = useRouter()
  
  // On form submission
  async function saveInvoice(state) {

    axios.post('/invoices', state).then(res => {
                                
      toaster.success(res.data.message);
      route.push('/admin/invoices')
  
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