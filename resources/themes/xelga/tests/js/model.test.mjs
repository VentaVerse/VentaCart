// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The editor's row model. The cases in tests/fixtures/layout are shared with
 * the PHP normalizer's test, so the editor and the server read a page
 * document alike; the rest covers the board's moves.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import {
  useLayout, normalize, rowsOf, rowIdFor, freshRowId, locate, spanAt, fits, place,
  addRow, moveRow, removeRow, changeSplit, setRow, effectsOf, tidy, checkAddSpot,
  contentSpan, sideTargets, catalogRules, roomOf, areasOf, countInPlace,
} from '../../editor/js/modules/board/model.js';

const here = dirname(fileURLToPath(import.meta.url));
const FIXTURES = join(here, '../fixtures/layout');
const LAYOUT = JSON.parse(readFileSync(join(FIXTURES, '_layout.json'), 'utf8'));
useLayout(LAYOUT);

const DEFAULTS = LAYOUT.row_defaults;

function rulesOf(spec) {
  return {
    layout: LAYOUT,
    minSpan: (section) => spec.min?.[section?.type] ?? 12,
    isMain: (section) => (spec.main || []).includes(section?.type),
    sidesAllowed: Boolean(spec.sides ?? true),
    ownRow: (section) => (spec.own_row || []).includes(section?.type),
    contentOnly: (section) => (spec.content_only || []).includes(section?.type),
  };
}

const RULES = rulesOf({ min: { text: 3, half: 6, four: 4, wide: 8 }, main: ['product-main'], sides: true });

/** A page whose sections are given as {id: type}. */
function page(types, extra = {}) {
  const sections = {};
  Object.entries(types).forEach(([id, type]) => { sections[id] = { type }; });
  return { schema: 1, order: Object.keys(types), sections, ...extra };
}

const row = (id, split, columns, settings = DEFAULTS) => ({ id, split, columns, settings: { ...settings } });
const json = (value) => JSON.stringify(value);

const AREA_RULES = {
  ...rulesOf({
    min: { text: 3, half: 6, wide: 8, banner: 12, bar: 6, filters: 3 },
    main: ['product-main'],
    sides: true,
    own_row: ['bar'],
    content_only: ['filters'],
  }),
  limitPerArea: (section) => section?.type === 'bar' || section?.type === 'notice',
};

/** A one-column row (or a row of columns) stored in an area. */
const arow = (id, area, columns, split = '12') => ({ id, split, columns, settings: { ...DEFAULTS }, area });

/**
 * The shared cases: the top folder, content-width/, whose cases measure
 * columns beside the sides, and areas/, the pages with areas.
 */
function fixtureFiles() {
  const list = (dir) => readdirSync(join(FIXTURES, dir)).filter((f) => f.endsWith('.json') && !f.startsWith('_')).map((f) => join(FIXTURES, dir, f));
  return [...list('.'), ...list('content-width'), ...list('areas')];
}

test('normalize matches every shared fixture', () => {
  const files = fixtureFiles();
  assert.ok(files.length > 15);
  assert.ok(files.some((f) => f.includes('content-width')), 'the content width cases are read');
  assert.ok(files.some((f) => f.includes('/areas/')), 'the cases with areas are read');
  files.forEach((file) => {
    const fixture = JSON.parse(readFileSync(file, 'utf8'));
    const rules = rulesOf(fixture.rules);
    const input = JSON.parse(json(fixture.input));
    const result = normalize(input, rules);
    // Key order too, so a saved document reads the same byte for byte.
    assert.equal(json(result.content), json(fixture.expected), `${fixture.name}: content`);
    assert.deepEqual(result.moved, fixture.moved, `${fixture.name}: moved`);
    assert.equal(json(input), json(fixture.input), `${fixture.name}: input left as it was`);

    const again = normalize(fixture.expected, rules);
    assert.equal(json(again.content), json(fixture.expected), `${fixture.name}: normalizing twice changes nothing`);
    assert.deepEqual(again.moved, [], `${fixture.name}: normalizing twice moves nothing`);

    // The cases with areas give rowsOf the rules, so their rows carry an area.
    const withRules = file.includes('/areas/') ? rules : undefined;
    if (fixture.rows) assert.equal(json(rowsOf(fixture.input, LAYOUT, withRules)), json(fixture.rows), `${fixture.name}: rows`);
  });
});

test('rowsOf gives implied rows the same ids as the server', () => {
  assert.equal(rowIdFor('a', []), 'row-86f7e437');
  assert.equal(rowIdFor('a', ['row-86f7e437']), 'row-86f7e437fa');
  assert.equal(rowIdFor('a', ['row-86f7e437', 'row-86f7e437fa']), 'row-86f7e437faa5');

  const rows = rowsOf(page({ a: 'text', b: 'text' }));
  assert.deepEqual(rows.map((r) => r.id), ['row-86f7e437', 'row-e9d71f5e']);
  assert.deepEqual(rows[0], row('row-86f7e437', '12', [['a']]));

  // A side's sections have no row of their own.
  const withSide = page({ a: 'text', b: 'text' }, { sides: { left: ['a'] } });
  assert.deepEqual(rowsOf(withSide).map((r) => r.columns), [[['b']]]);
});

test('freshRowId seeds an empty row with "row" and numbers it once every length is taken', () => {
  const c = page({ a: 'text' });
  assert.equal(freshRowId(c), rowIdFor('row', ['row-86f7e437']));
  const taken = [];
  for (let n = 0; n < 15; n += 1) taken.push(rowIdFor('row', taken));
  assert.equal(rowIdFor('row', taken), rowIdFor('row-2', []));
});

