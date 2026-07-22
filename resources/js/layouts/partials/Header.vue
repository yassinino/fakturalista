<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useTemplateStore } from "@/stores/template";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { setLocale } from "@/i18n";

const store  = useTemplateStore();
const router = useRouter();
const route  = useRoute();
const { t, locale } = useI18n();

// ── User ─────────────────────────────────────────────────────
const DEFAULT_AVATAR = "/assets/media/avatars/avatar10.jpg";
const userName    = ref("");
const userEmail   = ref("");
const userAvatar  = ref(DEFAULT_AVATAR);
const isLoadingUser = ref(true);

const userDisplayName = computed(() => {
  locale.value;
  return userName.value || t("header.userFallback");
});

function avatarInitials(name) {
  if (!name) return "?";
  const parts = name.trim().split(/\s+/);
  return (parts.length >= 2 ? parts[0][0] + parts[1][0] : name.slice(0, 2)).toUpperCase();
}

const hasCustomAvatar = computed(
  () => userAvatar.value && userAvatar.value !== DEFAULT_AVATAR
);

const loadUser = async () => {
  isLoadingUser.value = true;
  try {
    const { data } = await axios.get("/user");
    userName.value   = data?.user?.name      ?? "";
    userEmail.value  = data?.user?.email     ?? "";
    userAvatar.value = data?.user?.avatar_url || DEFAULT_AVATAR;
    if (data?.user?.locale) setLocale(data.user.locale);
  } catch { /* keep fallbacks */ } finally {
    isLoadingUser.value = false;
  }
};

const handleUserProfileUpdated = (e) => {
  const user = e?.detail?.user;
  if (!user) return;
  userName.value   = user.name      ?? userName.value;
  userEmail.value  = user.email     ?? userEmail.value;
  userAvatar.value = user.avatar_url || DEFAULT_AVATAR;
  if (user.locale) setLocale(user.locale);
};

// ── Search ────────────────────────────────────────────────────
const searchTerm    = ref("");
const searchRef     = ref(null);
const searchWrapRef = ref(null);
const mobileSearch  = ref(false);

const searchLoading = ref(false);
const searchOpen    = ref(false);
const searchResults = ref({ invoices: [], quotes: [], customers: [] });
let   searchTimer   = null;

const hasResults = computed(() =>
  searchResults.value.invoices.length  > 0 ||
  searchResults.value.quotes.length    > 0 ||
  searchResults.value.customers.length > 0
);

watch(searchTerm, (val) => {
  clearTimeout(searchTimer);
  const q = val.trim();
  if (!q || q.length < 2) {
    searchOpen.value    = false;
    searchLoading.value = false;
    searchResults.value = { invoices: [], quotes: [], customers: [] };
    return;
  }
  searchLoading.value = true;
  searchOpen.value    = true;
  searchTimer = setTimeout(() => runSearch(q), 300);
});

// Close results on navigation
watch(() => route.name, () => {
  searchTerm.value    = "";
  searchOpen.value    = false;
  searchLoading.value = false;
  searchResults.value = { invoices: [], quotes: [], customers: [] };
});

async function runSearch(q) {
  try {
    const [invRes, quoteRes, custRes] = await Promise.allSettled([
      axios.get("/invoices", { params: { search: q, per_page: 5 } }),
      axios.get("/quotes"),
      axios.get("/customers"),
    ]);

    const invoices = invRes.status === "fulfilled"
      ? (invRes.value.data.invoices ?? []).slice(0, 5)
      : [];

    const ql = q.toLowerCase();

    const quotes = (quoteRes.status === "fulfilled" ? (quoteRes.value.data.quotes ?? []) : [])
      .filter(item =>
        item.reference?.toLowerCase().includes(ql) ||
        item.customer?.toLowerCase().includes(ql)
      ).slice(0, 5);

    const customers = (custRes.status === "fulfilled" ? (custRes.value.data.customers ?? []) : [])
      .filter(item =>
        item.name?.toLowerCase().includes(ql) ||
        item.email?.toLowerCase().includes(ql) ||
        item.reference?.toLowerCase().includes(ql)
      ).slice(0, 5);

    searchResults.value = { invoices, quotes, customers };
  } catch {
    /* silently fail — empty state stays */
  } finally {
    searchLoading.value = false;
  }
}

function goToResult(type, item) {
  searchTerm.value    = "";
  searchOpen.value    = false;
  searchResults.value = { invoices: [], quotes: [], customers: [] };
  mobileSearch.value  = false;
  if (type === "invoice")  router.push({ name: "backend-edit-invoice",  params: { id: item.uuid } });
  if (type === "quote")    router.push({ name: "backend-edit-quote",    params: { id: item.uuid } });
  if (type === "customer") router.push({ name: "backend-show-customer", params: { id: item.uuid } });
}

function submitSearch() {
  // On Enter: navigate to first available result
  const r = searchResults.value;
  if (r.invoices[0])  { goToResult("invoice",  r.invoices[0]);  return; }
  if (r.quotes[0])    { goToResult("quote",    r.quotes[0]);    return; }
  if (r.customers[0]) { goToResult("customer", r.customers[0]); return; }
}

