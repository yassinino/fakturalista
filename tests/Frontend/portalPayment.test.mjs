import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createI18n } from 'vue-i18n';
import fr from '../../resources/js/i18n/locales/fr.js';
import ar from '../../resources/js/i18n/locales/ar.js';
import {
  POLL_INTERVAL_MS,
  POLL_MAX_ATTEMPTS,
  readPaymentReturn,
  paidInvoiceUuids,
  paymentConfirmation,
  safeCheckoutUrl,
  paymentErrorKey,
} from '../../resources/js/views/portal/portalPayment.mjs';

// Client Portal Step 6B - "Pay now" UI. Same approach as tax.test.mjs: the
// real SFC compiled with @vue/compiler-sfc and server-rendered, no browser.

const source = readFileSync(new URL('../../resources/js/views/portal/PortalInvoiceActions.vue', import.meta.url), 'utf8');
const { descriptor } = parse(source);
const compiled = compileScript(descriptor, { id: 'portal-invoice-actions-test', inlineTemplate: true }).content
  .replaceAll('"vue"', JSON.stringify(import.meta.resolve('vue')))
  .replaceAll("'vue'", JSON.stringify(import.meta.resolve('vue')))
  .replaceAll("'vue-i18n'", JSON.stringify(import.meta.resolve('vue-i18n')));
const PortalInvoiceActions = (await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`)).default;

function render(props, locale = 'fr') {
  const app = createSSRApp(PortalInvoiceActions, props);
  app.use(createI18n({ legacy: false, locale, messages: { fr, ar } }));
  return renderToString(app);
}

// Portal-API-shaped fixtures (what GET /api/portal/{token} returns).
const invoice = (overrides = {}) => ({
  uuid: 'inv-1', number: 'FAC-2026-001', total: 4500, currency: 'MAD', status: 'issued',
  paid_at: null, payment: { payable: true, provider: 'stripe' }, ...overrides,
});

// ── Pay now rendering ──────────────────────────────────────────────────

test('Pay now is rendered for a payable invoice, next to the PDF and UBL downloads', async () => {
  for (const variant of ['row', 'card']) {
    const html = await render({ invoice: invoice(), variant });
    assert.match(html, /data-test="pay-now"/, variant);
    assert.match(html, /Payer maintenant/, variant);
    assert.match(html, /data-test="download-pdf"/, variant);
    assert.match(html, /data-test="download-ubl"/, variant);
  }
});

test('Pay now is never rendered when the server says the invoice is not payable', async () => {
  const cases = [
    invoice({ status: 'paid', paid_at: '2026-09-20', payment: { payable: false, provider: null } }),
    invoice({ status: 'cancelled', payment: { payable: false, provider: null } }),
    invoice({ payment: { payable: false, provider: null } }), // e.g. seller without Stripe Connect
    invoice({ payment: undefined }),                          // older API response without the field
  ];

  for (const inv of cases) {
    for (const variant of ['row', 'card']) {
      const html = await render({ invoice: inv, variant });
      assert.doesNotMatch(html, /data-test="pay-now"/);
      // PDF/UBL stay available, including for paid invoices.
      assert.match(html, /data-test="download-pdf"/);
      assert.match(html, /data-test="download-ubl"/);
    }
  }
});

test('Pay now is disabled while a Checkout is being created, and translated (RTL locale)', async () => {
  assert.match(await render({ invoice: invoice(), payBusy: true }), /<button[^>]*class="pt-pay-btn[^"]*"[^>]*disabled/);
  assert.match(await render({ invoice: invoice() }, 'ar'), /ادفع الآن/);
});

// ── Return from Stripe: never marks anything paid locally ───────────────

test('only ?payment=success|cancelled are recognised', () => {
  assert.equal(readPaymentReturn({ payment: 'success' }), 'success');
  assert.equal(readPaymentReturn({ payment: 'cancelled' }), 'cancelled');
  assert.equal(readPaymentReturn({ payment: ['success'] }), 'success');
  assert.equal(readPaymentReturn({ payment: 'paid' }), null);
  assert.equal(readPaymentReturn({}), null);
});

test('?payment=success alone never confirms: only the server status does', () => {
  const unpaid = Object.freeze([Object.freeze(invoice())]);

  assert.equal(paymentConfirmation(unpaid, { targetUuid: 'inv-1' }), 'waiting');
  assert.equal(paymentConfirmation(unpaid, { paidBefore: paidInvoiceUuids(unpaid) }), 'waiting');
  // Frozen inputs: the helpers never write an invoice (would throw in strict ESM).
  assert.equal(unpaid[0].status, 'issued');

  const paidByWebhook = [invoice({ status: 'paid', paid_at: '2026-09-29', payment: { payable: false, provider: null } })];
  assert.equal(paymentConfirmation(paidByWebhook, { targetUuid: 'inv-1' }), 'confirmed');
});

test('without a hint, an invoice that was already paid never counts as this payment', () => {
  const before = [invoice({ uuid: 'old', status: 'paid' }), invoice({ uuid: 'new' })];
  const paidBefore = paidInvoiceUuids(before);

  assert.equal(paymentConfirmation(before, { paidBefore }), 'waiting');
  assert.equal(paymentConfirmation([before[0], { ...before[1], status: 'paid' }], { paidBefore }), 'confirmed');
  // Unknown hint (invoice not in the list) falls back to the same rule.
  assert.equal(paymentConfirmation(before, { targetUuid: 'gone', paidBefore }), 'waiting');
});

test('polling is bounded to roughly 15-20 seconds', () => {
  const total = POLL_INTERVAL_MS * POLL_MAX_ATTEMPTS;
  assert.ok(total >= 15000 && total <= 20000, `${total}ms`);
});

test('only an https Checkout URL is ever followed', () => {
  assert.equal(safeCheckoutUrl('https://checkout.stripe.com/c/pay/cs_test_1'), 'https://checkout.stripe.com/c/pay/cs_test_1');
  for (const bad of ['javascript:alert(1)', 'http://checkout.stripe.com/x', '//evil.test', '', null, undefined, 42]) {
    assert.equal(safeCheckoutUrl(bad), null, String(bad));
  }
});

test('only known backend reasons map to specific messages; everything else is generic', () => {
  for (const reason of ['already_paid', 'not_payable', 'currency_unsupported', 'unavailable', 'in_progress']) {
    const key = paymentErrorKey(reason);
    assert.equal(key, `portal.payment.errors.${reason}`);
    assert.ok(fr.portal.payment.errors[reason] && ar.portal.payment.errors[reason], reason);
  }
  assert.equal(paymentErrorKey('Stripe error: card_declined raw'), 'portal.payment.errors.generic');
  assert.equal(paymentErrorKey(undefined), 'portal.payment.errors.generic');
});

test('the portal view never sets an invoice paid or payable on its own', () => {
  const view = readFileSync(new URL('../../resources/js/views/portal/ClientPortalView.vue', import.meta.url), 'utf8');
  assert.doesNotMatch(view, /\.status\s*=\s*['"]paid['"]/);
  assert.doesNotMatch(view, /payable\s*=\s*(true|false)/);
  assert.doesNotMatch(view, /paid_at\s*=/);
  // The payment request carries no amount/currency/account.
  assert.match(view, /\/payment\/stripe'\n\s*\);/);
});