test('locate finds a section in a column or a side', () => {
  const c = page({ a: 'text', b: 'text', c: 'text' }, { rows: [row('r1', '6-6', [['a'], ['b']])], sides: { right: ['c'] } });
  assert.deepEqual(locate(c, 'b'), { where: 'row', rowId: 'r1', column: 1, index: 0 });
  assert.deepEqual(locate(c, 'c'), { where: 'side', side: 'right', index: 0 });
  assert.equal(locate(c, 'nope'), null);
});

test('spanAt gives a column its part of the content area beside the sides', () => {
  const c = page({ main: 'product-main', a: 'text', b: 'text', f: 'text', g: 'text' }, {
    rows: [arow('rm', 'content', [['main']]), arow('r1', 'content', [['a'], ['b']], '8-4')], sides: { left: ['f'] },
  });
  assert.equal(spanAt(c, { drop: 'column', rowId: 'r1', column: 0, before: null }, RULES), 6);
  assert.equal(spanAt(c, { drop: 'column', rowId: 'r1', column: 1, before: null }, RULES), 3);
  assert.equal(spanAt(c, { drop: 'side', side: 'right', before: null }, RULES), 3);
  // The section leaving the only side: the page is whole again.
  assert.equal(spanAt(c, { drop: 'column', rowId: 'r1', column: 0, before: null }, RULES, 'f'), 8);
  const both = { ...c, sides: { left: ['f'], right: ['g'] } };
  assert.equal(spanAt(both, { drop: 'column', rowId: 'r1', column: 0, before: null }, RULES), 4);
  assert.equal(roomOf(both, 'a', RULES), 4);
  // A page that cannot have sides is measured whole.
  assert.equal(spanAt(c, { drop: 'column', rowId: 'r1', column: 0, before: null }, rulesOf({ min: {}, main: ['product-main'], sides: false })), 8);
  // So are Top and Bottom, beside no side.
  const top = { ...c, rows: [arow('r1', 'top', [['a'], ['b']], '8-4'), arow('rm', 'content', [['main']])] };
  assert.equal(spanAt(top, { drop: 'column', rowId: 'r1', column: 0, before: null }, RULES), 8);
});

test('fits measures a column beside the sides, as the server does', () => {
  const c = page({ main: 'product-main', h: 'half', a: 'text', f: 'text', x: 'half' }, {
    rows: [arow('rm', 'content', [['main']]), arow('r1', 'content', [['a'], []], '6-6'), arow('rx', 'content', [['x']])], sides: { left: ['f'] },
  });
  c.order = ['f', 'main', 'a', 'x'];
  // A half beside one side is 4.5 twelfths: too narrow for a section of 6.
  assert.equal(fits(c, 'x', { drop: 'column', rowId: 'r1', column: 1, before: null }, RULES), false);
  assert.equal(checkAddSpot(c, { drop: 'column', rowId: 'r1', column: 1, before: null }, { type: 'half' }, RULES).reason, 'narrow');
  assert.equal(fits(c, 'f', { drop: 'column', rowId: 'r1', column: 1, before: null }, RULES), true);
  // Out of the only side, the page is whole again and the half fits.
  const out = place(c, 'f', { drop: 'column', rowId: 'r1', column: 1, before: null }, RULES).content;
  assert.equal(out.sides, undefined);
  assert.equal(fits(out, 'x', { drop: 'column', rowId: 'r1', column: 1, before: null }, RULES), true);
});

test('a section dropped in an empty side narrows the page and moves what no longer fits', () => {
  const c = page({ main: 'product-main', h: 'half', a: 'text', t: 'text' }, {
    rows: [arow('rm', 'content', [['main']]), arow('r1', 'content', [['h'], ['a']], '6-6'), arow('rt', 'content', [['t']])],
  });
  c.order = ['main', 'h', 'a', 't'];
  const result = place(c, 't', { drop: 'side', side: 'left', before: null }, RULES);
  assert.deepEqual(result.content.sides, { left: ['t'] });
  assert.deepEqual(result.moved, ['h']);
  assert.deepEqual(rowsOf(result.content, LAYOUT, RULES).map((r) => [r.columns, r.area]), [[[['main']], 'content'], [[[], ['a']], 'content'], [[['h']], 'content']]);
});

test('contentSpan ignores a side of hidden sections', () => {
  const c = page({ main: 'product-main', a: 'text', f: 'text', g: 'text' }, { sides: { left: ['f'], right: ['g'] } });
  assert.equal(contentSpan(c, RULES), 6);
  c.sections.g.disabled = true;
  assert.equal(contentSpan(c, RULES), 9);
  c.sections.f.disabled = true;
  assert.equal(contentSpan(c, RULES), 12);
  // A side of a type the catalog does not know draws nothing either.
  const defs = { text: { span: { min: 3 } }, 'product-main': { main: true, span: { min: 12 } } };
  const unknown = page({ main: 'product-main', a: 'text', f: 'gone' }, { sides: { left: ['f'] } });
  assert.equal(contentSpan(unknown, catalogRules(LAYOUT, (type) => defs[type] || null, true)), 12);
  assert.equal(contentSpan(page({ main: 'product-main', a: 'text', f: 'text' }, { sides: { left: ['f'] } }), rulesOf({ min: { text: 3 }, main: ['product-main'], sides: false })), 12);
  // A page without its main section has no sides to narrow it.
  assert.equal(contentSpan(page({ a: 'text', f: 'text' }, { sides: { left: ['f'] } }), RULES), 12);
});

