// UI preview only. The backend calculator remains authoritative.
export const taxKey = (line) => `${Number(line.vta)}|${line.tax_treatment ?? 'taxable'}`;

export function taxFields(source) {
  return {
    vta: source ? Number(source.vta ?? 0) : null,
    tax_treatment: source?.tax_treatment ?? 'taxable',
  };
}

export function taxLabel(rate, treatment, name = 'Tax') {
  if (treatment === 'exempt') return 'Exonéré';
  if (treatment === 'out_of_scope') return 'Out of scope';
  return `${name} ${Number(rate)}%`;
}

export function previewTaxGroups(lines, discountRate = 0) {
  const round = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
  const groups = new Map();
  for (const line of lines) {
    const key = taxKey(line);
    const group = groups.get(key) ?? {
      key, rate: Number(line.vta), treatment: line.tax_treatment ?? 'taxable', base: 0,
    };
    const gross = round(Number(line.qty) * Number(line.price));
    group.base += round(gross - round(gross * Number(line.discount || 0) / 100));
    groups.set(key, group);
  }
  return [...groups.values()].map(group => {
    const base = round(group.base * (1 - Number(discountRate || 0) / 100));
    return { ...group, base, amount: round(base * group.rate / 100) };
  }).sort((a, b) => a.rate - b.rate);
}