function clearSearch() {
  searchTerm.value    = "";
  searchOpen.value    = false;
  searchResults.value = { invoices: [], quotes: [], customers: [] };
  searchRef.value?.focus();
}

// ── Profile dropdown ──────────────────────────────────────────
const profileOpen    = ref(false);
const profileBtnRef  = ref(null);
const profileMenuRef = ref(null);

function toggleProfile() { profileOpen.value = !profileOpen.value; }
function closeProfile()  { profileOpen.value = false; }

function handleDocClick(e) {
  // Close profile dropdown
  if (profileOpen.value) {
    const clickedBtn  = profileBtnRef.value?.contains(e.target);
    const clickedMenu = profileMenuRef.value?.contains(e.target);
    if (!clickedBtn && !clickedMenu) closeProfile();
  }
  // Close search results
  if (searchOpen.value && !searchWrapRef.value?.contains(e.target)) {
    searchOpen.value = false;
  }
}

// ── Dark mode ─────────────────────────────────────────────────
const isDark = computed(() => store.settings.darkMode);
function toggleDarkMode() {
  store.darkMode({ mode: "toggle" });
}

// ── Plan badge ────────────────────────────────────────────────
const planBadge = computed(() => {
  const s = store.billing.subscriptionStatus;
  if (!s || s === "incomplete" || s === "canceled")
    return { label: t('header.plan.free'), cls: "hdr-badge--free" };
  if (s === "trialing") {
    const d = store.billing.trialDaysLeft;
    return { label: d ? t('header.plan.trialDays', { days: d }) : t('header.plan.trial'), cls: "hdr-badge--trial" };
  }
  if (s === "active")    return { label: t('header.plan.pro'),     cls: "hdr-badge--pro" };
  if (s === "past_due")  return { label: t('header.plan.pastDue'), cls: "hdr-badge--warn" };
  return { label: s, cls: "hdr-badge--free" };
});

// ── Page title breadcrumb ─────────────────────────────────────
const pageMeta = computed(() => {
  const bc   = t('header.breadcrumb.dashboard');
  const inv  = t('header.breadcrumb.invoices');
  const qt   = t('header.breadcrumb.quotes');
  const cust = t('header.breadcrumb.customers');
  const prod = t('header.breadcrumb.products');
  const metaMap = {
    "backend-dashboard":       { crumb: [],             title: t('header.pageTitle.dashboard') },
    "backend-invoices":        { crumb: [bc],            title: t('header.pageTitle.invoices') },
    "backend-create-invoice":  { crumb: [bc, inv],       title: t('header.pageTitle.newInvoice') },
    "backend-edit-invoice":    { crumb: [bc, inv],       title: t('header.pageTitle.editInvoice') },
    "backend-quotes":          { crumb: [bc],            title: t('header.pageTitle.quotes') },
    "backend-create-quote":    { crumb: [bc, qt],        title: t('header.pageTitle.newQuote') },
    "backend-edit-quote":      { crumb: [bc, qt],        title: t('header.pageTitle.editQuote') },
    "backend-customers":       { crumb: [bc],            title: t('header.pageTitle.customers') },
    "backend-create-customer": { crumb: [bc, cust],      title: t('header.pageTitle.newCustomer') },
    "backend-show-customer":   { crumb: [bc, cust],      title: t('header.pageTitle.customer') },
    "backend-edit-customer":   { crumb: [bc, cust],      title: t('header.pageTitle.editCustomer') },
    "backend-items":           { crumb: [bc],            title: t('header.pageTitle.products') },
    "backend-create-item":     { crumb: [bc, prod],      title: t('header.pageTitle.newProduct') },
    "backend-edit-item":       { crumb: [bc, prod],      title: t('header.pageTitle.editProduct') },
    "backend-payments":        { crumb: [bc],            title: t('header.pageTitle.payments') },
    "backend-profile":         { crumb: [bc],            title: t('header.pageTitle.myProfile') },
    "backend-settings":        { crumb: [bc],            title: t('header.pageTitle.settings') },
    "backend-templates":       { crumb: [bc],            title: t('header.pageTitle.templates') },
    "backend-subscription":    { crumb: [bc],            title: t('header.pageTitle.subscription') },
  };
  return metaMap[route.name] ?? { crumb: [], title: "Fakturalista" };
});

// ── Keyboard shortcuts ────────────────────────────────────────
function handleKeydown(e) {
  if (e.key === "Escape") {
    if (searchOpen.value) { searchOpen.value = false; return; }
    profileOpen.value  = false;
    mobileSearch.value = false;
    return;
  }
  if ((e.metaKey || e.ctrlKey) && e.key === "k") {
    e.preventDefault();
    mobileSearch.value = false;
    searchRef.value?.focus();
  }
}

onMounted(() => {
  document.addEventListener("keydown", handleKeydown);
  document.addEventListener("click",   handleDocClick);
  window.addEventListener("user-profile-updated", handleUserProfileUpdated);
  loadUser();
});

