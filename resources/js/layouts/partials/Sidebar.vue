<script setup>
import { ref, computed, onMounted, nextTick } from "vue";
import { useRoute } from "vue-router";
import { useTemplateStore } from "@/stores/template";
import { useI18n } from "vue-i18n";
import SimpleBar from "simplebar";

defineProps({
  withMiniNav: { type: Boolean, default: false },
});

const store = useTemplateStore();
const route  = useRoute();
const { t }  = useI18n();

// ── Navigation sections ───────────────────────────────────────────

const mainNav = [
  { to: "backend-dashboard", i18nKey: "nav.dashboard", icon: "fa fa-house" },
  { to: "backend-customers", i18nKey: "nav.customers", icon: "fa fa-users" },
  { to: "backend-invoices",  i18nKey: "nav.invoices",  icon: "fa fa-file-invoice" },
  { to: "backend-quotes",    i18nKey: "nav.quotes",    icon: "fa fa-pen-to-square" },
];

const businessNav = [
  { to: "backend-payments",  i18nKey: "nav.payments",  icon: "fa fa-credit-card" },
  { to: "backend-dashboard", i18nKey: "nav.reports",   icon: "fa fa-chart-bar", placeholder: true },
];

const catalogNav = [
  { to: "backend-items",     i18nKey: "nav.items",     icon: "fa fa-box" },
  { to: "backend-templates", i18nKey: "nav.templates", icon: "fa fa-layer-group" },
];

const bottomNav = [
  { to: "backend-settings",  i18nKey: "nav.settings",  icon: "fa fa-gear" },
];

// Maps any route name to the canonical nav-item it should highlight
const ROUTE_SECTION = {
  "backend-dashboard":            "backend-dashboard",
  "backend-customers":            "backend-customers",
  "backend-pages-generic-profile":"backend-customers",
  "backend-create-customer":      "backend-customers",
  "backend-show-customer":        "backend-customers",
  "backend-edit-customer":        "backend-customers",
  "backend-invoices":             "backend-invoices",
  "backend-create-invoice":       "backend-invoices",
  "backend-edit-invoice":         "backend-invoices",
  "backend-quotes":               "backend-quotes",
  "backend-create-quote":         "backend-quotes",
  "backend-edit-quote":           "backend-quotes",
  "backend-payments":             "backend-payments",
  "backend-items":                "backend-items",
  "backend-create-item":          "backend-items",
  "backend-edit-item":            "backend-items",
  "backend-templates":            "backend-templates",
  "backend-settings":             "backend-settings",
  "backend-profile":              "backend-settings",
  "backend-subscription":         "backend-settings",
};

const activeSection = computed(() => ROUTE_SECTION[route.name] ?? null);

function isActive(item) {
  if (item.placeholder) return false;        // "Reports" is a placeholder
  return activeSection.value === item.to;
}

// ── Theme toggle ──────────────────────────────────────────────────

const isDark = computed(() => store.settings.darkMode);

function toggleTheme() {
  store.darkModeSystem({ mode: "off" });
  store.darkMode({ mode: store.settings.darkMode ? "off" : "on" });
}

// ── Close sidebar on mobile nav click ────────────────────────────

function onNavClick() {
  if (window.innerWidth < 992) {
    store.sidebar({ mode: "close" });
  }
}

// ── SimpleBar (custom scrolling) ─────────────────────────────────

onMounted(async () => {
  await nextTick();
  const el = document.getElementById("simplebar-sidebar");
  if (el) new SimpleBar(el);
});
</script>

