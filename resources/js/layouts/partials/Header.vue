<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useTemplateStore } from "@/stores/template";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { setLocale } from "@/i18n";

// Grab example data
import notifications from "@/data/notifications";

// Main store and Router
const store = useTemplateStore();
const router = useRouter();
const { t, locale } = useI18n();

// Reactive variables
const baseSearchTerm = ref("");
const userName = ref("");
const defaultAvatar = "/assets/media/avatars/avatar10.jpg";
const userAvatar = ref(defaultAvatar);
const isLoadingUser = ref(true);
const userDisplayName = computed(() => {
  locale.value;
  return userName.value || t("header.userFallback");
});

// On form search submit functionality
function onSubmitSearch() {
  router.push("/backend/pages/generic/search?" + baseSearchTerm.value);
}

// When ESCAPE key is hit close the header search section
function eventHeaderSearch(event) {
  if (event.which === 27) {
    event.preventDefault();
    store.headerSearch({ mode: "off" });
  }
}

// Attach ESCAPE key event listener
onMounted(() => {
  document.addEventListener("keydown", eventHeaderSearch);
  window.addEventListener("user-profile-updated", handleUserProfileUpdated);
  loadUser();
});

// Remove keydown event listener
onUnmounted(() => {
  document.removeEventListener("keydown", eventHeaderSearch);
  window.removeEventListener("user-profile-updated", handleUserProfileUpdated);
});

const loadUser = async () => {
  isLoadingUser.value = true;
  try {
    const { data } = await axios.get("/user");
    userName.value = data?.user?.name ?? "";
    userAvatar.value = data?.user?.avatar_url || defaultAvatar;
    if (data?.user?.locale) {
      setLocale(data.user.locale);
    }
  } catch (error) {
    // keep fallback display name
  } finally {
    isLoadingUser.value = false;
  }
};

const handleUserProfileUpdated = (event) => {
  const user = event?.detail?.user;
  if (!user) {
    return;
  }

  userName.value = user.name ?? userName.value;
  userAvatar.value = user.avatar_url || defaultAvatar;
  if (user.locale) {
    setLocale(user.locale);
  }
};
</script>

