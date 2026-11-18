// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The address at the top of the board's frame: the store's own domain and
 * the page's path, and for a layout shared by many records the path up to
 * where the record's part starts, then the words for any record.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

const { storeAddressFor, ANY_RECORD } = await import('../../editor/js/modules/store-address.js');

test('a record page keeps its address', () => {
  assert.equal(storeAddressFor('https://shop.example.com/products/demo-001', { shared: false, pageType: 'product' }), 'shop.example.com/products/demo-001');
  // The query (a preview's own key, say) is not part of the address people read.
  assert.equal(storeAddressFor('https://shop.example.com/search?q=demo', { shared: false, pageType: 'search' }), 'shop.example.com/search');
});

test('a shared product layout ends with any product', () => {
  assert.equal(storeAddressFor('https://shop.example.com/products/demo-001', { shared: true, pageType: 'product' }), 'shop.example.com/products/ any product');
  assert.equal(storeAddressFor('https://shop.example.com/laptops', { shared: true, pageType: 'category' }), 'shop.example.com/ any category');
});

test('brand and blog-post layouts end with any brand and any post', () => {
  assert.equal(storeAddressFor('https://shop.example.com/brands/demo-brand', { shared: true, pageType: 'brand' }), 'shop.example.com/brands/ any brand');
  assert.equal(storeAddressFor('https://shop.example.com/blog/first-post', { shared: true, pageType: 'blog-post' }), 'shop.example.com/blog/ any post');
  assert.deepEqual(Object.keys(ANY_RECORD), ['product', 'category', 'brand', 'page', 'blog-post']);
});

test('the home page is the host alone', () => {
  assert.equal(storeAddressFor('https://shop.example.com/', { shared: false, pageType: 'home' }), 'shop.example.com');
  // A page type without records is never "any": it has one address.
  assert.equal(storeAddressFor('https://shop.example.com/', { shared: true, pageType: 'home' }), 'shop.example.com');
  assert.equal(storeAddressFor('http://192.0.2.10:8012/contact', { shared: false, pageType: 'contact' }), '192.0.2.10:8012/contact');
  assert.equal(storeAddressFor('', { shared: false }), '');
});
