// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * Where the board lets a section or a row go: the drop targets of a drag,
 * the steps of Alt plus the arrow keys, and which section types the add
 * panel offers for a spot.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

// The editor modules register a few listeners when they load; Node has no DOM.
const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

const { useLayout } = await import('../../editor/js/modules/board/model.js');
const { targetsFor, keyTarget, keyMoves, rowKeyMoves } = await import('../../editor/js/modules/board/drag.js');
const { addableTypes } = await import('../../editor/js/modules/board/choices.js');

const here = dirname(fileURLToPath(import.meta.url));
const LAYOUT = JSON.parse(readFileSync(join(here, '../fixtures/layout/_layout.json'), 'utf8'));
useLayout(LAYOUT);
const DEFAULTS = LAYOUT.row_defaults;

const RULES = {
  layout: LAYOUT,
  minSpan: (section) => ({ text: 3, half: 6, four: 4, wide: 8 })[section?.type] ?? 12,
  isMain: (section) => section?.type === 'product-main',
  sidesAllowed: true,
};

function page(types, extra = {}) {
  const sections = {};
  Object.entries(types).forEach(([id, type]) => { sections[id] = { type }; });
  return { schema: 1, order: Object.keys(types), sections, ...extra };
}

const row = (id, split, columns) => ({ id, split, columns, settings: { ...DEFAULTS } });
const key = (t) => JSON.stringify(t);

/** A page with a full-width row, a row of columns 8-4, the main section and a left side. */
function sample() {
  const c = page({ a: 'text', w: 'wide', b: 'text', t: 'text', main: 'product-main', s: 'text', h: 'half' }, {
    rows: [row('ra', '12', [['a']]), row('r1', '8-4', [['b'], ['t']]), row('rm', '12', [['main']]), row('rh', '12', [['h']]), row('rw', '12', [['w']])],
    sides: { left: ['s'] },
  });
  c.order = ['s', 'a', 'b', 't', 'main', 'h', 'w'];
  return c;
}

test('targetsFor a span-8 section never lists a 4 column', () => {
  const c = sample();
  const targets = targetsFor(c, { kind: 'section', sid: 'w' }, RULES);
  assert.ok(targets.length > 0);
  assert.ok(targets.some((t) => t.drop === 'column' && t.rowId === 'r1' && t.column === 0), 'the 8 column is offered');
  assert.ok(!targets.some((t) => t.drop === 'column' && t.column === 1), 'the 4 column is never offered');
  assert.ok(!targets.some((t) => t.drop === 'side'), 'a side (3) is never offered');
  assert.ok(!targets.some((t) => t.drop === 'beside'), 'beside (6) is never offered');
});

test('targetsFor a main section lists only gaps between rows', () => {
  const c = sample();
  const targets = targetsFor(c, { kind: 'section', sid: 'main' }, RULES);
  assert.ok(targets.length > 0);
  assert.ok(targets.every((t) => t.drop === 'gap'));
  // Not the gaps just above and below its own row: it is already there.
  assert.deepEqual(targets.map((t) => t.before), ['ra', 'r1', 'rw', 'end']);
});

test('targetsFor lists the left and right edge of a full-width section when both fit 6', () => {
  const c = sample();
  const targets = targetsFor(c, { kind: 'section', sid: 't' }, RULES).map(key);
  assert.ok(targets.includes(key({ drop: 'beside', rowId: 'rh', side: 'left' })));
  assert.ok(targets.includes(key({ drop: 'beside', rowId: 'rh', side: 'right' })));
  assert.ok(targets.includes(key({ drop: 'beside', rowId: 'ra', side: 'left' })));
  // The other one needs 8: two of them never fit side by side.
  assert.ok(!targets.some((t) => t.includes('"rowId":"rw"') && t.includes('beside')));
  // The main section never gets a neighbor.
  assert.ok(!targets.some((t) => t.includes('"rowId":"rm"') && t.includes('beside')));
  // A section of 8 cannot sit beside anything.
  assert.ok(!targetsFor(c, { kind: 'section', sid: 'w' }, RULES).some((t) => t.drop === 'beside'));
});

test('targetsFor a row lists every gap but the two around it', () => {
  const c = sample();
  const targets = targetsFor(c, { kind: 'row', rowId: 'r1' }, RULES);
  assert.deepEqual(targets.map((t) => t.before), ['ra', 'rh', 'rw', 'end']);
  assert.ok(targets.every((t) => t.drop === 'gap'));
});

test('keyMove targets are a subset of drag targets', () => {
  const c = sample();
  let some = 0;
  Object.keys(c.sections).forEach((sid) => {
    const all = targetsFor(c, { kind: 'section', sid }, RULES).map(key);
    ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].forEach((arrow) => {
      const t = keyTarget(c, sid, arrow, RULES);
      if (t === null) return;
      some += 1;
      assert.ok(all.includes(key(t)), `${sid} ${arrow}: ${key(t)} is a drag target`);
    });
  });
  assert.ok(some >= 6);
});

