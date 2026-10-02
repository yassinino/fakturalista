import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick, h, ref, reactive, defineComponent, vModelText, withDirectives } from 'vue';

// "Plus d'options" (progressive disclosure) on Create Invoice / Create Quote.
// The real MoreOptions.vue is mounted with Vue's own custom renderer (no
// browser) and driven through its click handler.

const dir = new URL('../../resources/js/views/admin/invoices/', import.meta.url);
const toDataUrl = (code) => `data:text/javascript;base64,${Buffer.from(code).toString('base64')}`;
const { descriptor } = parse(readFileSync(new URL('MoreOptions.vue', dir), 'utf8'));
const code = compileScript(descriptor, { id: 'more-options-test', inlineTemplate: true }).content
  .replaceAll('"vue"', JSON.stringify(import.meta.resolve('vue')))
  .replaceAll("'vue'", JSON.stringify(import.meta.resolve('vue')));
const MoreOptions = (await import(toDataUrl(code))).default;

globalThis.document ??= { activeElement: null };
const el = (tag) => ({
  tag, props: {}, children: [], parent: null, listeners: {}, value: '', style: {},
  addEventListener(type, fn) { this.listeners[type] = fn; }, removeEventListener() {},
  setAttribute(k, v) { this.props[k] = v; }, focus() {},
});
const { createApp } = createRenderer({
  createElement: el,
  createText: (text) => ({ text, children: [] }),
  createComment: (text) => ({ comment: text, children: [] }),
  setText: (n, t) => { n.text = t; },
  setElementText: (n, t) => { n.children = [{ text: t, children: [] }]; },
  insert: (c, p, a) => { c.parent = p; const i = a ? p.children.indexOf(a) : -1; i >= 0 ? p.children.splice(i, 0, c) : p.children.push(c); },
  remove: (c) => { const p = c.parent; if (p) p.children.splice(p.children.indexOf(c), 1); },
  parentNode: (n) => n.parent,
  nextSibling: (n) => (n.parent ? n.parent.children[n.parent.children.indexOf(n) + 1] ?? null : null),
  patchProp: (node, key, _p, next) => { node.props[key] = next; },
});
const all = (n, out = []) => { out.push(n); (n.children || []).forEach((c) => all(c, out)); return out; };
const byAttr = (root, k, v) => all(root).find((n) => n.props?.[k] === v);
const flush = async () => { await nextTick(); await nextTick(); };

/** A host shaped like the create forms: v-model open state + a field inside. */
function mountHost({ open = false, error = false } = {}) {
  const state = reactive({ note: '' });
  const showAdvanced = ref(open);
  const hasError = ref(error);
  const Host = defineComponent({
    setup: () => () => h(MoreOptions, {
      modelValue: showAdvanced.value,
      'onUpdate:modelValue': (v) => { showAdvanced.value = v; },
      label: "Plus d'options",
      hasError: hasError.value,
    }, {
      default: () => [withDirectives(h('textarea', {
        id: 'note',
        'onUpdate:modelValue': (v) => { state.note = v; },
      }), [[vModelText, state.note]])],
    }),
  });
  const root = el('root');
  const app = createApp(Host);
  app.mount(root);
  return { root, app, state, showAdvanced, hasError };
}

const panel = (root) => byAttr(root, 'data-test', 'more-options-panel');
const toggle = (root) => byAttr(root, 'data-test', 'more-options-toggle');
const isVisible = (node) => node.style.display !== 'none';

