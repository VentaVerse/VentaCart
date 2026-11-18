// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The settings drawer beside the board: the settings a row of columns has,
 * and the color schemes it offers, which carry their colors and never a
 * name; what a locked section and a band section offer in the panel; and
 * the one message that says what moved when a page opens.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Element } from './support/mini-dom.mjs';
import { useWindow, openPage, drawBoard, documents, S } from './support/board-page.mjs';

// The editor modules register a few listeners when they load; Node has no DOM.
const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

const { rowFields, schemeChoices } = await import('../../editor/js/modules/board/drawer.js');
const { changesSchemeUse } = await import('../../editor/js/modules/forms/schemes.js');

test('rowFields are exactly the four row settings', () => {
  assert.deepEqual(rowFields(), ['split', 'width', 'scheme', 'space']);
});

test('schemeChoices never carries a label', () => {
  const stored = [
    { id: 'scheme-1', label: 'Studio wall', colors: { bg: '#ecedee', text: '#121417', button: '#121417' } },
    { id: 'scheme-2', label: 'White', colors: { bg: '#ffffff', text: '#121417' } },
    { id: 'scheme-3', colors: { bg: '#101214' } },
  ];
  const choices = schemeChoices(stored);
  assert.deepEqual(choices, [
    { id: 'scheme-1', colors: { bg: '#ecedee', text: '#121417', button: '#121417' } },
    { id: 'scheme-2', colors: { bg: '#ffffff', text: '#121417' } },
    { id: 'scheme-3', colors: { bg: '#101214' } },
  ]);
  choices.forEach((c) => assert.deepEqual(Object.keys(c), ['id', 'colors']));
  // The stored list keeps its labels; only what is offered drops them.
  assert.equal(stored[0].label, 'Studio wall');
  choices[0].colors.bg = '#000000';
  assert.equal(stored[0].colors.bg, '#ecedee');
});

test('schemeChoices takes anything that is not a list as no schemes', () => {
  assert.deepEqual(schemeChoices(null), []);
  assert.deepEqual(schemeChoices([null, { id: 'scheme-1' }]), [{ id: 'scheme-1', colors: {} }]);
});

test('only a change that can move a color scheme use asks for the uses again', () => {
  const isScheme = (op) => op.key === 'color_scheme';
  const yes = [
    { t: 'set', key: 'color_scheme' },
    { t: 'row-set', key: 'scheme' },
    { t: 'row-split' },
    { t: 'row-remove' },
    { t: 'insert' },
    { t: 'remove' },
    { t: 'replace' },
    { t: 'block-add' },
    { t: 'block-remove' },
  ];
  const no = [
    { t: 'set', key: 'heading' },
    { t: 'row-set', key: 'space' },
    { t: 'rename' },
    { t: 'visible' },
    { t: 'move' },
    { t: 'place' },
    { t: 'row-move' },
    { t: 'row-add' },
    { t: 'block-move' },
    { t: 'theme', key: 'font_heading' },
    { t: 'color', scheme: 'scheme-1', role: 'bg' },
  ];
  yes.forEach((op) => assert.equal(changesSchemeUse(op, isScheme), true, JSON.stringify(op)));
  no.forEach((op) => assert.equal(changesSchemeUse(op, isScheme), false, JSON.stringify(op)));
});

/* ------------------------------------------------------------------------
   A section's panel
   ------------------------------------------------------------------------ */

const { ui } = await import('../../editor/js/modules/state.js');
const { initInspector } = await import('../../editor/js/modules/inspector.js');
const { noteOpenedMoves } = await import('../../editor/js/modules/drafts.js');

const flat = (el) => el?.textContent.replace(/\s+/g, ' ').trim() ?? null;

function holder(id) {
  document.querySelector(`#${id}`)?.remove();
  const el = new Element('div');
  el.setAttribute('id', id);
  document.body.append(el);
  return el;
}

/** Opens a section's settings in the panel and answers the panel. */
function panelOf(sid, page = 'product', options = {}) {
  useWindow('computer');
  openPage(page, options);
  const drawer = holder('drawer');
  const body = new Element('div');
  body.setAttribute('id', 'drawer-body');
  drawer.append(body);
  Object.assign(ui, { pageCrumb: () => new Element('span'), sheetClose: () => new Element('span'), renderDrawer: () => {}, syncDrawer: () => {} });
  initInspector();
  S.sel = { kind: 'section', sid };
  ui.renderInspector();
  return body;
}