test('sideTargets offers both sides on the page types that may have them, never on Home or the cart', () => {
  assert.deepEqual(sideTargets('category', LAYOUT), ['left', 'right']);
  assert.deepEqual(sideTargets('product', LAYOUT), ['left', 'right']);
  assert.deepEqual(sideTargets('home', LAYOUT), [], 'Home is all rows.');
  assert.deepEqual(sideTargets('cart', LAYOUT), []);
  assert.deepEqual(sideTargets('header', LAYOUT), []);
});

test('removing the last section of a side drops the side', () => {
  const c = page({ a: 'text', b: 'text', f: 'text' }, { rows: [row('r1', '6-6', [['a'], ['b']])], sides: { left: ['f'] } });
  c.order = ['f', 'a', 'b'];
  delete c.sections.f;
  c.order = ['a', 'b'];
  const next = tidy(c);
  assert.equal(Object.hasOwn(next, 'sides'), false);
  assert.deepEqual(next.order, ['a', 'b']);
  // Moved out of its side, the last section takes the side with it.
  const d = page({ a: 'text', f: 'text' }, { sides: { right: ['f'] } });
  const out = place(d, 'f', { drop: 'gap', before: 'end' }, RULES).content;
  assert.equal(Object.hasOwn(out, 'sides'), false);
  assert.deepEqual(out.order, ['a', 'f']);
});

test('effectsOf names every section whose width a new side changed', () => {
  const c = page({ main: 'product-main', a: 'text', b: 'text', t: 'text' }, {
    rows: [arow('rm', 'content', [['main']]), arow('r1', 'content', [['a'], ['b']], '6-6'), arow('rt', 'content', [['t']])],
  });
  c.order = ['main', 'a', 'b', 't'];
  const after = place(c, 't', { drop: 'side', side: 'right', before: null }, RULES).content;
  assert.deepEqual(effectsOf(c, after).sections.sort(), ['a', 'b', 'main', 't']);
});

test('spanAt gives a column its split part, a side 3, a gap 12 and beside 6', () => {
  const c = page({ a: 'text', b: 'text' }, { rows: [row('r1', '8-4', [['a'], ['b']])] });
  assert.equal(spanAt(c, { drop: 'column', rowId: 'r1', column: 0, before: null }, RULES), 8);
  assert.equal(spanAt(c, { drop: 'column', rowId: 'r1', column: 1, before: null }, RULES), 4);
  assert.equal(spanAt(c, { drop: 'side', side: 'left', before: null }, RULES), 3);
  assert.equal(spanAt(c, { drop: 'gap', before: 'end' }, RULES), 12);
  assert.equal(spanAt(c, { drop: 'beside', rowId: 'r1', side: 'left' }, RULES), 6);
});

test('fits refuses a main section in a column and a span-8 section in a 4 column', () => {
  const c = page({ main: 'product-main', w: 'wide', t: 'text', a: 'text' }, { rows: [row('r1', '8-4', [['a'], []])] });
  c.order = ['a', 'main', 'w', 't'];
  const wideCol = { drop: 'column', rowId: 'r1', column: 0, before: null };
  const narrowCol = { drop: 'column', rowId: 'r1', column: 1, before: null };
  assert.equal(fits(c, 'main', wideCol, RULES), false);
  assert.equal(fits(c, 'main', { drop: 'side', side: 'left', before: null }, RULES), false);
  assert.equal(fits(c, 'main', { drop: 'gap', before: 'end' }, RULES), true);
  assert.equal(fits(c, 'w', narrowCol, RULES), false);
  assert.equal(fits(c, 'w', wideCol, RULES), true);
  assert.equal(fits(c, 't', narrowCol, RULES), true);
  assert.equal(fits(c, 'w', { drop: 'side', side: 'left', before: null }, RULES), false);
  assert.equal(fits(c, 't', { drop: 'side', side: 'left', before: null }, rulesOf({ min: { text: 3 }, sides: false })), false);
});

test('fits refuses dropping a section where it already is', () => {
  const c = page({ a: 'text', b: 'text', c: 'text', d: 'text' }, {
    rows: [row('ra', '12', [['a']]), row('r1', '6-6', [['b', 'c'], []]), row('rd', '12', [['d']])],
  });
  c.order = ['a', 'b', 'c', 'd'];
  // A section alone in its row: the gaps just above and below it are its place.
  assert.equal(fits(c, 'a', { drop: 'gap', before: 'ra' }, RULES), false);
  assert.equal(fits(c, 'a', { drop: 'gap', before: 'r1' }, RULES), false);
  assert.equal(fits(c, 'a', { drop: 'gap', before: 'rd' }, RULES), true);
  assert.equal(fits(c, 'd', { drop: 'gap', before: 'end' }, RULES), false);
  // In a column: just before the section after it, or at the end when it is last.
  assert.equal(fits(c, 'b', { drop: 'column', rowId: 'r1', column: 0, before: 'c' }, RULES), false);
  assert.equal(fits(c, 'c', { drop: 'column', rowId: 'r1', column: 0, before: null }, RULES), false);
  assert.equal(fits(c, 'c', { drop: 'column', rowId: 'r1', column: 0, before: 'b' }, RULES), true);
  assert.equal(fits(c, 'b', { drop: 'column', rowId: 'r1', column: 0, before: 'b' }, RULES), false);
  // Beside itself.
  assert.equal(fits(c, 'a', { drop: 'beside', rowId: 'ra', side: 'right' }, RULES), false);
});

