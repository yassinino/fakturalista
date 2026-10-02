import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

// Smart defaults on NEW invoices / quotes. The workspace default tax is
// applied by useTaxPresets() to every line whose tax is still unset (that
// behaviour itself is exercised in tax.test.mjs); these checks pin that both
// create forms start from the existing defaults and leave the tax unset so
// the workspace default fills it.

const FORMS = {
  'Create invoice': '../../resources/js/views/admin/invoices/CreateInvoiceForm.vue',
  'Create quote':   '../../resources/js/views/admin/quotes/CreateQuoteForm.vue',
};

for (const [page, file] of Object.entries(FORMS)) {
  const src = readFileSync(new URL(file, import.meta.url), 'utf8');
  const state = src.slice(src.indexOf('const state = reactive({'), src.indexOf('const { presets'));
  const addLine = src.slice(src.indexOf('const addNewItem'), src.indexOf('const removeCart'));

  test(`${page}: today's date, qty 1, default unit, due/validity date as before`, () => {
    assert.match(state, /date:\s+fmtDate\(today\)/);
    assert.match(state, /qty:\s*1/);
    assert.match(state, /unite:\s*"pc"/);
    // No payment-terms setting exists: keep the existing +30 days.
    assert.match(src, /setDate\((dueDate|validDate)\.getDate\(\) \+ 30\)/);
    assert.match(state, /expiration_date: fmtDate\((dueDate|validDate)\)/);
  });

  test(`${page}: the workspace default tax fills the first line and every new line`, () => {
    // First line starts with no tax so useTaxPresets() applies the default...
    assert.match(state, /vta: null, tax_treatment: "taxable"/);
    assert.match(src, /useTaxPresets\(\(\) => state\.carts\)/);
    // ...and lines added later take the same default.
    assert.match(addLine, /qty: 1/);
    assert.match(addLine, /unite: "pc"/);
    assert.match(addLine, /\.\.\.defaultTax\(\)/);
  });

  test(`${page}: no currency field - amounts use the workspace currency`, () => {
    assert.doesNotMatch(state, /currency/);
    assert.match(src, /\$toCurrency\(total\)/);
  });
}
