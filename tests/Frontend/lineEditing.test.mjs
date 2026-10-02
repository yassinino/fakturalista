import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { reactive, ref } from 'vue';
import { useLineEditing } from '../../resources/js/views/admin/invoices/useLineEditing.js';

// Keyboard line entry + "Dupliquer la ligne" on Create Invoice / Create Quote.

const DEFAULT_TAX = { vta: 20, tax_treatment: 'taxable' };

/** A form-like setup: lines + the forms' own addNewItem defaults + a fake <tbody>. */
function setup(lines) {
  const state = reactive({ carts: lines });
  const focused = [];
  // addNewItem exactly as the create forms define it (qty 1, unit pc, workspace tax).
  const addNewItem = () => state.carts.push({
    item_id: '', name: '', description: '', qty: 1, unite: 'pc', price: 0, discount: 0, ...DEFAULT_TAX, total: 0,
  });
  // <tbody> whose rows mirror state.carts at query time (after Vue's update).
  const linesRoot = ref({
    querySelectorAll: () => state.carts.map((_, i) => ({
      querySelector: (sel) => (sel === '.vs__search' ? { focus: () => focused.push(i) } : null),
    })),
  });
  const duplicated = [];
  const api = useLineEditing({ getCarts: () => state.carts, addLine: addNewItem, linesRoot, onDuplicated: (i) => duplicated.push(i) });
  return { state, focused, duplicated, ...api };
}

const freeText = (text, price) => ({ item_id: '', name: '', description: text, qty: 1, unite: 'pc', price, discount: 0, ...DEFAULT_TAX, total: price });
const catalog = () => ({ item_id: '42', name: 'Hébergement', description: 'Annuel', qty: 2, unite: 'kg', price: 1000, discount: 10, vta: 10, tax_treatment: 'taxable', total: 1800 });

test('Enter on the LAST line adds a line with the existing defaults and focuses its product field', async () => {
  const { state, focused, onLineEnter } = setup([freeText('Création site web', 4500)]);
  await onLineEnter(0);
  assert.equal(state.carts.length, 2);
  const added = state.carts[1];
  assert.deepEqual({ qty: added.qty, unite: added.unite, vta: added.vta, tax_treatment: added.tax_treatment, item_id: added.item_id, description: added.description },
    { qty: 1, unite: 'pc', vta: 20, tax_treatment: 'taxable', item_id: '', description: '' });
  assert.deepEqual(focused, [1], 'focus moved to the new line');
  assert.equal(state.carts[0].description, 'Création site web', 'existing data preserved');
});

test('Enter on a line that is NOT the last moves to the next line without adding one', async () => {
  const { state, focused, onLineEnter } = setup([freeText('A', 1), freeText('B', 2), freeText('C', 3)]);
  await onLineEnter(0);
  assert.equal(state.carts.length, 3);
  assert.deepEqual(focused, [1]);
  await onLineEnter(2); // now the last one
  assert.equal(state.carts.length, 4);
  assert.deepEqual(focused, [1, 3]);
});

test('"+ Ajouter une ligne" still adds a default line, now focusing it', async () => {
  const { state, focused, addLineAndFocus } = setup([catalog()]);
  await addLineAndFocus();
  assert.equal(state.carts.length, 2);
  assert.equal(state.carts[1].qty, 1);
  assert.equal(state.carts[1].vta, 20);
  assert.deepEqual(focused, [1]);
});

test('duplicating a catalog line copies everything into a NEW independent line right below', async () => {
  const { state, focused, duplicated, duplicateLine } = setup([catalog(), freeText('Déplacement', 300)]);
  await duplicateLine(0);
  assert.equal(state.carts.length, 3);
  const [original, copy, other] = state.carts;
  for (const k of ['item_id', 'name', 'description', 'qty', 'unite', 'price', 'discount', 'vta', 'tax_treatment']) {
    assert.deepEqual(copy[k], original[k], k);
  }
  assert.notEqual(copy, original, 'a separate object');
  copy.qty = 5; copy.description = 'Changé';
  assert.equal(original.qty, 2, 'editing the copy never touches the original');
  assert.equal(original.description, 'Annuel');
  assert.equal(other.description, 'Déplacement', 'following lines kept in order');
  assert.deepEqual(focused, [1]);
  assert.deepEqual(duplicated, [0]);
});

test('duplicating a free-text line keeps it free-text (no catalog item)', async () => {
  const { state, duplicateLine } = setup([freeText('Consultation octobre', 1200)]);
  await duplicateLine(0);
  assert.equal(state.carts[1].item_id, '');
  assert.equal(state.carts[1].description, 'Consultation octobre');
  assert.equal(state.carts[1].price, 1200);
});