test('fits refuses a full column or side', () => {
  const ids = ['s1', 's2', 's3', 's4', 's5', 's6', 'x'];
  const c = page(Object.fromEntries(ids.map((id) => [id, 'text'])), { rows: [row('r1', '6-6', [ids.slice(0, 6), []]), row('rx', '12', [['x']])] });
  assert.equal(fits(c, 'x', { drop: 'column', rowId: 'r1', column: 0, before: null }, RULES), false);
  assert.equal(fits(c, 'x', { drop: 'column', rowId: 'r1', column: 1, before: null }, RULES), true);
  // Moving within the full column is still allowed.
  assert.equal(fits(c, 's6', { drop: 'column', rowId: 'r1', column: 0, before: 's1' }, RULES), true);
});

test('beside makes a 6-6 row only when both fit 6', () => {
  const c = page({ a: 'text', b: 'half', w: 'wide' });
  const rows = rowsOf(c);
  const [ra, rb, rw] = rows.map((r) => r.id);

  assert.equal(fits(c, 'a', { drop: 'beside', rowId: rw, side: 'left' }, RULES), false, 'the section there needs 8');
  assert.equal(fits(c, 'w', { drop: 'beside', rowId: ra, side: 'right' }, RULES), false, 'the moved section needs 8');

  const right = place(c, 'a', { drop: 'beside', rowId: rb, side: 'right' }, RULES);
  assert.deepEqual(rowsOf(right.content).map((r) => [r.id, r.split, r.columns]), [[rb, '6-6', [['b'], ['a']]], [rw, '12', [['w']]]]);
  assert.deepEqual(right.content.order, ['b', 'a', 'w']);
  assert.deepEqual(right.created, []);

  const left = place(c, 'a', { drop: 'beside', rowId: rb, side: 'left' }, RULES);
  assert.deepEqual(rowsOf(left.content)[0].columns, [['a'], ['b']]);
  assert.deepEqual(left.content.order, ['a', 'b', 'w']);
});

test('place into a gap takes a section out of a column into a row of its own', () => {
  const c = page({ a: 'text', b: 'text', c: 'text' }, { rows: [row('r1', '6-6', [['a'], ['b']]), row('rc', '12', [['c']])] });
  const result = place(c, 'b', { drop: 'gap', before: 'end' }, RULES);
  const rows = rowsOf(result.content);
  assert.deepEqual(rows.map((r) => r.columns), [[['a'], []], [['c']], [['b']]]);
  assert.deepEqual(result.content.order, ['a', 'c', 'b']);
  assert.deepEqual(result.created, [rows[2].id]);
  assert.equal(json(normalize(result.content, RULES).content), json(result.content), 'already normal');
});

test('place into a gap never takes the id of an empty row of columns', () => {
  let c = page({ a: 'text', b: 'text', c: 'text' });
  c = place(c, 'b', { drop: 'beside', rowId: rowsOf(c)[0].id, side: 'right' }, RULES).content;
  c = place(c, 'b', { drop: 'gap', before: 'end' }, RULES).content;
  const pair = rowsOf(c)[0];
  assert.equal(pair.split, '6-6');
  assert.equal(pair.id, rowIdFor('a', []), 'the row of two kept the id of the row it was made from');

  const result = place(c, 'a', { drop: 'gap', before: pair.id }, RULES);
  const rows = rowsOf(result.content);
  assert.deepEqual(rows.map((r) => [r.split, r.columns]), [['12', [['a']]], ['6-6', [[], []]], ['12', [['c']]], ['12', [['b']]]]);
  assert.equal(rows[1].id, pair.id, 'the empty row keeps its id');
  assert.deepEqual(result.created, [rows[0].id], "created names a's new row");
});

test('place into a side and back into a row', () => {
  const c = page({ main: 'product-main', a: 'text', b: 'text' });
  const toSide = place(c, 'b', { drop: 'side', side: 'left', before: null }, RULES);
  assert.deepEqual(toSide.content.sides, { left: ['b'] });
  assert.deepEqual(toSide.content.order, ['b', 'main', 'a'], 'the side stands beside Content, above Bottom');
  assert.deepEqual(toSide.content.rows.map((r) => r.area), ['content', 'bottom'], 'a page with sides keeps its rows, which carry the areas');

  const back = place(toSide.content, 'b', { drop: 'gap', area: 'bottom', before: 'end' }, RULES);
  assert.equal(json(back.content), json(c), 'plain rows in the areas their places give say no more than order');

  // A page without its main section has no areas, so no sides.
  const plain = page({ a: 'text', b: 'text' });
  assert.equal(place(plain, 'b', { drop: 'side', side: 'left', before: null }, RULES).content, plain);
});

test('changeSplit to thirds pours the fourth column into the third, moves misfits below and returns their new row ids', () => {
  const c = page({ a: 'text', b: 'text', c: 'text', d: 'text', f: 'four', h: 'half' }, {
    rows: [row('r1', '3-3-3-3', [['a'], ['b'], ['c'], ['d']]), row('r2', '6-6', [['f'], ['h']])],
  });
  c.order = ['a', 'b', 'c', 'd', 'f', 'h'];
  const thirds = changeSplit(c, 'r1', '4-4-4', RULES);
  assert.deepEqual(rowsOf(thirds.content)[0].columns, [['a'], ['b'], ['c', 'd']]);
  assert.deepEqual(thirds.moved, []);
  assert.deepEqual(thirds.created, []);

  const quarters = changeSplit(c, 'r2', '3-3-3-3', RULES);
  const rows = rowsOf(quarters.content);
  assert.deepEqual(rows[1].columns, [[], [], [], []]);
  assert.deepEqual(rows.slice(2).map((r) => r.columns), [[['f']], [['h']]]);
  assert.deepEqual(quarters.moved, ['f', 'h']);
  assert.deepEqual(quarters.created, [rows[2].id, rows[3].id]);
  assert.deepEqual(quarters.content.order, ['a', 'b', 'c', 'd', 'f', 'h']);
});

