import { createI18n } from "vue-i18n";

import en from "./locales/en";
import es from "./locales/es";
import fr from "./locales/fr";

const supportedLocales = ["es", "en", "fr"];
const defaultLocale = "es";

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
  },
});

const setLocale = (locale) => {
  const next = normalizeLocale(locale);
  i18n.global.locale.value = next;
  localStorage.setItem("locale", next);
  document.documentElement.setAttribute("lang", next);
};

setLocale(i18n.global.locale.value);

export { i18n, setLocale, supportedLocales };
