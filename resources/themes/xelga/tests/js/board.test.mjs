// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The layout board as drawn: one frame holding the Header band, the page
 * and the Footer band; the bands with their reach, tint and link mark;
 * Top, Content and Bottom named on rules, the sides beside Content at a
 * side's full width; the page content, the Header and the Footer locked;
 * and the Adds of the bands.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Element } from './support/mini-dom.mjs';
import { useWindow, openPage, drawBoard, documents, S } from './support/board-page.mjs';

const { pickerSpot, availableTypes, closePicker } = await import('../../editor/js/modules/board/picker.js');
const { toggleVisible } = await import('../../editor/js/modules/actions.js');

const text = (el) => el?.textContent.replace(/\s+/g, ' ').trim() ?? null;
const listens = (el, type) => (el.listeners ?? []).some((l) => l.type === type);
const pieceOf = (board, sid) => board.querySelector(`.piece[data-sid="${sid}"]`);
const flagsOf = (piece) => piece.querySelectorAll('.piece__flagtext').map(text);

test('the header band has its heading, tint class and link mark', () => {
  useWindow('computer');
  openPage('product');
  const board = drawBoard();
  const frame = board.querySelector('.frame');
  assert.ok(frame, 'One frame holds the page.');
  const groups = frame.querySelector('.page-groups').children;
  assert.deepEqual(groups.map((g) => g.className), ['band band--header', 'sheet', 'band band--footer'], 'The header band, the page and the footer band, apart.');
  const band = groups[0];
  assert.equal(text(band.querySelector('.group-head')), 'Header · on every page');
  assert.equal(band.querySelector('.group-head svg use').getAttribute('href'), '#i-link');
  assert.equal(text(groups[2].querySelector('.group-head')), 'Footer · on every page');
  // The page's heading is only its reach, from the real count of products.
  assert.equal(text(groups[1].querySelector('.group-head')), 'All 24 products');
  // The address pill: the store's own domain, and any product for a shared layout.
  assert.equal(text(frame.querySelector('.frame__address')), 'shop.example.com/products/ any product');
});

test('a product board draws Top, Content and Bottom rules in that order', () => {
  useWindow('computer');
  openPage('product');
  const board = drawBoard();
  assert.deepEqual(board.querySelectorAll('.area-rule').map(text), ['Top', 'Content', 'Bottom']);
  assert.deepEqual(board.querySelectorAll('section.area').map((a) => a.dataset.area), ['top', 'content', 'bottom']);
  // Each area starts and ends with an Add line, named for its area.
  const top = board.querySelector('.area--top');
  const lines = top.querySelectorAll('.gap__add').map((b) => b.getAttribute('aria-label'));
  assert.deepEqual(lines, ['Add a section or a row of columns to Top, above Breadcrumbs', 'Add a section or a row of columns to Top, below Breadcrumbs']);
  // The sides stand beside Content only.
  assert.equal(board.querySelectorAll('.side').length, 2);
  assert.ok(board.querySelectorAll('.area--content .side').length === 2, 'Both sides are in Content.');
  // An empty area is one calm box.
  openPage('category');
  const category = drawBoard();
  const bottom = category.querySelector('.area--bottom');
  assert.equal(bottom.querySelectorAll('.gap').length, 0);
  assert.equal(text(bottom.querySelector('.area-empty')), 'Add section');
  assert.equal(text(category.querySelector('.sheet .group-head')), 'All 6 categories');
});

test('the home board draws no area rule and no side', () => {
  useWindow('computer');
  openPage('home');
  const board = drawBoard();
  assert.equal(board.querySelectorAll('.area-rule').length, 0);
  assert.equal(board.querySelectorAll('.side').length, 0);
  assert.equal(board.querySelectorAll('.piece').map((p) => p.dataset.sid).join(' '), 'bar head hero words foot');
  assert.equal(text(board.querySelector('.sheet .group-head')), 'This page only');
  assert.equal(text(board.querySelector('.frame__address')), 'shop.example.com');
  // A page with no rows yet is one box that adds the first section.
  openPage('home', { content: { schema: 1, order: [], sections: {} } });
  assert.equal(text(drawBoard().querySelector('.sheet .area-empty')), 'Add section');
});