onUnmounted(() => {
  clearTimeout(searchTimer);
  document.removeEventListener("keydown", handleKeydown);
  document.removeEventListener("click",   handleDocClick);
  window.removeEventListener("user-profile-updated", handleUserProfileUpdated);
});
</script>


<template>
  <header id="page-header" class="hdr-root">

    <!-- ── MAIN BAR ─────────────────────────────────────────── -->
    <div class="hdr-bar">

      <!-- LEFT: toggle + page title -->
      <div class="hdr-left">

        <button
          class="hdr-icon-btn d-lg-none"
          @click="store.sidebar({ mode: 'toggle' })"
          :aria-label="$t('header.openMenu')"
        >
          <i class="fa fa-bars"></i>
        </button>

        <button
          class="hdr-icon-btn d-none d-lg-flex"
          @click="store.sidebarMini({ mode: 'toggle' })"
          :aria-label="$t('header.toggleSidebar')"
        >
          <i class="fa fa-bars"></i>
        </button>

        <div class="hdr-page-meta">
          <div class="hdr-breadcrumb" v-if="pageMeta.crumb.length">
            <template v-for="(crumb, i) in pageMeta.crumb" :key="i">
              <span class="hdr-crumb">{{ crumb }}</span>
              <span class="hdr-crumb-sep">/</span>
            </template>
          </div>
          <h1 class="hdr-page-title">{{ pageMeta.title }}</h1>
        </div>
      </div>

      <!-- CENTER: search -->
      <div class="hdr-center">
        <div class="hdr-search-wrap" ref="searchWrapRef">
          <i class="fa fa-search hdr-search-icon-left"></i>
          <input
            ref="searchRef"
            type="text"
            class="hdr-search-input"
            :placeholder="$t('header.searchPlaceholderFull')"
            v-model="searchTerm"
            @keydown.enter.prevent="submitSearch"
            @focus="searchResults.invoices.length || searchResults.quotes.length || searchResults.customers.length ? (searchOpen = true) : null"
            autocomplete="off"
          />
          <span v-if="searchLoading" class="hdr-search-spin" aria-hidden="true">
            <i class="fa fa-circle-notch fa-spin"></i>
          </span>
          <button
            v-else-if="searchTerm"
            class="hdr-search-clear"
            @click="clearSearch"
            type="button"
            :aria-label="$t('header.clearSearch')"
          >
            <i class="fa fa-times"></i>
          </button>
          <span class="hdr-search-kbd" v-else>⌘K</span>

          <!-- Results dropdown -->
          <Transition name="hdr-sr">
            <div
              v-if="searchOpen"
              class="hdr-sr-dropdown"
              @click.stop
            >
              <!-- Loading state -->
              <div v-if="searchLoading" class="hdr-sr-state">
                <i class="fa fa-circle-notch fa-spin hdr-sr-spin-icon"></i>
                {{ $t('header.searching') }}
              </div>

              <!-- Empty state -->
              <div v-else-if="!hasResults" class="hdr-sr-state hdr-sr-empty">
                <i class="fa fa-search hdr-sr-empty-icon"></i>
                {{ $t('header.noResultsFor') }} "<strong>{{ searchTerm }}</strong>"
              </div>

              <!-- Results -->
              <template v-else>

                <!-- Invoices group -->
                <template v-if="searchResults.invoices.length">
                  <div class="hdr-sr-group">{{ $t('header.searchInvoices') }}</div>
                  <button
                    v-for="item in searchResults.invoices"
                    :key="'inv-' + item.uuid"
                    class="hdr-sr-item"
                    @click="goToResult('invoice', item)"
                    type="button"
                  >
                    <span class="hdr-sr-icon hdr-sr-icon--inv">
                      <i class="fa fa-file-invoice"></i>
                    </span>
                    <span class="hdr-sr-body">
                      <span class="hdr-sr-ref">{{ item.reference }}</span>
                      <span class="hdr-sr-sub">{{ item.customer }}</span>
                    </span>
                    <span class="hdr-sr-pill" :class="'hdr-sr-pill--' + item.status">
                      {{ item.status }}
                    </span>
                  </button>
                </template>

                <!-- Quotes group -->
                <template v-if="searchResults.quotes.length">
                  <div
                    class="hdr-sr-group"
                    :class="{ 'hdr-sr-group--gap': searchResults.invoices.length }"
                  >
                    {{ $t('header.searchQuotes') }}
                  </div>
                  <button
                    v-for="item in searchResults.quotes"
                    :key="'qt-' + item.uuid"
                    class="hdr-sr-item"
                    @click="goToResult('quote', item)"
                    type="button"
                  >
                    <span class="hdr-sr-icon hdr-sr-icon--qt">
                      <i class="fa fa-file-alt"></i>
                    </span>
                    <span class="hdr-sr-body">
                      <span class="hdr-sr-ref">{{ item.reference }}</span>
                      <span class="hdr-sr-sub">{{ item.customer }}</span>
                    </span>
                    <span class="hdr-sr-pill" :class="'hdr-sr-pill--' + item.status">
                      {{ item.status }}
                    </span>
                  </button>
                </template>

                <!-- Customers group -->
                <template v-if="searchResults.customers.length">
                  <div
                    class="hdr-sr-group"
                    :class="{ 'hdr-sr-group--gap': searchResults.invoices.length || searchResults.quotes.length }"
                  >
                    {{ $t('header.searchCustomers') }}
                  </div>
                  <button
                    v-for="item in searchResults.customers"
                    :key="'cu-' + item.uuid"
                    class="hdr-sr-item"
                    @click="goToResult('customer', item)"
                    type="button"
                  >
                    <span class="hdr-sr-icon hdr-sr-icon--cu">
                      <i class="fa fa-user"></i>
                    </span>
                    <span class="hdr-sr-body">
                      <span class="hdr-sr-ref">{{ item.name }}</span>
                      <span class="hdr-sr-sub">{{ item.email || item.reference }}</span>
                    </span>
                  </button>
                </template>

              </template>
            </div>
          </Transition>
        </div>
      </div>

      <!-- RIGHT: actions + profile -->
      <div class="hdr-right">

        <!-- Mobile search toggle -->
        <button
          class="hdr-icon-btn d-md-none"
          @click="mobileSearch = !mobileSearch"
          aria-label="Search"
        >
          <i class="fa fa-search"></i>
        </button>

        <!-- New Invoice (medium+) -->
        <router-link
          :to="{ name: 'backend-create-invoice' }"
          class="hdr-action-btn hdr-action-btn--primary d-none d-md-inline-flex"
        >
          <i class="fa fa-plus hdr-action-icon"></i>
          <span>{{ $t('header.newInvoiceBtn') }}</span>
        </router-link>

        <!-- New Quote (large+) -->
        <router-link
          :to="{ name: 'backend-create-quote' }"
          class="hdr-action-btn hdr-action-btn--ghost d-none d-lg-inline-flex"
        >
          <i class="fa fa-plus hdr-action-icon"></i>
          <span>{{ $t('header.newQuoteBtn') }}</span>
        </router-link>

        <!-- Settings shortcut (medium+) -->
        <router-link
          :to="{ name: 'backend-settings' }"
          class="hdr-icon-btn d-none d-md-flex"
          aria-label="Settings"
        >
          <i class="fa fa-cog"></i>
        </router-link>

        <!-- Profile -->
        <div class="hdr-profile-wrap">
          <button
            ref="profileBtnRef"
            class="hdr-profile-btn"
            :class="{ 'hdr-profile-btn--open': profileOpen }"
            @click.stop="toggleProfile"
            aria-label="User menu"
            :aria-expanded="profileOpen"
          >
            <img
              v-if="hasCustomAvatar"
              :src="userAvatar"
              class="hdr-profile-img"
              alt="Avatar"
            />
            <span v-else class="hdr-profile-initials">
              {{ avatarInitials(userDisplayName) }}
            </span>
            <i
              class="fa fa-angle-down hdr-profile-chevron"
              :class="{ 'hdr-profile-chevron--open': profileOpen }"
            ></i>
          </button>

          <!-- DROPDOWN ───────────────────────────────────────── -->
          <Transition name="hdr-dd">
            <div
              ref="profileMenuRef"
              class="hdr-dropdown"
              v-show="profileOpen"
              @click.stop
            >

              <!-- User card -->
              <div class="hdr-dd-card">
                <div class="hdr-dd-av-wrap">
                  <img
                    v-if="hasCustomAvatar"
                    :src="userAvatar"
                    class="hdr-dd-av-img"
                    alt="Avatar"
                  />
                  <div v-else class="hdr-dd-av-initials">
                    {{ avatarInitials(userDisplayName) }}
                  </div>
                </div>
                <div class="hdr-dd-user-info">
                  <p class="hdr-dd-name">{{ userDisplayName }}</p>
                  <p class="hdr-dd-email">{{ userEmail || '—' }}</p>
                  <span class="hdr-badge" :class="planBadge.cls">
                    {{ planBadge.label }}
                  </span>
                </div>
              </div>

              <div class="hdr-dd-sep"></div>

              <!-- Nav items -->
              <div class="hdr-dd-section">
                <RouterLink
                  :to="{ name: 'backend-profile' }"
                  class="hdr-dd-item"
                  @click="closeProfile"
                >
                  <span class="hdr-dd-item-emoji">👤</span>
                  <span>{{ $t('menu.profile') }}</span>
                </RouterLink>

                <RouterLink
                  :to="{ name: 'backend-settings' }"
                  class="hdr-dd-item"
                  @click="closeProfile"
                >
                  <span class="hdr-dd-item-emoji">🏢</span>
                  <span>{{ $t('header.companySettings') }}</span>
                </RouterLink>

                <RouterLink
                  :to="{ name: 'backend-subscription' }"
                  class="hdr-dd-item"
                  @click="closeProfile"
                >
                  <span class="hdr-dd-item-emoji">💳</span>
                  <span>{{ $t('menu.subscription') }}</span>
                </RouterLink>

                <RouterLink
                  :to="{ name: 'backend-templates' }"
                  class="hdr-dd-item"
                  @click="closeProfile"
                >
                  <span class="hdr-dd-item-emoji">🎨</span>
                  <span>{{ $t('menu.templates') }}</span>
                </RouterLink>
              </div>

              <div class="hdr-dd-sep"></div>

              <div class="hdr-dd-section">
                <!-- Dark mode toggle -->
                <button
                  class="hdr-dd-item hdr-dd-item--btn"
                  @click="toggleDarkMode"
                  type="button"
                >
                  <span class="hdr-dd-item-emoji">{{ isDark ? '☀️' : '🌙' }}</span>
                  <span>{{ isDark ? $t('header.lightMode') : $t('header.darkMode') }}</span>
                  <div
                    class="hdr-toggle"
                    :class="{ 'hdr-toggle--on': isDark }"
                    aria-hidden="true"
                  >
                    <div class="hdr-toggle-knob"></div>
                  </div>
                </button>
              </div>

              <div class="hdr-dd-sep"></div>

              <div class="hdr-dd-section hdr-dd-section--last">
                <RouterLink
                  :to="{ name: 'auth-signout' }"
                  class="hdr-dd-item hdr-dd-item--danger"
                  @click="closeProfile"
                >
                  <span class="hdr-dd-item-emoji">🚪</span>
                  <span>{{ $t('menu.logout') }}</span>
                </RouterLink>
              </div>

            </div>
          </Transition>
        </div>

      </div>
    </div>

    <!-- Mobile search bar (slides down) -->
    <Transition name="hdr-msearch">
      <div class="hdr-mobile-search" v-show="mobileSearch">
        <i class="fa fa-search hdr-search-icon-left"></i>
        <input
          type="text"
          class="hdr-search-input"
          :placeholder="$t('header.searchPlaceholderFull')"
          v-model="searchTerm"
          @keydown.enter.prevent="submitSearch"
          autocomplete="off"
        />
        <button class="hdr-icon-btn" @click="mobileSearch = false" type="button">
          <i class="fa fa-times"></i>
        </button>
      </div>
    </Transition>

    <!-- Header loader -->
    <div class="hdr-loader-bar" v-show="store.settings.headerLoader">
      <div class="hdr-loader-fill"></div>
    </div>

  </header>
