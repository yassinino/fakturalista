<template>

<div class="bg-white p-3 push">
      <div
        class="d-lg-block mt-2 mt-lg-0"
        :class="{
          'd-none': !mobileVisibleNavHoverCentered,
        }"
      >
        <BaseNavigation
          :nodes="menu"
          horizontal
          horizontal-hover
          horizontal-center
        />
      </div>
      </div>
</template>

<script setup>
  import { ref } from "vue";

import BaseNavigation from "@/components/BaseNavigation.vue";
const mobileVisibleNavHoverCentered = ref(false);

import { useRoute } from "vue-router";

const menu = ref([
    {
      name: "Dashboard",
      to: "backend-dashboard",
    },
    {
      name: "Customers",
      to: "backend-customers",
    },
    {
      name: "Quotes",
      to: "backend-quotes",
    },
    {
      name: "Invoices",
      to: "backend-invoices",
    },
    {
      name: "More",
      to: "backend-items",
      subActivePaths: "backend-items",
          sub: [
            {
              name: "Products and Services",
              to: "backend-items",
            },
          ],
    },
])
const router = useRoute();

const currentPath = router.name
</script>