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

// `done` re-enables the form's create button once the request has finished.
async function saveQuote(state, done) {
  try {
    const res = await axios.post("/quotes", state);
    // Success: clear the form's unsaved-changes protection BEFORE redirecting.
    done?.(true);
    toaster.success(res.data.message);
    // Open the document just created (its page shows the next steps);
    // fall back to the list if the id is somehow missing.
    const uuid = res.data?.quote?.uuid;
    router.push(uuid ? { path: "/admin/quotes/edit/" + uuid, query: { created: "1" } } : "/admin/quotes");
  } catch (e) {
    // Failure: button re-enabled, document still marked unsaved.
    done?.(false);
    toaster.error(e.response?.data?.message ?? t('quotes.errorGeneric'));
  }
}
</script>

<style lang="scss">
@import "flatpickr/dist/flatpickr.css";
@import "@/assets/scss/vendor/flatpickr";
</style>
