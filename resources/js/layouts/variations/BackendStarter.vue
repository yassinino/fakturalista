<template>
  <BaseLayout>
    <!-- Billing banners (trial countdown + read-only notice) -->
    <template #page-top-content>
      <BillingBanners />
    </template>
    <!-- END Billing banners -->

    <!-- Side Overlay Content -->
    <!-- Using the available v-slot, we can override the default Side Overlay content from layouts/partials/SideOvelay.vue -->
    <template #side-overlay-content>
      <div class="content-side">
        <p>Side Overlay content..</p>
      </div>
    </template>
    <!-- END Side Overlay Content -->

    <!-- Sidebar Content -->
    <!-- Using the available v-slot, we can override the default Sidebar content from layouts/partials/Sidebar.vue -->
    <template #sidebar-content>
      <div class="content-side">
        <BaseNavigation
          :nodes="navigation"
        />
      </div>
    </template>
    <!-- END Sidebar Content -->

    <!-- Header slots intentionally empty — Header.vue handles all content directly -->

  </BaseLayout>
</template>

<script setup>
import { useTemplateStore } from "@/stores/template";

import BaseLayout from "@/layouts/BaseLayout.vue";
import BaseNavigation from "@/components/BaseNavigation.vue";
import BillingBanners from "@/views/admin/layouts/BillingBanners.vue";

import axios from 'axios'
import { ref, onMounted } from "vue";

// Main store
const store = useTemplateStore();

import menu from "@/data/menu";

const navigation = menu.main;
const user = ref()

onMounted(async () => {
  const response = await axios.get('/user');
  user.value = response.data.user;
  if (response.data.billing) {
    store.setBillingStatus(response.data.billing);
  }
});


// Set default elements for this layout
store.setLayout({
  header: true,
  sidebar: true,
  footer: true,
});

// Set various template options for this layout variation
store.headerStyle({ mode: "light" });
store.mainContent({ mode: "narrow" });
</script>

