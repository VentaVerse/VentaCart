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

const { useLayout, catalogRules, sideTargets } = await import('../../editor/js/modules/board/model.js');
const { targetsFor, keyTarget, keyMoves, rowKeyMoves, bandTargets, bandKeyTarget, bandIndex, listDropIndex, targetKey } = await import('../../editor/js/modules/board/drag.js');
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

const NO_SIDES = { ...RULES, sidesAllowed: false };

function page(types, extra = {}) {
  const sections = {};
  Object.entries(types).forEach(([id, type]) => { sections[id] = { type }; });
  return { schema: 1, order: Object.keys(types), sections, ...extra };
}

const row = (id, split, columns) => ({ id, split, columns, settings: { ...DEFAULTS } });
const key = (t) => JSON.stringify(t);

/** A page with a full-width row, a row of columns 8-4, the main section and no sides. */
function sample() {
  const c = page({ a: 'text', w: 'wide', b: 'text', t: 'text', main: 'product-main', h: 'half' }, {
    rows: [row('ra', '12', [['a']]), row('r1', '8-4', [['b'], ['t']]), row('rm', '12', [['main']]), row('rh', '12', [['h']]), row('rw', '12', [['w']])],
  });
  c.order = ['a', 'b', 't', 'main', 'h', 'w'];
  return c;
}

/** The sample with a section in its left side, so every column draws at three quarters. */
function sampleWithSide() {
  const c = sample();
  c.sections.s = { type: 'text' };
  c.sides = { left: ['s'] };
  c.order = ['s', ...c.order];
  return c;
}

const sidesIn = (targets) => targets.filter((t) => t.drop === 'side');

test('targetsFor a span-3 section lists both sides', () => {
  const c = sample();
  const sides = sidesIn(targetsFor(c, { kind: 'section', sid: 't' }, RULES));
  assert.deepEqual(sides, [{ drop: 'side', side: 'left', before: null }, { drop: 'side', side: 'right', before: null }]);
  // A filled side offers each place in it.
  const filled = sidesIn(targetsFor(sampleWithSide(), { kind: 'section', sid: 't' }, RULES)).map(key);
  assert.ok(filled.includes(key({ drop: 'side', side: 'left', before: 's' })));
  assert.ok(filled.includes(key({ drop: 'side', side: 'left', before: null })));
  assert.ok(filled.includes(key({ drop: 'side', side: 'right', before: null })));
});

test('targetsFor a span-4 section lists no side', () => {
  const c = page({ a: 'text', f: 'four' });
  assert.deepEqual(sidesIn(targetsFor(c, { kind: 'section', sid: 'f' }, { ...RULES, minSpan: (s) => (s?.type === 'four' ? 4 : 3) })), []);
});

test('the cart board offers no side', () => {
  const defs = { text: { span: { min: 3 } } };
  const rules = catalogRules(LAYOUT, (type) => defs[type] || null, sideTargets('cart', LAYOUT).length > 0);
  const c = page({ a: 'text', b: 'text' });
  assert.deepEqual(sidesIn(targetsFor(c, { kind: 'section', sid: 'a' }, rules)), []);
  const home = catalogRules(LAYOUT, (type) => defs[type] || null, sideTargets('home', LAYOUT).length > 0);
  assert.equal(sidesIn(targetsFor(c, { kind: 'section', sid: 'a' }, home)).length, 2);
});

