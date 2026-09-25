import { createI18n } from "vue-i18n";

import en from "./locales/en";
import es from "./locales/es";
import fr from "./locales/fr";
import ar from "./locales/ar";

// es stays a supported/loaded locale (never delete Spanish data), it's
// just not offered in language selectors anymore - see
// visibleLocales below, which is what pickers should render from.
const supportedLocales = ["es", "en", "fr", "ar"];
const visibleLocales = ["fr", "ar", "en"];
const rtlLocales = ["ar"];

// Morocco Phase 1A: French is the default for a fresh browser with no
// stored preference yet (e.g. first visit to /login). Once a user logs
// in, SignIn.vue/profile.vue set this from their own saved users.locale.
const defaultLocale = "fr";

const normalizeLocale = (locale) =>
  supportedLocales.includes(locale) ? locale : defaultLocale;

const getInitialLocale = () => {
  const stored = localStorage.getItem("locale");
  return normalizeLocale(stored);
};

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: getInitialLocale(),
  fallbackLocale: defaultLocale,
  messages: {
    en,
    es,
    fr,
    ar,
  },
});

const setLocale = (locale) => {
  const next = normalizeLocale(locale);
  i18n.global.locale.value = next;
  localStorage.setItem("locale", next);
  document.documentElement.setAttribute("lang", next);
  document.documentElement.setAttribute("dir", rtlLocales.includes(next) ? "rtl" : "ltr");
};

setLocale(i18n.global.locale.value);

export { i18n, setLocale, supportedLocales, visibleLocales, rtlLocales };
