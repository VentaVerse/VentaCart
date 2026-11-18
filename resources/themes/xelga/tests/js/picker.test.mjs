// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The section picker: which section types and layouts it offers at a spot
 * of the board and why it greys the others, its groups and search, and
 * replacing a section in its place as one change that undo takes back
 * exactly.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Element } from './support/mini-dom.mjs';
import * as board from './support/board-page.mjs';

// The editor modules register a few listeners when they load; Node has no DOM.
const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

const { useLayout, replaceIn, catalogRules } = await import('../../editor/js/modules/board/model.js');
const {
  addableTypes, pickerGroups, matches, choicesOf, layoutsFor, pillWords, replacedWords, needsWords,
} = await import('../../editor/js/modules/board/choices.js');
const { S, ctx } = await import('../../editor/js/modules/state.js');
const { setCatalog } = await import('../../editor/js/modules/catalog.js');
const { undo, redo, reapply } = await import('../../editor/js/modules/operations.js');
const { replaceSection } = await import('../../editor/js/modules/actions.js');

const here = dirname(fileURLToPath(import.meta.url));
const LAYOUT = JSON.parse(readFileSync(join(here, '../fixtures/layout/_layout.json'), 'utf8'));
useLayout(LAYOUT);
const DEFAULTS = LAYOUT.row_defaults;

const scheme = (value) => ({ key: 'scheme', type: 'color-scheme', label: 'Color scheme', default: value });
const variantSetting = (value) => ({ key: 'variant', type: 'select', label: 'Layout', default: value });

/* Section types as the boot payload carries them: a span, a group, presets or variants. */
const CATALOG = {
  banner: {
    label: 'Main banner', group: 'template', picker_group: 'banners', span: { min: 12, variants: {} },
    settings: [variantSetting('full'), scheme('scheme-1'), { key: 'heading', type: 'text', default: '' }],
    variants: [{ value: 'full', label: 'Full-width photo' }, { value: 'split', label: 'Photo and text side by side' }],
    blocks: { min: 1, max: 4, types: [{ type: 'slide', label: 'Photo', settings: [{ key: 'image', type: 'image', default: '' }, { key: 'heading', type: 'text', default: '' }] }] },
  },
  look: {
    label: 'Shop the look', group: 'template', picker_group: 'banners', span: { min: 8, variants: { full: 12 } },
    settings: [variantSetting('side'), scheme('scheme-2')],
    variants: [{ value: 'side', label: 'Photo with a list beside it' }, { value: 'full', label: 'Wide photo, list below' }],
  },
  products: {
    label: 'Featured products', group: 'template', picker_group: 'products', span: { min: 6, variants: {} },
    settings: [variantSetting('grid'), scheme('scheme-1'), { key: 'heading', type: 'text', default: 'Best sellers' }, { key: 'products', type: 'product-picker', default: [] }],
    variants: [{ value: 'grid', label: 'Grid' }, { value: 'carousel', label: 'Swipe row' }],
    presets: [
      { key: 'best', label: 'Grid of best sellers', settings: { variant: 'grid' } },
      { key: 'new', label: 'Swipe row of new products', settings: { variant: 'carousel', heading: 'New this week' } },
    ],
  },
  recent: {
    label: 'Recently viewed', group: 'template', picker_group: 'products', limit: 1, span: { min: 6, variants: {} },
    settings: [scheme('scheme-1')],
  },
  tiles: {
    label: 'Category tiles', group: 'template', picker_group: 'categories', span: { min: 6, variants: {} },
    settings: [variantSetting('cards'), scheme('scheme-1')],
    variants: [{ value: 'cards', label: 'Photo cards' }, { value: 'chips', label: 'Compact list' }],
  },
  text: {
    label: 'Text', group: 'template', picker_group: 'text', span: { min: 3, variants: { split: 6 } },
    settings: [variantSetting('start'), scheme('scheme-1'), { key: 'heading', type: 'text', default: 'Talk about your brand' }],
    variants: [{ value: 'start', label: 'Left aligned' }, { value: 'center', label: 'Centered' }, { value: 'split', label: 'Heading beside text' }],
  },
  plain: { label: 'Plain note', group: 'template', span: { min: 3, variants: {} }, settings: [] },
  'demo.card': {
    label: 'Seller card', group: 'template', source: 'demo', source_label: 'Demo Market', span: { min: 3, variants: {} }, settings: [],
  },
  'product-main': { label: 'Product', group: 'template', main: true, span: { min: 12, variants: {} }, settings: [] },
};

