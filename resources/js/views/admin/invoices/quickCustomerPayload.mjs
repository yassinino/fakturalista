/**
 * Builds the POST /customers payload for the invoice/quote quick-create
 * customer modal. Pure (no Vue) so it can be tested directly.
 *
 *  - company (type 1): name; individual (type 2): last name + optional first name
 *  - optional details: only the fields actually filled in, trimmed, using the
 *    API's real column names (address_billing / city_billing / post_code_billing)
 *  - tax identifier follows the full Customers page's country rule:
 *    ICE only for a Moroccan company, NIF (tax_id) only outside Morocco
 */
export const QUICK_CUSTOMER_OPTIONAL_FIELDS = ['email', 'phone', 'address_billing', 'city_billing', 'post_code_billing'];

export function buildQuickCustomerPayload(form, { isMorocco = false } = {}) {
  const trim = (v) => String(v ?? '').trim();

  const identity = Number(form.type) === 2
    ? { type: 2, first_name: trim(form.first_name) || null, last_name: trim(form.last_name) }
    : { type: 1, name: trim(form.name) };

  const fields = [...QUICK_CUSTOMER_OPTIONAL_FIELDS];
  if (isMorocco && Number(form.type) === 1) fields.push('ice');
  if (!isMorocco) fields.push('tax_id');

  const details = Object.fromEntries(
    fields.map((f) => [f, trim(form[f])]).filter(([, v]) => v !== '')
  );

  return { ...identity, ...details, contacts: [] };
}
