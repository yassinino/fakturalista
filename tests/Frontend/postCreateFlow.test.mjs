import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createI18n } from 'vue-i18n';
import fr from '../../resources/js/i18n/locales/fr.js';
import ar from '../../resources/js/i18n/locales/ar.js';

// After "Create": the new invoice/quote page opens with a one-time
// "created → here it is → next steps" banner. Nothing is issued/sent.

const admin = new URL('../../resources/js/views/admin/', import.meta.url);
const read = (f) => readFileSync(new URL(f, admin), 'utf8');
const toDataUrl = (code) => `data:text/javascript;base64,${Buffer.from(code).toString('base64')}`;

const { descriptor } = parse(read('invoices/DocumentCreatedBanner.vue'));
const Banner = (await import(toDataUrl(compileScript(descriptor, { id: 'dc', inlineTemplate: true }).content
  .replaceAll('"vue"', JSON.stringify(import.meta.resolve('vue')))))).default;

async function render(props, locale = 'fr') {
  const app = createSSRApp(Banner, props);
  const i18n = createI18n({ legacy: false, locale, messages: { fr, ar } });
  app.use(i18n);
  return { html: await renderToString(app), t: i18n.global.t };
}

test('invoice banner: "created as draft, not sent" + the page\'s real next actions', async () => {
  const { t } = await render({ title: 'x', text: 'y' });
  const text = t('invoices.form.createdNextSteps', { issue: t('invoices.actionIssue'), send: t('invoices.sendAndSave') });
  const { html } = await render({ title: t('invoices.form.createdTitle'), text });
  assert.match(html, /Facture créée en brouillon/);
  assert.match(html, /n&#39;a pas été envoyée|n'a pas été envoyée/);
  assert.match(html, /Émettre/);
  assert.match(html, /Enregistrer et envoyer/);
  assert.match(html, /role="status"/);
  assert.match(html, /data-test="document-created-dismiss"/);
});

test('quote banner names the existing Send action; translated (Arabic)', async () => {
  const { t } = await render({ title: 'x', text: 'y' }, 'ar');
  const { html } = await render({ title: t('quotes.form.createdTitle'), text: t('quotes.form.createdNextSteps', { send: t('quotes.sendAndSave') }) }, 'ar');
  assert.match(html, /تم إنشاء عرض السعر/);
  assert.match(html, new RegExp(t('quotes.sendAndSave')));
});

for (const [doc, form, page, ns, actions] of [
  ['Invoice', 'invoices/EditInvoiceForm.vue', 'invoices/edit.vue', 'invoices', ['invoices.actionIssue', 'invoices.sendAndSave']],
  ['Quote', 'quotes/EditQuoteForm.vue', 'quotes/edit.vue', 'quotes', ['quotes.sendAndSave']],
]) {
  test(`${doc} page: banner only right after creation, dismissible, naming the same buttons the page shows`, () => {
    const f = read(form);
    assert.match(f, /justCreated: \{ type: Boolean, default: false \}/);
    assert.match(f, /<DocumentCreatedBanner\s+v-if="justCreated && !createdBannerDismissed"/);
    assert.match(f, /@dismiss="createdBannerDismissed = true"/);
    const topbar = f.slice(f.indexOf('inv-topbar-actions'), f.indexOf('<DocumentCreatedBanner'));
    for (const key of actions) {
      assert.ok(topbar.includes(`$t('${key}')`), `${key} is an existing button on the page`);
      assert.ok(f.slice(f.indexOf('<DocumentCreatedBanner')).includes(`$t('${key}')`), `banner names ${key}`);
    }
  });

  test(`${doc} page: reads ?created=1 once, clears it, and does nothing automatically`, () => {
    const p = read(page);
    assert.match(p, /const justCreated = route\.query\.created === '1';/);
    assert.match(p, /if \(justCreated\) router\.replace\(\{ query: \{\} \}\)/, 'a refresh will not show it again');
    assert.match(p, /:just-created="justCreated"/);
    // No issue / send / status change triggered by arriving here.
    const f = read(form);
    for (const src of [p, f]) {
      assert.doesNotMatch(src, /justCreated[^\n]*(handleIssue|handleSendAndSave|\/issue|\/send|status\s*=)/);
    }
    const onMounted = p.slice(p.indexOf('onMounted('), p.indexOf('});', p.indexOf('onMounted(')));
    assert.doesNotMatch(onMounted, /axios\.post/, 'only loads the document');
  });
}

test('create pages open the new document by the id from the API (not the list), after clearing protection', () => {
  for (const [page, key, base] of [['invoices/create.vue', 'invoice', '/admin/invoices'], ['quotes/create.vue', 'quote', '/admin/quotes']]) {
    const p = read(page);
    assert.match(p, new RegExp(`const uuid = res\\.data\\?\\.${key}\\?\\.uuid;`));
    assert.ok(p.includes(`router.push(uuid ? { path: "${base}/edit/" + uuid, query: { created: "1" } } : "${base}")`));
    assert.ok(p.indexOf('done?.(true);') < p.indexOf('router.push('), 'unsaved protection cleared first');
  }
});