const sectionDef = (type) => CATALOG[type] || null;
const RULES = catalogRules(LAYOUT, sectionDef, true);

const row = (id, split, columns) => ({ id, split, columns, settings: { ...DEFAULTS } });

/**
 * A page: a banner row, a row of halves, a row of thirds, recently viewed,
 * and a note before them; with `side`, the note sits in a left side (which
 * narrows every column to three quarters of its part).
 */
function samplePage({ side = false } = {}) {
  const c = {
    schema: 1,
    order: ['note', 'hero', 'words', 'shop', 'a', 'b', 'c', 'seen'],
    sections: {
      note: { type: 'plain', label: 'Note', disabled: false, settings: {}, block_order: [], blocks: {} },
      hero: {
        type: 'banner', label: 'Main banner', disabled: false, settings: { variant: 'split', scheme: 'scheme-4', heading: 'Phones paid at your door' },
        block_order: ['slide-a', 'slide-b', 'slide-c'],
        blocks: {
          'slide-a': { type: 'slide', label: 'Photo 1', disabled: false, settings: { image: 'media/demo/one.jpg', heading: 'Shop phones' } },
          'slide-b': { type: 'slide', label: 'Photo 2', disabled: false, settings: { image: 'media/demo/two.jpg' } },
          'slide-c': { type: 'slide', label: 'Photo 3', disabled: false, settings: { image: 'media/demo/three.jpg' } },
        },
      },
      words: { type: 'text', label: 'Text', disabled: false, settings: { scheme: 'scheme-3' }, block_order: [], blocks: {} },
      shop: { type: 'products', label: 'Featured products', disabled: false, settings: {}, block_order: [], blocks: {} },
      a: { type: 'text', label: 'Text A', disabled: false, settings: {}, block_order: [], blocks: {} },
      b: { type: 'text', label: 'Text B', disabled: false, settings: {}, block_order: [], blocks: {} },
      c: { type: 'text', label: 'Text C', disabled: false, settings: {}, block_order: [], blocks: {} },
      seen: { type: 'recent', label: 'Recently viewed', disabled: false, settings: {}, block_order: [], blocks: {} },
    },
    rows: [
      row('r-hero', '12', [['hero']]),
      row('r-two', '6-6', [['words'], ['shop']]),
      row('r-three', '4-4-4', [['a'], ['b'], ['c']]),
      row('r-seen', '12', [['seen']]),
    ],
  };
  if (!side) return c;
  // Only a page with page content has sides; they stand beside its Content rows.
  c.sections.main = { type: 'product-main', label: 'Product', disabled: false, settings: {}, block_order: [], blocks: {} };
  c.order.push('main');
  c.rows = [...c.rows, row('r-main', '12', [['main']])].map((r) => ({ ...r, area: 'content' }));
  c.sides = { left: ['note'] };
  return c;
}

const offered = Object.fromEntries(Object.entries(CATALOG).filter(([, def]) => !def.main));
const byType = (list) => Object.fromEntries(list.map((x) => [x.type, x]));

test('a half column offers what fits and greys the rest in place, with the reason', () => {
  const c = samplePage();
  const list = addableTypes(c, { drop: 'column', rowId: 'r-two', column: 0, before: null }, offered, RULES);
  const by = byType(list);
  assert.equal(by['product-main'], undefined, 'Main sections are never offered.');
  assert.equal(pillWords(by.banner), 'Needs full width');
  assert.equal(pillWords(by.look), 'Needs two thirds');
  assert.equal(pillWords(by.recent), 'Already on this page');
  ['products', 'tiles', 'text', 'plain', 'demo.card'].forEach((type) => assert.equal(by[type].disabled, false, type));
  // The order of the catalog is kept: greyed tiles do not move.
  assert.deepEqual(list.map((x) => x.type), Object.keys(offered));
});

