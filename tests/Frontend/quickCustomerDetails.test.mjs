import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import fr from '../../resources/js/i18n/locales/fr.js';
import { buildQuickCustomerPayload } from '../../resources/js/views/admin/invoices/quickCustomerPayload.mjs';

// Quick customer modal (invoices + quotes): minimal form, optional details
// collapsed behind "+ Ajouter plus d'informations". The real component is
// mounted with a tiny DOM-less renderer (Vue's own createRenderer) and
// driven through its click / input / submit handlers - no browser needed.

const dir = new URL('../../resources/js/views/admin/invoices/', import.meta.url);
const toDataUrl = (code) => `data:text/javascript;base64,${Buffer.from(code).toString('base64')}`;

function compile(file, extra = {}) {
  const { descriptor } = parse(readFileSync(new URL(file, dir), 'utf8'));
  let code = compileScript(descriptor, { id: file, inlineTemplate: true }).content;
  const map = {
    vue: import.meta.resolve('vue'),
    'vue-i18n': import.meta.resolve('vue-i18n'),
    '@vuelidate/core': import.meta.resolve('@vuelidate/core'),
    '@vuelidate/validators': import.meta.resolve('@vuelidate/validators'),
    ...extra,
  };
  for (const [spec, target] of Object.entries(map)) {
    code = code.replaceAll(`"${spec}"`, JSON.stringify(target)).replaceAll(`'${spec}'`, JSON.stringify(target));
  }
  return code;
}

globalThis.__posts = [];
const fakeAxios = toDataUrl(`export default { post: async (url, body) => { globalThis.__posts.push([url, body]); return { data: { customer: { uuid: 'new-uuid' } } }; } };`);
const fakeCountry = toDataUrl(`import { computed } from ${JSON.stringify(import.meta.resolve('vue'))};
  export function useTenantCountry() { return { isMorocco: computed(() => !!globalThis.__morocco) }; }`);
const shellUrl = toDataUrl(compile('QuickCreateModal.vue'));
const QuickCustomerModal = (await import(toDataUrl(compile('QuickCustomerModal.vue', {
  axios: fakeAxios,
  './QuickCreateModal.vue': shellUrl,
  '@/composables/useTenantCountry': fakeCountry,
  './quickCustomerPayload.mjs': new URL('quickCustomerPayload.mjs', dir).href,
})))).default;

// ── Minimal host for Vue's custom renderer ────────────────────────────────
globalThis.document ??= { activeElement: null };
const el = (tag) => ({
  tag, props: {}, children: [], parent: null, listeners: {}, value: '',
  addEventListener(type, fn) { this.listeners[type] = fn; }, removeEventListener() {},
  setAttribute(k, v) { this.props[k] = v; }, focus() {},
});
const { createApp } = createRenderer({
  createElement: el,
  createText: (text) => ({ text, children: [] }),
  createComment: (text) => ({ comment: text, children: [] }),
  setText: (n, text) => { n.text = text; },
  setElementText: (n, text) => { n.children = [{ text, children: [] }]; },
  insert: (child, parent, anchor) => {
    child.parent = parent;
    const i = anchor ? parent.children.indexOf(anchor) : -1;
    i >= 0 ? parent.children.splice(i, 0, child) : parent.children.push(child);
  },
  remove: (child) => { const p = child.parent; if (p) p.children.splice(p.children.indexOf(child), 1); },
  parentNode: (n) => n.parent,
  nextSibling: (n) => (n.parent ? n.parent.children[n.parent.children.indexOf(n) + 1] ?? null : null),
  patchProp: (node, key, _prev, next) => { node.props[key] = next; },
});

const all = (n, out = []) => { out.push(n); (n.children || []).forEach((c) => all(c, out)); return out; };
const byAttr = (root, key, value) => all(root).find((n) => n.props?.[key] === value);
const text = (root) => all(root).map((n) => n.text ?? '').join(' ');
const flush = async () => { await nextTick(); await new Promise((r) => setTimeout(r, 0)); await nextTick(); };

function mount(props = {}) {
  const root = el('root');
  const events = { created: [], cancel: 0 };
  const app = createApp(QuickCustomerModal, {
    ...props,
    onCreated: (uuid) => events.created.push(uuid),
    onCancel: () => { events.cancel++; },
  });
  app.use(createI18n({ legacy: false, locale: 'fr', messages: { fr } }));
  app.mount(root);
  return { root, events, app };
}

async function type(root, id, value) {
  const input = byAttr(root, 'id', id);
  assert.ok(input, `#${id} rendered`);
  input.value = value;
  input.listeners.input({ target: input });
  await flush();
}

async function submit(root) {
  const form = all(root).find((n) => n.tag === 'form');
  form.props.onSubmit({ preventDefault() {}, stopPropagation() {} });
  await flush();
}

const OPTIONAL_IDS = ['qc-cust-email', 'qc-cust-phone', 'qc-cust-address', 'qc-cust-city', 'qc-cust-postcode'];