test('an empty right side is a full side box with Add section', () => {
  useWindow('computer');
  openPage('product');
  const board = drawBoard();
  const right = board.querySelector('.side--right');
  assert.equal(text(right.querySelector('.side__tag')), 'Right side');
  const box = right.querySelector('button.side__empty');
  assert.equal(text(box), 'Add section');
  assert.deepEqual(JSON.parse(box.dataset.target), { drop: 'side', side: 'right', before: null });
  // The same box as a side in use: a side, drawn in Content's grid beside the rows.
  const zone = board.querySelector('.area--content .zone');
  assert.deepEqual(zone.children.map((c) => c.className), ['side side--left', 'rows', 'side side--right is-empty']);
});

test('the page content piece has a lock and no pointerdown handler', () => {
  useWindow('computer');
  openPage('product');
  const board = drawBoard();
  const main = pieceOf(board, 'main');
  assert.deepEqual(flagsOf(main), ['Page content']);
  assert.equal(main.querySelector('.piece__flag svg use').getAttribute('href'), '#i-lock');
  assert.equal(listens(main, 'pointerdown'), false, 'Never picked up.');
  assert.equal(listens(main, 'keydown'), false, 'Alt and the arrows never move it.');
  assert.equal(main.closest('.piece-wrap'), null, 'No Change.');
  assert.equal(main.parentNode.querySelectorAll('.beside').length, 0, 'Nothing goes beside it.');
  // Any other section of the page still moves and changes.
  const words = pieceOf(board, 'words');
  assert.equal(listens(words, 'pointerdown'), true);
  assert.ok(words.closest('.piece-wrap').querySelector('.piece__change'));
});

test('the Header has Fixed, Hide and no Change', () => {
  useWindow('computer');
  openPage('product');
  let board = drawBoard();
  const head = pieceOf(board, 'head');
  assert.deepEqual(flagsOf(head), ['Fixed']);
  assert.equal(head.closest('.piece-wrap'), null, 'No Change.');
  assert.equal(listens(head, 'pointerdown'), false);
  assert.equal(listens(head, 'keydown'), false);
  // The bar beside it is an ordinary section of the band: it moves within it.
  assert.equal(listens(pieceOf(board, 'bar'), 'pointerdown'), true);
  // A fixed section can still be hidden; the page content cannot.
  toggleVisible('head');
  board = drawBoard();
  assert.deepEqual(flagsOf(pieceOf(board, 'head')), ['Fixed', 'Hidden']);
  toggleVisible('main');
  assert.equal(S.docs.product.content.sections.main.disabled, false);
});

test('the footer Add is a hover line in pointer mode and a button in touch mode', () => {
  useWindow('computer');
  openPage('product');
  let footer = drawBoard().querySelector('.band--footer');
  assert.equal(footer.querySelectorAll('.band-add').length, 0, 'No standing button on a computer.');
  const line = footer.querySelector('.bandgap--quiet .gap__add');
  assert.equal(line.getAttribute('aria-label'), 'Add a section to the footer, above Footer');
  useWindow('touch');
  footer = drawBoard().querySelector('.band--footer');
  assert.equal(footer.querySelectorAll('.gap__add').length, 0, 'No hover line on a touch screen.');
  assert.equal(text(footer.querySelector('.band-add')), 'Add section');
  // The header holds its one bar: no room, no Add.
  assert.equal(drawBoard().querySelector('.band--header').querySelectorAll('.band-add, .gap__add').length, 0);
  // Without its bar the header shows Add section above the Header, on a computer too.
  useWindow('computer');
  const docs = documents();
  docs.header = { schema: 1, order: ['head'], sections: { head: docs.header.sections.head } };
  openPage('product', { docs });
  assert.equal(text(drawBoard().querySelector('.band--header .bandgap--open .band-add')), 'Add section');
});

test('a band Add opens the picker with a band spot', () => {
  useWindow('touch');
  openPage('product');
  const dialog = new Element('dialog');
  dialog.setAttribute('id', 'section-picker');
  dialog.showModal = () => { dialog.open = true; };
  dialog.close = () => { dialog.open = false; };
  document.body.append(dialog);
  const add = drawBoard().querySelector('.band--footer .band-add');
  add.dispatchEvent({ type: 'click' });
  assert.deepEqual(pickerSpot(), { drop: 'band', group: 'footer', before: 'foot' });
  // Only what may go in the footer is offered.
  assert.deepEqual(Object.keys(availableTypes('footer')), ['announcement-bar']);
  closePicker({ focus: false });
  dialog.remove();
});
