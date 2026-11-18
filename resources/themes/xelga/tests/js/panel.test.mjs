// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The always-open settings panel and the views beside it: the names new
 * blocks start with, the panel staying on a section when one of its
 * blocks is removed, what the page's own settings say, when the Add lines
 * always show, and how switching between Board and Preview keeps the place
 * on the page.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

// The editor modules register a few listeners when they load; Node has no DOM.
const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || {
  addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, requestAnimationFrame: noop, location: { href: 'http://example.test/' },
};

const { blockLabel } = await import('../../editor/js/modules/board/labels.js');
const { placeIn, placeFor, scrollBy, addLineMode, OPEN_OFFSET, NARROW } = await import('../../editor/js/modules/board/place.js');
const { pageFacts } = await import('../../editor/js/modules/layouts.js');
const { S, ctx } = await import('../../editor/js/modules/state.js');
const { setCatalog } = await import('../../editor/js/modules/catalog.js');
const { commit, undo } = await import('../../editor/js/modules/operations.js');
const { buildSection } = await import('../../editor/js/modules/actions.js');

/* ------------------------------------------------------------ block names */

test('only a block type a section can hold more than one of gets a number', () => {
  assert.equal(blockLabel('Slide', { limit: 0, max: 6, taken: 1 }), 'Slide 2');
  assert.equal(blockLabel('Slide', { limit: 4, max: 6, taken: 0 }), 'Slide 1');
  assert.equal(blockLabel('Quantity and Add to cart', { limit: 1, max: 12, taken: 0 }), 'Quantity and Add to cart');
  // A section that holds one block at most has nothing to tell apart.
  assert.equal(blockLabel('Photo', { limit: 0, max: 1, taken: 0 }), 'Photo');
});

const CATALOG = {
  buy: {
    label: 'Product', group: 'template',
    settings: [],
    blocks: {
      min: 0,
      max: 12,
      types: [
        { type: 'title', label: 'Title', limit: 1, settings: [] },
        { type: 'buy', label: 'Quantity and Add to cart', limit: 1, settings: [] },
        { type: 'text', label: 'Text', settings: [] },
      ],
    },
  },
};

test('a new section names its blocks by the same rule, counting each type on its own', () => {
  setCatalog({ sections: CATALOG });
  const preset = { blocks: [{ type: 'title' }, { type: 'text' }, { type: 'buy' }, { type: 'text' }] };
  const { snapshot } = buildSection('buy', CATALOG.buy, { preset }, new Set());
  const labels = snapshot.block_order.map((bid) => snapshot.blocks[bid].label);
  assert.deepEqual(labels, ['Title', 'Text 1', 'Quantity and Add to cart', 'Text 2']);
});

/* ------------------------------------------------ removing a block */

test('removing the open block leaves its section open, and undo brings the block back', () => {
  setCatalog({ sections: CATALOG });
  ctx.boot = { layout: null };
  S.page = { handle: 'product', type: 'product' };
  S.docs = {
    product: {
      handle: 'product', kind: 'template', savedJson: '', savedSeq: 0,
      content: {
        schema: 1,
        order: ['main'],
        sections: {
          main: {
            type: 'buy', label: 'Product', disabled: false, settings: {},
            block_order: ['t', 'b', 'x'],
            blocks: {
              t: { type: 'title', label: 'Title', disabled: false, settings: {} },
              b: { type: 'buy', label: 'Quantity and Add to cart', disabled: false, settings: {} },
              x: { type: 'text', label: 'Text 1', disabled: false, settings: {} },
            },
          },
        },
      },
    },
  };
  S.loaded = true;
  S.history = [];
  S.pointer = 0;
  S.sel = { kind: 'section', sid: 'main', bid: 'b' };
  commit({ t: 'block-remove', doc: 'product', sid: 'main', bid: 'b' });
  assert.deepEqual(S.sel, { kind: 'section', sid: 'main', bid: null });
  assert.deepEqual(S.docs.product.content.sections.main.block_order, ['t', 'x'], 'The blocks after it are kept.');
  undo();
  assert.deepEqual(S.docs.product.content.sections.main.block_order, ['t', 'b', 'x']);
});

/* ------------------------------------------------ the page's own settings */

const CATEGORY = { one: 'category', many: 'categories' };

test('a page of its own says so and offers no layout to make', () => {
  const facts = pageFacts({ label: 'Home page' });
  assert.equal(facts.uses, 'Its own layout');
  assert.deepEqual(facts.rows, [['Layout', 'Its own, on this page only']]);
  assert.deepEqual(facts.actions, []);
});

test('a record opened from the menu: the heading says which layout it uses, the panel only counts and offers its own', () => {
  const facts = pageFacts({ label: 'Category', words: CATEGORY, subject: { name: 'Laptops' }, record: true, users: 6, specials: [] });
  assert.equal(facts.uses, 'Uses the category layout');
  assert.deepEqual(facts.rows, [['Used by', 'All 6 categories'], ['Special layouts', 'None']]);
  facts.rows.forEach(([, value]) => assert.doesNotMatch(value, /uses the/i, 'The layout note is told in the heading only.'));
  assert.deepEqual(facts.actions, ['only', 'special']);
});