test('changeSplit keeps a column within its limit', () => {
  const ids = ['a1', 'a2', 'a3', 'a4', 'b1', 'b2', 'b3', 'b4'];
  const c = page(Object.fromEntries(ids.map((id) => [id, 'text'])), { rows: [row('r1', '6-6', [ids.slice(0, 4), ids.slice(4)])] });
  const one = changeSplit(c, 'r1', '8-4', RULES);
  assert.deepEqual(rowsOf(one.content)[0].columns, [['a1', 'a2', 'a3', 'a4'], ['b1', 'b2', 'b3', 'b4']]);
  const merged = changeSplit({ ...c, rows: [row('r1', '3-3-3-3', [['a1', 'a2', 'a3', 'a4'], ['b1', 'b2'], ['b3'], ['b4']])] }, 'r1', '6-6', RULES);
  const rows = rowsOf(merged.content);
  assert.ok(rows[0].columns.every((col) => col.length <= LAYOUT.max_per_column));
  assert.deepEqual(merged.content.order.slice().sort(), ids.slice().sort(), 'every section kept');
});

test('changeSplit to one column makes plain rows in reading order', () => {
  const c = page({ a: 'text', b: 'text', c: 'text', x: 'text' }, {
    rows: [row('r1', '6-6', [['a', 'b'], ['c']], { width: 'full', scheme: 'scheme-2', space: 'lg' }), row('rx', '12', [['x']])],
  });
  c.order = ['a', 'b', 'c', 'x'];
  const result = changeSplit(c, 'r1', '12', RULES);
  const rows = rowsOf(result.content);
  assert.deepEqual(rows.map((r) => r.columns), [[['a']], [['b']], [['c']], [['x']]]);
  assert.deepEqual(rows.map((r) => r.settings), rows.map(() => DEFAULTS), 'a one-column row has no settings');
  assert.deepEqual(result.content.order, ['a', 'b', 'c', 'x']);
  assert.equal(result.created.length, 2);
});

test('removeRow keeps every section and returns the created ids', () => {
  const c = page({ a: 'text', b: 'text', c: 'text', d: 'text' }, {
    rows: [row('ra', '12', [['a']]), row('r1', '4-4-4', [['b'], [], ['c']]), row('rd', '12', [['d']])],
  });
  const result = removeRow(c, 'r1');
  const rows = rowsOf(result.content);
  assert.deepEqual(rows.map((r) => r.columns), [[['a']], [['b']], [['c']], [['d']]]);
  assert.deepEqual(result.content.order, ['a', 'b', 'c', 'd']);
  assert.deepEqual(result.created, [rows[1].id, rows[2].id]);

  const empty = addRow(c, '6-6', 'rd', 'row-empty');
  const gone = removeRow(empty, 'row-empty');
  assert.equal(json(gone.content), json(c));
  assert.deepEqual(gone.created, []);
});

test('addRow and moveRow keep order derived from the rows', () => {
  const c = page({ a: 'text', b: 'text' });
  const id = freshRowId(c);
  const added = addRow(c, '6-6', 'end', id);
  assert.deepEqual(rowsOf(added).map((r) => r.id).at(-1), id);
  assert.deepEqual(rowsOf(added).at(-1).columns, [[], []]);
  assert.equal(json(addRow(c, '12', 'end', id)), json(c), 'an empty one-column row says nothing');

  const rb = rowsOf(c)[1].id;
  const moved = moveRow(c, rb, rowsOf(c)[0].id);
  assert.deepEqual(moved.order, ['b', 'a']);
});

test('setRow changes one row setting', () => {
  const c = page({ a: 'text', b: 'text' }, { rows: [row('r1', '6-6', [['a'], ['b']])] });
  const set = setRow(c, 'r1', 'space', 'lg');
  assert.equal(rowsOf(set)[0].settings.space, 'lg');
  assert.equal(json(setRow(c, 'r1', 'nope', 'x')), json(c));
});

test('effectsOf names a changed multi-column row', () => {
  const c = page({ a: 'text', b: 'text', c: 'text' }, { rows: [row('r1', '6-6', [['a'], ['b']]), row('rc', '12', [['c']])] });
  const after = place(c, 'c', { drop: 'column', rowId: 'r1', column: 1, before: null }, RULES).content;
  const effects = effectsOf(c, after);
  assert.equal(effects.board, true);
  assert.deepEqual(effects.rows, ['r1']);
  assert.deepEqual(effects.sections, ['c'], 'c now draws at 6 twelfths');

  const reordered = moveRow(c, 'rc', 'r1');
  assert.deepEqual(effectsOf(c, reordered), { board: true, rows: [], sections: [], left: [] });
});

test('tidy drops removed ids and places new sections after their predecessor', () => {
  const c = page({ a: 'text', b: 'text', c: 'text' }, { rows: [row('r1', '6-6', [['a'], ['b']]), row('rc', '12', [['c']])] });
  delete c.sections.b;
  c.order = ['a', 'c'];
  assert.deepEqual(rowsOf(tidy(c)).map((r) => r.columns), [[['a'], []], [['c']]]);

  c.sections.n = { type: 'text' };
  c.order = ['a', 'n', 'c'];
  assert.deepEqual(tidy(c).order, ['a', 'n', 'c']);
});

/* ------------------------------------------------------------------------
   Operations, through the editor's undo history
   ------------------------------------------------------------------------ */

