<template>
  <EditQuoteForm
    v-if="quote"
    :quote="quote"
    @saveDocument="saveQuote"
  />
  <div v-else class="content">
    <div style="padding: 40px; text-align: center; color: #9ca3af;">
      <i class="fa fa-spinner fa-spin fa-2x"></i>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { createToaster } from "@meforma/vue-toaster";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import EditQuoteForm from "./EditQuoteForm.vue";

const toaster = createToaster();
const router  = useRouter();
const route   = useRoute();
const { t }   = useI18n();

const uuid  = route.params.id;
const quote = ref(null);

onMounted(async () => {
  const res = await axios.get("/quotes/" + uuid + "/edit");
  quote.value = res.data.quote;
});

async function saveQuote(state) {
  try {
    const res = await axios.post("/quotes/" + state.uuid, state, {
      params: { _method: "put" },
    });
    toaster.success(res.data.message);
    router.push("/admin/quotes");
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('quotes.errorGeneric'));
  }
}
</script>

<style lang="scss">
@import "flatpickr/dist/flatpickr.css";
@import "@/assets/scss/vendor/flatpickr";
</style>
