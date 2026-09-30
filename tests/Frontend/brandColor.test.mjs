import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
  LIGHT_TEXT,
  DARK_TEXT,
  normalizeHexColor,
  contrastRatio,
  readableTextOn,
  accentCssVars,
} from '../../resources/js/views/portal/brandColor.mjs';

// Client Portal Step 6C - readable text on the company's brand colour.

test('light brand colours get dark text', () => {
  for (const light of ['#FFFFFF', '#FFEB3B', '#F5F5DC', '#A7F3D0', '#FDE68A', '#fff']) {
    assert.equal(readableTextOn(light), DARK_TEXT, light);
  }
});

test('dark brand colours get light text', () => {
  for (const dark of ['#000000', '#1E3A8A', '#6D28D9', '#B91C1C', '#14532D', '#123']) {
    assert.equal(readableTextOn(dark), LIGHT_TEXT, dark);
  }
});

test('the chosen text colour is always the more readable one, and readable', () => {
  for (const bg of ['#E4287C', '#FF9900', '#00A3E0', '#7F7F7F', '#10B981', '#FACC15', '#0EA5E9']) {
    const chosen = readableTextOn(bg);
    const other = chosen === LIGHT_TEXT ? DARK_TEXT : LIGHT_TEXT;
    const hex = normalizeHexColor(bg);
    assert.ok(contrastRatio(hex, chosen) >= contrastRatio(hex, other), bg);
    // The better of white / dark ink is always >= 3:1 (WCAG AA for bold
    // button text). Mid-tones (e.g. the default #E4287C: 4.29) can't reach
    // 4.5:1 with either - that's a property of the colour, not the helper.
    assert.ok(contrastRatio(hex, chosen) >= 3, `${bg}: ${contrastRatio(hex, chosen).toFixed(2)}`);
  }
});

test('invalid or hostile brand colours are ignored safely', () => {
  for (const bad of [null, undefined, '', 'red', '#12', '#GGGGGG', '#1234567', 'url(javascript:alert(1))', '#fff; color: red', 42, {}]) {
    assert.equal(normalizeHexColor(bad), null, String(bad));
    assert.equal(readableTextOn(bad), null, String(bad));
    // Nothing is emitted, so the portal keeps its default accent + white text.
    assert.deepEqual(accentCssVars(bad), {}, String(bad));
  }
});

test('a valid brand colour yields a normalized accent and its text colour', () => {
  assert.deepEqual(accentCssVars('  #ffeb3b '), { '--pt-accent': '#FFEB3B', '--pt-on-accent': DARK_TEXT });
  assert.deepEqual(accentCssVars('#1e3a8a'), { '--pt-accent': '#1E3A8A', '--pt-on-accent': LIGHT_TEXT });
});

test('primary portal actions use the contrast-checked text colour, not hard-coded white', () => {
  const view = readFileSync(new URL('../../resources/js/views/portal/ClientPortalView.vue', import.meta.url), 'utf8');
  const actions = readFileSync(new URL('../../resources/js/views/portal/PortalInvoiceActions.vue', import.meta.url), 'utf8');

  for (const [name, css] of [['ClientPortalView', view], ['PortalInvoiceActions', actions]]) {
    // Every rule that paints the accent as a background sets text via --pt-on-accent.
    const rules = css.match(/[^{}]*\{[^{}]*background:\s*var\(--pt-accent\)[^{}]*\}/g) ?? [];
    assert.ok(rules.length > 0, name);
    for (const rule of rules) {
      assert.match(rule, /color:\s*var\(--pt-on-accent/, `${name}: ${rule.trim().split('{')[0]}`);
    }
  }
  assert.match(view, /accentCssVars\(data\.value\?\.company\?\.brand_color\)/);
});