test('targetsFor beside the sides offers only the columns wide enough there', () => {
  const c = sampleWithSide();
  // The 8 column is 6 twelfths beside one side: too narrow for a section of 8.
  assert.ok(!targetsFor(c, { kind: 'section', sid: 'w' }, RULES).some((t) => t.drop === 'column'));
  // A half beside one side is 4.5 twelfths: two sections of 6 never sit side by side.
  assert.ok(!targetsFor(c, { kind: 'section', sid: 't' }, RULES).some((t) => t.drop === 'beside' && t.rowId === 'rh'));
  // Out of its side the section leaves the page whole, so the half is 6 again.
  assert.ok(targetsFor(c, { kind: 'section', sid: 's' }, RULES).some((t) => t.drop === 'column' && t.rowId === 'r1' && t.column === 1));
});

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
  const c = sampleWithSide();
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
  // Too wide for the 4 column and for a side: it stays.
  assert.equal(keyTarget(c, 'w', 'ArrowRight', RULES), null);
  assert.deepEqual(keyTarget(c, 't', 'ArrowLeft', RULES), { drop: 'column', rowId: 'r1', column: 0, before: null });
  // A full-width section moves its row up and down.
  assert.deepEqual(keyTarget(c, 'a', 'ArrowDown', RULES), { drop: 'gap', before: 'end' });
  assert.equal(keyTarget(c, 'a', 'ArrowUp', RULES), null);
  // On a page that cannot have sides there is nothing to its side.
  assert.equal(keyTarget(c, 'a', 'ArrowLeft', NO_SIDES), null);
  assert.equal(keyTarget(c, 't', 'ArrowRight', NO_SIDES), null);
});

test('Alt with left or right moves a section into a side past the outer column', () => {
  const c = page({ a: 'text', b: 'text', w: 'wide', t: 'text' }, {
    rows: [row('ra', '12', [['a']]), row('r1', '8-4', [['b', 'w'], ['t']])],
  });
  c.order = ['a', 'b', 'w', 't'];
  // From a full-width row: Left to the left side, Right to the right side.
  assert.deepEqual(keyTarget(c, 'a', 'ArrowLeft', RULES), { drop: 'side', side: 'left', before: null });
  assert.deepEqual(keyTarget(c, 'a', 'ArrowRight', RULES), { drop: 'side', side: 'right', before: null });
  // From the first column Left, from the last column Right.
  assert.deepEqual(keyTarget(c, 'b', 'ArrowLeft', RULES), { drop: 'side', side: 'left', before: null });
  assert.deepEqual(keyTarget(c, 't', 'ArrowRight', RULES), { drop: 'side', side: 'right', before: null });
  // Into a filled side, at its end.
  const filled = { ...c, sections: { ...c.sections, s: { type: 'text' } }, sides: { left: ['s'] }, order: ['s', ...c.order] };
  assert.deepEqual(keyTarget(filled, 'b', 'ArrowLeft', RULES), { drop: 'side', side: 'left', before: null });
  // A section of 8 never goes into a side.
  assert.equal(keyTarget(c, 'w', 'ArrowLeft', RULES), null);
});

test('Alt with up or down moves a section out of a side past its top or end', () => {
  const c = sampleWithSide();
  c.sections.s2 = { type: 'text' };
  c.sides = { left: ['s', 's2'] };
  c.order = ['s', 's2', ...c.order.filter((id) => id !== 's')];
  // From the top of the side: the gap above the first row.
  assert.deepEqual(keyTarget(c, 's', 'ArrowUp', RULES), { drop: 'gap', before: 'ra' });
  // From its end: the gap below the last row.
  assert.deepEqual(keyTarget(c, 's2', 'ArrowDown', RULES), { drop: 'gap', before: 'end' });
  // Within the side, one place.
  assert.deepEqual(keyTarget(c, 's', 'ArrowDown', RULES), { drop: 'side', side: 'left', before: null });
  assert.deepEqual(keyMoves(c, 's', RULES), ['ArrowUp', 'ArrowDown']);
  // Left and right never move a section out of a side.
  assert.equal(keyTarget(c, 's', 'ArrowRight', RULES), null);
});

