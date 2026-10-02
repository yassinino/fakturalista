import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { useFreeTextLines } from '../../resources/js/views/admin/invoices/useFreeTextLines.js';

// Free-text invoice/quote lines: text typed in the product selector without
// picking an item stays on the line (as its description) - no catalog item.

const emptyLine = () => ({ item_id: '', name: '', description: '', qty: 1, unite: 'pc', price: 0, discount: 0, vta: 20, tax_treatment: 'taxable', total: 0 });

test('typed text kept when the selector loses focus without a pick ("Consultation octobre" -> Price)', () => {
  const carts = [emptyLine()];
  const { onLineSearch, onLineSearchBlur } = useFreeTextLines(() => carts);

  // vue-select emits "search" per keystroke, then "search:blur" on blur
  // (its cleared-search "search" event only arrives afterwards).
  onLineSearch(0, 'Consultation');
  onLineSearch(0, 'Consultation octobre');
  onLineSearchBlur(0);
  onLineSearch(0, '');

  assert.equal(carts[0].description, 'Consultation octobre');
  assert.equal(carts[0].item_id, '', 'no catalog item is set or created');
  // Other line fields are untouched and keep working normally.
  carts[0].price = 1200;
  assert.deepEqual({ qty: carts[0].qty, price: carts[0].price, vta: carts[0].vta }, { qty: 1, price: 1200, vta: 20 });
});

test('a picked catalog item keeps the normal autofill - nothing is copied', () => {
  const carts = [emptyLine()];
  const { onLineSearch, onLineSearchBlur } = useFreeTextLines(() => carts);

  onLineSearch(0, 'Héb');
  // option:selected -> the form's selectProduct() replaces the line
  carts[0] = { ...emptyLine(), item_id: '7', description: 'Hébergement annuel', price: 1000 };
  onLineSearchBlur(0);

  assert.equal(carts[0].description, 'Hébergement annuel');
  assert.equal(carts[0].item_id, '7');
});

test('an existing description is never overwritten; empty text changes nothing', () => {
  const carts = [{ ...emptyLine(), description: 'Texte saisi à la main' }, emptyLine()];
  const { onLineSearch, onLineSearchBlur } = useFreeTextLines(() => carts);

  onLineSearch(0, 'autre chose');
  onLineSearchBlur(0);
  assert.equal(carts[0].description, 'Texte saisi à la main');

  onLineSearch(1, '   ');
  onLineSearchBlur(1);
  assert.equal(carts[1].description, '');

  onLineSearchBlur(1); // blur without typing at all
  assert.equal(carts[1].description, '');
});

test('each line keeps its own text (correct line, mixed documents)', () => {
  const carts = [{ ...emptyLine(), item_id: '3', description: 'Catalogue' }, emptyLine(), emptyLine()];
  const { onLineSearch, onLineSearchBlur } = useFreeTextLines(() => carts);

  onLineSearch(2, 'Déplacement');
  onLineSearch(1, 'Maquette');
  onLineSearchBlur(1);
  onLineSearchBlur(2);

  assert.deepEqual(carts.map(c => [c.item_id, c.description]), [['3', 'Catalogue'], ['', 'Maquette'], ['', 'Déplacement']]);
});

const FORMS = {
  'Create invoice': '../../resources/js/views/admin/invoices/CreateInvoiceForm.vue',
  'Edit invoice':   '../../resources/js/views/admin/invoices/EditInvoiceForm.vue',
  'Create quote':   '../../resources/js/views/admin/quotes/CreateQuoteForm.vue',
  'Edit quote':     '../../resources/js/views/admin/quotes/EditQuoteForm.vue',
};

for (const [page, file] of Object.entries(FORMS)) {
  test(`${page}: product selector accepts free text; catalog + Quick Create unchanged`, () => {
    const src = readFileSync(new URL(file, import.meta.url), 'utf8');
    const select = src.slice(src.indexOf(':options="items"'), src.indexOf('</VueSelect>', src.indexOf(':options="items"')));

    assert.match(select, /@option:selected="v => selectProduct\(index, v\)"/, 'normal catalog autofill kept');
    assert.match(select, /@search="q => onLineSearch\(index, q\)"/);
    assert.match(select, /@search:blur="onLineSearchBlur\(index\)"/);
    assert.match(select, /quickCreate\.newItem[^>]*@activate="openQuickItem\(index, search\)"/, 'Quick Create kept');
    assert.match(src, /useFreeTextLines\(\(\) => state\.carts\)/);
    // The free-text field the text lands in is still saved with the line.
    assert.match(src, /v-model="cart\.description"/);
  });
}

test('Edit quote: an accepted quote keeps the product selector disabled (no typing)', () => {
  const src = readFileSync(new URL(FORMS['Edit quote'], import.meta.url), 'utf8');
  const select = src.slice(src.indexOf(':options="items"'), src.indexOf('</VueSelect>', src.indexOf(':options="items"')));
  assert.match(select, /:disabled="locked"/);
});
