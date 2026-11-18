// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * A press on a phone: a finger that moves before the press is held long
 * enough scrolls the board, a press held still long enough picks the
 * section up, and a short still press is a tap.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

// The editor modules register a few listeners when they load; Node has no DOM.
const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

const { createPress, PRESS_DELAY } = await import('../../editor/js/modules/board/phone.js');

test('a press is held for 350 ms before it drags', () => {
  assert.equal(PRESS_DELAY, 350);
});

test('a press under 350 ms with movement scrolls', () => {
  const press = createPress({ delay: PRESS_DELAY });
  press.down(1000);
  assert.equal(press.move(1100, 0, 3), null);
  assert.equal(press.move(1200, 2, 24), 'scroll');
  // Once the board scrolls, holding on never turns it into a drag.
  assert.equal(press.move(1500, 0, 0), 'scroll');
  assert.equal(press.up(1600), 'scroll');
});

test('a press held 350 ms starts a drag', () => {
  const press = createPress({ delay: PRESS_DELAY });
  press.down(1000);
  assert.equal(press.move(1349, 0, 0), null);
  assert.equal(press.move(1350, 0, 0), 'drag');
  // Moving after the pick-up carries the section along.
  assert.equal(press.move(1500, 0, 160), 'drag');
  assert.equal(press.up(1700), 'drag');
});

test('a short still press is a tap', () => {
  const press = createPress({ delay: PRESS_DELAY });
  press.down(1000);
  assert.equal(press.move(1100, 1, 1), null);
  assert.equal(press.up(1200), 'tap');
});

test('a still press let go after the delay without a move is a drag that goes nowhere, not a tap', () => {
  const press = createPress({ delay: PRESS_DELAY });
  press.down(1000);
  assert.equal(press.up(1400), 'drag');
});

/*
 * The board drawn as a list on a phone: the same frame and groups, with
 * the page in the order a phone shows the store in.
 */

const { useWindow, openPage, drawBoard } = await import('./support/board-page.mjs');

const words = (el) => el?.textContent.replace(/\s+/g, ' ').trim() ?? null;

test('the phone board lists Top, the left side, Content, the right side, Bottom', () => {
  useWindow('phone');
  openPage('product');
  const board = drawBoard();
  assert.equal(board.dataset.view, 'list');
  assert.ok(board.querySelector('.frame .page-groups'), 'The same frame and groups as on a computer.');
  // In page order: each area's rows, and each side, named by what it holds.
  const marks = board.querySelectorAll('.area-rule, .side, .piece')
    .filter((el) => !el.closest('.band'))
    .map((el) => (el.matches('.area-rule') ? words(el) : el.matches('.side') ? el.dataset.side : el.dataset.sid));
  assert.deepEqual(marks, ['Top', 'crumbs', 'Content', 'left', 'note', 'main', 'right', 'Bottom', 'words']);
});

test('filters on the phone board says In the Filter button', () => {
  useWindow('phone');
  openPage('category');
  const flags = (board) => board.querySelector('.piece[data-sid="filters"]').querySelectorAll('.piece__flagtext').map(words);
  assert.deepEqual(flags(drawBoard()), ['In the Filter button']);
  // On a computer Filters stands in the side as the store shows it there.
  useWindow('computer');
  assert.deepEqual(flags(drawBoard()), []);
});
