import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRenderer, nextTick, reactive, ref, defineComponent, h } from 'vue';
import { documentFingerprint } from '../../resources/js/views/admin/invoices/documentFingerprint.mjs';

// Unsaved-change protection on Create Invoice / Create Quote.

const dir = new URL('../../resources/js/views/admin/', import.meta.url);
const toDataUrl = (code) => `data:text/javascript;base64,${Buffer.from(code).toString('base64')}`;

// The forms' initial state (as in CreateInvoiceForm / CreateQuoteForm).
const newInvoice = () => reactive({
  customer_id: '', address: '', discount_rate: 0, discount_amount: 0,
  date: '2026-10-01', status: '', expiration_date: '2026-10-31', note: '', descripcion_operacion: '',
  carts: [{ item_id: '', name: '', description: '', qty: 1, unite: 'pc', price: 0, discount: 0, vta: null, tax_treatment: 'taxable', total: 0 }],
});
const newQuote = () => { const s = newInvoice(); delete s.descripcion_operacion; return s; };
const WORKSPACE_TAX = { vta: 20, tax_treatment: 'taxable' };

// ── What counts as a change (documentFingerprint) ──────────────────────

for (const [doc, make] of [['Invoice', newInvoice], ['Quote', newQuote]]) {
  test(`${doc}: untouched form and automatic defaults alone are NOT changes`, () => {
    const state = make();
    const base = documentFingerprint(state, { vta: null, tax_treatment: 'taxable' }); // at mount, taxes still loading
    // Workspace default tax arrives asynchronously and is applied to the line.
    Object.assign(state.carts[0], WORKSPACE_TAX);
    assert.equal(documentFingerprint(state, WORKSPACE_TAX), base);
    // Derived/UI values (totals, address preview) never count either.
    state.total = 120; state.sub_total = 100; state.carts[0].total = 0; state.address = 'x';
    assert.equal(documentFingerprint(state, WORKSPACE_TAX), base);
  });

  const changes = {
    'selecting/creating a customer': (s) => { s.customer_id = 'cust-uuid'; },
    'typing a free-text line':       (s) => { s.carts[0].description = 'Création site web'; },
    'selecting/creating an item':    (s) => { s.carts[0].item_id = '42'; },
    'changing quantity':             (s) => { s.carts[0].qty = 2; },
    'changing price':                (s) => { s.carts[0].price = 4500; },
    'changing unit':                 (s) => { s.carts[0].unite = 'kg'; },
    'changing tax':                  (s) => { Object.assign(s.carts[0], { vta: 10, tax_treatment: 'taxable' }); },
    'adding a line':                 (s) => { s.carts.push({ ...s.carts[0] }); },
    'removing a line':               (s) => { s.carts.push({ ...s.carts[0], description: 'B' }); s.carts.splice(0, 2); },
    'entering a note':               (s) => { s.note = 'Merci'; },
    'changing a status option':      (s) => { s.status = '1'; },
    'changing the document date':    (s) => { s.date = '2026-10-05'; },
    'changing the due/validity date':(s) => { s.expiration_date = '2026-11-15'; },
  };
  for (const [label, change] of Object.entries(changes)) {
    test(`${doc}: ${label} marks the document as changed`, () => {
      const state = make();
      Object.assign(state.carts[0], WORKSPACE_TAX);
      const base = documentFingerprint(state, WORKSPACE_TAX);
      change(state);
      assert.notEqual(documentFingerprint(state, WORKSPACE_TAX), base);
    });
  }
}

test('Invoice: the Spain operation description counts as a change', () => {
  const s = newInvoice();
  const base = documentFingerprint(s);
  s.descripcion_operacion = 'Servicios';
  assert.notEqual(documentFingerprint(s), base);
});

// ── useUnsavedChanges: browser + in-app navigation ─────────────────────

const fakeRouter = toDataUrl(`
  export function onBeforeRouteLeave(fn) { globalThis.__uc.guard = fn; }
  export function useRouter() { return { push: (p) => globalThis.__uc.pushes.push(p) }; }`);
const src = readFileSync(new URL('invoices/useUnsavedChanges.js', dir), 'utf8')
  .replace("from 'vue-router'", `from ${JSON.stringify(fakeRouter)}`)
  .replace("from 'vue'", `from ${JSON.stringify(import.meta.resolve('vue'))}`);
const { useUnsavedChanges } = await import(toDataUrl(src));

const listeners = {};
globalThis.window = {
  addEventListener: (t, fn) => { listeners[t] = fn; },
  removeEventListener: (t) => { delete listeners[t]; },
};
const node = () => ({ children: [], props: {}, style: {} });
const { createApp } = createRenderer({
  createElement: node, createText: node, createComment: node, setText() {}, setElementText() {},
  insert: (c, p) => p.children.push(c), remove() {}, parentNode: () => null, nextSibling: () => null, patchProp() {},
});

function mountForm() {
  globalThis.__uc = { guard: null, pushes: [] };
  const state = newInvoice();
  const defaultTax = ref({ vta: null, tax_treatment: 'taxable' });
  const ui = reactive({ moreOptionsOpen: false, quickCustomerOpen: false, quickItemOpen: false });
  let api;
  const app = createApp(defineComponent({
    setup() { api = useUnsavedChanges(() => documentFingerprint(state, defaultTax.value)); return () => h('div'); },
  }));
  app.mount(node());
  // Workspace tax loads after mount and fills the line (as useTaxPresets does).
  defaultTax.value = WORKSPACE_TAX;
  Object.assign(state.carts[0], WORKSPACE_TAX);
  const unload = () => {
    const event = { prevented: false, returnValue: undefined, preventDefault() { this.prevented = true; } };
    listeners.beforeunload(event);
    return event;
  };
  const navigate = (fullPath = '/admin/customers') => globalThis.__uc.guard({ fullPath });
  return { app, api, state, ui, unload, navigate };
}

