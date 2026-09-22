import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { parse, compileScript } from '@vue/compiler-sfc';
import { taxFields, taxKey, previewTaxGroups } from '../../resources/js/utils/tax.mjs';

const taxUrl = new URL('../../resources/js/utils/tax.mjs', import.meta.url).href;
const source = readFileSync(new URL('../../resources/js/components/TaxSelect.vue', import.meta.url), 'utf8');
const { descriptor } = parse(source);
const compiled = compileScript(descriptor, { id: 'tax-select-test', inlineTemplate: true }).content
  .replaceAll('"vue"', JSON.stringify(import.meta.resolve('vue')))
  .replaceAll("'vue'", JSON.stringify(import.meta.resolve('vue')))
  .replaceAll("'@/utils/tax.mjs'", JSON.stringify(taxUrl));
const TaxSelect = (await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`)).default;

// Endpoint-shaped fixtures: the UI must render server-provided options, not a local country catalog.
const presets = [
  { code: 'test-20', rate: 20, treatment: 'taxable', label: 'TVA 20%' },
  { code: 'test-10', rate: 10, treatment: 'taxable', label: 'TVA 10%' },
  { code: 'test-exempt', rate: 0, treatment: 'exempt', label: 'Exonéré' },
];

test('service selection copies its configured rate and treatment, including exemption', () => {
  assert.deepEqual(taxFields({ vta: '10.00', tax_treatment: 'taxable' }), { vta: 10, tax_treatment: 'taxable' });
  assert.deepEqual(taxFields({ vta: '0.00', tax_treatment: 'exempt' }), { vta: 0, tax_treatment: 'exempt' });
});

test('legacy zero is never inferred to be exempt', () => {
  assert.deepEqual(taxFields({ vta: 0 }), { vta: 0, tax_treatment: 'taxable' });
  assert.deepEqual(taxFields({ vta: null }), { vta: 0, tax_treatment: 'taxable' });
  assert.notEqual(taxKey({ vta: 0 }), taxKey({ vta: 0, tax_treatment: 'exempt' }));
});

test('mixed Morocco preview includes every rate with discounts applied', () => {
  const groups = previewTaxGroups([{ qty: 1, price: 1000, vta: 20 }, { qty: 1, price: 1000, vta: 10 }]);
  assert.deepEqual(groups.map(g => [g.rate, g.base, g.amount]), [[10, 1000, 100], [20, 1000, 200]]);
  assert.equal(previewTaxGroups([{ qty: 1, price: 5000, discount: 50, vta: 20 }], 10)[0].amount, 450);
});

test('zero-rate preview keeps taxable, exempt and out-of-scope buckets distinct', () => {
  const lines = ['taxable', 'exempt', 'out_of_scope'].map(tax_treatment => ({ qty: 1, price: 5000, vta: 0, tax_treatment }));
  assert.equal(previewTaxGroups(lines).length, 3);
  assert.equal(previewTaxGroups(lines).reduce((sum, g) => sum + g.amount, 0), 0);
});

test('shared selector shows only supplied country presets and selects exemption by treatment', async () => {
  const html = await renderToString(createSSRApp(TaxSelect, { presets, taxName: 'TVA', line: { vta: 0, tax_treatment: 'exempt' } }));
  assert.match(html, /<select value="0\|exempt"/);
  assert.match(html, /TVA 20%/);
  assert.match(html, /Exonéré/);
  assert.doesNotMatch(html, /IVA|21%|4%|saved/);
});

test('saved taxable zero remains visible without being reinterpreted or offered as a new preset', async () => {
  const html = await renderToString(createSSRApp(TaxSelect, { presets, taxName: 'TVA', line: { vta: 0, tax_treatment: 'taxable' } }));
  assert.match(html, /<option value="0\|taxable" disabled/);
  assert.match(html, /TVA 0% \(saved\)/);
  assert.match(html, /value="0\|exempt"/);
});

test('Spain selector renders its supplied IVA choices', async () => {
  const spanish = [21, 10, 4].map(rate => ({ code: `test-${rate}`, rate, treatment: 'taxable', label: `IVA ${rate}%` }));
  const html = await renderToString(createSSRApp(TaxSelect, { presets: spanish, taxName: 'IVA', line: { vta: 21, tax_treatment: 'taxable' } }));
  for (const rate of [21, 10, 4]) assert.match(html, new RegExp(`IVA ${rate}%`));
  assert.doesNotMatch(html, /TVA|Exonéré|20%/);
});

// Mount the composable with Vue's custom renderer so its actual onMounted
// load runs, including delayed API results, without a browser dependency.
const { createRenderer } = await import('vue');
const { useTaxPresets } = await import(`data:text/javascript;base64,${Buffer.from(
  readFileSync(new URL('../../resources/js/composables/useTaxPresets.js', import.meta.url), 'utf8')
    .replace("'vue'", JSON.stringify(import.meta.resolve('vue')))
    .replace("import axios from 'axios';", 'const axios = { get: (...args) => globalThis.taxTestGet(...args) };')
    .replace("'@/utils/tax.mjs'", JSON.stringify(taxUrl))
).toString('base64')}`);
const renderer = createRenderer({
  createComment: () => ({}), insert() {}, remove() {}, parentNode() {}, nextSibling() {},
});
const flush = () => new Promise(resolve => setImmediate(resolve));

test('default loading changes only unset new lines, including after Settings changes', async () => {
  const lines = [{ vta: null }, { vta: 0, tax_treatment: 'taxable' }, { vta: 10, tax_treatment: 'taxable' }];
  let response = { country: 'MA', tax_name: 'TVA', presets, default_code: 'test-exempt' };
  globalThis.taxTestGet = async () => ({ data: response });
  let taxes;
  const app = renderer.createApp({ setup() { taxes = useTaxPresets(() => lines); return () => null; } });
  app.mount({});
  await flush();
  assert.deepEqual(lines, [
    { vta: 0, tax_treatment: 'exempt' },
    { vta: 0, tax_treatment: 'taxable' },
    { vta: 10, tax_treatment: 'taxable' },
  ]);
  response = { ...response, default_code: 'test-20' };
  await taxes.loadTaxes();
  assert.equal(lines[0].tax_treatment, 'exempt');
  assert.equal(lines[2].vta, 10);
  assert.deepEqual(taxes.defaultTax(), { vta: 20, tax_treatment: 'taxable' });
  assert.equal(taxes.taxReady.value, true);
  app.unmount();
  delete globalThis.taxTestGet;
});

test('failed preset loading blocks new-line tax selection and can be retried', async () => {
  const lines = [{ vta: null }];
  globalThis.taxTestGet = async () => { throw new Error('network'); };
  let taxes;
  const app = renderer.createApp({ setup() { taxes = useTaxPresets(() => lines); return () => null; } });
  app.mount({});
  await flush();
  assert.equal(taxes.taxReady.value, false);
  assert.match(taxes.taxError.value, /Unable to load/);
  assert.equal(lines[0].vta, null);
  globalThis.taxTestGet = async () => ({ data: { country: 'MA', tax_name: 'TVA', presets, default_code: 'test-20' } });
  await taxes.loadTaxes();
  assert.deepEqual(lines[0], { vta: 20, tax_treatment: 'taxable' });
  assert.equal(taxes.taxError.value, '');
  app.unmount();
  delete globalThis.taxTestGet;
});