test('beside a side, a half column greys a section of 6 as narrowed by the sides, not as needing half', () => {
  const c = samplePage({ side: true });
  const target = { drop: 'column', rowId: 'r-two', column: 0, before: null };
  const by = byType(addableTypes(c, target, offered, RULES));
  assert.equal(by.tiles.disabled, true);
  assert.equal(pillWords(by.tiles), 'Too narrow beside the sides');
  // A section that needs more than the column's own part still names its size.
  assert.equal(pillWords(by.look), 'Needs two thirds');
  assert.equal(by.text.disabled, false);
  // The same in replace mode, from the spot's own sizes.
  const replacing = byType(addableTypes(c, null, offered, RULES, { span: 4.5, whole: 6, now: 'text' }));
  assert.equal(pillWords(replacing.tiles), 'Too narrow beside the sides');
  assert.equal(needsWords(6, 6), 'Too narrow beside the sides');
  assert.equal(needsWords(8, 6), 'Needs two thirds');
});

test('a gap between rows offers every type but those at their limit', () => {
  const list = addableTypes(samplePage(), { drop: 'gap', before: 'r-seen' }, offered, RULES);
  const greyed = list.filter((x) => x.disabled).map((x) => x.type);
  assert.deepEqual(greyed, ['recent']);
});

test('a narrow column greys wider types and, inside a type, the layouts that need more room', () => {
  const c = samplePage();
  const by = byType(addableTypes(c, { drop: 'column', rowId: 'r-three', column: 1, before: null }, offered, RULES));
  assert.equal(pillWords(by.products), 'Needs half or more');
  assert.equal(pillWords(by.tiles), 'Needs half or more');
  assert.equal(by.text.disabled, false);
  const layouts = layoutsFor('text', CATALOG.text, 4, RULES);
  assert.deepEqual(layouts.map((l) => [l.label, l.fits]), [['Left aligned', true], ['Centered', true], ['Heading beside text', false]]);
  assert.equal(layouts[2].need, 6);
});

test('a type with no presets and no variants has exactly one layout, so its tile adds at once', () => {
  assert.equal(choicesOf(CATALOG.recent).length, 1);
  assert.equal(choicesOf(CATALOG.plain).length, 1);
  assert.equal(choicesOf(CATALOG.products).length, 2, 'Presets are the layouts when a type has them.');
  assert.equal(choicesOf(CATALOG.products)[1].preset.key, 'new');
  assert.equal(choicesOf(CATALOG.look).length, 2);
});

test('search reads type names and layout names', () => {
  assert.equal(matches(CATALOG.products, 'swipe'), true, 'A preset name answers the search.');
  assert.equal(matches(CATALOG.look, 'wide photo'), true, 'A variant name answers the search.');
  assert.equal(matches(CATALOG.tiles, 'swipe'), false);
  assert.equal(matches(CATALOG.recent, 'RECENT'), true);
  assert.equal(matches(CATALOG.tiles, '  '), true);
});

test('tiles sit in the four groups, an ungrouped type under text and trust, extensions after under their name', () => {
  const groups = pickerGroups(addableTypes(samplePage(), { drop: 'gap', before: 'end' }, offered, RULES), offered);
  assert.deepEqual(groups.map((g) => [g.name, g.types.map((t) => t.type)]), [
    ['Banners and photos', ['banner', 'look']],
    ['Products', ['products', 'recent']],
    ['Categories and brands', ['tiles']],
    ['Text and trust', ['text', 'plain']],
    ['Demo Market', ['demo.card']],
  ]);
});

test('tiles follow picker_order within a group, types without one after in catalog order', () => {
  const ordered = {
    ...offered,
    tiles: { ...CATALOG.tiles, picker_order: 30 },
    text: { ...CATALOG.text, picker_order: 70 },
    plain: { ...CATALOG.plain, picker_order: 20 },
    look: { ...CATALOG.look, picker_order: 5 },
    banner: { ...CATALOG.banner, picker_order: 10 },
    products: { ...CATALOG.products, picker_order: 20 },
  };
  const groups = pickerGroups(addableTypes(samplePage(), { drop: 'gap', before: 'end' }, ordered, RULES), ordered);
  assert.deepEqual(groups.map((g) => g.types.map((t) => t.type)), [['look', 'banner'], ['products', 'recent'], ['tiles'], ['plain', 'text'], ['demo.card']]);
});