<template>
  <!-- Header -->
  <header id="page-header">
    <slot>
      <!-- Header Content -->
      <div class="content-header">
        <slot name="content">
          <!-- Left Section -->
          <div class="d-flex align-items-center">
            <slot name="content-left">
              <!-- Toggle Sidebar -->
              <button
                type="button"
                class="btn btn-sm btn-alt-secondary me-2 d-lg-none"
                @click="store.sidebar({ mode: 'toggle' })"
              >
                <i class="fa fa-fw fa-bars"></i>
              </button>
              <!-- END Toggle Sidebar -->

              <!-- Toggle Mini Sidebar -->
              <button
                type="button"
                class="btn btn-sm btn-alt-secondary me-2 d-none d-lg-inline-block"
                @click="store.sidebarMini({ mode: 'toggle' })"
              >
                <i class="fa fa-fw fa-ellipsis-v"></i>
              </button>
              <!-- END Toggle Mini Sidebar -->

              <!-- Open Search Section (visible on smaller screens) -->
              <button
                type="button"
                class="btn btn-sm btn-alt-secondary d-md-none"
                @click="store.headerSearch({ mode: 'on' })"
              >
                <i class="fa fa-fw fa-search"></i>
              </button>
              <!-- END Open Search Section -->

              <!-- Search Form (visible on larger screens) -->
              <form
                class="d-none d-md-inline-block"
                @submit.prevent="onSubmitSearch"
              >
                <div class="input-group input-group-sm">
                  <input
                    type="text"
                    class="form-control form-control-alt"
                    :placeholder="$t('header.searchPlaceholder')"
                    id="page-header-search-input2"
                    name="page-header-search-input2"
                    v-model="baseSearchTerm"
                  />
                  <span class="input-group-text border-0">
                    <i class="fa fa-fw fa-search"></i>
                  </span>
                </div>
              </form>
              <!-- END Search Form -->
            </slot>
          </div>
          <!-- END Left Section -->

          <!-- Right Section -->
          <!-- Right Section -->
          <div class="d-flex align-items-center">
            <slot name="content-right">
              <!-- User Dropdown -->
              <div class="dropdown d-inline-block ms-2">
                <button
                  type="button"
                  class="btn btn-sm btn-alt-secondary d-flex align-items-center"
                  id="page-header-user-dropdown"
                  data-bs-toggle="dropdown"
                  aria-haspopup="true"
                  aria-expanded="false"
                >
                  <img
                    class="rounded-circle"
                    :src="userAvatar"
                    alt="Header Avatar"
                    style="width: 21px"
                  />
                  <span class="d-none d-sm-inline-block ms-2">{{ userDisplayName }}</span>
                  <i
                    class="fa fa-fw fa-angle-down d-none d-sm-inline-block opacity-50 ms-1 mt-1"
                  ></i>
                </button>
                <div
                  class="dropdown-menu dropdown-menu-md dropdown-menu-end p-0 border-0"
                  aria-labelledby="page-header-user-dropdown"
                >
                  <div
                    class="p-3 text-center bg-body-light border-bottom rounded-top"
                  >
                    <img
                      class="img-avatar img-avatar48 img-avatar-thumb"
                      :src="userAvatar"
                      alt="Header Avatar"
                    />
                    <p class="mt-2 mb-0 fw-medium">
                      {{ userDisplayName }}
                    </p>
                    <!-- <p class="mb-0 text-muted fs-sm fw-medium">Web Developer</p> -->
                  </div>
                  <div class="p-2">

                    <RouterLink
                      :to="{ name: 'backend-profile' }"
                      class="dropdown-item d-flex align-items-center justify-content-between"
                    >
                      <span class="fs-sm fw-medium">{{ $t("menu.profile") }}</span>
                      <!-- <span class="badge rounded-pill bg-primary ms-2">1</span> -->
                    </RouterLink>

                    <RouterLink
                      :to="{ name: 'backend-templates' }"
                      class="dropdown-item d-flex align-items-center justify-content-between"
                    >
                      <span class="fs-sm fw-medium">{{ $t("menu.templates") }}</span>
                      <!-- <span class="badge rounded-pill bg-primary ms-2">1</span> -->
                    </RouterLink>

                    <RouterLink
                      :to="{ name: 'backend-subscription' }"
                      class="dropdown-item d-flex align-items-center justify-content-between"
                    >
                      <span class="fs-sm fw-medium">{{ $t("menu.subscription") }}</span>
                      <!-- <span class="badge rounded-pill bg-primary ms-2">1</span> -->
                    </RouterLink>

                    <RouterLink
                      :to="{ name: 'backend-settings' }"
                      class="dropdown-item d-flex align-items-center justify-content-between"
                    >
                      <span class="fs-sm fw-medium">{{ $t("menu.settings") }}</span>
                      <!-- <span class="badge rounded-pill bg-primary ms-2">1</span> -->
                    </RouterLink>

                  </div>
                  <div role="separator" class="dropdown-divider m-0"></div>
                  <div class="p-2">

                    <RouterLink
                      :to="{ name: 'auth-signout' }"
                      class="dropdown-item d-flex align-items-center justify-content-between"
                    >
                      <span class="fs-sm fw-medium">{{ $t("menu.logout") }}</span>
                    </RouterLink>
                  </div>
                </div>
              </div>
              <!-- END User Dropdown -->


              <!-- Toggle Side Overlay -->

              <!-- END Toggle Side Overlay -->
            </slot>
          </div>

          <!-- END Right Section -->
        </slot>
      </div>
      <!-- END Header Content -->

      <!-- Header Search -->
      <div
        id="page-header-search"
        class="overlay-header bg-body-extra-light"
        :class="{ show: store.settings.headerSearch }"
      >
        <div class="content-header">
          <form class="w-100" @submit.prevent="onSubmitSearch">
            <div class="input-group">
              <button
                type="button"
                class="btn btn-alt-danger"
                @click="store.headerSearch({ mode: 'off' })"
              >
                <i class="fa fa-fw fa-times-circle"></i>
              </button>
              <input
                type="text"
                class="form-control"
                :placeholder="$t('header.searchPlaceholderAlt')"
                id="page-header-search-input"
                name="page-header-search-input"
                v-model="baseSearchTerm"
              />
            </div>
          </form>
        </div>
      </div>
      <!-- END Header Search -->

      <!-- Header Loader -->
      <div
        id="page-header-loader"
        class="overlay-header bg-body-extra-light"
        :class="{ show: store.settings.headerLoader }"
      >
        <div class="content-header">
          <div class="w-100 text-center">
            <i class="fa fa-fw fa-circle-notch fa-spin"></i>
          </div>
        </div>
      </div>
      <!-- END Header Loader -->
    </slot>
  </header>
  <!-- END Header -->
</template>
