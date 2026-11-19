// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The link field beside a list of products: "These products" is offered
 * first only where the setting follows the section's products, its line
 * says where it goes for the section's "Products to show", the line
 * changes as soon as that choice does, and a hand-picked list says why the
 * link is hidden.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Element } from './support/mini-dom.mjs';
import { openPage, ctx } from './support/board-page.mjs';

const { linkPicker, followTarget, placeRows, HAND_PICKED_NOTE } = await import('../../editor/js/modules/pickers/link.js');

// The stand-in DOM does not track whether an element is in the page.
if (!('isConnected' in Element.prototype)) {
  Object.defineProperty(Element.prototype, 'isConnected', {
    get() {
      for (let n = this; n; n = n.parentNode) if (n === document.body) return true;
      return false;
    },
  });
}
const FOLLOWING = { key: 'link', type: 'url', label: 'Link goes to', follows_products: true };
const PLAIN = { key: 'button_link', type: 'url', label: 'Button link' };

/** A link field in a form whose other values are `values`; `refresh()` is the form telling it a value changed. */
function field(value, setting, values = {}) {
  const refreshers = [];
  const changes = [];
  const fctx = { kind: 'section', sid: 'products-1', read: (key) => values[key], onRefresh: (fn) => refreshers.push(fn) };
  const el = linkPicker(value, (v) => changes.push(v), 'f-link', setting, fctx);
  document.body.append(el);
  return { el, changes, values, refresh: () => refreshers.forEach((fn) => fn()) };
}

const rowText = (el) => el.querySelectorAll('.pick-row')[0].textContent;

test('these products leads where the section\'s products come from', () => {
  const read = (values) => (key) => values[key];
  assert.equal(followTarget(read({ source: 'newest' })).meta, 'Newest');
  assert.equal(followTarget(read({ source: 'best-sellers' })).meta, 'Best sellers');
  assert.equal(followTarget(read({ source: 'most-viewed' })).meta, 'Most viewed');
  assert.equal(followTarget(read({ source: 'on-sale' })).meta, 'On sale');

  const picked = followTarget(read({ source: 'hand-picked' }));
  assert.equal(picked.shown, false);
  assert.equal(picked.note, HAND_PICKED_NOTE);

  assert.equal(followTarget(read({ source: 'category', category: null })).shown, false, 'no category chosen, no link');
});

test('a following link offers these products first, a plain link does not', () => {
  const read = (key) => ({ source: 'best-sellers' })[key];

  const rows = placeRows('', read);
  assert.equal(rows[0].label, 'These products');
  assert.equal(rows[0].meta, 'Best sellers');
  assert.deepEqual(rows[0].value, { type: 'these-products' });
  assert.equal(rows[1].label, 'Home page');

  assert.equal(placeRows('these', read)[0].label, 'These products', 'found by its name');
  assert.ok(placeRows('cart', read).every((row) => row.label !== 'These products'));
  assert.ok(placeRows('', null).every((row) => row.label !== 'These products'), 'not offered without the setting flag');
});

test('these products, once chosen, follows the products to show', () => {
  openPage('home');
  ctx.api = { pick: async () => ({ items: [] }) };
  const f = field({ type: 'these-products' }, FOLLOWING, { source: 'newest' });

  assert.match(rowText(f.el), /These products/);
  assert.match(rowText(f.el), /Newest/);

  f.values.source = 'on-sale';
  f.refresh();
  assert.match(rowText(f.el), /On sale/);

  f.values.source = 'hand-picked';
  f.refresh();
  assert.match(f.el.textContent, /Picked products have no page of their own, so the link is hidden\. Choose another page to show it\./);

  f.values.source = 'most-viewed';
  f.refresh();
  assert.match(rowText(f.el), /Most viewed/);
  assert.doesNotMatch(f.el.textContent, /Picked products have no page/);

  // Without the flag the field ignores the section.
  const plain = field({ type: 'search' }, PLAIN, { source: 'hand-picked' });
  assert.match(rowText(plain.el), /All products/);
});

test('a category or brand is named, and a switched-off one says the link is hidden', async () => {
  openPage('home');
  const asked = [];
  ctx.api = {
    pick: async (kind, { ids }) => {
      asked.push(...ids);
      return { items: [
        { type: 'category', id: 12, name: 'Laptops', active: true },
        { type: 'brand', id: 7, name: 'Northwind', active: false },
      ].filter((item) => ids.includes(`${item.type}:${item.id}`)) };
    },
  };

  const f = field({ type: 'these-products' }, FOLLOWING, { source: 'category', category: 12 });
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.deepEqual(asked, ['category:12']);
  assert.match(rowText(f.el), /Category: Laptops/);

  f.values.source = 'brand';
  f.values.brand = 7;
  f.refresh();
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.match(rowText(f.el), /Brand: Northwind, switched off, so the link is hidden/);

  f.values.brand = 404;
  f.refresh();
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.match(rowText(f.el), /Brand: no longer in the store, so the link is hidden/);
});

test('another link chosen in a following field is not redrawn by the section', () => {
  openPage('home');
  ctx.api = { pick: async () => ({ items: [] }) };
  const f = field({ type: 'cart' }, FOLLOWING, { source: 'newest' });

  f.values.source = 'hand-picked';
  f.refresh();

  assert.match(rowText(f.el), /Cart/);
  assert.doesNotMatch(f.el.textContent, /Picked products/);
});