test('collapsed initially, with a subtle "Plus d\'options" toggle', async () => {
  const { root, app } = mountHost();
  await flush();
  assert.equal(isVisible(panel(root)), false);
  assert.equal(toggle(root).props['aria-expanded'], 'false');
  assert.match(all(toggle(root)).map((n) => n.text ?? '').join(''), /Plus d'options/);
  app.unmount();
});

test('expands and collapses inline', async () => {
  const { root, app, showAdvanced } = mountHost();
  await flush();
  toggle(root).props.onClick();
  await flush();
  assert.equal(showAdvanced.value, true);
  assert.equal(isVisible(panel(root)), true);
  assert.equal(toggle(root).props['aria-expanded'], 'true');

  toggle(root).props.onClick();
  await flush();
  assert.equal(isVisible(panel(root)), false);
  app.unmount();
});

test('values entered inside survive collapsing (fields stay mounted)', async () => {
  const { root, app, state } = mountHost({ open: true });
  await flush();
  const note = byAttr(root, 'id', 'note');
  note.value = 'Merci pour votre confiance';
  note.listeners.input({ target: note });
  await flush();

  toggle(root).props.onClick(); // collapse
  await flush();
  assert.equal(state.note, 'Merci pour votre confiance');
  assert.equal(byAttr(root, 'id', 'note'), note, 'same element - not destroyed');

  toggle(root).props.onClick(); // expand again
  await flush();
  assert.equal(byAttr(root, 'id', 'note').value, 'Merci pour votre confiance');
  app.unmount();
});

test('a validation error inside opens the section automatically', async () => {
  const { root, app, showAdvanced, hasError } = mountHost();
  await flush();
  assert.equal(isVisible(panel(root)), false);

  hasError.value = true;
  await flush();
  assert.equal(showAdvanced.value, true);
  assert.equal(isVisible(panel(root)), true);
  app.unmount();
});

// ── Wiring on the two create forms ───────────────────────────────────────

const FORMS = {
  'Create invoice': ['CreateInvoiceForm.vue', 'invoices.form.moreOptions'],
  'Create quote':   ['../quotes/CreateQuoteForm.vue', 'quotes.form.moreOptions'],
};

for (const [page, [file, labelKey]] of Object.entries(FORMS)) {
  const src = readFileSync(new URL(file, dir), 'utf8');
  const start = src.indexOf('<MoreOptions');
  const section = src.slice(start, src.indexOf('</MoreOptions>', start));
  const outside = src.slice(0, start) + src.slice(src.indexOf('</MoreOptions>', start));

  test(`${page}: optional fields under "Plus d'options", collapsed by default`, () => {
    assert.match(src, /import MoreOptions from "(\.\/|\.\.\/invoices\/)MoreOptions\.vue"/);
    assert.match(section, new RegExp(`v-model="showAdvanced"[\\s\\S]*:label="\\$t\\('${labelKey}'\\)"`));
    assert.match(src, /const showAdvanced = ref\(false\);/, 'collapsed on a new document');
    assert.match(section, /v-model="state\.status"/);
    assert.match(section, /v-model="state\.note"/);
    // The old toggle under the dates is gone (one disclosure only).
    assert.doesNotMatch(src, /@click="showAdvanced = !showAdvanced"/);
  });

  test(`${page}: everything needed to create the document stays visible`, () => {
    for (const essential of [
      /v-model="state\.customer_id"/, /v-model="state\.date"/, /v-model="state\.expiration_date"/,
      /v-for="\(cart, index\) in state\.carts"/, /v-model="cart\.item_id"/, /v-model="cart\.description"/,
      /v-model="cart\.qty"/, /v-model="cart\.price"/, /<TaxSelect/, /\$toCurrency\(total\)/, /@click="handleSave"/,
    ]) {
      assert.match(outside, essential, String(essential));
      assert.doesNotMatch(section, essential, `${essential} must not be hidden`);
    }
    // Quick Create + free-text lines untouched and outside the section.
    assert.match(outside, /openQuickCustomer\(search\)/);
    assert.match(outside, /openQuickItem\(index, search\)/);
    assert.match(outside, /@search:blur="onLineSearchBlur\(index\)"/);
  });
}

test('Create invoice: the Spain-only operation description is inside and opens the section on error', () => {
  const src = readFileSync(new URL('CreateInvoiceForm.vue', dir), 'utf8');
  const start = src.indexOf('<MoreOptions');
  const section = src.slice(start, src.indexOf('</MoreOptions>', start));
  assert.match(section, /v-model="state\.descripcion_operacion"/);
  assert.match(section, /:has-error="advancedHasError"/);
  assert.match(src, /descripcion_operacion: \{ maxLength: maxLength\(500\) \}/, 'same limit as InvoiceRequest');
  assert.match(src, /const advancedHasError = computed\(\(\) => v\$\.value\.descripcion_operacion\.\$error\);/);
});

test('Edit Invoice / Edit Quote are unchanged (no MoreOptions)', () => {
  for (const file of ['EditInvoiceForm.vue', '../quotes/EditQuoteForm.vue']) {
    assert.doesNotMatch(readFileSync(new URL(file, dir), 'utf8'), /MoreOptions/, file);
  }
});
