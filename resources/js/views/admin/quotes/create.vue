<template>
  <CreateQuoteForm @saveDocument="saveQuote" />
</template>

<script setup>
import axios from "axios";
import { createToaster } from "@meforma/vue-toaster";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import CreateQuoteForm from "./CreateQuoteForm.vue";

const toaster = createToaster();
const router  = useRouter();
const { t } = useI18n();

async function saveQuote(state) {
  try {
    const res = await axios.post("/quotes", state);
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