<template>
  <nav id="sidebar" aria-label="Main Navigation">

    <!-- ══════════════════════════════════════════════════
         HEADER — Logo + controls
         ══════════════════════════════════════════════════ -->
    <div class="sk-hd">
      <RouterLink :to="{ to: '/' }" class="sk-logo" aria-label="Fakturalista home">
        <!-- Full wordmark -->
        <img
          src="/assets/logo_white.png"
          alt="Fakturalista"
          class="sk-logo-img smini-hide"
        />
      </RouterLink>

      <div class="sk-hd-actions smini-hide">
        <!-- Theme toggle -->
        <button
          type="button"
          class="sk-icon-btn"
          @click="toggleTheme"
          :aria-label="isDark ? t('theme.light') : t('theme.dark')"
        >
          <!-- Sun → switch to light -->
          <svg v-if="isDark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
               stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true">
            <path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z"/>
          </svg>
          <!-- Moon → switch to dark -->
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
               stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true">
            <path d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998z"/>
          </svg>
        </button>

        <!-- Close — mobile only -->
        <button
          type="button"
          class="sk-icon-btn d-lg-none"
          @click="store.sidebar({ mode: 'close' })"
          aria-label="Close sidebar"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
               stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true">
            <path d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    <!-- END Header -->

    <!-- ══════════════════════════════════════════════════
         SCROLLABLE NAV BODY
         ══════════════════════════════════════════════════ -->
    <div id="simplebar-sidebar" class="sk-body">

      <!-- ── MAIN ── -->
      <div class="sk-section">
        <p class="sk-sec-label smini-hide">{{ $t('nav.sections.main') }}</p>
        <RouterLink
          v-for="item in mainNav"
          :key="item.to"
          :to="{ name: item.to }"
          class="sk-link"
          :class="{ 'sk-link--on': isActive(item) }"
          @click="onNavClick"
        >
          <i :class="item.icon" class="sk-link-ico" aria-hidden="true"></i>
          <span class="sk-link-lbl smini-hide">{{ $t(item.i18nKey) }}</span>
        </RouterLink>
      </div>

      <!-- ── BUSINESS ── -->
      <div class="sk-section">
        <p class="sk-sec-label smini-hide">{{ $t('nav.sections.business') }}</p>
        <RouterLink
          v-for="item in businessNav"
          :key="item.i18nKey"
          :to="{ name: item.to }"
          class="sk-link"
          :class="{
            'sk-link--on':   isActive(item),
            'sk-link--soon': item.placeholder,
          }"
          @click="onNavClick"
        >
          <i :class="item.icon" class="sk-link-ico" aria-hidden="true"></i>
          <span class="sk-link-lbl smini-hide">{{ $t(item.i18nKey) }}</span>
          <span v-if="item.placeholder" class="sk-badge smini-hide">Soon</span>
        </RouterLink>
      </div>

      <!-- ── CATALOG ── -->
      <div class="sk-section">
        <p class="sk-sec-label smini-hide">{{ $t('nav.sections.catalog') }}</p>
        <RouterLink
          v-for="item in catalogNav"
          :key="item.to"
          :to="{ name: item.to }"
          class="sk-link"
          :class="{ 'sk-link--on': isActive(item) }"
          @click="onNavClick"
        >
          <i :class="item.icon" class="sk-link-ico" aria-hidden="true"></i>
          <span class="sk-link-lbl smini-hide">{{ $t(item.i18nKey) }}</span>
        </RouterLink>
      </div>

      <!-- ── SETTINGS (pinned at bottom) ── -->
      <div class="sk-section sk-section--bottom">
        <div class="sk-divider smini-hide"></div>
        <RouterLink
          v-for="item in bottomNav"
          :key="item.to"
          :to="{ name: item.to }"
          class="sk-link"
          :class="{ 'sk-link--on': isActive(item) }"
          @click="onNavClick"
        >
          <i :class="item.icon" class="sk-link-ico" aria-hidden="true"></i>
          <span class="sk-link-lbl smini-hide">{{ $t(item.i18nKey) }}</span>
        </RouterLink>
      </div>

    </div>
    <!-- END Nav body -->

  </nav>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════════════
   Fakturalista Premium Sidebar — 2026
   Design tokens: dark navy shell, pink accent, ultra-clean spacing.
   ═══════════════════════════════════════════════════════════════ */

/* ── Design tokens ─────────────────────────────────────────────── */
:global(#sidebar) {
  --sk-bg:          #0b0e17;
  --sk-border:      rgba(255, 255, 255, 0.065);
  --sk-text:        #6e7a96;
  --sk-text-dim:    #353a52;
  --sk-hover-bg:    rgba(255, 255, 255, 0.055);
  --sk-hover-tx:    #b8c2e0;
  --sk-active-bg:   rgba(233, 30, 99, 0.09);
  --sk-active-tx:   #ffffff;
  --sk-accent:      #E91E63;
  --sk-btn-bg:      rgba(255, 255, 255, 0.07);
  --sk-btn-hover:   rgba(255, 255, 255, 0.13);
  --sk-soon-bg:     rgba(255, 255, 255, 0.06);
  --sk-soon-tx:     #4b5575;
}

/* ── Force dark shell (always dark regardless of app theme) ─────── */
:global(#sidebar),
:global(#sidebar .content-header) {
  background-color: var(--sk-bg) !important;
  border-right: 1px solid var(--sk-border) !important;
  box-shadow: none !important;
}

/* ── Sidebar structural override (keep OneUI layout intact) ────── */
:global(#page-container.sidebar-o #sidebar),
:global(#page-container.sidebar-o-xs #sidebar) {
  background-color: var(--sk-bg) !important;
}

/* ════════════════════════════════
   HEADER
   ════════════════════════════════ */
.sk-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 64px;
  padding: 0 16px 0 22px;
  position: relative;
  flex-shrink: 0;
}

/* Gradient accent line — pink left, fades right */
.sk-hd::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(
    to right,
    var(--sk-accent) 0%,
    rgba(233, 30, 99, 0.18) 45%,
    transparent 100%
  );
}

