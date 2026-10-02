import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse, compileStyle } from '@vue/compiler-sfc';

// Light/Dark theme regression guard.
//
// The app has ONE theme switch: the .dark-mode class on #page-container
// (BaseLayout.vue), also put on teleported content via useThemeScope().
// Vue 3.2's scoped-CSS compiler turns `:global(.dark-mode) .x { … }` into a
// bare `.dark-mode { … }` rule - it silently drops everything after
// :global(...). One such rule (`opacity: .5` meant for a disabled TaxSelect)
// faded the whole #page-container in dark mode, which read as a grey veil over
// the entire UI. These tests compile every component's styles the way Vite
// does and fail if any of them would restyle the theme/RTL roots themselves.

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const srcDir = join(root, 'resources/js');

const walk = (dir) => readdirSync(dir).flatMap((name) => {
  const p = join(dir, name);
  return statSync(p).isDirectory() ? walk(p) : [p];
});
const vueFiles = walk(srcDir).filter((p) => p.endsWith('.vue'));

// Every <style> block, compiled exactly as the build does (scoped id and all).
const compiled = vueFiles.flatMap((file) => {
  const { descriptor } = parse(readFileSync(file, 'utf8'), { filename: file });
  return descriptor.styles.map((style, i) => {
    const source = style.src ? readFileSync(join(dirname(file), style.src), 'utf8') : style.content;
    const { code } = compileStyle({
      source,
      filename: file,
      id: `data-v-test${i}`,
      scoped: !!style.scoped,
      preprocessLang: style.lang,
    });
    return { file: relative(root, file), scoped: !!style.scoped, code };
  });
});

const stripComments = (css) => css.replace(/\/\*[\s\S]*?\*\//g, '');
// Selector lists of top-level and nested rules (good enough for flat SFC CSS).
const selectorsOf = (css) => [...stripComments(css).matchAll(/(^|[{};])\s*([^{};@][^{};]*?)\s*\{/g)]
  .flatMap((m) => m[2].split(',').map((s) => s.trim()))
  .filter(Boolean);

test('the SFC set was found and compiled', () => {
  assert.ok(vueFiles.length > 50, `only ${vueFiles.length} .vue files found`);
  assert.ok(compiled.some((c) => c.scoped));
});

test('no component style compiles to a bare .dark-mode / .rtl-support / #page-container rule', () => {
  const bare = /^(\.dark-mode|\.rtl-support|#page-container|\.page-header-dark|\.sidebar-dark)$/;
  const offenders = compiled.flatMap(({ file, code }) =>
    selectorsOf(code).filter((sel) => bare.test(sel)).map((sel) => `${file}: "${sel} { … }"`));
  assert.deepEqual(offenders, [], 'these rules would restyle the whole app container:\n' + offenders.join('\n'));
});

// True when something follows a :global(...) group in the selector (the part
// Vue 3.2 drops). Balanced-paren scan, so :global(.a:has(.b)) isn't misread.
const lossy = { test(sel) {
  for (let i = sel.indexOf(':global('); i !== -1; i = sel.indexOf(':global(', i + 1)) {
    let depth = 0, j = i + ':global'.length;
    for (; j < sel.length; j++) {
      if (sel[j] === '(') depth++;
      else if (sel[j] === ')' && --depth === 0) break;
    }
    if (sel.slice(j + 1).trim() !== '') return true;
  }
  return false;
} };

test('scoped styles never use the lossy `:global(X) Y` form', () => {
  const offenders = [];
  for (const file of vueFiles) {
    const { descriptor } = parse(readFileSync(file, 'utf8'), { filename: file });
    for (const style of descriptor.styles.filter((s) => s.scoped)) {
      const source = style.src ? readFileSync(join(dirname(file), style.src), 'utf8') : style.content;
      for (const sel of selectorsOf(source)) {
        if (lossy.test(sel)) offenders.push(`${relative(root, file)}: ${sel}`);
      }
    }
  }
  assert.deepEqual(offenders, [], 'write `.dark-mode .x` (ancestor form) instead:\n' + offenders.join('\n'));
});

test('dark rules written in the ancestor form keep their component scope', () => {
  // e.g. `.dark-mode .tax-select:disabled` -> `.dark-mode .tax-select[data-v-…]:disabled`
  const tax = compiled.find((c) => c.file.endsWith('components/TaxSelect.vue') && c.scoped);
  assert.ok(tax, 'TaxSelect.vue scoped style not found');
  assert.match(tax.code, /\.dark-mode \.tax-select\[data-v-test\d+\]:disabled\s*\{/);
  assert.doesNotMatch(tax.code, /(^|[};])\s*\.dark-mode\s*\{/);
});

test('teleported menus/modals carry the theme class via useThemeScope()', () => {
  const teleporting = vueFiles.filter((f) => /<Teleport\b/.test(readFileSync(f, 'utf8')));
  assert.ok(teleporting.length > 0);
  for (const file of teleporting) {
    const src = readFileSync(file, 'utf8');
    assert.match(src, /class="theme-scope"\s+:class="themeScope"/, `${relative(root, file)}: teleported content has no theme-scope wrapper`);
    assert.match(src, /useThemeScope\(\)/, `${relative(root, file)}: useThemeScope() not called`);
  }
});
