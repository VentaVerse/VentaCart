// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * A special layout's panel: "Choose products" opens the list of records
 * that can use the layout, and picking one assigns it.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Element } from './support/mini-dom.mjs';
import { useWindow, openPage, PAGES, S } from './support/board-page.mjs';

const { ctx } = await import('../../editor/js/modules/state.js');
const { pageSettings } = await import('../../editor/js/modules/layouts.js');

// Nothing is drawn under Node, so every box is a small square at the origin.
Element.prototype.getBoundingClientRect ||= () => ({ top: 0, left: 0, right: 10, bottom: 10, width: 10, height: 10 });

// The little DOM has no form values and knows few selectors; focus moves are skipped.
if (!Object.getOwnPropertyDescriptor(Element.prototype, 'value')) {
  Object.defineProperty(Element.prototype, 'value', { get() { return this.typed ?? ''; }, set(v) { this.typed = v; }, configurable: true });
}
const find = Element.prototype.querySelector;
Element.prototype.querySelector = function safeFind(selector) {
  try { return find.call(this, selector); } catch { return null; }
};

const walk = (el, found = []) => {
  if (el?.children) el.children.forEach((child) => { found.push(child); walk(child, found); });
  return found;
};

test('Choose products opens the list of products to pick from', async () => {
  useWindow('computer');
  openPage('product');
  S.templates = [{ handle: 'gift-guide', pageType: 'product', label: 'Gift guide', subjectType: 'product', assigned: [], count: 0 }];
  S.page = { ...PAGES.product, handle: 'gift-guide', kind: 'custom', layout: true };
  let asked = null;
  ctx.api = { pick: async (kind) => { asked = kind; return { items: [{ id: 7, name: 'Demo gift box' }] }; } };
  const layer = new Element('div');
  layer.setAttribute('id', 'popover-layer');
  document.body.append(layer);

  const panel = pageSettings();
  const choose = panel.flatMap((el) => [el, ...walk(el)]).find((el) => el.getAttribute?.('id') === 'layout-choose');
  assert.ok(choose, 'the panel has a Choose products button');
  choose.dispatchEvent({ type: 'click', currentTarget: choose });
  await new Promise((resolve) => setTimeout(resolve, 80));

  assert.equal(asked, 'products');
  const dialog = walk(layer).find((el) => el.getAttribute?.('role') === 'dialog');
  assert.ok(dialog, 'the list opens');
  assert.equal(dialog.getAttribute('aria-label'), 'Choose products for Gift guide');
  layer.remove?.();
});