</template>


<style scoped>
/* ═══════════════════════════════════════════════════════════════
   Header — all styles scoped; uses :global(.dark-mode) for dark
   ═══════════════════════════════════════════════════════════════ */

/* ── Tokens: light ─────────────────────────────────────────── */
.hdr-root {
  --hdr-h:          64px;
  --hdr-bg:         rgba(255, 255, 255, 0.88);
  --hdr-border:     rgba(0, 0, 0, 0.07);
  --hdr-shadow:     0 1px 0 rgba(0,0,0,.06), 0 2px 12px rgba(0,0,0,.04);
  --hdr-text:       #0f1117;
  --hdr-text-2:     #445066;
  --hdr-text-3:     #8b96a8;
  --hdr-surface:    #ffffff;
  --hdr-surface-2:  #f6f8fa;
  --hdr-border-2:   #e1e5ea;
  --hdr-pink:       #E91E63;
  --hdr-pink-dk:    #c2185b;
  --hdr-pink-bg:    rgba(233, 30, 99, 0.08);
  --hdr-hover:      rgba(0, 0, 0, 0.04);
  --hdr-icon-btn-bg:rgba(0, 0, 0, 0.0);
  --hdr-ease:       cubic-bezier(0.4, 0, 0.2, 1);
  --hdr-shadow-dd:  0 8px 30px rgba(0,0,0,.12), 0 2px 8px rgba(0,0,0,.07);
  --hdr-radius-dd:  14px;
}

