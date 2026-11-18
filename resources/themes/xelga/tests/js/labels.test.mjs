// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * How a section is named on the board's name bar and in the second line
 * of its settings header: the type is said only when the section has a
 * name of its own, and the settings say where it sits.
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

const { isRenamed, barWords, typeLine, placeWords } = await import('../../editor/js/modules/board/labels.js');

const here = dirname(fileURLToPath(import.meta.url));
const LAYOUT = JSON.parse(readFileSync(join(here, '../fixtures/layout/_layout.json'), 'utf8'));
const row = (id, split, columns) => ({ id, split, columns, settings: { ...LAYOUT.row_defaults } });

test('a section named after its type is not renamed, whatever the case or spaces', () => {
  assert.equal(isRenamed('Brand logos', 'Brand logos'), false);
  assert.equal(isRenamed('  brand LOGOS ', 'Brand logos'), false);
  assert.equal(isRenamed('', 'Brand logos'), false, 'No name is the type\'s name.');
  assert.equal(isRenamed('Holiday promo', 'Promo banners'), true);
});

test('the name bar says the type only when the section is renamed', () => {
  assert.deepEqual(barWords('Holiday promo', 'Promo banners', 'Text under the photo'), {
    name: 'Holiday promo', type: 'Promo banners', layout: 'Text under the photo',
    title: 'Holiday promo · Promo banners · Text under the photo',
  });
  assert.deepEqual(barWords('Brand logos', 'Brand logos', 'Grid with lines'), {
    name: 'Brand logos', type: null, layout: 'Grid with lines', title: 'Brand logos · Grid with lines',
  });
  assert.deepEqual(barWords('', 'Breadcrumbs', ''), { name: 'Breadcrumbs', type: null, layout: null, title: 'Breadcrumbs' });
});

test('the settings header names the type for a renamed section, and only where it sits otherwise', () => {
  assert.deepEqual(typeLine('Holiday promo', 'Promo banners', 'In the middle column of a row'), { type: 'Promo banners', where: 'In the middle column of a row' });
  assert.deepEqual(typeLine('Holiday promo', 'Promo banners', null), { type: 'Promo banners', where: null });
  assert.deepEqual(typeLine('Promo banners', 'Promo banners', 'In the middle column of a row'), { type: null, where: 'In the middle column of a row' });
  assert.equal(typeLine('Promo banners', 'Promo banners', null), null, 'Nothing to add: no second line.');
});

test('where a section sits, in the words of the settings header', () => {
  const content = {
    schema: 1,
    order: ['s', 'a', 'b', 'c', 'd'],
    sections: { s: { type: 'x' }, a: { type: 'x' }, b: { type: 'x' }, c: { type: 'x' }, d: { type: 'x' } },
    rows: [row('r1', '12', [['a']]), row('r2', '4-4-4', [['b'], ['c'], ['d']])],
    sides: { left: ['s'] },
  };
  assert.equal(placeWords(content, 'a', LAYOUT), null);
  assert.equal(placeWords(content, 'c', LAYOUT), 'In the middle column of a row');
  assert.equal(placeWords(content, 'd', LAYOUT), 'In the right column of a row');
  assert.equal(placeWords(content, 's', LAYOUT), 'In the left side');
  assert.equal(placeWords(content, 'h', LAYOUT, 'header'), 'Header, on every page');
});

test('the type line of a header section says it shows on every page', () => {
  const header = { schema: 1, order: ['bar', 'head'], sections: { bar: { type: 'announcement-bar' }, head: { type: 'header' } } };
  assert.equal(placeWords(header, 'bar', LAYOUT, 'header'), 'Header, on every page');
  assert.equal(placeWords({ schema: 1, order: ['bar'], sections: { bar: { type: 'announcement-bar' } } }, 'bar', LAYOUT, 'footer'), 'Footer, on every page');
  // A band section is never renamed away from its place: the line is the place alone.
  assert.deepEqual(typeLine('Announcement bar', 'Announcement bar', 'Header, on every page'), { type: null, where: 'Header, on every page' });
});

test('the type line of a page section names its area and says it is on this page only', () => {
  const rules = {
    layout: LAYOUT,
    minSpan: () => 3,
    isMain: (section) => section?.type === 'main',
    isLocked: (section) => section?.type === 'main',
    sidesAllowed: true,
  };
  const area = (id, name, columns, split = '12') => ({ ...row(id, split, columns), area: name });
  const content = {
    schema: 1,
    order: ['t', 'm', 'b', 'c', 'd'],
    sections: { t: { type: 'x' }, m: { type: 'main' }, b: { type: 'x' }, c: { type: 'x' }, d: { type: 'x' } },
    rows: [area('r1', 'top', [['t']]), area('r2', 'content', [['m']]), area('r3', 'bottom', [['b']]), area('r4', 'bottom', [['c'], ['d']], '6-6')],
  };
  assert.equal(placeWords(content, 't', LAYOUT, 'template', rules), 'In Top, this page only');
  assert.equal(placeWords(content, 'b', LAYOUT, 'template', rules), 'In Bottom, this page only');
  assert.equal(placeWords(content, 'd', LAYOUT, 'template', rules), 'In Bottom, the right half of a row');
  assert.equal(placeWords(content, 'm', LAYOUT, 'template', rules), 'The page content, fixed in place');
});
