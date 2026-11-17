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