/* ── Tokens: dark (OneUI's .dark-mode on #page-container) ─── */
:global(.dark-mode) .hdr-root,
:global(.page-header-dark) .hdr-root {
  --hdr-bg:         rgba(18, 22, 30, 0.92);
  --hdr-border:     rgba(255, 255, 255, 0.07);
  --hdr-shadow:     0 1px 0 rgba(0,0,0,.3), 0 2px 12px rgba(0,0,0,.2);
  --hdr-text:       #e6edf3;
  --hdr-text-2:     #8b949e;
  --hdr-text-3:     #606b79;
  --hdr-surface:    #1c2230;
  --hdr-surface-2:  #252d3d;
  --hdr-border-2:   #2e3a4e;
  --hdr-pink-bg:    rgba(233, 30, 99, 0.14);
  --hdr-hover:      rgba(255, 255, 255, 0.06);
  --hdr-icon-btn-bg:rgba(255, 255, 255, 0.0);
  --hdr-shadow-dd:  0 8px 30px rgba(0,0,0,.4), 0 2px 8px rgba(0,0,0,.25);
}

/* System dark preference */
@media (prefers-color-scheme: dark) {
  .hdr-root {
    --hdr-bg:         rgba(18, 22, 30, 0.92);
    --hdr-border:     rgba(255, 255, 255, 0.07);
    --hdr-shadow:     0 1px 0 rgba(0,0,0,.3), 0 2px 12px rgba(0,0,0,.2);
    --hdr-text:       #e6edf3;
    --hdr-text-2:     #8b949e;
    --hdr-text-3:     #606b79;
    --hdr-surface:    #1c2230;
    --hdr-surface-2:  #252d3d;
    --hdr-border-2:   #2e3a4e;
    --hdr-pink-bg:    rgba(233, 30, 99, 0.14);
    --hdr-hover:      rgba(255, 255, 255, 0.06);
    --hdr-icon-btn-bg:rgba(255, 255, 255, 0.0);
    --hdr-shadow-dd:  0 8px 30px rgba(0,0,0,.4), 0 2px 8px rgba(0,0,0,.25);
  }
}