const buttons = (panel) => panel.querySelectorAll('button').map((b) => b.getAttribute('aria-label') || flat(b));

test('the page content has no Hide, Remove, Duplicate or Change', () => {
  const panel = panelOf('main');
  const list = buttons(panel);
  ['Hide on the store', 'Show on the store', 'Remove section', 'Duplicate'].forEach((name) => assert.ok(!list.includes(name), name));
  assert.ok(!list.some((name) => name.startsWith('Change')), 'No Change.');
  assert.equal(flat(panel.querySelector('.insp-type__where')), 'The page content, fixed in place');
  // Nor on the board.
  const piece = drawBoard().querySelector('.piece[data-sid="main"]');
  assert.equal(piece.closest('.piece-wrap'), null);
});

test('the Header has Hide and no Remove, Duplicate or Change', () => {
  const panel = panelOf('head');
  const list = buttons(panel);
  assert.ok(list.includes('Hide on the store'), 'Show or hide only.');
  ['Remove section', 'Duplicate'].forEach((name) => assert.ok(!list.includes(name), name));
  assert.ok(!list.some((name) => name.startsWith('Change')), 'No Change.');
  assert.equal(drawBoard().querySelector('.piece[data-sid="head"]').closest('.piece-wrap').querySelector('.piece__change'), null);
});

test('a header bar can be hidden and removed, and its panel says changes show on every page', async () => {
  const panel = panelOf('bar');
  const list = buttons(panel);
  assert.ok(list.includes('Hide on the store'));
  assert.ok(list.includes('Remove section'), 'A band section is removed from its panel.');
  const where = panel.querySelector('.insp-type');
  assert.equal(flat(where), 'Header, on every page');
  assert.equal(where.querySelector('svg use').getAttribute('href'), '#i-link', 'The link mark of the bands.');
  assert.equal(flat(panel.querySelector('.notice--shared')), 'Changes here show on every page.');
  // The band has one bar, so a copy has nowhere to go.
  const copy = panel.querySelectorAll('button').find((b) => flat(b) === 'Duplicate');
  assert.ok(copy.hasAttribute('disabled'));
  assert.equal(copy.getAttribute('title'), 'The header already has an announcement bar.');
  // Remove asks first; Delete takes it out of the header, and the band then offers Add section above the Header.
  copy.parentNode.querySelectorAll('button').find((b) => flat(b) === 'Remove section').dispatchEvent({ type: 'click' });
  assert.deepEqual(S.docs.header.content.order, ['bar', 'head'], 'Nothing is removed before the answer.');
  document.querySelector('.dialog__actions .btn--danger').dispatchEvent({ type: 'click' });
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.deepEqual(S.docs.header.content.order, ['head']);
  assert.equal(flat(drawBoard().querySelector('.band--header .bandgap--open .band-add')), 'Add section');
});

test('a page section says where it is and that it is on this page only', () => {
  const panel = panelOf('crumbs');
  assert.equal(flat(panel.querySelector('.insp-type__where')), 'In Top, this page only');
  assert.equal(panel.querySelector('.notice--shared'), null);
  assert.ok(buttons(panel).includes('Remove section'));
});

test('a page opened with adjusted moves shows one toast naming them', () => {
  const toasts = holder('toasts');
  const announcer = holder('announcer');
  const docs = documents();
  docs.home = {
    schema: 1,
    order: ['hero', 'words', 'note'],
    sections: { ...docs.home.sections, note: { type: 'rich-text', label: 'Side <b>note</b>', disabled: false, settings: {}, block_order: [], blocks: {} } },
  };
  openPage('home', { docs });
  S.docs.home.adjusted = [
    { path: 'content.sections.note', reason: 'no-sides', value: 'r-note' },
    { path: 'content.sections.words', reason: 'no-sides', value: 'r-words' },
    { path: 'content.sections.hero.settings.heading', reason: 'too-long', value: '' },
  ];
  noteOpenedMoves('home');
  assert.equal(toasts.children.length, 1, 'One message for the page.');
  const words = flat(toasts.children[0]);
  assert.match(words, /Side <b>note<\/b> and Text/, 'The names are text, never markup.');
  assert.match(words, /This page cannot have sides, so a section in one moved to a row of its own\./);
  assert.equal(toasts.children[0].querySelector('b'), null);
  // Said once: opening the page again later does not repeat it.
  noteOpenedMoves('home');
  assert.equal(toasts.children.length, 1);
  toasts.remove();
  announcer.remove();
});