test('keyMoves lists only the arrows a section can really move by', () => {
  const c = page({ a: 'text', b: 'text', w: 'wide', t: 'text' }, {
    rows: [row('ra', '12', [['a']]), row('r1', '8-4', [['b', 'w'], ['t']])],
  });
  c.order = ['a', 'b', 'w', 't'];
  // The first section offers no Up; Left and Right lead into the sides.
  assert.deepEqual(keyMoves(c, 'a', RULES), ['ArrowDown', 'ArrowLeft', 'ArrowRight']);
  assert.deepEqual(keyMoves(c, 'b', RULES), ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']);
  // Too wide for the column on its right and for a side.
  assert.deepEqual(keyMoves(c, 'w', RULES), ['ArrowUp', 'ArrowDown']);
  assert.deepEqual(keyMoves(c, 't', RULES), ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']);
  // Without sides, a full-width section never offers Left or Right.
  assert.deepEqual(keyMoves(c, 'a', NO_SIDES), ['ArrowDown']);
  assert.deepEqual(keyMoves(c, 't', NO_SIDES), ['ArrowUp', 'ArrowDown', 'ArrowLeft']);
});

test('rowKeyMoves offers up only below the first row and down only above the last', () => {
  const rows = [row('r1', '6-6', [[], []]), row('r2', '6-6', [[], []]), row('r3', '6-6', [[], []])];
  assert.deepEqual(rowKeyMoves(rows, 'r1'), ['ArrowDown']);
  assert.deepEqual(rowKeyMoves(rows, 'r2'), ['ArrowUp', 'ArrowDown']);
  assert.deepEqual(rowKeyMoves(rows, 'r3'), ['ArrowUp']);
  assert.deepEqual(rowKeyMoves(rows.slice(0, 1), 'r1'), []);
});

test('a header or footer section moves only within its own band', () => {
  const order = ['bar', 'head', 'promo'];
  // Every place in the band but the two around where it is now.
  assert.deepEqual(bandTargets('header', order, 'bar'), [
    { drop: 'band', group: 'header', before: 'promo' },
    { drop: 'band', group: 'header', before: null },
  ]);
  assert.deepEqual(bandTargets('header', order, 'head'), [{ drop: 'band', group: 'header', before: 'bar' }, { drop: 'band', group: 'header', before: null }]);
  assert.deepEqual(bandTargets('footer', ['foot'], 'foot'), []);
  assert.deepEqual(bandTargets('header', order, 'missing'), []);
  // The same spot in the other band is another target.
  assert.notEqual(targetKey({ drop: 'band', group: 'header', before: null }), targetKey({ drop: 'band', group: 'footer', before: null }));
});

test('Alt with up or down moves a band section one place, and nothing past either end', () => {
  const order = ['bar', 'head', 'promo'];
  assert.equal(bandKeyTarget('header', order, 'bar', 'ArrowUp'), null);
  assert.deepEqual(bandKeyTarget('header', order, 'bar', 'ArrowDown'), { drop: 'band', group: 'header', before: 'promo' });
  assert.deepEqual(bandKeyTarget('header', order, 'promo', 'ArrowUp'), { drop: 'band', group: 'header', before: 'head' });
  assert.equal(bandKeyTarget('header', order, 'promo', 'ArrowDown'), null);
  assert.equal(bandKeyTarget('header', order, 'head', 'ArrowLeft'), null);
  // The place in the order the move commits.
  assert.equal(bandIndex(order, 'bar', 'promo'), 1);
  assert.equal(bandIndex(order, 'bar', null), 2);
  assert.equal(bandIndex(order, 'promo', 'head'), 1);
});

test('listDropIndex puts a held item where the finger is among the others', () => {
  const mids = [20, 60, 100];
  assert.equal(listDropIndex(mids, 10, 0), 0);
  assert.equal(listDropIndex(mids, 70, 0), 1);
  assert.equal(listDropIndex(mids, 200, 0), 2);
  assert.equal(listDropIndex(mids, 10, 2), 0);
  assert.equal(listDropIndex(mids, 80, 2), 2);
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

test('addableTypes measures a column beside a side, and a side offers only types of 3', () => {
  const c = page({ a: 'text', b: 'text', f: 'text' }, { rows: [row('r1', '8-4', [['a'], ['b']])], sides: { left: ['f'] } });
  const wide = Object.fromEntries(addableTypes(c, { drop: 'column', rowId: 'r1', column: 0, before: null }, CATALOG, CATALOG_RULES).map((x) => [x.type, x]));
  assert.equal(wide.banner.disabled, true, 'the 8 column is 6 twelfths here');
  assert.equal(wide.gallery.disabled, false, 'its compact layout of 4 fits');
  const side = Object.fromEntries(addableTypes(c, { drop: 'side', side: 'right', before: null }, CATALOG, CATALOG_RULES).map((x) => [x.type, x]));
  assert.equal(side.quote.disabled, false);
  assert.equal(side.gallery.disabled, true);
  assert.equal(side.banner.disabled, true);
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