test('each operation undone returns the identical content', async () => {
  const { S, ctx } = await import('../../editor/js/modules/state.js');
  const { setCatalog } = await import('../../editor/js/modules/catalog.js');
  const { commit, undo, redo, resetHistory } = await import('../../editor/js/modules/operations.js');

  ctx.boot = { layout: LAYOUT };
  setCatalog({ sections: {
    text: { label: 'Text', span: { min: 3, variants: {} } },
    half: { label: 'Half', span: { min: 6, variants: {} } },
    wide: { label: 'Wide', span: { min: 8, variants: {} } },
    'product-main': { label: 'Product', main: true, span: { min: 12, variants: {} } },
  } });

  // The page content first, so the rows below it are in Bottom.
  const start = page({ main: 'product-main', a: 'text', b: 'half', c: 'text', w: 'wide' }, {
    rows: [row('rm', '12', [['main']]), row('r1', '6-6', [['a'], ['b']]), row('rc', '12', [['c']])],
    placed: ['x'],
  });
  start.order = ['main', 'a', 'b', 'c', 'w'];
  const normal = normalize(start, RULES).content;

  // Each step and what the row model alone makes of the same page, which the operation must match.
  const steps = [
    [{ t: 'place', sid: 'c', target: { drop: 'column', rowId: 'r1', column: 0, before: 'a' } }, (c) => place(c, 'c', { drop: 'column', rowId: 'r1', column: 0, before: 'a' }, RULES).content],
    [{ t: 'place', sid: 'a', target: { drop: 'gap', before: 'end' } }, (c) => place(c, 'a', { drop: 'gap', before: 'end' }, RULES).content],
    [{ t: 'place', sid: 'c', target: { drop: 'side', side: 'right', before: null } }, (c) => place(c, 'c', { drop: 'side', side: 'right', before: null }, RULES).content],
    [{ t: 'row-add', split: '4-8', before: 'r1', rowId: 'row-added' }, (c) => addRow(c, '4-8', 'r1', 'row-added', RULES)],
    [{ t: 'row-add', split: '6-6', area: 'content', before: 'end', rowId: 'row-content' }, (c) => addRow(c, '6-6', { drop: 'gap', area: 'content', before: 'end' }, 'row-content', RULES)],
    [{ t: 'row-move', rowId: 'rc', before: 'r1' }, (c) => moveRow(c, 'rc', 'r1', RULES)],
    [{ t: 'row-move', rowId: 'r1', area: 'top', before: 'end' }, (c) => moveRow(c, 'r1', { drop: 'gap', area: 'top', before: 'end' }, RULES)],
    [{ t: 'row-remove', rowId: 'r1' }, (c) => removeRow(c, 'r1', RULES).content],
    [{ t: 'row-split', rowId: 'r1', split: '3-3-3-3' }, (c) => changeSplit(c, 'r1', '3-3-3-3', RULES).content],
    [{ t: 'row-split', rowId: 'r1', split: '12' }, (c) => changeSplit(c, 'r1', '12', RULES).content],
    [{ t: 'row-set', rowId: 'r1', key: 'scheme', to: 'scheme-1' }, (c) => setRow(c, 'r1', 'scheme', 'scheme-1', RULES)],
  ];
  steps.forEach(([step, expected]) => {
    // A product page, which may have sides.
    S.docs = { product: { handle: 'product', kind: 'template', content: JSON.parse(json(normal)), savedSeq: 0 } };
    S.loaded = true;
    S.fatal = null;
    resetHistory();
    const before = json(S.docs.product.content);
    commit({ ...step, doc: 'product', name: 'Section' });
    const after = json(S.docs.product.content);
    assert.notEqual(after, before, `${step.t} changes the page`);
    assert.equal(after, json(expected(JSON.parse(before))), `${step.t} gives what the row model gives`);
    assert.equal(json(normalize(JSON.parse(after), RULES).content), after, `${step.t} leaves a normal page`);
    undo();
    assert.equal(json(S.docs.product.content), before, `${step.t} undone`);
    redo();
    assert.equal(json(S.docs.product.content), after, `${step.t} redone`);
  });
});

test('removing and inserting a section on a page with rows keeps the rows', async () => {
  const { S, ctx } = await import('../../editor/js/modules/state.js');
  const { commit, undo, resetHistory } = await import('../../editor/js/modules/operations.js');
  ctx.boot = { layout: LAYOUT };

  const start = page({ a: 'text', b: 'text', c: 'text' }, { rows: [row('r1', '6-6', [['a'], ['b']]), row('rc', '12', [['c']])] });
  S.docs = { home: { handle: 'home', kind: 'template', content: JSON.parse(json(start)), savedSeq: 0 } };
  S.loaded = true;
  resetHistory();

  commit({ t: 'remove', doc: 'home', sid: 'b', name: 'B' });
  assert.deepEqual(S.docs.home.content.rows[0].columns, [['a'], []]);
  undo();
  // A restored section returns to the end of the section map; only its key order differs.
  assert.deepEqual(S.docs.home.content, start);

  commit({ t: 'insert', doc: 'home', sid: 'n', index: 2, snapshot: { type: 'text' }, name: 'N' });
  assert.deepEqual(S.docs.home.content.order, ['a', 'b', 'n', 'c']);
  assert.deepEqual(rowsOf(S.docs.home.content).map((r) => r.columns), [[['a'], ['b']], [['n']], [['c']]]);
  undo();
  assert.deepEqual(S.docs.home.content, start);
});

