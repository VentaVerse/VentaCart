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

// The editor modules register a few listeners when they load; Node has no DOM.
const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

const { useLayout, replaceIn, catalogRules } = await import('../../editor/js/modules/board/model.js');
const {
  addableTypes, pickerGroups, matches, choicesOf, layoutsFor, pillWords, replacedWords,
} = await import('../../editor/js/modules/board/choices.js');
const { S, ctx } = await import('../../editor/js/modules/state.js');
const { setCatalog } = await import('../../editor/js/modules/catalog.js');
const { undo } = await import('../../editor/js/modules/operations.js');
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

/** A page: a banner row, a row of halves, a row of thirds, recently viewed, a left side. */
function samplePage() {
  return {
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
    sides: { left: ['note'] },
  };
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

  const inSide = replaceIn(c, 'note', 'text-1', { type: 'text', settings: {} }, RULES).content;
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
  assert.equal(replaceIn(c, 'note', 'products-1', { type: 'products', settings: {} }, RULES), null, 'A side is 3 wide.');
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