// ── Wiring on both create forms ───────────────────────────────────────

const FORMS = {
  'Create invoice': ['../../resources/js/views/admin/invoices/CreateInvoiceForm.vue', 'invoices'],
  'Create quote':   ['../../resources/js/views/admin/quotes/CreateQuoteForm.vue', 'quotes'],
};

for (const [page, [file, ns]] of Object.entries(FORMS)) {
  const src = readFileSync(new URL(file, import.meta.url), 'utf8');
  const template = src.slice(0, src.indexOf('<script setup>'));
  const line = template.slice(template.indexOf('v-for="(cart, index) in state.carts"'), template.indexOf('</tr>', template.indexOf('v-for="(cart, index) in state.carts"')));

  test(`${page}: Enter in description / qty / price uses the line-aware handler and never submits`, () => {
    const enter = line.match(/@keydown\.enter\.prevent="onLineEnter\(index\)"/g) ?? [];
    assert.equal(enter.length, 3, 'description, quantity, price');
    assert.doesNotMatch(line, /@keydown\.enter(?!\.prevent)/, 'every Enter handler prevents the default');
    assert.doesNotMatch(template, /type="submit"/, 'no submit button -> Enter can never submit the document');
    assert.match(template, /<form @submit\.prevent="handleSave" novalidate>/);
  });

  test(`${page}: natural tab order kept (no positive tabindex), field order unchanged`, () => {
    assert.doesNotMatch(template, /tabindex="[1-9]/);
    const order = ['v-model="cart.item_id"', 'v-model="cart.description"', 'v-model="cart.qty"', 'v-model="cart.unite"', 'v-model="cart.price"', '<TaxSelect', 'data-test="duplicate-line"', 'class="inv-del-btn"']
      .map((needle) => line.indexOf(needle));
    assert.ok(order.every((pos) => pos > 0), 'all fields present');
    assert.deepEqual([...order].sort((a, b) => a - b), order, 'visual = DOM = tab order');
  });

  test(`${page}: Add Line button kept (now focusing the new line); duplicate is a small labelled icon button`, () => {
    assert.match(template, /class="inv-add-line" @click="addLineAndFocus"/);
    assert.match(line, /class="inv-dup-btn"[\s\S]*?@click="duplicateLine\(index\)"[\s\S]*?:aria-label="\$t\('[a-z]+\.form\.duplicateLineTitle'\)"/);
    assert.match(src, /useLineEditing\(\{\s*getCarts: \(\) => state\.carts,\s*addLine: addNewItem,\s*linesRoot: linesBody,/);
    assert.match(template, /<tbody ref="linesBody">/);
  });

  test(`${page}: keyboard hint stays desktop-only; duplicate button is the same tap size as remove`, () => {
    assert.match(src, /@media \(max-width: 768px\)[\s\S]*?\.inv-footer-hint\s*\{[^}]*display:\s*none/);
    assert.match(src, /\.inv-dup-btn \{\s*width: 30px;\s*height: 30px;/);
    assert.match(src, /\.inv-del-btn \{\s*width: 30px;\s*height: 30px;/);
  });

  test(`${page}: Quick Create + free-text wiring untouched on every line`, () => {
    assert.match(line, /@search:blur="onLineSearchBlur\(index\)"/);
    assert.match(line, /openQuickItem\(index, search\)/);
    assert.match(line, /@option:selected="v => selectProduct\(index, v\)"/);
  });
}

test('Create invoice: AI badges follow their rows when a line is duplicated', () => {
  const src = readFileSync(new URL(FORMS['Create invoice'][0], import.meta.url), 'utf8');
  assert.match(src, /onDuplicated: \(i\) => \{\s*aiCustomLineIndexes\.value = aiCustomLineIndexes\.value\s*\.flatMap\(\(x\) => \(x === i \? \[x, x \+ 1\] : \[x > i \? x \+ 1 : x\]\)\);/);
  // Same expression, checked for real:
  const shift = (list, i) => list.flatMap((x) => (x === i ? [x, x + 1] : [x > i ? x + 1 : x]));
  assert.deepEqual(shift([0, 2], 0), [0, 1, 3]);
  assert.deepEqual(shift([0, 2], 1), [0, 3]);
});

test('labels translated in FR / ES / EN / AR', async () => {
  for (const l of ['fr', 'es', 'en', 'ar']) {
    const m = (await import(new URL(`../../resources/js/i18n/locales/${l}.js`, import.meta.url))).default;
    for (const ns of ['invoices', 'quotes']) assert.ok(m[ns].form.duplicateLineTitle, `${l}.${ns}`);
  }
});
