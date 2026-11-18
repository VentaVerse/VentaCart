// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The settings form's text controls: the length limit of a setting is the
 * schema's `max`, and a control without one keeps the general limits.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { useWindow } from './support/board-page.mjs';

const noop = () => {};
globalThis.document = globalThis.document || { addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [] };
globalThis.window = globalThis.window || { addEventListener: noop, removeEventListener: noop, setTimeout, clearTimeout, location: { href: 'http://example.test/' } };

useWindow();
const { buildControl } = await import('../../editor/js/modules/forms/controls.js');

const limit = (setting) => buildControl(setting, '', noop, 'field', {}).getAttribute('maxlength');

test('a text setting is limited to its schema max', () => {
  assert.equal(limit({ type: 'text', max: 40 }), '40');
});

test('a textarea setting is limited to its schema max', () => {
  assert.equal(limit({ type: 'textarea', max: 300 }), '300');
});

test('a setting without a max keeps the general limits', () => {
  assert.equal(limit({ type: 'text' }), '255');
  assert.equal(limit({ type: 'textarea' }), '2000');
});

test('max_length still wins where a setting carries it', () => {
  assert.equal(limit({ type: 'text', max_length: 20, max: 40 }), '20');
});
