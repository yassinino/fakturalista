import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick, defineComponent, h } from 'vue';
import fr from '../../resources/js/i18n/locales/fr.js';
import ar from '../../resources/js/i18n/locales/ar.js';

// Create Invoice / Create Quote final action: ONE primary create button
// (fixed bottom bar, desktop + mobile), explicit label + next-step hint, and
// no double submission (the button stays disabled until the request ends).

const root = new URL('../../resources/js/views/admin/', import.meta.url);
const read = (f) => readFileSync(new URL(f, root), 'utf8');
const toDataUrl = (code) => `data:text/javascript;base64,${Buffer.from(code).toString('base64')}`;

const FORMS = {
  invoice: { form: 'invoices/CreateInvoiceForm.vue', page: 'invoices/create.vue', ns: 'invoices', label: 'createDraftBtn', endpoint: '/invoices', list: '/admin/invoices', key: 'invoice' },
  quote:   { form: 'quotes/CreateQuoteForm.vue',     page: 'quotes/create.vue',   ns: 'quotes',   label: 'createBtn',      endpoint: '/quotes',   list: '/admin/quotes',   key: 'quote' },
};

for (const [doc, cfg] of Object.entries(FORMS)) {
  const src = read(cfg.form);
  const template = src.slice(0, src.indexOf('<script setup>'));

  test(`Create ${doc}: exactly one create action, the primary button in the fixed bottom bar`, () => {
    const saveButtons = template.match(/@click="handleSave"/g) ?? [];
    assert.equal(saveButtons.length, 1, 'top-bar duplicate removed');
    const bar = template.slice(template.indexOf('inv-sticky-footer'));
    assert.match(bar, /class="inv-btn inv-btn-primary"[\s\S]*?data-test="primary-create"[\s\S]*?@click="handleSave"/);
    assert.equal((template.match(/inv-btn-primary/g) ?? []).length, 1, 'one visually dominant action');
    assert.match(bar, new RegExp(`\\$t\\('${cfg.ns}\\.form\\.${cfg.label}'\\)`));
    assert.match(bar, new RegExp(`\\$t\\('${cfg.ns}\\.form\\.nextStepHint'\\)`));
  });

  test(`Create ${doc}: loading state blocks duplicate clicks until the request finishes`, () => {
    assert.match(src, /if \(saving\.value \|\| !taxReady\.value\) return;/, 're-entry guard');
    assert.match(src, /emit\("saveDocument", state, \(success\) => \{\s*saving\.value = false;/, 'only the parent re-enables it');
    assert.doesNotMatch(src, /emit\("saveDocument", state\);\s*saving\.value = false;/, 'no immediate re-enable');
    assert.match(template, /:disabled="saving \|\| !taxReady"/);
    assert.match(template, /:aria-busy="saving \? 'true' : 'false'"/);
    assert.match(template, /<i v-if="saving" class="fa fa-spinner fa-spin me-1"><\/i>/);
  });

  test(`Create ${doc}: action stays reachable on mobile (full-width, hint visible)`, () => {
    const mobile = src.slice(src.indexOf('.inv-footer-action {'));
    assert.match(mobile, /@media \(max-width: 768px\) \{\s*\.inv-footer-action \{ width: 100%;/);
    assert.match(mobile, /\.inv-footer-action \.inv-btn \{ width: 100%;/);
    assert.match(src, /\.inv-sticky-footer \{\s*position: fixed;/);
  });

  test(`Create ${doc}: labels are explicit and translated (FR + AR)`, () => {
    for (const messages of [fr, ar]) {
      assert.ok(messages[cfg.ns].form[cfg.label], `${cfg.label}`);
      assert.ok(messages[cfg.ns].form.nextStepHint, 'nextStepHint');
    }
  });
}

test('invoice label says what the action really does (a draft, not sent)', () => {
  assert.equal(fr.invoices.form.createDraftBtn, 'Créer la facture en brouillon');
  assert.match(fr.invoices.form.nextStepHint, /brouillon/i);
  assert.match(fr.quotes.form.nextStepHint, /pas envoyé/);
});

// ── The parent pages: one POST per save, done() only once it has finished ─

globalThis.document ??= { activeElement: null };
const el = (tag) => ({ tag, props: {}, children: [], parent: null, style: {}, addEventListener() {}, removeEventListener() {}, setAttribute() {} });
const { createApp } = createRenderer({
  createElement: el, createText: (t) => ({ text: t, children: [] }), createComment: (t) => ({ comment: t, children: [] }),
  setText() {}, setElementText() {}, insert: (c, p) => { c.parent = p; p.children.push(c); },
  remove() {}, parentNode: (n) => n.parent, nextSibling: () => null, patchProp: (n, k, _p, v) => { n.props[k] = v; },
});

async function mountPage(cfg, { fail = false, uuid = 'new-doc-uuid' } = {}) {
  const calls = { posts: [], pushes: [], toasts: [] };
  globalThis.__page = { calls, fail, key: cfg.key, uuid };
  let captured = null;
  globalThis.__captureSave = (fn) => { captured = fn; };

  const stubForm = toDataUrl(`import { defineComponent } from ${JSON.stringify(import.meta.resolve('vue'))};
    export default defineComponent({ props: ['onSaveDocument'], setup(props) { globalThis.__captureSave(props.onSaveDocument); return () => null; } });`);
  const fakeAxios = toDataUrl(`export default { post: (url, body) => { globalThis.__page.calls.posts.push(url);
    return new Promise((res, rej) => setTimeout(() => globalThis.__page.fail ? rej({ response: { data: { message: 'Erreur serveur' } } })
      : res({ data: { message: 'OK', ...(globalThis.__page.uuid ? { [globalThis.__page.key]: { uuid: globalThis.__page.uuid } } : {}) } }), 5)); } };`);
  const fakeToaster = toDataUrl(`export function createToaster() { return { success: (m) => globalThis.__page.calls.toasts.push(['success', m]), error: (m) => globalThis.__page.calls.toasts.push(['error', m]) }; }`);
  const fakeRouter = toDataUrl(`export function useRouter() { return { push: (p) => globalThis.__page.calls.pushes.push(p) }; }`);
  const fakeI18n = toDataUrl(`export function useI18n() { return { t: (k) => k }; }`);

  const { descriptor } = parse(read(cfg.page));
  let code = compileScript(descriptor, { id: cfg.page, inlineTemplate: true }).content;
  const formFile = cfg.form.split('/').pop();
  for (const [spec, target] of Object.entries({
    vue: import.meta.resolve('vue'), axios: fakeAxios, '@meforma/vue-toaster': fakeToaster,
    'vue-router': fakeRouter, 'vue-i18n': fakeI18n, [`./${formFile}`]: stubForm,
  })) {
    code = code.replaceAll(`"${spec}"`, JSON.stringify(target)).replaceAll(`'${spec}'`, JSON.stringify(target));
  }
  const Page = (await import(toDataUrl(code))).default;
  createApp(defineComponent({ render: () => h(Page) })).mount(el('root'));
  await nextTick();
  return { calls, save: (state, done) => captured(state, done) };
}

for (const [doc, cfg] of Object.entries(FORMS)) {
  test(`Create ${doc} page: one request per save, then opens the NEW ${doc} (not the list)`, async () => {
    const { calls, save } = await mountPage(cfg);
    const done = [];
    const pending = save({ customer_id: 'c1' }, (success) => { done.push([success, calls.pushes.length]); });
    assert.equal(calls.posts.length, 1);
    assert.equal(done.length, 0, 'still saving while the request is in flight');
    await pending;
    assert.deepEqual(done, [[true, 0]], 'done(true) once, BEFORE the redirect');
    assert.deepEqual(calls.pushes, [{ path: `${cfg.list}/edit/new-doc-uuid`, query: { created: '1' } }], 'the id returned by the API');
  });

  test(`Create ${doc} page: falls back to the list if the response has no id`, async () => {
    const { calls, save } = await mountPage(cfg, { uuid: null });
    await save({ customer_id: 'c1' }, () => {});
    assert.deepEqual(calls.pushes, [cfg.list]);
    assert.deepEqual(calls.toasts, [['success', 'OK']]);
  });

  test(`Create ${doc} page: a failed save shows an error and re-enables the button (no silent failure)`, async () => {
    const { calls, save } = await mountPage(cfg, { fail: true });
    const done = [];
    await save({ customer_id: 'c1' }, (success) => { done.push(success); });
    assert.deepEqual(done, [false], 'failed save: re-enabled, still unsaved');
    assert.deepEqual(calls.pushes, []);
    assert.deepEqual(calls.toasts, [['error', 'Erreur serveur']]);
  });
}
