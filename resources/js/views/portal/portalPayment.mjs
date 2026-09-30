/**
 * Client Portal Step 6B - pure helpers for the "Pay now" return flow.
 *
 * The backend (signed Stripe Connect webhook) is the only thing that ever
 * marks an invoice paid. Nothing here changes an invoice: it only reads
 * what the portal API returned and decides what to SHOW. Kept framework-
 * free so tests/Frontend can exercise it directly with node:test.
 */

// Re-fetch every 2s, at most 10 times (~20s), then stop and say so.
export const POLL_INTERVAL_MS = 2000;
export const POLL_MAX_ATTEMPTS = 10;

/** 'success' | 'cancelled' | null from the route's ?payment= value. */
export function readPaymentReturn(query) {
  const value = Array.isArray(query?.payment) ? query.payment[0] : query?.payment;
  return value === 'success' || value === 'cancelled' ? value : null;
}

/** Uuids of invoices the server currently reports as paid. */
export function paidInvoiceUuids(invoices) {
  return new Set((invoices ?? []).filter((invoice) => invoice.status === 'paid').map((invoice) => invoice.uuid));
}

/**
 * 'confirmed' once the SERVER reports the payment:
 *  - the invoice the customer started paying (a UX hint kept in
 *    sessionStorage) is now status 'paid', or
 *  - without that hint (other device, cleared storage): some invoice that
 *    wasn't paid when the customer came back is paid now.
 * Otherwise 'waiting'. Never infers "paid" from ?payment=success itself.
 */
export function paymentConfirmation(invoices, { targetUuid = null, paidBefore = new Set() } = {}) {
  const list = invoices ?? [];

  if (targetUuid && list.some((invoice) => invoice.uuid === targetUuid)) {
    return list.some((invoice) => invoice.uuid === targetUuid && invoice.status === 'paid') ? 'confirmed' : 'waiting';
  }

  return list.some((invoice) => invoice.status === 'paid' && !paidBefore.has(invoice.uuid)) ? 'confirmed' : 'waiting';
}

/** Only ever redirect to an https URL (the Stripe Checkout URL the API returned). */
export function safeCheckoutUrl(url) {
  try {
    return typeof url === 'string' && new URL(url).protocol === 'https:' ? url : null;
  } catch {
    return null;
  }
}

/** Known, safe reasons the payment endpoint may answer with. */
export const PAYMENT_ERROR_REASONS = ['already_paid', 'not_payable', 'currency_unsupported', 'unavailable', 'in_progress'];

export function paymentErrorKey(reason) {
  return PAYMENT_ERROR_REASONS.includes(reason) ? `portal.payment.errors.${reason}` : 'portal.payment.errors.generic';
}

/** sessionStorage key for "which invoice did I just start paying" (UX hint only). */
export function paymentHintKey(token) {
  return `fk-portal-pay:${token}`;
}
