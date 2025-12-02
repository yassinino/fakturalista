import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";

// You can use the following starter router instead of the default one as a clean starting point
// import router from "./router/starter";
import router from "./router";

// Template components
import BaseBlock from "@/components/BaseBlock.vue";
import BaseBackground from "@/components/BaseBackground.vue";
import BasePageHeading from "@/components/BasePageHeading.vue";
import SalesHeading from "@/views/admin/layouts/SalesHeading.vue";
import CreateDocument from "@/views/admin/layouts/documents/create.vue";
import EditDocument from "@/views/admin/layouts/documents/edit.vue";
import printDocument from "@/views/admin/layouts/documents/print.vue";
import AddProduct from "@/views/admin/items/create.vue";
import showListProduct from "@/views/admin/layouts/showListProduct.vue";

import Toaster from '@meforma/vue-toaster';
import "vue-select/dist/vue-select.css";
import 'flatpickr/dist/flatpickr.css';

// Template directives
import clickRipple from "@/directives/clickRipple";

// Bootstrap framework
import * as bootstrap from "bootstrap";
window.bootstrap = bootstrap;
import axios from 'axios'

// Craft new application
const app = createApp(App);

// Register global components
app.component("BaseBlock", BaseBlock);
app.component("BaseBackground", BaseBackground);
app.component("BasePageHeading", BasePageHeading);
app.component("SalesHeading", SalesHeading);
app.component("CreateDocument", CreateDocument);
app.component("EditDocument", EditDocument);
app.component("printDocument", printDocument);
app.component("AddProduct", AddProduct);
app.component("showListProduct", showListProduct);

// Register global directives
app.directive("click-ripple", clickRipple);

// Use Pinia and Vue Router
app.use(createPinia());
app.use(Toaster)
app.use(router);

// keep your helper as-is or use this slightly harder version
function normalizeDecimal(v, decimals = 2) {
  if (v == null || v === '') return '';
  let s = String(v).trim();

  // allow one leading minus sign
  const neg = s.startsWith('-');
  s = s.replace(/^\-/, '');

  // keep digits and separators, collapse to a single separator
  s = s.replace(/[^\d.,]/g, '');
  const i = s.search(/[.,]/);
  if (i !== -1) {
    const sep = s[i];
    const head = s.slice(0, i + 1);
    const tail = s.slice(i + 1).replace(/[.,]/g, '');
    s = head + tail;
  }

  // unify to dot for model
  s = s.replace(',', '.');

  // split & clamp decimals
  let [I = '', D = ''] = s.split('.');
  if (!I) I = '0';
  D = D.slice(0, Math.max(0, decimals));

  const model = D ? `${I}.${D}` : I;
  return (neg ? '-' : '') + model;
}

app.config.globalProperties.$normalizeDecimal = normalizeDecimal;

app.config.globalProperties.$toComma = function (value, decimals = 2) {
  if (value === null || value === undefined || value === '') return '';
  let s = String(value).replace(',', '.');
  let [i, d = ''] = s.split('.');
  d = d.slice(0, decimals);
  return d ? `${i},${d}` : i;
};

// --- Drop-in directive replacement ---
app.directive('decimal', {
  mounted(el, binding) {
    const decimals = Number(binding.value?.decimals ?? 2);

    const sanitizeView = (raw) => {
      let v = String(raw ?? '').replace(/[^\d,.\-]/g, '');
      // allow single leading minus
      v = v.replace(/(?!^)-/g, '');
      const i = v.search(/[.,]/);
      if (i !== -1) {
        const sep  = v[i];
        const head = v.slice(0, i + 1);
        const tail = v.slice(i + 1).replace(/[.,]/g, '');
        v = head + tail;
        const parts = v.split(/[.,]/);
        if (parts[1]) v = parts[0] + sep + parts[1].slice(0, decimals);
      }
      return v;
    };

    const toCommaView = (val) => {
      if (val == null || val === '') return '';
      const s = String(val);
      // already comma-like?
      if (/,/.test(s) && !/\./.test(s)) return sanitizeView(s);
      // otherwise normalize then swap dot → comma
      const norm = normalizeDecimal(s, decimals);
      return norm.replace('.', ',');
    };

    // soft typing: fix junk, keep comma, don't push to model
    const onInputSoft = () => {
      const before = el.value;
      const view = sanitizeView(before);
      if (view !== before) {
        const pos = el.selectionStart ?? view.length;
        el.value = view;
        try { el.setSelectionRange(pos, pos); } catch {}
      }
    };

    // commit: push dot-decimal to v-model, then restore comma in UI
    const commitToModel = () => {
      const model = normalizeDecimal(el.value, decimals); // "18.23"
      el._decimalLastModel = model;
      el.value = model;
      el.dispatchEvent(new Event('input', { bubbles: true })); // update v-model

      if (model !== '') {
        queueMicrotask(() => {
          el.value = model.replace('.', ','); // visual only
        });
      }
    };

    // Initial paint: DB value → comma immediately (no model change)
    queueMicrotask(() => {
      if (el.value != null && el.value !== '') {
        el.value = toCommaView(el.value);
      }
    });

    el.addEventListener('input', onInputSoft, { passive: true });
    el.addEventListener('blur',  commitToModel);
    el.addEventListener('change', commitToModel);

    // On form submit ensure model is dot-decimal
    const form = el.closest('form');
    if (form) {
      el._decimalSubmit = commitToModel;
      form.addEventListener('submit', el._decimalSubmit);
    }

    el._decimalDestroy = () => {
      el.removeEventListener('input', onInputSoft);
      el.removeEventListener('blur',  commitToModel);
      el.removeEventListener('change', commitToModel);
      if (form && el._decimalSubmit) form.removeEventListener('submit', el._decimalSubmit);
    };
  },

  // react to external v-model changes (DB load, programmatic sets)
  updated(el, binding) {
    // don't fight the user while typing
    if (document.activeElement === el) return;

    const decimals = Number(binding.value?.decimals ?? 2);
    const norm = normalizeDecimal(el.value ?? '', decimals);

    // Only repaint if value looks like a model string or changed externally
    if (el._decimalLastModel !== norm || /\./.test(el.value)) {
      el._decimalLastModel = norm;
      el.value = norm ? norm.replace('.', ',') : '';
    }
  },

  beforeUnmount(el) {
    if (el._decimalDestroy) el._decimalDestroy();
  },
});
// ..and finally mount it!
app.mount("#app");

import { useTemplateStore } from "@/stores/template";
const store = useTemplateStore();

 


axios.defaults.baseURL = '/api/'

axios.interceptors.response.use(function (response) {
    return response
  }, function (error) {

    if (error.response.status === 401 || error.response.status === 403) {
        store.logoutUser();
        router.replace('/admin/login');
    }

    if (error.response.status === 423) {
        router.replace('/admin/login');
    }

    return Promise.reject(error)
})

console.log(store.app.accessToken)


axios.defaults.headers.common['Authorization'] = 'Bearer ' + store.app.accessToken;



router.beforeEach((to, from, next) => {
    if (to.matched.some(record => record.meta.requiresAuth) ) {
        if (store.app.isLoggedUserIn) {
            next();
            return;
        } else {
            router.replace('/admin/login');
        }
    } else {
        next();
    }
  });