test('replace mode greys the type in the spot as now here', () => {
  const by = byType(addableTypes(samplePage(), null, offered, RULES, { span: 12, now: 'banner' }));
  assert.equal(pillWords(by.banner), 'Now here');
  assert.equal(by.look.disabled, false);
});

test('replaceIn keeps the spot: a column, a side and a full-width row', () => {
  const c = samplePage();
  const fresh = { type: 'tiles', settings: {}, block_order: [], blocks: {} };
  const inColumn = replaceIn(c, 'shop', 'tiles-1', fresh, RULES).content;
  assert.deepEqual(inColumn.rows[1].columns, [['words'], ['tiles-1']]);
  assert.equal(inColumn.rows[1].id, 'r-two');
  assert.deepEqual(inColumn.order, ['note', 'hero', 'words', 'tiles-1', 'a', 'b', 'c', 'seen']);
  assert.deepEqual(Object.keys(inColumn.sections), ['note', 'hero', 'words', 'tiles-1', 'a', 'b', 'c', 'seen']);
  assert.equal(inColumn.sections.shop, undefined);

  const inSide = replaceIn(samplePage({ side: true }), 'note', 'text-1', { type: 'text', settings: {} }, RULES).content;
  assert.deepEqual(inSide.sides, { left: ['text-1'] });

  const inRow = replaceIn(c, 'hero', 'look-1', { type: 'look', settings: { variant: 'full' } }, RULES).content;
  assert.deepEqual(inRow.rows[0], row('r-hero', '12', [['look-1']]));
  // The input is never changed.
  assert.deepEqual(c, samplePage());
});

test('replaceIn refuses a section that does not fit the spot', () => {
  const c = samplePage();
  assert.equal(replaceIn(c, 'shop', 'banner-1', { type: 'banner', settings: {} }, RULES), null);
  assert.equal(replaceIn(c, 'b', 'tiles-1', { type: 'tiles', settings: {} }, RULES), null);
  assert.equal(replaceIn(samplePage({ side: true }), 'note', 'products-1', { type: 'products', settings: {} }, RULES), null, 'A side is 3 wide.');
  // Beside a side, a half is four and a half twelfths: a section of 6 no longer fits it.
  assert.equal(replaceIn(samplePage({ side: true }), 'words', 'tiles-1', { type: 'tiles', settings: {} }, RULES), null);
  assert.ok(replaceIn(samplePage(), 'words', 'tiles-1', { type: 'tiles', settings: {} }, RULES));
  assert.equal(replaceIn(c, 'hero', 'main-1', { type: 'product-main', settings: {} }, RULES), null, 'Main sections are never put in by replacing.');
  assert.equal(replaceIn(c, 'hero', 'shop', { type: 'look', settings: {} }, RULES), null, 'The new id must be free.');
  assert.equal(replaceIn(c, 'gone', 'look-1', { type: 'look', settings: {} }, RULES), null);
});

/* The editor's state for one open page, with the catalog and the layout of the boot payload. */
function openPage(content) {
  setCatalog({ sections: CATALOG });
  ctx.boot = { layout: LAYOUT };
  S.page = { handle: 'home', type: 'home', pageType: 'home' };
  S.docs = { home: { handle: 'home', kind: 'template', content, savedJson: '', savedSeq: 0 } };
  S.history = [];
  S.pointer = 0;
  S.loaded = true;
  S.fatal = null;
  S.sel = null;
}