test('untouched new form: no browser prompt, in-app navigation allowed', async () => {
  const { app, api, unload, navigate } = mountForm();
  await nextTick();
  assert.equal(api.isDirty.value, false);
  assert.equal(unload().prevented, false);
  assert.equal(navigate(), true);
  assert.equal(api.showLeaveConfirm.value, false);
  app.unmount();
});

test('opening/closing "Plus d\'options" or Quick Create alone does not count', async () => {
  const { app, api, ui, unload, navigate } = mountForm();
  ui.moreOptionsOpen = true; ui.quickCustomerOpen = true; ui.quickItemOpen = true;
  ui.moreOptionsOpen = false; ui.quickCustomerOpen = false; ui.quickItemOpen = false; // cancelled
  await nextTick();
  assert.equal(api.isDirty.value, false);
  assert.equal(unload().prevented, false);
  assert.equal(navigate(), true);
  app.unmount();
});

test('dirty form: browser refresh/close is blocked by beforeunload', async () => {
  const { app, state, unload } = mountForm();
  state.customer_id = 'cust-uuid';
  await nextTick();
  const event = unload();
  assert.equal(event.prevented, true);
  assert.equal(event.returnValue, '');
  app.unmount();
  assert.equal(listeners.beforeunload, undefined, 'listener removed when leaving the page');
});

test('dirty form: in-app navigation shows the modal; "Continuer la modification" stays', async () => {
  const { app, api, state, navigate } = mountForm();
  state.carts[0].description = 'Consultation octobre';
  await nextTick();
  assert.equal(navigate('/admin/customers'), false, 'navigation blocked');
  assert.equal(api.showLeaveConfirm.value, true);
  api.stay();
  assert.equal(api.showLeaveConfirm.value, false);
  assert.deepEqual(globalThis.__uc.pushes, []);
  assert.equal(api.isDirty.value, true, 'still protected');
  app.unmount();
});

test('dirty form: "Quitter sans enregistrer" continues to the requested page', async () => {
  const { app, api, state, navigate } = mountForm();
  state.carts[0].price = 1200;
  await nextTick();
  navigate('/admin/customers?page=2');
  api.leave();
  assert.deepEqual(globalThis.__uc.pushes, ['/admin/customers?page=2']);
  assert.equal(navigate('/admin/customers?page=2'), true, 'the retried navigation goes through');
  app.unmount();
});

test('successful save clears the protection before the redirect', async () => {
  const { app, api, state, unload, navigate } = mountForm();
  state.customer_id = 'cust-uuid';
  api.markSaved(); // done(true) from the save page
  await nextTick();
  assert.equal(api.isDirty.value, false);
  assert.equal(unload().prevented, false);
  assert.equal(navigate('/admin/invoices'), true, 'redirect to the list is not interrupted');
  app.unmount();
});

test('a failed save keeps the protection', async () => {
  const { app, api, state, unload, navigate } = mountForm();
  state.customer_id = 'cust-uuid';
  // done(false): markSaved is NOT called
  await nextTick();
  assert.equal(api.isDirty.value, true);
  assert.equal(unload().prevented, true);
  assert.equal(navigate(), false);
  app.unmount();
});

// ── Wiring: both forms + both save pages ───────────────────────────────

for (const [doc, form, page] of [
  ['Invoice', 'invoices/CreateInvoiceForm.vue', 'invoices/create.vue'],
  ['Quote', 'quotes/CreateQuoteForm.vue', 'quotes/create.vue'],
]) {
  test(`${doc}: form uses the shared protection and modal; save page clears it only on success, before redirecting`, () => {
    const f = readFileSync(new URL(form, dir), 'utf8');
    assert.match(f, /useUnsavedChanges\(\(\) => documentFingerprint\(state, defaultTax\(\)\)\)/);
    assert.match(f, /<UnsavedChangesModal v-if="showLeaveConfirm" @stay="stay" @leave="leave" \/>/);
    assert.match(f, /emit\("saveDocument", state, \(success\) => \{\s*saving\.value = false;\s*if \(success\) markSaved\(\);/);

    const p = readFileSync(new URL(page, dir), 'utf8');
    const ok = p.indexOf('done?.(true);');
    assert.ok(ok > 0 && ok < p.indexOf('router.push('), 'done(true) before the redirect');
    assert.match(p, /catch \(e\) \{[\s\S]*?done\?\.\(false\);/);
  });
}

test('modal texts exist in FR / ES / EN / AR', async () => {
  for (const l of ['fr', 'es', 'en', 'ar']) {
    const m = (await import(new URL(`../../resources/js/i18n/locales/${l}.js`, import.meta.url))).default;
    for (const k of ['title', 'text', 'stay', 'leave']) assert.ok(m.common.unsavedChanges[k], `${l}.${k}`);
  }
  const fr = (await import(new URL('../../resources/js/i18n/locales/fr.js', import.meta.url))).default;
  assert.equal(fr.common.unsavedChanges.title, 'Modifications non enregistrées');
  assert.equal(fr.common.unsavedChanges.stay, 'Continuer la modification');
  assert.equal(fr.common.unsavedChanges.leave, 'Quitter sans enregistrer');
});