/* ── Override OneUI #page-header global styles ─────────────── */
#page-header {
  background: var(--hdr-bg) !important;
  backdrop-filter: blur(14px) saturate(1.6) !important;
  -webkit-backdrop-filter: blur(14px) saturate(1.6) !important;
  border-bottom: 1px solid var(--hdr-border) !important;
  box-shadow: var(--hdr-shadow) !important;
  min-height: var(--hdr-h) !important;
  padding: 0 !important;
}

/* ── Main bar layout ─────────────────────────────────────────── */
.hdr-bar {
  display: flex;
  align-items: center;
  height: var(--hdr-h);
  padding: 0 20px;
  gap: 12px;
}

/* ── LEFT ────────────────────────────────────────────────────── */
.hdr-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
  min-width: 0;
}

.hdr-page-meta {
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
}

.hdr-breadcrumb {
  display: flex;
  align-items: center;
  gap: 4px;
  line-height: 1;
  margin-bottom: 1px;
}

.hdr-crumb {
  font-size: 10.5px;
  font-weight: 500;
  color: var(--hdr-text-3);
  white-space: nowrap;
}

.hdr-crumb-sep {
  font-size: 10px;
  color: var(--hdr-text-3);
  opacity: 0.5;
}

.hdr-page-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--hdr-text);
  letter-spacing: -0.02em;
  margin: 0;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 200px;
}

/* ── CENTER: search ──────────────────────────────────────────── */
.hdr-center {
  flex: 1;
  display: flex;
  justify-content: center;
  padding: 0 12px;
  max-width: 560px;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .hdr-center { display: none; }
}

.hdr-search-wrap {
  position: relative;
  width: 100%;
  display: flex;
  align-items: center;
}

.hdr-search-icon-left {
  position: absolute;
  left: 12px;
  font-size: 13px;
  color: var(--hdr-text-3);
  pointer-events: none;
  z-index: 1;
}

/* Fix pink-at-rest: pin border and shadow to neutral, override any global input styles */
.hdr-search-input {
  width: 100%;
  height: 36px;
  padding: 0 80px 0 36px;
  background: var(--hdr-surface-2);
  border: 1px solid var(--hdr-border-2) !important;
  border-radius: 10px;
  font-size: 13px;
  color: var(--hdr-text);
  outline: none !important;
  box-shadow: none !important;
  transition: border-color 0.16s var(--hdr-ease),
              box-shadow 0.16s var(--hdr-ease),
              background 0.16s var(--hdr-ease);
}

.hdr-search-input::placeholder { color: var(--hdr-text-3); }

.hdr-search-input:focus {
  border-color: var(--hdr-pink) !important;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.12) !important;
  background: var(--hdr-surface);
  outline: none !important;
}

.hdr-search-spin,
.hdr-search-clear,
.hdr-search-kbd {
  position: absolute;
  right: 10px;
}

.hdr-search-spin {
  font-size: 12px;
  color: var(--hdr-text-3);
  pointer-events: none;
}

.hdr-search-clear {
  background: none;
  border: none;
  padding: 4px 6px;
  cursor: pointer;
  color: var(--hdr-text-3);
  font-size: 11px;
  border-radius: 4px;
  transition: color 0.14s, background 0.14s;
}
.hdr-search-clear:hover { color: var(--hdr-text); background: var(--hdr-hover); }

.hdr-search-kbd {
  font-size: 10px;
  font-weight: 600;
  color: var(--hdr-text-3);
  background: var(--hdr-surface);
  border: 1px solid var(--hdr-border-2);
  border-radius: 5px;
  padding: 2px 6px;
  pointer-events: none;
  letter-spacing: 0.02em;
}

/* ── Search results dropdown ─────────────────────────────────── */
.hdr-sr-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  background: var(--hdr-surface);
  border: 1px solid var(--hdr-border-2);
  border-radius: 12px;
  box-shadow: var(--hdr-shadow-dd);
  overflow: hidden;
  z-index: 9998;
  max-height: 420px;
  overflow-y: auto;
}

.hdr-sr-state {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 14px;
  font-size: 13px;
  color: var(--hdr-text-3);
}