test('replace swaps the section in its spot with the new defaults and the old color scheme, and one undo restores it exactly', () => {
  openPage(samplePage());
  const before = JSON.stringify(S.docs.home.content);
  const newSid = replaceSection('words', 'products', { preset: CATALOG.products.presets[1] });
  assert.ok(newSid);
  const c = S.docs.home.content;
  assert.equal(S.history.length, 1, 'The replace is one undo step.');
  assert.equal(c.sections.words, undefined);
  assert.deepEqual(c.rows[1].columns, [[newSid], ['shop']]);
  assert.equal(c.order.indexOf(newSid), 2);
  const fresh = c.sections[newSid];
  assert.equal(fresh.type, 'products');
  assert.equal(fresh.label, 'Featured products');
  assert.deepEqual(fresh.settings, { variant: 'carousel', heading: 'New this week', scheme: 'scheme-3' });

  undo();
  assert.equal(JSON.stringify(S.docs.home.content), before, 'Id, settings, blocks and place are back, key order included.');
});

test('redo after undo puts the same replacement back, and a replay skips a replace whose section is gone', () => {
  openPage(samplePage());
  const newSid = replaceSection('words', 'tiles', { variant: 'chips' });
  const after = JSON.stringify(S.docs.home.content);
  const op = S.history[0];
  undo();
  redo();
  assert.equal(JSON.stringify(S.docs.home.content), after, 'The same new section, in the same place.');
  assert.ok(S.docs.home.content.sections[newSid]);

  // Replayed on a document where someone else removed the old section: nothing happens.
  openPage(samplePage());
  delete S.docs.home.content.sections.words;
  S.docs.home.content.order = S.docs.home.content.order.filter((id) => id !== 'words');
  S.docs.home.content.rows[1].columns[0] = [];
  const before = JSON.stringify(S.docs.home.content);
  assert.equal(reapply([op]), 0);
  assert.equal(JSON.stringify(S.docs.home.content), before);
  assert.equal(S.history.length, 0);
});

test('replace keeps the scheme the old section shows even when it was never set', () => {
  openPage(samplePage());
  const newSid = replaceSection('a', 'plain');
  assert.equal(S.docs.home.content.sections[newSid].settings.scheme, undefined, 'A type with no scheme setting gets none.');
  undo();
  const shown = replaceSection('shop', 'tiles');
  assert.equal(S.docs.home.content.sections[shown].settings.scheme, 'scheme-1', 'The default the old section showed is kept.');
  undo();
  const other = replaceSection('b', 'text', { variant: 'center' });
  assert.equal(other, null, 'The type in the spot now is not offered.');
  const tiles = replaceSection('hero', 'tiles', { variant: 'chips' });
  assert.deepEqual(S.docs.home.content.sections[tiles].settings, { variant: 'chips', scheme: 'scheme-4' });
});

test('replace is refused when the new type does not fit the spot, and nothing changes', () => {
  openPage(samplePage());
  const before = JSON.stringify(S.docs.home.content);
  assert.equal(replaceSection('shop', 'banner'), null);
  assert.equal(replaceSection('b', 'products'), null);
  assert.equal(replaceSection('words', 'recent'), null, 'A type at its limit is refused too.');
  assert.equal(JSON.stringify(S.docs.home.content), before);
  assert.equal(S.history.length, 0);
});

test('the warning names what the merchant put in the section', () => {
  const page = samplePage();
  assert.equal(replacedWords(page.sections.hero, CATALOG.banner), '3 photos and their text');
  assert.equal(replacedWords(page.sections.shop, CATALOG.products), null, 'A section that reads the catalog loses nothing typed.');
  assert.equal(replacedWords({ settings: { heading: 'Our story', products: [4, 7] } }, CATALOG.products), 'text and chosen products');
  assert.equal(replacedWords({ settings: { heading: 'Best sellers' } }, CATALOG.products), null, 'The schema\'s own words are not the merchant\'s.');
  const one = { settings: {}, block_order: ['s'], blocks: { s: { type: 'slide', settings: { image: 'media/demo/one.jpg', heading: 'Hi' } } } };
  assert.equal(replacedWords(one, CATALOG.banner), 'photo and its text');
});

/* ------------------------------------------------------------------------
   Areas and bands: the Demo Store's Product and Category pages
   ------------------------------------------------------------------------ */

const { openPicker, closePicker, availableTypes } = await import('../../editor/js/modules/board/picker.js');
const { pickerWhere } = await import('../../editor/js/modules/board/words.js');