test('the shared layout itself says what shares it and names the record it is drawn on', () => {
  const facts = pageFacts({ label: 'Category', words: CATEGORY, subject: { name: 'Phones' }, users: 6, specials: ['Sale'] });
  assert.equal(facts.uses, 'Shared by 6 categories');
  assert.deepEqual(facts.rows, [['Used by', 'All 6 categories'], ['Special layouts', 'Sale'], ['Shows', 'Phones']]);
  assert.deepEqual(facts.actions, ['special'], 'A layout for one record is offered on that record.');
});

test('a record already on a special layout is not offered one of its own again', () => {
  const facts = pageFacts({ label: 'Category', words: CATEGORY, subject: { name: 'Laptops' }, record: true, users: 4, elsewhere: true });
  assert.deepEqual(facts.actions, ['special']);
});

test('a special layout says what uses it', () => {
  const own = pageFacts({ label: 'Category', words: CATEGORY, layout: { label: 'Gift guide', count: 1 }, subject: { name: 'Gifts' } });
  assert.equal(own.uses, 'Special category layout, used by 1 category');
  assert.deepEqual(own.rows, [['Used by', '1 category'], ['Shows', 'Gifts']]);
  const record = pageFacts({ label: 'Category', words: CATEGORY, layout: { label: 'Gift guide', count: 0 }, subject: { name: 'Gifts' }, record: true });
  assert.equal(record.uses, 'Uses the Gift guide layout');
  assert.deepEqual(record.rows, [['Used by', 'No category yet']]);
});

test('a page with a fixed layout points to Theme settings', () => {
  const facts = pageFacts({ fixed: true, label: 'Cart' });
  assert.equal(facts.uses, 'A fixed layout');
  assert.deepEqual(facts.rows, [['Layout', 'Fixed. Theme settings change how it looks.']]);
  assert.deepEqual(facts.actions, []);
});

test('Page settings never carry help text or search-engine fields', () => {
  const all = [
    pageFacts({ label: 'Home page' }),
    pageFacts({ label: 'Category', words: CATEGORY, subject: { name: 'Laptops' }, record: true, users: 6 }),
    pageFacts({ label: 'Category', words: CATEGORY, layout: { label: 'Gift guide', count: 2 } }),
  ];
  all.forEach((facts) => facts.rows.forEach(([term, value]) => {
    assert.doesNotMatch(`${term} ${value}`, /click|board to edit|search engine|meta|description|title tag/i);
  }));
});

/* ------------------------------------------------------------ the Add line */

test('the Add line shows on hover on a computer, and always on touch, tablets and phones', () => {
  assert.equal(addLineMode({ coarse: false, hover: true, width: 1440 }), 'hover');
  assert.equal(addLineMode({ coarse: false, hover: true, width: 1280 }), 'hover');
  assert.equal(addLineMode({ coarse: false, hover: true, width: NARROW }), 'hover');
  assert.equal(addLineMode({ coarse: false, hover: true, width: NARROW - 1 }), 'always');
  assert.equal(addLineMode({ coarse: false, hover: true, width: 834 }), 'always');
  assert.equal(addLineMode({ coarse: false, hover: true, width: 390 }), 'always');
  assert.equal(addLineMode({ coarse: true, hover: false, width: 1440 }), 'always', 'A large touch screen has no hover either.');
  assert.equal(addLineMode({ coarse: false, hover: false, width: 1440 }), 'always');
});

/* ------------------------------------------------- Board and Preview */

const BOXES = [
  { sid: 'header', top: 0, bottom: 80 },
  { sid: 'hero', top: 92, bottom: 176 },
  { sid: 'tiles', top: 188, bottom: 272 },
  { sid: 'footer', top: 284, bottom: 368 },
];

test('the section at the top of a view is the first that reaches past its top', () => {
  assert.deepEqual(placeIn(BOXES, 100), { sid: 'hero', offset: -8 });
  // A section showing only its last few pixels does not count.
  assert.deepEqual(placeIn(BOXES, 160), { sid: 'tiles', offset: 28 });
  assert.equal(placeIn(BOXES, 400), null);
  assert.equal(placeIn([], 0), null);
});

test('switching keeps the open section, else the section at the top, else the top of the page', () => {
  assert.deepEqual(placeFor({ selected: 'tiles', scrolled: 0, boxes: BOXES, viewTop: 0 }), { sid: 'tiles', offset: OPEN_OFFSET, open: true });
  assert.equal(placeFor({ selected: null, scrolled: 12, boxes: BOXES, viewTop: 12 }), null, 'A view at its top keeps the other at its top.');
  assert.deepEqual(placeFor({ selected: null, scrolled: 190, boxes: BOXES, viewTop: 190 }), { sid: 'tiles', offset: -2 });
});

test('the other view scrolls so the place shows where it was, never under its top edge', () => {
  // The section is 500 below the view's top and was 30 below the other view's top: scroll by 470.
  assert.equal(scrollBy(600, 100, 30), 470);
  // A section that started above the other view's top is shown from its start.
  assert.equal(scrollBy(600, 100, -40), 484);
});
