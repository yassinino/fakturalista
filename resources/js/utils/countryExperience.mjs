// Country comes from TenantContextService's API response, never the UI language.
export const isSpain = (company) => company?.country === 'ES';
export const isMorocco = (company) => company?.country === 'MA';

export function requestsVerifactu(route) {
  return route.hash?.toLowerCase() === '#verifactu'
    || String(route.query?.section ?? '').toLowerCase() === 'verifactu'
    || /\/settings\/verifactu\/?$/i.test(route.path ?? '');
}

export function canOpenSettingsSection(company, section) {
  return section !== 'verifactu' || isSpain(company);
}
