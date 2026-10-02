import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createI18n } from 'vue-i18n';
import fr from '../../resources/js/i18n/locales/fr.js';
import ar from '../../resources/js/i18n/locales/ar.js';

// Invoice page quick-create (customer / product-service). Same approach as
// tax.test.mjs / portalPayment.test.mjs: real SFCs compiled and SSR-rendered.

const dir = new URL('../../resources/js/views/admin/invoices/', import.meta.url);
const toDataUrl = (code) => `data:text/javascript;base64,${Buffer.from(code).toString('base64')}`;

function compile(file, replacements = {}) {
  const { descriptor } = parse(readFileSync(new URL(file, dir), 'utf8'));
  let code = compileScript(descriptor, { id: file, inlineTemplate: true }).content;
  const map = {
    vue: import.meta.resolve('vue'),
    'vue-i18n': import.meta.resolve('vue-i18n'),
    '@vuelidate/core': import.meta.resolve('@vuelidate/core'),
    '@vuelidate/validators': import.meta.resolve('@vuelidate/validators'),
    ...replacements,
  };
  for (const [spec, target] of Object.entries(map)) {
    code = code.replaceAll(`"${spec}"`, JSON.stringify(target)).replaceAll(`'${spec}'`, JSON.stringify(target));
  }
  return code;
}

const posts = [];
const fakeAxios = toDataUrl(`export default { post: async (...a) => { globalThis.__qcPosts.push(a); return { data: {} }; }, get: async () => ({ data: {} }) };`);
globalThis.__qcPosts = posts;

const Shell = (await import(toDataUrl(compile('QuickCreateModal.vue')))).default;
const shellUrl = toDataUrl(compile('QuickCreateModal.vue'));
const fakeTenantCountry = toDataUrl(`import { computed } from ${JSON.stringify(import.meta.resolve('vue'))};
  export function useTenantCountry() { return { isMorocco: computed(() => !!globalThis.__qcMorocco) }; }`);
const QuickCustomerModal = (await import(toDataUrl(compile('QuickCustomerModal.vue', {
  axios: fakeAxios,
  './QuickCreateModal.vue': shellUrl,
  '@/composables/useTenantCountry': fakeTenantCountry,
  './quickCustomerPayload.mjs': new URL('quickCustomerPayload.mjs', dir).href,
})))).default;

function render(component, props = {}, locale = 'fr') {
  const app = createSSRApp(component, props);
  app.use(createI18n({ legacy: false, locale, messages: { fr, ar } }));
  return renderToString(app);
}

test('quick customer modal: title, minimum fields only, Cancel + Create and select', async () => {
  const html = await render(QuickCustomerModal, { initialName: 'Acme SARL' });
  assert.match(html, /Nouveau client/);
  assert.match(html, /value="Acme SARL"/, 'what was typed in the dropdown pre-fills the name');
  assert.match(html, /Créer et sélectionner/);
  assert.match(html, /Annuler/);
  assert.match(html, /Entreprise/);
  assert.match(html, /Particulier/);
  // Quick create - no address/fiscal/contact fields from the full form.
  assert.doesNotMatch(html, /ICE|NIF|Adresse|Site web|E-mail/i);
  // Rendering alone never creates anything.
  assert.equal(posts.length, 0);
});

test('quick customer modal is translated (Arabic)', async () => {
  const html = await render(QuickCustomerModal, {}, 'ar');
  assert.match(html, /عميل جديد/);
  assert.match(html, /إنشاء واختيار/);
});

test('modal shell: loading state disables both actions and shows the creating label', async () => {
  const idle = await render(Shell, { title: 'T' });
  assert.doesNotMatch(idle, /<button[^>]*disabled/);
  const busy = await render(Shell, { title: 'T', loading: true });
  assert.equal((busy.match(/<button[^>]*disabled/g) ?? []).length, 2);
  assert.match(busy, /Création…/);
  const err = await render(Shell, { title: 'T', error: 'Le nom est requis' });
  assert.match(err, /role="alert"[^>]*>.*Le nom est requis/s);
});

