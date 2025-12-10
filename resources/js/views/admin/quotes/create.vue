<template>
    
  <CreateDocument 
  title="New Quote"
  @saveDocument = "saveQuote"
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
async function saveQuote(state) {

  axios.post('/quotes', state).then(res => {
                              
    toaster.success(res.data.message);
    route.push('/admin/quotes')

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