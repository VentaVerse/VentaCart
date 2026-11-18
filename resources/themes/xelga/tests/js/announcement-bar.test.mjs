// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The Announcement bar in the editor's catalog: a page section the header
 * band and the footer band may also hold, offered in Text and trust right
 * after Text.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

const { useLayout, catalogRules } = await import('../../editor/js/modules/board/model.js');
const { addableTypes, pickerGroups } = await import('../../editor/js/modules/board/choices.js');
const { setCatalog, allowedIn } = await import('../../editor/js/modules/catalog.js');

const here = dirname(fileURLToPath(import.meta.url));
const LAYOUT = JSON.parse(readFileSync(join(here, '../fixtures/layout/_layout.json'), 'utf8'));
useLayout(LAYOUT);

const load = (name) => {
  const raw = JSON.parse(readFileSync(join(here, `../../schemas/sections/${name}.json`), 'utf8'));
  return { ...raw, span: { min: raw.span?.min ?? 12, variants: {} } };
};
const CATALOG = Object.fromEntries(['announcement-bar', 'header', 'footer', 'rich-text', 'usp-bar', 'filters'].map((n) => [n, load(n)]));
setCatalog({ sections: CATALOG });
const RULES = catalogRules(LAYOUT, (type) => CATALOG[type] || null, true);

test('the bar goes in the header band, the footer band and on any page', () => {
  const bar = CATALOG['announcement-bar'];
  assert.equal(allowedIn(bar, null, false, false, 'header'), true);
  assert.equal(allowedIn(bar, null, false, false, 'footer'), true);
  assert.equal(allowedIn(bar, 'product', false), true);
  assert.equal(allowedIn(bar, 'home', false), true);
  assert.equal(allowedIn(bar, 'product', true), true, 'A custom template too.');
  assert.equal(allowedIn(bar, 'sample_page', false, true), true, 'An extension page too.');
});

test('a band holds its own sections and the ones that list it, never a page-only section', () => {
  assert.equal(allowedIn(CATALOG.header, null, false, false, 'header'), true);
  assert.equal(allowedIn(CATALOG.header, null, false, false, 'footer'), false);
  assert.equal(allowedIn(CATALOG.footer, null, false, false, 'footer'), true);
  assert.equal(allowedIn(CATALOG['rich-text'], 'home', false, false, 'header'), false, 'The page arguments are not read for a band.');
  assert.equal(allowedIn(CATALOG.header, 'home', false), false, 'A header section is never added to a page.');
});

test('Filters stays on category pages', () => {
  assert.equal(allowedIn(CATALOG.filters, 'category', false), true);
  assert.equal(allowedIn(CATALOG.filters, 'product', false), false);
  assert.equal(allowedIn(CATALOG.filters, null, false, false, 'header'), false);
});

test('the picker offers the bar in Text and trust, right after Text', () => {
  const page = { schema: 1, order: [], sections: {} };
  const types = { 'announcement-bar': CATALOG['announcement-bar'], 'rich-text': CATALOG['rich-text'], 'usp-bar': CATALOG['usp-bar'] };
  const groups = pickerGroups(addableTypes(page, { drop: 'gap', before: 'end' }, types, RULES), types);
  const text = groups.find((g) => g.name === 'Text and trust');
  assert.deepEqual(text.types.map((t) => t.type), ['rich-text', 'announcement-bar', 'usp-bar']);
});