test('a copy sits under its original in a column with room, as the server puts it', async () => {
  const { S, ctx } = await import('../../editor/js/modules/state.js');
  const { commit, undo, resetHistory } = await import('../../editor/js/modules/operations.js');
  ctx.boot = { layout: LAYOUT };

  const start = page({ a: 'text', b: 'text', c: 'text' }, { rows: [row('r1', '6-6', [['a'], ['b']]), row('rc', '12', [['c']])] });
  S.docs = { home: { handle: 'home', kind: 'template', content: JSON.parse(json(start)), savedSeq: 0 } };
  S.loaded = true;
  resetHistory();

  commit({ t: 'insert', kind: 'duplicate', source: 'a', doc: 'home', sid: 'a-copy', index: 1, snapshot: { type: 'text' }, name: 'A' });
  assert.deepEqual(S.docs.home.content.rows[0].columns, [['a', 'a-copy'], ['b']]);
  assert.deepEqual(S.docs.home.content.order, ['a', 'a-copy', 'b', 'c']);
  undo();
  assert.deepEqual(S.docs.home.content, start);

  commit({ t: 'insert', kind: 'duplicate', source: 'c', doc: 'home', sid: 'c-copy', index: 3, snapshot: { type: 'text' }, name: 'C' });
  assert.deepEqual(rowsOf(S.docs.home.content).map((r) => r.columns), [[['a'], ['b']], [['c']], [['c-copy']]]);
});

/* Adding at a spot of the board: the spot is checked against the page as it is now. */
const addPage = () => page({ a: 'text', b: 'text', c: 'text' }, {
  rows: [row('r1', '12', [['a']]), row('r2', '6-6', [['b'], ['c']])],
});

test('a new section may go in the first gap of the page', () => {
  assert.deepEqual(checkAddSpot(addPage(), { drop: 'gap', before: 'r1' }, { type: 'text' }, RULES), { ok: true });
});

test('a new section may go in the last gap of the page', () => {
  assert.deepEqual(checkAddSpot(addPage(), { drop: 'gap', before: 'end' }, { type: 'text' }, RULES), { ok: true });
});

test('a full column takes no new section', () => {
  const c = addPage();
  const ids = Array.from({ length: LAYOUT.max_per_column }, (_, i) => `t${i}`);
  ids.forEach((id) => { c.sections[id] = { type: 'text' }; c.order.push(id); });
  c.rows[1].columns[0] = ids;
  const target = { drop: 'column', rowId: 'r2', column: 0, before: null };
  assert.deepEqual(checkAddSpot(c, target, { type: 'text' }, RULES), { ok: false, reason: 'full' });
  assert.deepEqual(checkAddSpot(c, { ...target, column: 1 }, { type: 'text' }, RULES), { ok: true });
});

test('a spot that is no longer on the page is refused, never moved to the end', () => {
  const c = addPage();
  [
    { drop: 'gap', before: 'r9' },
    { drop: 'column', rowId: 'r9', column: 0, before: null },
    { drop: 'column', rowId: 'r2', column: 0, before: 'gone' },
    { drop: 'column', rowId: 'r2', column: 5, before: null },
    { drop: 'beside', rowId: 'r9', side: 'left' },
  ].forEach((target) => {
    assert.deepEqual(checkAddSpot(c, target, { type: 'text' }, RULES), { ok: false, reason: 'gone' }, JSON.stringify(target));
  });
  assert.deepEqual(checkAddSpot(c, { drop: 'column', rowId: 'r2', column: 0, before: null }, { type: 'wide' }, RULES), { ok: false, reason: 'narrow' });
});

/* ------------------------------------------------------------------------
   Pages with areas: Top, Content between the sides, Bottom
   ------------------------------------------------------------------------ */

test('home has no areas', () => {
  const defs = { text: { span: { min: 3 } }, 'product-main': { main: true, span: { min: 12 } } };
  const sectionDef = (type) => defs[type] || null;
  const c = page({ a: 'text', main: 'product-main', b: 'text' });

  const home = catalogRules(LAYOUT, sectionDef, sideTargets('home', LAYOUT).length > 0);
  assert.equal(areasOf(c, home), null);
  assert.ok(rowsOf(c, LAYOUT, home).every((r) => !Object.hasOwn(r, 'area')), 'its rows carry no area');

  // A product page has them: Top above the page content, Bottom below it.
  const product = catalogRules(LAYOUT, sectionDef, sideTargets('product', LAYOUT).length > 0);
  const names = (rows) => rows.map((r) => r.columns[0][0]);
  const areas = areasOf(c, product);
  assert.deepEqual({ top: names(areas.top), content: names(areas.content), bottom: names(areas.bottom) }, { top: ['a'], content: ['main'], bottom: ['b'] });
  assert.deepEqual(rowsOf(c, LAYOUT, product).map((r) => r.area), ['top', 'content', 'bottom']);
  // Without its page content a product page has no areas either.
  assert.equal(areasOf(page({ a: 'text' }), product), null);
});

test('the first side section reports the rows it moves out of Content', () => {
  const c = page({ main: 'product-main', banner: 'banner', best: 'text', f: 'text' }, {
    rows: [arow('rm', 'content', [['main']]), arow('rb', 'content', [['banner']]), arow('rs', 'content', [['best']]), arow('rf', 'bottom', [['f']])],
  });
  const result = place(c, 'f', { drop: 'side', side: 'left', before: null }, AREA_RULES);
  assert.deepEqual(result.content.sides, { left: ['f'] });
  assert.deepEqual(result.moved, [], 'no section moved to a row of its own');
  assert.deepEqual(rowsOf(result.content, LAYOUT, AREA_RULES).map((r) => [r.id, r.area]), [['rm', 'content'], ['rb', 'bottom'], ['rs', 'bottom']]);
  // The banner is too wide beside the side; the row below it goes too, so the page keeps its order.
  assert.deepEqual(effectsOf(c, result.content, AREA_RULES).left, [{ sid: 'banner', area: 'bottom' }, { sid: 'best', area: 'bottom' }]);
  assert.deepEqual(effectsOf(result.content, c, AREA_RULES).left, [], 'undo moves nothing out of Content');
});