test('cancel only closes: it never calls the API', () => {
  const shell = readFileSync(new URL('QuickCreateModal.vue', dir), 'utf8');
  assert.match(shell, /@click="\$emit\('cancel'\)"/);
  assert.doesNotMatch(shell, /axios/);
  for (const file of ['QuickCustomerModal.vue', 'QuickItemModal.vue']) {
    const src = readFileSync(new URL(file, dir), 'utf8');
    // The only POSTs happen inside submit().
    const submitBody = src.slice(src.indexOf('async function submit'));
    const beforeSubmit = src.slice(0, src.indexOf('async function submit'));
    assert.doesNotMatch(beforeSubmit, /axios\.post/, file);
    assert.match(submitBody, /axios\.post\('\/(customers|items)'/, file);
    assert.match(src, /@cancel="\$emit\('cancel'\)"/, file);
  }
});

test('quick item modal: name, type, price, tax only - no category', () => {
  const src = readFileSync(new URL('QuickItemModal.vue', dir), 'utf8');
  const payload = src.slice(src.indexOf("axios.post('/items'"), src.indexOf('emit(\'created\''));
  for (const field of ['name', 'type', 'sales_price', 'vta', 'tax_treatment', 'unite']) {
    assert.match(payload, new RegExp(`${field}:`), field);
  }
  assert.doesNotMatch(payload, /family_id|purchase_price|reference|description/);
  // Category is optional now: never shown, never auto-picked, never created.
  assert.doesNotMatch(src, /famil/i);
  assert.match(src, /useTaxPresets\(\(\) => \[form\]\)/, 'tenant default tax preselected like the full Products page');
});

// ── Shared selection logic (useQuickCreate) - exercised for real ─────────

const fakeApi = toDataUrl(`
  export default {
    get: async (url) => {
      if (globalThis.__qcFail) throw new Error('network');
      return { data: url === '/customers' ? { customers: globalThis.__qcCustomers } : { items: globalThis.__qcItems } };
    },
  };`);
const composableSrc = readFileSync(new URL('useQuickCreate.js', dir), 'utf8')
  .replace("from 'vue'", `from ${JSON.stringify(import.meta.resolve('vue'))}`)
  .replace("from 'axios'", `from ${JSON.stringify(fakeApi)}`);
const { useQuickCreate } = await import(toDataUrl(composableSrc));
const { ref } = await import('vue');

function setup(isLocked) {
  const calls = { customer: [], item: [] };
  const customers = ref([{ uuid: 'old', name: 'Old' }]);
  const items = ref([{ id: '1', name: 'Old item' }]);
  const qc = useQuickCreate({
    customers, items,
    selectCustomer: (c) => calls.customer.push(c),
    selectItem: (line, i) => calls.item.push([line, i]),
    ...(isLocked ? { isLocked } : {}),
  });
  return { qc, calls, customers, items };
}

test('new customer: list refreshed, the created one selected by uuid (not by name)', async () => {
  globalThis.__qcFail = false;
  globalThis.__qcCustomers = [{ uuid: 'other', name: 'Acme' }, { uuid: 'new-uuid', name: 'Acme' }];
  const { qc, calls, customers } = setup();
  qc.openQuickCustomer('Acme');
  assert.equal(qc.quickCustomer.open, true);
  assert.equal(qc.quickCustomer.search, 'Acme');
  await qc.onQuickCustomerCreated('new-uuid');
  assert.equal(customers.value.length, 2);
  assert.deepEqual(calls.customer.map(c => c.uuid), ['new-uuid']);
  assert.equal(qc.quickCustomer.open, false);
});

test('new item: selected on the line that opened the modal, matched by id', async () => {
  globalThis.__qcFail = false;
  globalThis.__qcItems = [{ id: '1', name: 'Old item' }, { id: '42', name: 'Design' }];
  const { qc, calls } = setup();
  qc.openQuickItem(3, 'Des');
  await qc.onQuickItemCreated(42); // numeric id from the API vs string ids in the list
  assert.deepEqual(calls.item.map(([line, i]) => [line, i.id]), [[3, '42']]);
  assert.equal(qc.quickItem.open, false);
});

test('a locked form (accepted quote) cannot open quick create', () => {
  const { qc } = setup(() => true);
  qc.openQuickCustomer('x');
  qc.openQuickItem(0, 'y');
  assert.equal(qc.quickCustomer.open, false);
  assert.equal(qc.quickItem.open, false);
});

test('the modal closes even if refreshing the list fails, and nothing is selected', async () => {
  globalThis.__qcFail = true;
  const { qc, calls } = setup();
  qc.openQuickCustomer();
  await assert.rejects(qc.onQuickCustomerCreated('x'));
  assert.equal(qc.quickCustomer.open, false);
  assert.equal(calls.customer.length, 0);
  globalThis.__qcFail = false;
});

// ── Wiring on every page (Create/Edit Invoice, Create/Edit Quote) ─────────

const FORMS = {
  'Create invoice': ['../invoices/CreateInvoiceForm.vue', null],
  'Edit invoice':   ['../invoices/EditInvoiceForm.vue', null],
  'Create quote':   ['../quotes/CreateQuoteForm.vue', null],
  'Edit quote':     ['../quotes/EditQuoteForm.vue', 'locked.value'],
};

for (const [page, [file, lock]] of Object.entries(FORMS)) {
  test(`${page}: quick create in both dropdowns, reusing the shared modals and the form's own selection`, () => {
    const form = readFileSync(new URL(file, dir), 'utf8');

    assert.match(form, /:options="customers"[\s\S]*?#list-footer="\{ search \}"[\s\S]*?<QuickCreateAction[^>]*quickCreate\.newCustomer[^>]*@activate="openQuickCustomer\(search\)"/);
    assert.match(form, /:options="items"[\s\S]*?#list-footer="\{ search \}"[\s\S]*?<QuickCreateAction[^>]*quickCreate\.newItem[^>]*@activate="openQuickItem\(index, search\)"/);

    // The one shared implementation - no copies of the modals or handlers.
    assert.match(form, /import QuickCustomerModal from "(\.\/|\.\.\/invoices\/)QuickCustomerModal\.vue"/);
    assert.match(form, /import QuickItemModal from "(\.\/|\.\.\/invoices\/)QuickItemModal\.vue"/);
    assert.match(form, /useQuickCreate\(\{/);
    assert.match(form, /selectCustomer: \(created\) => \{ state\.customer_id = created\.uuid; onClientSelect\(created\); \}/);
    assert.match(form, /selectItem:\s+\(line, created\) => \{ if \(state\.carts\[line\]\) selectProduct\(line, created\); \}/);
    assert.doesNotMatch(form, /async function onQuickCustomerCreated|async function onQuickItemCreated/);

    assert.match(form, /<QuickCustomerModal[\s\S]*?@created="onQuickCustomerCreated"/);
    assert.match(form, /<QuickItemModal[\s\S]*?@created="onQuickItemCreated"/);

    if (lock) assert.match(form, new RegExp(`isLocked:\\s+\\(\\) => ${lock.replace('.', '\\.')}`));
  });
}

test('Edit invoice: the editable form (with quick create) only exists for unlocked invoices', () => {
  const page = readFileSync(new URL('edit.vue', dir), 'utf8');
  assert.match(page, /<EditInvoiceForm\s+v-else-if="!state\.is_locked"/);
});

test('Edit quote: an accepted quote keeps both dropdowns disabled', () => {
  const form = readFileSync(new URL('../quotes/EditQuoteForm.vue', dir), 'utf8');
  assert.match(form, /:options="customers"[\s\S]*?:disabled="locked"/);
  assert.match(form, /:options="items"[\s\S]*?:disabled="locked"/);
});
