// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The Divider in the editor: the picker offers it among the text and trust
 * types, every layout has a drawing, the board's box draws the divider's own
 * label, and Edge to edge is greyed where the section shares its row.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

const { useLayout, catalogRules, sharesRow } = await import('../../editor/js/modules/board/model.js');
const { addableTypes, pickerGroups } = await import('../../editor/js/modules/board/choices.js');
const { setCatalog, variantDrawing, sectionDef } = await import('../../editor/js/modules/catalog.js');
const { sketchOf } = await import('../../editor/js/modules/board/sketch.js');

const here = dirname(fileURLToPath(import.meta.url));
const LAYOUT = JSON.parse(readFileSync(join(here, '../fixtures/layout/_layout.json'), 'utf8'));
useLayout(LAYOUT);

/* The theme's own schemas, shaped as the boot payload carries them. */
const load = (name) => {
  const raw = JSON.parse(readFileSync(join(here, `../../schemas/sections/${name}.json`), 'utf8'));
  return { ...raw, span: { min: raw.span?.min ?? 12, variants: {} } };
};
const CATALOG = Object.fromEntries(['divider', 'rich-text', 'usp-bar', 'testimonials', 'blog-posts'].map((n) => [n, load(n)]));
setCatalog({ sections: CATALOG });
const RULES = catalogRules(LAYOUT, (type) => CATALOG[type] || null, true);

const row = (id, split, columns) => ({ id, split, columns, settings: { ...LAYOUT.row_defaults } });
const page = () => ({
  schema: 1,
  order: ['d-row', 'd-col', 't', 'd-side'],
  sections: {
    'd-row': { type: 'divider', label: 'Divider', disabled: false, settings: {}, block_order: [], blocks: {} },
    'd-col': { type: 'divider', label: 'Divider', disabled: false, settings: {}, block_order: [], blocks: {} },
    t: { type: 'rich-text', label: 'Text', disabled: false, settings: {}, block_order: [], blocks: {} },
    'd-side': { type: 'divider', label: 'Divider', disabled: false, settings: {}, block_order: [], blocks: {} },
  },
  rows: [row('r1', '12', [['d-row']]), row('r2', '6-6', [['d-col'], ['t']])],
  sides: { left: ['d-side'] },
});

test('the picker offers the divider in Text and trust, after the text types', () => {
  const groups = pickerGroups(addableTypes(page(), { drop: 'gap', before: 'end' }, CATALOG, RULES), CATALOG);
  const text = groups.find((g) => g.name === 'Text and trust');
  assert.ok(text, 'Text and trust is offered.');
  assert.deepEqual(text.types.map((t) => t.type), ['rich-text', 'usp-bar', 'testimonials', 'blog-posts', 'divider']);
});

test('every layout of the divider has a drawing and a name', () => {
  assert.deepEqual(CATALOG.divider.variants.map((v) => v.label), ['Line', 'Line with a label', 'Dots', 'Space only']);
  CATALOG.divider.variants.forEach((v) => assert.ok(variantDrawing('divider', v.value), `${v.value} is drawn.`));
  const drawings = CATALOG.divider.variants.map((v) => variantDrawing('divider', v.value));
  assert.equal(new Set(drawings).size, 4, 'No two layouts share a drawing.');
});

test('the board box draws the label of a labelled divider, the layout drawing otherwise', () => {
  assert.deepEqual(sketchOf('divider', 'label', 'Our picks'), { label: 'Our picks' });
  assert.deepEqual(sketchOf('divider', 'label', '  New in  '), { label: 'New in' });
  assert.deepEqual(sketchOf('divider', 'label', ''), { drawing: variantDrawing('divider', 'label') });
  assert.deepEqual(sketchOf('divider', 'dots', 'Our picks'), { drawing: variantDrawing('divider', 'dots') });
  assert.deepEqual(sketchOf('rich-text', 'center', 'Our picks'), { drawing: variantDrawing('rich-text', 'center') });
});

test('Edge to edge is the only width that needs a row of its own', () => {
  const width = sectionDef('divider').settings.find((s) => s.key === 'width');
  assert.deepEqual(width.options.filter((o) => o.own_row).map((o) => o.value), ['edge']);
});

test('a divider shares its row in a column or a side, not on a row of its own', () => {
  const c = page();
  assert.equal(sharesRow(c, 'd-row', RULES), false);
  assert.equal(sharesRow(c, 'd-col', RULES), true);
  assert.equal(sharesRow(c, 'd-side', RULES), true);
  assert.equal(sharesRow(c, 'missing', RULES), false);
});
