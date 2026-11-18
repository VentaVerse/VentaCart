// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The page menu under the page's heading: its search (the count in the
 * box, the match marked in each name, the special layout a record uses,
 * the record open now) and what each key does.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

const { RECORD_TYPES, searchWords, markedParts, recordRows, menuKey } = await import('../../editor/js/modules/board/page-menu.js');

test('Category, Product and Information page open in place to their records', () => {
  assert.deepEqual(Object.keys(RECORD_TYPES), ['category', 'product', 'page']);
  assert.equal(RECORD_TYPES.product.kind, 'products');
});

test('the search box names how many records there are', () => {
  assert.equal(searchWords(248, RECORD_TYPES.product), 'Search 248 products');
  assert.equal(searchWords(1, RECORD_TYPES.category), 'Search 1 category');
  assert.equal(searchWords(0, RECORD_TYPES.page), 'Search pages');
});

test('the search is marked where it appears in a name, whatever its case', () => {
  assert.deepEqual(markedParts('Laptops', 'lap'), [{ text: 'Lap', match: true }, { text: 'tops', match: false }]);
  assert.deepEqual(markedParts('Folding laptop stand', 'LAPTOP'), [
    { text: 'Folding ', match: false }, { text: 'laptop', match: true }, { text: ' stand', match: false },
  ]);
  assert.deepEqual(markedParts('Phones', 'lap'), [{ text: 'Phones', match: false }]);
  assert.deepEqual(markedParts('Phones', '  '), [{ text: 'Phones', match: false }]);
});

test('a record row carries the special layout it uses and whether it is open now', () => {
  const templates = [
    { pageType: 'product', label: 'Gift guide', assigned: [7, 9] },
    { pageType: 'category', label: 'Sale', assigned: [5] },
  ];
  const rows = recordRows([{ id: 5, name: 'Aero 14 laptop' }, { id: 7, name: 'Holiday gift box' }], {
    query: 'gift', templates, pageType: 'product', current: '7',
  });
  assert.deepEqual(rows.map((r) => [r.id, r.tag, r.current]), [[5, null, false], [7, 'Gift guide', true]]);
  assert.deepEqual(rows[1].parts.map((p) => p.match), [false, true, false]);
});

const STOPS = { count: 6, names: ['Home', 'Category', 'Product', 'Brand', 'Cart', 'Contact'] };

test('Up and Down move through every stop and wrap; Home and End jump to the ends', () => {
  assert.deepEqual(menuKey({ key: 'ArrowDown', index: 0, ...STOPS }), { do: 'move', to: 1 });
  assert.deepEqual(menuKey({ key: 'ArrowDown', index: 5, ...STOPS }), { do: 'move', to: 0 });
  assert.deepEqual(menuKey({ key: 'ArrowUp', index: 0, ...STOPS }), { do: 'move', to: 5 });
  assert.deepEqual(menuKey({ key: 'Home', index: 3, ...STOPS }), { do: 'move', to: 0 });
  assert.deepEqual(menuKey({ key: 'End', index: 1, ...STOPS }), { do: 'move', to: 5 });
  // In the search box the arrows still move, but Home and End stay with the text.
  assert.deepEqual(menuKey({ key: 'ArrowDown', index: 2, inSearch: true, ...STOPS }), { do: 'move', to: 3 });
  assert.equal(menuKey({ key: 'Home', index: 2, inSearch: true, ...STOPS }), null);
});

test('Right opens a type in place and Left closes it', () => {
  assert.deepEqual(menuKey({ key: 'ArrowRight', index: 1, type: 'category', expanded: null, ...STOPS }), { do: 'toggle', type: 'category' });
  assert.equal(menuKey({ key: 'ArrowRight', index: 1, type: 'category', expanded: 'category', ...STOPS }), null);
  assert.deepEqual(menuKey({ key: 'ArrowLeft', index: 2, type: 'category', expanded: 'category', ...STOPS }), { do: 'toggle', type: 'category' });
  assert.equal(menuKey({ key: 'ArrowLeft', index: 0, type: null, expanded: null, ...STOPS }), null);
});

test('a letter goes into the open search, or jumps to the next page type starting with it', () => {
  assert.deepEqual(menuKey({ key: 'l', index: 1, hasSearch: true, ...STOPS }), { do: 'search', letter: 'l' });
  assert.deepEqual(menuKey({ key: 'c', index: 0, ...STOPS }), { do: 'move', to: 1 });
  assert.deepEqual(menuKey({ key: 'C', index: 1, ...STOPS }), { do: 'move', to: 4 }, 'Again: the next one.');
  assert.deepEqual(menuKey({ key: 'c', index: 5, ...STOPS }), { do: 'move', to: 1 }, 'It wraps round.');
  assert.equal(menuKey({ key: 'z', index: 0, ...STOPS }), null);
  assert.equal(menuKey({ key: 'c', index: 0, modifier: true, ...STOPS }), null, 'Shortcuts are left alone.');
  assert.equal(menuKey({ key: ' ', index: 0, ...STOPS }), null, 'Space presses the row.');
});

test('Enter in the search opens the first match, and Escape closes from anywhere', () => {
  assert.deepEqual(menuKey({ key: 'Enter', index: 2, inSearch: true, ...STOPS }), { do: 'first' });
  assert.equal(menuKey({ key: 'Enter', index: 2, ...STOPS }), null, 'Enter on a row presses it.');
  assert.deepEqual(menuKey({ key: 'Escape', index: 2, inSearch: true, ...STOPS }), { do: 'close' });
});