const AREA_RULES = catalogRules(board.LAYOUT, (type) => board.CATALOG[type] || null, true);
const areaRow = (id, area, columns, split = '12') => ({ id, split, columns, settings: { ...board.LAYOUT.row_defaults }, area });
const bar = (label = 'Announcement bar') => ({ type: 'announcement-bar', label, disabled: false, settings: {}, block_order: [], blocks: {} });
const pageTypes = () => Object.fromEntries(Object.entries(board.CATALOG).filter(([, def]) => def.group === 'template'));

/* The Demo Store's product page, with a row of halves in Bottom and, when asked, bars in Top and Bottom. */
function productPage({ top = false, bottom = false } = {}) {
  const c = board.documents().product;
  c.sections.l = { ...c.sections.words, label: 'Left text' };
  c.sections.r = { ...c.sections.words, label: 'Right text' };
  c.order.push('l', 'r');
  c.rows.push(areaRow('r-halves', 'bottom', [['l'], ['r']], '6-6'));
  if (top) {
    c.sections['bar-top'] = bar('Top bar');
    c.order.unshift('bar-top');
    c.rows.unshift(areaRow('r-bar-top', 'top', [['bar-top']]));
  }
  if (bottom) {
    c.sections['bar-bottom'] = bar('Bottom bar');
    c.order.push('bar-bottom');
    c.rows.push(areaRow('r-bar-bottom', 'bottom', [['bar-bottom']]));
  }
  return c;
}

const stateOf = (content, target, type) => addableTypes(content, target, pageTypes(), AREA_RULES).find((x) => x.type === type);

test('the bar is grey in a column with Needs a row of its own', () => {
  const column = stateOf(productPage(), { drop: 'column', rowId: 'r-halves', column: 0, before: null }, 'announcement-bar');
  assert.equal(column.disabled, true);
  assert.equal(pillWords(column), 'Needs a row of its own');
  const side = stateOf(productPage(), { drop: 'side', side: 'left', before: null }, 'announcement-bar');
  assert.equal(pillWords(side), 'Needs a row of its own');
  // Between rows it fits.
  assert.equal(stateOf(productPage(), { drop: 'gap', area: 'bottom', before: 'end' }, 'announcement-bar').disabled, false);
});

test('a second bar in Bottom is grey with Already in Bottom', () => {
  const state = stateOf(productPage({ bottom: true }), { drop: 'gap', area: 'bottom', before: 'end' }, 'announcement-bar');
  assert.equal(state.disabled, true);
  assert.equal(pillWords(state), 'Already in Bottom');
  // On a page without areas the whole page counts.
  const home = board.documents().home;
  home.sections.b = bar();
  home.order.push('b');
  assert.equal(pillWords(stateOf(home, { drop: 'gap', before: 'end' }, 'announcement-bar')), 'Already on this page');
});

test('a bar in Top does not grey a bar for Bottom', () => {
  const c = productPage({ top: true });
  assert.equal(stateOf(c, { drop: 'gap', area: 'bottom', before: 'end' }, 'announcement-bar').disabled, false);
  assert.equal(pillWords(stateOf(c, { drop: 'gap', area: 'top', before: 'end' }, 'announcement-bar')), 'Already in Top');
});

test('filters is grey in Top with Goes beside the page content', () => {
  const c = board.documents().category;
  const top = stateOf(c, { drop: 'gap', area: 'top', before: 'end' }, 'filters');
  assert.equal(top.disabled, true);
  assert.equal(pillWords(top), 'Goes beside the page content');
  assert.equal(pillWords(stateOf(c, { drop: 'gap', area: 'bottom', before: 'end' }, 'filters')), 'Goes beside the page content');
  // In Content it is only at its limit.
  assert.equal(pillWords(stateOf(c, { drop: 'gap', area: 'content', before: 'end' }, 'filters')), 'Already on this page');
});

test('a section too wide for Content beside a side is grey with the narrow words', () => {
  const state = stateOf(productPage(), { drop: 'gap', area: 'content', before: 'end' }, 'banner');
  assert.equal(pillWords(state), 'Too narrow beside the sides');
  assert.equal(stateOf(productPage(), { drop: 'gap', area: 'top', before: 'end' }, 'banner').disabled, false);
});

