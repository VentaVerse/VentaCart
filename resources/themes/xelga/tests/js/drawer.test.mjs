// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The settings drawer beside the board: the settings a row of columns has,
 * and the color schemes it offers, which carry their colors and never a
 * name.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

// The editor modules register a few listeners when they load; Node has no DOM.
const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

const { rowFields, schemeChoices } = await import('../../editor/js/modules/board/drawer.js');

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