/* Logo link */
.sk-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
  min-width: 0;
  border-radius: 8px;
  padding: 5px 6px;
  margin-left: -6px;
  transition: opacity 0.2s ease;
}

.sk-logo:hover {
  opacity: 0.75;
  text-decoration: none;
}

/* Full wordmark image */
.sk-logo-img {
  width: 122px;
  height: auto;
  display: block;
  opacity: 1;
}

/* Mini-mode typographic mark */
.sk-logo-mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: rgba(233, 30, 99, 0.13);
  color: var(--sk-accent);
  font-size: 16px;
  font-weight: 800;
  font-style: italic;
  letter-spacing: -0.5px;
  line-height: 1;
  user-select: none;
}

/* Header action buttons */
.sk-hd-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.sk-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 7px;
  border: none;
  background: var(--sk-btn-bg);
  color: var(--sk-text);
  cursor: pointer;
  transition: background 0.18s ease, color 0.18s ease;
  padding: 0;
}
.sk-icon-btn:hover {
  background: var(--sk-btn-hover);
  color: #d0d8f0;
}
.sk-icon-btn:focus-visible {
  outline: 2px solid var(--sk-accent);
  outline-offset: 2px;
}

/* ════════════════════════════════
   NAV BODY (scrollable)
   ════════════════════════════════ */
.sk-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 14px 10px 20px;
  /* SimpleBar will manage scrolling */
}

/* Override simplebar styles inside sidebar */
:global(#simplebar-sidebar) {
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

:global(#simplebar-sidebar .simplebar-content) {
  padding: 14px 10px 20px !important;
}

/* ════════════════════════════════
   SECTIONS
   ════════════════════════════════ */
.sk-section {
  margin-bottom: 22px;
}

.sk-section--bottom {
  margin-bottom: 0;
  margin-top: auto;
}

/* Section label */
.sk-sec-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--sk-text-dim);
  margin: 0 0 5px 10px;
  user-select: none;
  line-height: 1;
}

/* ════════════════════════════════
   NAV LINKS
   ════════════════════════════════ */
.sk-link {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 36px;
  padding: 0 10px;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 450;
  color: var(--sk-text);
  text-decoration: none;
  cursor: pointer;
  transition: background 0.16s ease, color 0.16s ease, box-shadow 0.16s ease;
  position: relative;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
}

.sk-link:hover {
  background: var(--sk-hover-bg);
  color: var(--sk-hover-tx);
  text-decoration: none;
}

.sk-link:focus-visible {
  outline: 2px solid var(--sk-accent);
  outline-offset: 1px;
}

/* Active state */
.sk-link--on {
  background: var(--sk-active-bg);
  color: var(--sk-active-tx);
  font-weight: 500;
  box-shadow: inset 3px 0 0 var(--sk-accent);
}
.sk-link--on:hover {
  background: rgba(233, 30, 99, 0.12);
  color: #fff;
}
.sk-link--on .sk-link-ico {
  color: var(--sk-accent);
}

/* Icon */
.sk-link-ico {
  font-size: 14px;
  width: 16px;
  text-align: center;
  flex-shrink: 0;
  transition: color 0.16s ease;
  line-height: 1;
}

/* Label */
.sk-link-lbl {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1;
}

/* Placeholder "Soon" state */
.sk-link--soon {
  opacity: 0.55;
  cursor: default;
  pointer-events: none;
}
.sk-link--soon:hover {
  background: transparent;
  color: var(--sk-text);
}

/* "Soon" badge */
.sk-badge {
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--sk-soon-tx);
  background: var(--sk-soon-bg);
  border-radius: 4px;
  padding: 2px 5px;
  flex-shrink: 0;
  line-height: 1.4;
}

/* Divider above bottom nav */
.sk-divider {
  height: 1px;
  background: var(--sk-border);
  margin: 6px 4px 10px;
}

/* ════════════════════════════════
   MINI SIDEBAR MODE
   ════════════════════════════════ */
:global(.sidebar-mini #sidebar) .sk-link {
  justify-content: center;
  padding: 0;
}

:global(.sidebar-mini #sidebar) .sk-section {
  margin-bottom: 8px;
}

:global(.sidebar-mini #sidebar) .sk-hd {
  padding: 0 12px;
  justify-content: center;
}

/* ════════════════════════════════
   RESPONSIVE — mobile drawer
   ════════════════════════════════ */
@media (max-width: 991px) {
  .sk-hd {
    padding: 0 14px 0 16px;
  }
}

/* ════════════════════════════════
   KILL LEGACY ONEUI NAV STYLES
   inside #sidebar so they don't
   bleed through our new markup.
   ════════════════════════════════ */
:global(#sidebar .nav-main-link) {
  all: unset;
}
:global(#sidebar .nav-main-heading) {
  all: unset;
}
:global(#sidebar .content-side) {
  padding: 0 !important;
}
</style>
