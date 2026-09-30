/**
 * Client Portal Step 6C - safe use of the company's brand_color as the
 * background of primary actions (Pay now, Accept quote, confirm buttons).
 *
 * The brand colour is tenant-controlled: it is only ever used after
 * validation, and the text drawn on it is whichever of white / the
 * portal's dark ink has the higher WCAG contrast ratio - so a pale yellow
 * brand colour gets dark text instead of unreadable white.
 */

export const LIGHT_TEXT = '#FFFFFF';
export const DARK_TEXT = '#1A1B25'; // the portal's own --pt-text

/** '#abc' / '#AABBCC' (surrounding spaces allowed) -> '#AABBCC'; anything else -> null. */
export function normalizeHexColor(value) {
  if (typeof value !== 'string') return null;
  const match = value.trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
  if (!match) return null;
  const hex = match[1].length === 3 ? match[1].replace(/./g, (c) => c + c) : match[1];
  return '#' + hex.toUpperCase();
}

/** WCAG 2.x relative luminance of a normalized '#RRGGBB'. */
export function relativeLuminance(hex) {
  const channels = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
  const [r, g, b] = channels.map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** WCAG contrast ratio between two normalized colours (1..21). */
export function contrastRatio(a, b) {
  const [hi, lo] = [relativeLuminance(a), relativeLuminance(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

/** The more readable of LIGHT_TEXT / DARK_TEXT on this background (null if invalid). */
export function readableTextOn(background) {
  const hex = normalizeHexColor(background);
  if (!hex) return null;
  return contrastRatio(hex, LIGHT_TEXT) >= contrastRatio(hex, DARK_TEXT) ? LIGHT_TEXT : DARK_TEXT;
}

/**
 * CSS custom properties for the portal root: `--pt-accent` + `--pt-on-accent`
 * for a valid brand colour; nothing for a missing/invalid one, so the
 * portal's built-in default accent (and its white text) stays as is.
 */
export function accentCssVars(brandColor) {
  const hex = normalizeHexColor(brandColor);
  return hex ? { '--pt-accent': hex, '--pt-on-accent': readableTextOn(hex) } : {};
}
