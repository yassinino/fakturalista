<template>
  <CreateInvoiceForm @saveDocument="saveInvoice" />
</template>

<script setup>
import CreateInvoiceForm from "./CreateInvoiceForm.vue";
import axios from "axios";
import { createToaster } from "@meforma/vue-toaster";
import { useRouter } from "vue-router";

const toaster = createToaster();
const router = useRouter();

async function saveInvoice(state) {
  axios.post("/invoices", state).then((res) => {
    toaster.success(res.data.message);
    router.push("/admin/invoices");
  });
}
</script>