.hdr-sr-spin-icon { color: var(--hdr-pink); }

.hdr-sr-empty { flex-direction: column; gap: 6px; align-items: center; padding: 24px 14px; text-align: center; }
.hdr-sr-empty-icon { font-size: 20px; opacity: 0.3; margin-bottom: 4px; }
.hdr-sr-empty strong { color: var(--hdr-text-2); font-weight: 600; }

.hdr-sr-group {
  padding: 8px 14px 4px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--hdr-text-3);
}
.hdr-sr-group--gap { border-top: 1px solid var(--hdr-border-2); padding-top: 10px; margin-top: 4px; }

.hdr-sr-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 14px;
  background: none;
  border: none;
  text-align: left;
  cursor: pointer;
  transition: background 0.12s var(--hdr-ease);
}
.hdr-sr-item:hover { background: var(--hdr-hover); }

.hdr-sr-icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  flex-shrink: 0;
}
.hdr-sr-icon--inv { background: rgba(233, 30, 99, 0.10); color: var(--hdr-pink); }
.hdr-sr-icon--qt  { background: rgba(99, 102, 241, 0.10); color: #6366f1; }
.hdr-sr-icon--cu  { background: rgba(16, 185, 129, 0.10); color: #10b981; }

.hdr-sr-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.hdr-sr-ref {
  font-size: 13px;
  font-weight: 600;
  color: var(--hdr-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.hdr-sr-sub {
  font-size: 11px;
  color: var(--hdr-text-3);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.hdr-sr-pill {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 999px;
  flex-shrink: 0;
}
.hdr-sr-pill--draft     { background: rgba(139,150,168,.15); color: #6b7280; }
.hdr-sr-pill--issued    { background: rgba(59,130,246,.12);  color: #2563eb; }
.hdr-sr-pill--paid      { background: rgba(16,185,129,.12);  color: #059669; }
.hdr-sr-pill--cancelled { background: rgba(239,68,68,.12);   color: #dc2626; }
.hdr-sr-pill--accepted  { background: rgba(16,185,129,.12);  color: #059669; }
.hdr-sr-pill--refused   { background: rgba(239,68,68,.12);   color: #dc2626; }
.hdr-sr-pill--sent      { background: rgba(245,158,11,.12);  color: #d97706; }

/* Dropdown enter/leave */
.hdr-sr-enter-active { animation: hdr-sr-open 0.16s var(--hdr-ease); }
.hdr-sr-leave-active { animation: hdr-sr-open 0.12s var(--hdr-ease) reverse; }

@keyframes hdr-sr-open {
  from { opacity: 0; transform: translateY(-6px) scale(0.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ── RIGHT ───────────────────────────────────────────────────── */
.hdr-right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  margin-left: auto;
}

/* ── Icon button ─────────────────────────────────────────────── */
.hdr-icon-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: transparent;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  color: var(--hdr-text-2);
  transition: background 0.14s var(--hdr-ease), color 0.14s var(--hdr-ease);
  text-decoration: none;
  flex-shrink: 0;
}
.hdr-icon-btn:hover {
  background: var(--hdr-hover);
  color: var(--hdr-text);
  text-decoration: none;
}

/* ── Action buttons ──────────────────────────────────────────── */
.hdr-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 9px;
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: background 0.16s var(--hdr-ease),
              color 0.16s var(--hdr-ease),
              box-shadow 0.16s var(--hdr-ease),
              transform 0.13s var(--hdr-ease),
              border-color 0.16s var(--hdr-ease);
  white-space: nowrap;
  line-height: 1;
}

.hdr-action-btn--primary {
  background: var(--hdr-pink);
  color: #fff;
}
.hdr-action-btn--primary:hover {
  background: var(--hdr-pink-dk);
  color: #fff;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
  text-decoration: none;
}

.hdr-action-btn--ghost {
  background: var(--hdr-surface);
  color: var(--hdr-text-2);
  border: 1px solid var(--hdr-border-2);
}
.hdr-action-btn--ghost:hover {
  border-color: var(--hdr-pink);
  color: var(--hdr-pink);
  background: var(--hdr-pink-bg);
  text-decoration: none;
}

.hdr-action-icon { font-size: 10px; }

/* ── Profile button ──────────────────────────────────────────── */
.hdr-profile-wrap { position: relative; }

.hdr-profile-btn {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 4px 8px 4px 4px;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s var(--hdr-ease),
              border-color 0.15s var(--hdr-ease);
}
.hdr-profile-btn:hover,
.hdr-profile-btn--open {
  background: var(--hdr-hover);
  border-color: var(--hdr-border-2);
}

.hdr-profile-img {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
}

.hdr-profile-initials {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--hdr-pink-bg);
  color: var(--hdr-pink);
  font-size: 10px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.hdr-profile-chevron {
  font-size: 11px;
  color: var(--hdr-text-3);
  transition: transform 0.2s var(--hdr-ease);
}
.hdr-profile-chevron--open { transform: rotate(180deg); }

/* ── Dropdown ────────────────────────────────────────────────── */
.hdr-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 268px;
  background: var(--hdr-surface);
  border: 1px solid var(--hdr-border-2);
  border-radius: var(--hdr-radius-dd);
  box-shadow: var(--hdr-shadow-dd);
  overflow: hidden;
  z-index: 9999;
}

/* ── Dropdown: user card ─────────────────────────────────────── */
.hdr-dd-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: var(--hdr-surface-2);
}

.hdr-dd-av-wrap { flex-shrink: 0; }

.hdr-dd-av-img {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
  border: 2px solid var(--hdr-border-2);
}

.hdr-dd-av-initials {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--hdr-pink-bg);
  color: var(--hdr-pink);
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--hdr-border-2);
}

.hdr-dd-user-info { flex: 1; min-width: 0; }

.hdr-dd-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--hdr-text);
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.hdr-dd-email {
  font-size: 11px;
  color: var(--hdr-text-3);
  margin: 0 0 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Plan badge ──────────────────────────────────────────────── */
.hdr-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 9px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.hdr-badge--free  { background: rgba(139,150,168,.13); color: var(--hdr-text-2); }
.hdr-badge--trial { background: rgba(245,158,11,.12);  color: #d97706; }
.hdr-badge--pro   { background: rgba(16,185,129,.12);  color: #059669; }
.hdr-badge--warn  { background: rgba(239,68,68,.12);   color: #dc2626; }

/* ── Dropdown: separator ─────────────────────────────────────── */
.hdr-dd-sep {
  height: 1px;
  background: var(--hdr-border-2);
  margin: 0;
}

/* ── Dropdown: menu section ──────────────────────────────────── */
.hdr-dd-section {
  padding: 5px 0;
}

.hdr-dd-section--last {
  padding: 4px 0;
}

.hdr-dd-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 14px;
  font-size: 13px;
  font-weight: 500;
  color: var(--hdr-text);
  text-decoration: none;
  cursor: pointer;
  transition: background 0.12s var(--hdr-ease), color 0.12s var(--hdr-ease);
  width: 100%;
  text-align: left;
}
.hdr-dd-item:hover {
  background: var(--hdr-hover);
  color: var(--hdr-text);
  text-decoration: none;
}

.hdr-dd-item--btn {
  background: none;
  border: none;
  width: 100%;
}

.hdr-dd-item--danger { color: #dc2626; }
.hdr-dd-item--danger:hover { background: rgba(220, 38, 38, 0.07); color: #dc2626; }

.hdr-dd-item-emoji {
  font-size: 14px;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

/* ── Dark mode toggle switch ─────────────────────────────────── */
.hdr-toggle {
  width: 32px;
  height: 18px;
  border-radius: 9px;
  background: var(--hdr-border-2);
  position: relative;
  margin-left: auto;
  flex-shrink: 0;
  transition: background 0.2s var(--hdr-ease);
}
.hdr-toggle--on { background: var(--hdr-pink); }

.hdr-toggle-knob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,.2);
  transition: transform 0.2s var(--hdr-ease);
}
.hdr-toggle--on .hdr-toggle-knob { transform: translateX(14px); }

/* ── Mobile search bar ───────────────────────────────────────── */
.hdr-mobile-search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-top: 1px solid var(--hdr-border);
  background: var(--hdr-bg);
  position: relative;
}

.hdr-mobile-search .hdr-search-input {
  flex: 1;
  padding-left: 32px;
}

/* ── Header loader bar ───────────────────────────────────────── */
.hdr-loader-bar {
  height: 2px;
  background: var(--hdr-border-2);
  overflow: hidden;
}
.hdr-loader-fill {
  height: 100%;
  width: 40%;
  background: var(--hdr-pink);
  animation: hdr-load 1.2s ease infinite;
}

@keyframes hdr-load {
  0%   { transform: translateX(-150%); }
  100% { transform: translateX(350%); }
}

/* ── Dropdown transition ─────────────────────────────────────── */
.hdr-dd-enter-active {
  animation: hdr-dd-open 0.18s var(--hdr-ease);
}
.hdr-dd-leave-active {
  animation: hdr-dd-open 0.13s var(--hdr-ease) reverse;
}

@keyframes hdr-dd-open {
  from {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* ── Mobile search transition ────────────────────────────────── */
.hdr-msearch-enter-active,
.hdr-msearch-leave-active {
  transition: opacity 0.18s var(--hdr-ease), transform 0.18s var(--hdr-ease);
}
.hdr-msearch-enter-from,
.hdr-msearch-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* ── Reduced motion ──────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
  .hdr-action-btn,
  .hdr-icon-btn,
  .hdr-profile-btn,
  .hdr-profile-chevron,
  .hdr-toggle,
  .hdr-toggle-knob,
  .hdr-search-input { transition: none; }
  .hdr-dd-enter-active,
  .hdr-dd-leave-active,
  .hdr-msearch-enter-active,
  .hdr-msearch-leave-active,
  .hdr-sr-enter-active,
  .hdr-sr-leave-active { animation: none; transition: none; }
  .hdr-loader-fill { animation: none; }
}
</style>
