<script setup>
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import { useTemplateStore } from "@/stores/template";

const router = useRouter();
const store = useTemplateStore();

onMounted(() => {
  store.logoutUser();
  delete axios.defaults.headers.common.Authorization;
  router.replace("/admin/login");
});
</script>

<template>
  <div class="d-flex justify-content-center align-items-center vh-100">
    <div class="text-center">
      <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mb-0">Signing you out…</p>
    </div>
  </div>
</template>