test('locked types are never offered', () => {
  const list = addableTypes(productPage(), { drop: 'gap', area: 'top', before: 'end' }, board.CATALOG, AREA_RULES).map((x) => x.type);
  ['header', 'footer', 'product-main', 'category-main'].forEach((type) => assert.ok(!list.includes(type), type));
  board.openPage('product');
  assert.deepEqual(Object.keys(availableTypes()).filter((type) => ['header', 'footer', 'product-main'].includes(type)), []);
  assert.deepEqual(Object.keys(availableTypes('header')), ['announcement-bar']);
});

test('where words name the area and the side', () => {
  board.openPage('product');
  assert.deepEqual(pickerWhere({ target: { drop: 'gap', area: 'top', before: 'end' } }), ['Goes in Top, below ', 'Breadcrumbs']);
  assert.deepEqual(pickerWhere({ target: { drop: 'gap', area: 'content', before: 'r-main' } }), ['Goes in Content, beside the left side, above ', 'Product information']);
  assert.deepEqual(pickerWhere({ target: { drop: 'gap', area: 'bottom', before: 'r-words' } }), ['Goes in Bottom, above ', 'Text']);
  assert.deepEqual(pickerWhere({ replace: 'words' }), ['Stays in Bottom']);
  board.openPage('category');
  assert.deepEqual(pickerWhere({ target: { drop: 'gap', area: 'bottom', before: 'end' } }), ['Goes in Bottom']);
  // A band is shared by every page.
  assert.deepEqual(pickerWhere({ target: { drop: 'band', group: 'header', before: 'head' } }), ['Goes in the header on every page, above ', 'Header']);
  // A page without areas.
  board.openPage('home', { content: { schema: 1, order: [], sections: {} } });
  assert.deepEqual(pickerWhere({ target: { drop: 'gap', area: null, before: 'end' } }), ['Goes on this page']);
});

/* The picker's window, as the board opens it. */
function pickerDialog() {
  document.querySelector('#section-picker')?.remove();
  const dialog = new Element('dialog');
  dialog.setAttribute('id', 'section-picker');
  dialog.showModal = () => { dialog.open = true; };
  dialog.close = () => { dialog.open = false; };
  document.body.append(dialog);
  return dialog;
}

const flat = (el) => el?.textContent.replace(/\s+/g, ' ').trim() ?? null;

test('the band picker offers only the bar and opens its layouts', () => {
  board.useWindow('computer');
  const docs = board.documents();
  docs.header = { schema: 1, order: ['head'], sections: { head: docs.header.sections.head } };
  board.openPage('product', { docs });
  const dialog = pickerDialog();
  openPicker({ target: { drop: 'band', group: 'header', before: 'head' } });
  assert.equal(flat(dialog.querySelector('.spick__title')), 'Add to the header');
  assert.equal(dialog.dataset.place, 'band');
  assert.equal(dialog.querySelector('.spick__search'), null, 'No search: there is one type to choose.');
  assert.deepEqual(dialog.querySelectorAll('.stile').map((t) => t.dataset.type), ['announcement-bar']);
  // Its layouts are open at once.
  assert.equal(flat(dialog.querySelector('.spick__pane-title')), 'Announcement bar');
  assert.equal(dialog.querySelectorAll('#spick-pane .lcard').length, 1);
  // The map draws the band, with the new bar above the Header.
  const band = dialog.querySelector('.smap__band');
  assert.deepEqual(band.children.map((c) => c.className), ['smap__new', 'smap__sec smap__sec--fixed']);
  closePicker({ focus: false });

  // A page spot keeps the page's title and its search.
  openPicker({ target: { drop: 'gap', area: 'bottom', before: 'end' } });
  assert.equal(flat(dialog.querySelector('.spick__title')), 'Add a section');
  assert.equal(dialog.dataset.place, 'page');
  assert.ok(dialog.querySelector('.spick__search'));
  closePicker({ focus: false });
  dialog.remove();
});
