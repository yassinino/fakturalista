<template>
  <CreateInvoiceForm @saveDocument="saveInvoice" />
</template>

<script setup>
import CreateInvoiceForm from "./CreateInvoiceForm.vue";
import axios from "axios";
import { createToaster } from "@meforma/vue-toaster";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

const toaster = createToaster();
const router = useRouter();
const { t } = useI18n();

// `done` re-enables the form's create button once the request has finished
// (success navigates away; on failure the user can fix and retry).
async function saveInvoice(state, done) {
  try {
    const res = await axios.post("/invoices", state);
    // Success: clear the form's unsaved-changes protection BEFORE redirecting.
    done?.(true);
    toaster.success(res.data.message);
    // Open the document just created (its page shows the next steps);
    // fall back to the list if the id is somehow missing.
    const uuid = res.data?.invoice?.uuid;
    router.push(uuid ? { path: "/admin/invoices/edit/" + uuid, query: { created: "1" } } : "/admin/invoices");
  } catch (e) {
    // Failure: button re-enabled, document still marked unsaved.
    done?.(false);
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}
</script>