test('initially minimal: type + company name, optional details collapsed', async () => {
  const { root, app } = mount();
  await flush();
  assert.ok(byAttr(root, 'id', 'qc-cust-name'));
  for (const id of OPTIONAL_IDS) assert.equal(byAttr(root, 'id', id), undefined, id);
  assert.match(text(root), /\+ Ajouter plus d'informations/);
  assert.equal(byAttr(root, 'data-test', 'quick-customer-more').props['aria-expanded'], 'false');
  app.unmount();
});

test('"+ Ajouter plus d\'informations" expands the optional fields in the same modal, and collapses again', async () => {
  const { root, app } = mount();
  await flush();
  byAttr(root, 'data-test', 'quick-customer-more').props.onClick();
  await flush();
  for (const id of OPTIONAL_IDS) assert.ok(byAttr(root, 'id', id), id);
  assert.ok(byAttr(root, 'id', 'qc-cust-nif'), 'NIF outside Morocco');
  assert.equal(byAttr(root, 'id', 'qc-cust-ice'), undefined);
  assert.match(text(root), /Masquer les informations supplémentaires/);

  byAttr(root, 'data-test', 'quick-customer-more').props.onClick();
  await flush();
  assert.equal(byAttr(root, 'id', 'qc-cust-email'), undefined);
  app.unmount();
});

test('minimal company: only the name is sent, then the new customer is emitted for selection', async () => {
  globalThis.__posts.length = 0;
  const { root, events, app } = mount({ initialName: 'Acme SARL' });
  await flush();
  await submit(root);
  assert.deepEqual(globalThis.__posts, [['/customers', { type: 1, name: 'Acme SARL', contacts: [] }]]);
  assert.deepEqual(events.created, ['new-uuid']);
  app.unmount();
});

test('minimal individual: last name required, first name optional', async () => {
  globalThis.__posts.length = 0;
  const { root, events, app } = mount();
  await flush();
  byAttr(root, 'role', 'radio') && all(root).filter((n) => n.props?.role === 'radio')[1].props.onClick(); // Particulier
  await flush();
  await submit(root);
  assert.equal(globalThis.__posts.length, 0, 'last name is required');
  assert.match(text(root), /Requis/);

  await type(root, 'qc-cust-last', 'Benali');
  await submit(root);
  assert.deepEqual(globalThis.__posts, [['/customers', { type: 2, first_name: null, last_name: 'Benali', contacts: [] }]]);
  assert.deepEqual(events.created, ['new-uuid']);
  app.unmount();
});

test('company with optional details (Morocco): details + ICE sent with the API field names', async () => {
  globalThis.__posts.length = 0;
  globalThis.__morocco = true;
  const { root, app } = mount({ initialName: 'Atlas Conseil' });
  await flush();
  byAttr(root, 'data-test', 'quick-customer-more').props.onClick();
  await flush();
  assert.ok(byAttr(root, 'id', 'qc-cust-ice'), 'ICE for a Moroccan company');
  assert.equal(byAttr(root, 'id', 'qc-cust-nif'), undefined);

  await type(root, 'qc-cust-email', ' contact@atlas.ma ');
  await type(root, 'qc-cust-phone', '+212600000000');
  await type(root, 'qc-cust-address', '12 Rue Allal');
  await type(root, 'qc-cust-city', 'Rabat');
  await type(root, 'qc-cust-postcode', '10000');
  await type(root, 'qc-cust-ice', '001234567000089');
  await submit(root);

  assert.deepEqual(globalThis.__posts[0], ['/customers', {
    type: 1, name: 'Atlas Conseil',
    email: 'contact@atlas.ma', phone: '+212600000000', address_billing: '12 Rue Allal',
    city_billing: 'Rabat', post_code_billing: '10000', ice: '001234567000089', contacts: [],
  }]);
  globalThis.__morocco = false;
  app.unmount();
});

test('an invalid email blocks creation with a translated error', async () => {
  globalThis.__posts.length = 0;
  const { root, events, app } = mount({ initialName: 'Acme' });
  await flush();
  byAttr(root, 'data-test', 'quick-customer-more').props.onClick();
  await flush();
  await type(root, 'qc-cust-email', 'pas-un-email');
  await submit(root);
  assert.equal(globalThis.__posts.length, 0);
  assert.deepEqual(events.created, []);
  assert.match(text(root), /Adresse e-mail invalide/);
  app.unmount();
});

test('cancel creates nothing', async () => {
  globalThis.__posts.length = 0;
  const { root, events, app } = mount({ initialName: 'Acme' });
  await flush();
  const cancel = all(root).find((n) => n.tag === 'button' && /Annuler/.test(text(n)));
  cancel.props.onClick();
  await flush();
  assert.equal(events.cancel, 1);
  assert.equal(globalThis.__posts.length, 0);
  app.unmount();
});

test('payload helper: blanks dropped, ICE only for Moroccan companies, NIF only outside Morocco', () => {
  const base = { type: 1, name: ' Co ', email: '', phone: '  ', address_billing: 'A', city_billing: '', post_code_billing: '', ice: '123', tax_id: 'B1' };
  assert.deepEqual(buildQuickCustomerPayload(base, { isMorocco: true }), { type: 1, name: 'Co', address_billing: 'A', ice: '123', contacts: [] });
  assert.deepEqual(buildQuickCustomerPayload(base, { isMorocco: false }), { type: 1, name: 'Co', address_billing: 'A', tax_id: 'B1', contacts: [] });
  assert.deepEqual(buildQuickCustomerPayload({ ...base, type: 2, last_name: 'Doe', first_name: '' }, { isMorocco: true }),
    { type: 2, first_name: null, last_name: 'Doe', address_billing: 'A', contacts: [] }, 'no ICE for an individual');
});

test('all four document forms still use the one shared QuickCustomerModal and select via onClientSelect', () => {
  for (const file of ['CreateInvoiceForm.vue', 'EditInvoiceForm.vue', '../quotes/CreateQuoteForm.vue', '../quotes/EditQuoteForm.vue']) {
    const src = readFileSync(new URL(file, dir), 'utf8');
    assert.match(src, /import QuickCustomerModal from "(\.\/|\.\.\/invoices\/)QuickCustomerModal\.vue"/, file);
    assert.match(src, /selectCustomer: \(created\) => \{ state\.customer_id = created\.uuid; onClientSelect\(created\); \}/, file);
  }
});