test('keyTarget moves within a column, out of it past its end, and to the next column that fits', () => {
  const c = page({ a: 'text', b: 'text', w: 'wide', t: 'text' }, {
    rows: [row('ra', '12', [['a']]), row('r1', '8-4', [['b', 'w'], ['t']])],
  });
  c.order = ['a', 'b', 'w', 't'];
  assert.deepEqual(keyTarget(c, 'b', 'ArrowDown', RULES), { drop: 'column', rowId: 'r1', column: 0, before: null });
  assert.deepEqual(keyTarget(c, 'w', 'ArrowUp', RULES), { drop: 'column', rowId: 'r1', column: 0, before: 'b' });
  assert.deepEqual(keyTarget(c, 'b', 'ArrowUp', RULES), { drop: 'gap', before: 'r1' });
  assert.deepEqual(keyTarget(c, 'w', 'ArrowDown', RULES), { drop: 'gap', before: 'end' });
  assert.deepEqual(keyTarget(c, 'b', 'ArrowRight', RULES), { drop: 'column', rowId: 'r1', column: 1, before: null });
  // Too wide for the 4 column: it stays.
  assert.equal(keyTarget(c, 'w', 'ArrowRight', RULES), null);
  assert.deepEqual(keyTarget(c, 't', 'ArrowLeft', RULES), { drop: 'column', rowId: 'r1', column: 0, before: null });
  // A full-width section moves its row up and down; there is nothing to its side.
  assert.deepEqual(keyTarget(c, 'a', 'ArrowDown', RULES), { drop: 'gap', before: 'end' });
  assert.equal(keyTarget(c, 'a', 'ArrowUp', RULES), null);
  assert.equal(keyTarget(c, 'a', 'ArrowLeft', RULES), null);
});

test('keyMoves lists only the arrows a section can really move by', () => {
  const c = page({ a: 'text', b: 'text', w: 'wide', t: 'text' }, {
    rows: [row('ra', '12', [['a']]), row('r1', '8-4', [['b', 'w'], ['t']])],
  });
  c.order = ['a', 'b', 'w', 't'];
  // A full-width section never offers Left or Right, and the first one not Up.
  assert.deepEqual(keyMoves(c, 'a', RULES), ['ArrowDown']);
  assert.deepEqual(keyMoves(c, 'b', RULES), ['ArrowUp', 'ArrowDown', 'ArrowRight']);
  // Too wide for the column on its right.
  assert.deepEqual(keyMoves(c, 'w', RULES), ['ArrowUp', 'ArrowDown']);
  assert.deepEqual(keyMoves(c, 't', RULES), ['ArrowUp', 'ArrowDown', 'ArrowLeft']);
});

test('rowKeyMoves offers up only below the first row and down only above the last', () => {
  const rows = [row('r1', '6-6', [[], []]), row('r2', '6-6', [[], []]), row('r3', '6-6', [[], []])];
  assert.deepEqual(rowKeyMoves(rows, 'r1'), ['ArrowDown']);
  assert.deepEqual(rowKeyMoves(rows, 'r2'), ['ArrowUp', 'ArrowDown']);
  assert.deepEqual(rowKeyMoves(rows, 'r3'), ['ArrowUp']);
  assert.deepEqual(rowKeyMoves(rows.slice(0, 1), 'r1'), []);
});

const CATALOG = {
  'top-bar': { label: 'Top bar', limit: 1, span: { min: 12, variants: {} } },
  quote: { label: 'Quote', span: { min: 3, variants: {} } },
  gallery: {
    label: 'Gallery',
    span: { min: 12, variants: { compact: 4 } },
    settings: [{ key: 'variant', default: 'wide' }],
    variants: [{ value: 'wide', label: 'Wide' }, { value: 'compact', label: 'Compact' }],
  },
  banner: { label: 'Banner', span: { min: 8, variants: {} } },
};

const CATALOG_RULES = {
  layout: LAYOUT,
  minSpan(section) {
    const def = CATALOG[section?.type];
    const variant = section?.settings?.variant ?? def?.settings?.[0]?.default;
    return def?.span?.variants?.[variant] ?? def?.span?.min ?? 12;
  },
  isMain: () => false,
  sidesAllowed: true,
};

test('addableTypes greys out a used limit-1 type with its reason', () => {
  const c = { schema: 1, order: ['bar'], sections: { bar: { type: 'top-bar' } } };
  const list = addableTypes(c, { drop: 'gap', before: 'end' }, CATALOG, CATALOG_RULES);
  const bar = list.find((x) => x.type === 'top-bar');
  assert.equal(bar.disabled, true);
  assert.equal(bar.reason, 'Already on this page');
  assert.equal(list.find((x) => x.type === 'quote').disabled, false);
  assert.equal(list.find((x) => x.type === 'banner').disabled, false);
});

test('addableTypes greys out types too wide for a column, unless a variant fits', () => {
  const c = page({ a: 'text', b: 'text' }, { rows: [row('r1', '8-4', [['a'], ['b']])] });
  const list = addableTypes(c, { drop: 'column', rowId: 'r1', column: 1, before: null }, CATALOG, CATALOG_RULES);
  const by = Object.fromEntries(list.map((x) => [x.type, x]));
  assert.equal(by.banner.disabled, true);
  assert.equal(by.banner.reason, 'Need a wider spot');
  assert.equal(by['top-bar'].disabled, true);
  assert.equal(by.quote.disabled, false);
  // Its compact layout fits a 4 column.
  assert.equal(by.gallery.disabled, false);
});
