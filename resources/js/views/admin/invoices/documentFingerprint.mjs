/**
 * Unsaved-change detection for Create Invoice / Create Quote.
 *
 * A fingerprint of the MEANINGFUL document data only, so the automatic
 * defaults never count as "changes": today's dates, qty 1, unit "pc" and the
 * workspace default tax (which arrives asynchronously, after the form has
 * opened - a line's tax only counts when it differs from that default).
 * Totals are derived and ignored; UI state (modals, "Plus d'options") is not
 * part of the document at all.
 *
 * dirty === fingerprint(now) !== fingerprint(at mount)
 */
export function documentFingerprint(state, defaultTax = { vta: null, tax_treatment: 'taxable' }) {
  const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));
  const isDefaultTax = (line) =>
    (line.vta === null || line.vta === undefined)
    || (num(line.vta) === num(defaultTax?.vta) && (line.tax_treatment ?? 'taxable') === (defaultTax?.tax_treatment ?? 'taxable'));

  return JSON.stringify({
    customer: state.customer_id || '',
    date: state.date || '',
    due: state.expiration_date || '',
    status: state.status ?? '',
    note: (state.note ?? '').trim(),
    operation: (state.descripcion_operacion ?? '').trim(),
    discount: num(state.discount_rate) ?? 0,
    lines: (state.carts ?? []).map((line) => ({
      item: line.item_id ? String(line.item_id) : '',
      text: (line.description ?? '').trim(),
      qty: num(line.qty),
      unit: line.unite || 'pc',
      price: num(line.price) ?? 0,
      discount: num(line.discount) ?? 0,
      tax: isDefaultTax(line) ? 'default' : [num(line.vta), line.tax_treatment ?? 'taxable'],
    })),
  });
}
