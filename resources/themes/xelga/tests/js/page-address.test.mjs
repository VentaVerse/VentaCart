// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The page open in the editor, kept in the tab's address: a record opened
 * from the page menu is written as `record`, and a reload opens it again,
 * or the page's shared layout when the store can no longer show it.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

const { addressQuery, recordAsked, recordTarget } = await import('../../editor/js/modules/page-address.js');

const CATEGORY = { type: 'category', subjectType: 'category', label: 'Category' };

test('a record opened from the menu is kept in the address with its page', () => {
  const laptops = { ...CATEGORY, record: true, subject: { id: 12, name: 'Laptops' } };
  assert.equal(addressQuery('?debug=1', laptops), 'debug=1&page=category&record=12');
  // The shared layout drawn on its sample record carries no record.
  assert.equal(addressQuery('?page=category&record=12', { ...CATEGORY, subject: { id: 3, name: 'Phones' } }), 'page=category');
  assert.equal(addressQuery('?page=product&record=5', { type: 'home' }), '');
  const gift = { ...CATEGORY, layout: true, handle: 'category.gift-guide', record: true, subject: { id: 7, name: 'Gifts' } };
  assert.equal(addressQuery('', gift), 'page=category&layout=category.gift-guide&record=7');
});

test('only a whole number is read back as a record', () => {
  assert.equal(recordAsked('?page=category&record=12'), 12);
  assert.equal(recordAsked('?page=category&record=12abc'), null);
  assert.equal(recordAsked('?page=category&record=0'), null);
  assert.equal(recordAsked('?page=category'), null);
});

test('a reload opens the record again, on the special layout it uses', async () => {
  const lookup = async (type, id) => ({ subject: { type, id, name: 'Laptops', slug: 'laptops' }, url: 'http://example.test/category/laptops' });
  assert.deepEqual(await recordTarget({ page: CATEGORY, id: 12, lookup }), {
    type: 'category', layout: null, subject: { type: 'category', id: 12, name: 'Laptops', slug: 'laptops' }, url: 'http://example.test/category/laptops',
  });
  const templates = [{ handle: 'category.sale', pageType: 'category', assigned: [12] }];
  assert.equal((await recordTarget({ page: CATEGORY, id: 12, lookup, templates })).layout, 'category.sale');
});

test('a record the store cannot show any more leaves the page on its shared layout', async () => {
  const gone = async () => { throw Object.assign(new Error('Shoppers cannot open that page.'), { kind: 'not-found' }); };
  assert.equal(await recordTarget({ page: CATEGORY, id: 12, lookup: gone }), null);
  assert.equal(await recordTarget({ page: { type: 'home' }, id: 12, lookup: gone }), null, 'A page without records ignores it.');
  const signedOut = async () => { throw Object.assign(new Error('Signed out'), { kind: 'unauthenticated' }); };
  await assert.rejects(recordTarget({ page: CATEGORY, id: 12, lookup: signedOut }), /Signed out/);
});