test('countInPlace counts a bar per area and per band', () => {
  const c = page({ bar1: 'bar', main: 'product-main', bar2: 'bar', t: 'text' }, {
    rows: [arow('r1', 'top', [['bar1']]), arow('rm', 'content', [['main']]), arow('r2', 'bottom', [['bar2']]), arow('rt', 'bottom', [['t']])],
  });
  assert.equal(countInPlace(c, 'bar', { drop: 'gap', area: 'top', before: 'end' }, AREA_RULES), 1);
  assert.equal(countInPlace(c, 'bar', { drop: 'gap', area: 'content', before: 'rm' }, AREA_RULES), 0);
  assert.equal(countInPlace(c, 'bar', { drop: 'side', side: 'left', before: null }, AREA_RULES), 0, 'a side counts as Content');
  assert.equal(countInPlace(c, 'bar', { drop: 'gap', area: 'bottom', before: 'end' }, AREA_RULES), 1);
  assert.equal(countInPlace(c, 'bar', { drop: 'column', rowId: 'r1', column: 0, before: null }, AREA_RULES), 1, 'a row counts in its area');
  // A type whose limit is per page counts the whole page.
  assert.equal(countInPlace(c, 'text', { drop: 'gap', area: 'top', before: 'end' }, AREA_RULES), 1);
  // A page without areas counts the page.
  assert.equal(countInPlace(c, 'bar', { drop: 'gap', area: null, before: 'end' }, { ...AREA_RULES, sidesAllowed: false }), 2);
  // A band is a document of its own.
  const header = { schema: 1, order: ['bar3', 'header'], sections: { bar3: { type: 'bar' }, header: { type: 'header' } } };
  assert.equal(countInPlace(header, 'bar', { drop: 'band', group: 'header', before: null }, { ...AREA_RULES, sidesAllowed: false }), 1);
});

test('a second bar in another area can be added and duplicated', async () => {
  // The editor's messages look for their holder in the page; Node has none.
  globalThis.document ??= { querySelector: () => null, querySelectorAll: () => [], addEventListener: () => {} };
  globalThis.window ??= { setTimeout, clearTimeout, addEventListener: () => {}, requestAnimationFrame: () => 0 };
  const { S, ctx } = await import('../../editor/js/modules/state.js');
  const { setCatalog } = await import('../../editor/js/modules/catalog.js');
  const { resetHistory } = await import('../../editor/js/modules/operations.js');
  const { addSection, canDuplicate, duplicateSection } = await import('../../editor/js/modules/actions.js');

  ctx.boot = { layout: LAYOUT };
  ctx.api = {};
  const bar = { label: 'Announcement bar', group: 'template', templates: ['*'], limit: 1, limit_per_area: true, own_row: true, span: { min: 6, variants: {} }, settings: [] };
  setCatalog({ sections: {
    bar,
    notice: { ...bar, label: 'Notice', limit: 2 },
    text: { label: 'Text', group: 'template', templates: ['*'], span: { min: 3, variants: {} }, settings: [] },
    'product-main': { label: 'Product', group: 'template', main: true, span: { min: 12, variants: {} }, settings: [] },
  } });
  const start = page({ bar1: 'bar', main: 'product-main', t: 'text' }, {
    rows: [arow('r1', 'top', [['bar1']]), arow('rm', 'content', [['main']]), arow('rt', 'bottom', [['t']])],
  });
  S.docs = { product: { handle: 'product', kind: 'template', content: JSON.parse(json(start)), savedSeq: 0 } };
  S.page = { handle: 'product', pageType: 'product', kind: 'template' };
  S.loaded = true;
  S.fatal = null;
  resetHistory();

  const added = addSection('bar', { target: { drop: 'gap', area: 'bottom', before: 'end' } });
  assert.ok(added, 'Bottom takes a bar while Top has one');
  const rows = rowsOf(S.docs.product.content, LAYOUT, AREA_RULES);
  assert.deepEqual(rows.map((r) => [r.columns[0][0], r.area]), [['bar1', 'top'], ['main', 'content'], ['t', 'bottom'], [added.sid, 'bottom']]);
  // Each bar's area is full, so neither is copied into it.
  assert.equal(canDuplicate('bar1'), false);
  assert.equal(canDuplicate(added.sid), false);

  // A type allowed twice in an area is copied while its own area has room, whatever the page holds.
  const n = page({ n1: 'notice', main: 'product-main', n2: 'notice', n3: 'notice' }, {
    rows: [arow('r1', 'top', [['n1']]), arow('rm', 'content', [['main']]), arow('r2', 'bottom', [['n2']]), arow('r3', 'bottom', [['n3']])],
  });
  S.docs = { product: { handle: 'product', kind: 'template', content: JSON.parse(json(n)), savedSeq: 0 } };
  resetHistory();
  assert.equal(canDuplicate('n1'), true);
  assert.equal(canDuplicate('n2'), false);
  await duplicateSection('n1');
  const copied = rowsOf(S.docs.product.content, LAYOUT, AREA_RULES);
  assert.equal(copied.length, 5);
  assert.deepEqual(copied.slice(0, 2).map((r) => r.area), ['top', 'top'], 'the copy sits under its original, in Top');
});
