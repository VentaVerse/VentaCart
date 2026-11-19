// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * Adding several blocks from photos chosen in the media library: one block
 * per photo in the order picked, as one undo step, never past the section's
 * limit, and the button only for a section whose only addable block names
 * the image setting a photo fills (`photo_setting`).
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { openPage, S } from './support/board-page.mjs';

const { setCatalog } = await import('../../editor/js/modules/catalog.js');
const { undo, redo } = await import('../../editor/js/modules/operations.js');
const { addBlocksWithImages, addPhotos, photoBlockOf, addableBlocks } = await import('../../editor/js/modules/actions.js');

const image = { key: 'image', type: 'image', label: 'Photo', default: '' };
const text = { key: 'caption', type: 'text', label: 'Caption', default: '' };

const CATALOG = {
  slides: {
    label: 'Slides', group: 'template', templates: ['*'], span: { min: 12, variants: {} }, settings: [],
    blocks: { min: 0, max: 5, types: [{ type: 'slide', label: 'Slide', limit: null, photo_setting: 'image', settings: [image, text] }] },
  },
  twin: {
    label: 'Two photos', group: 'template', templates: ['*'], span: { min: 12, variants: {} }, settings: [],
    blocks: { min: 0, max: 5, types: [{ type: 'slide', label: 'Slide', limit: null, photo_setting: 'image', settings: [image, { ...image, key: 'image_mobile' }] }]
    },
  },
  unnamed: {
    label: 'Unnamed', group: 'template', templates: ['*'], span: { min: 12, variants: {} }, settings: [],
    blocks: { min: 0, max: 5, types: [{ type: 'slide', label: 'Slide', limit: null, settings: [image] }] },
  },
  mixed: {
    label: 'Mixed', group: 'template', templates: ['*'], span: { min: 12, variants: {} }, settings: [],
    blocks: { min: 0, max: 5, types: [
      { type: 'slide', label: 'Slide', limit: null, photo_setting: 'image', settings: [image] },
      { type: 'note', label: 'Note', limit: null, settings: [text] },
    ] },
  },
  plain: {
    label: 'Notes', group: 'template', templates: ['*'], span: { min: 12, variants: {} }, settings: [],
    blocks: { min: 0, max: null, types: [{ type: 'note', label: 'Note', limit: null, settings: [text] }] },
  },
};

function page(type = 'slides', blocks = {}) {
  openPage('home');
  setCatalog({ sections: CATALOG });
  S.docs.home.content.sections.hero = { type, label: 'Gallery', disabled: false, settings: {}, block_order: Object.keys(blocks), blocks };
  S.history = [];
  S.pointer = 0;
}

const slide = (path) => ({ type: 'slide', label: 'Slide', disabled: false, settings: path ? { image: path } : {} });
const order = () => S.docs.home.content.sections.hero.block_order;
const blocks = () => S.docs.home.content.sections.hero.blocks;
const paths = () => order().map((id) => blocks()[id].settings.image);
const PICKED = ['media/banners/demo-001.jpg', 'media/banners/demo-002.jpg', 'media/banners/demo-003.jpg'];

test('three photos become three slides in the order picked', () => {
  page();
  const ids = addBlocksWithImages('hero', 'slide', 'image', PICKED);

  assert.equal(ids.length, 3);
  assert.deepEqual(order(), ids);
  assert.deepEqual(paths(), PICKED);
  assert.deepEqual(order().map((id) => blocks()[id].label), ['Slide 1', 'Slide 2', 'Slide 3']);
  assert.equal(new Set(ids).size, 3, 'every block has its own id');
});

test('new slides go after the ones already there and are numbered on from them', () => {
  page('slides', { 'slide-aaaa': slide('media/banners/demo-009.jpg') });
  addBlocksWithImages('hero', 'slide', 'image', PICKED.slice(0, 2));

  assert.deepEqual(paths(), ['media/banners/demo-009.jpg', PICKED[0], PICKED[1]]);
  assert.deepEqual(order().map((id) => blocks()[id].label).slice(1), ['Slide 2', 'Slide 3']);
});

test('one undo removes them all', () => {
  page('slides', { 'slide-aaaa': slide('media/banners/demo-009.jpg') });
  addBlocksWithImages('hero', 'slide', 'image', PICKED);
  assert.equal(order().length, 4);
  assert.equal(S.history.length, 1, 'one step in the history');

  undo();
  assert.deepEqual(order(), ['slide-aaaa']);
  assert.deepEqual(Object.keys(blocks()), ['slide-aaaa']);

  redo();
  assert.deepEqual(paths().slice(1), PICKED);
});

test('the limit counts the slides already there', () => {
  page('slides', { a: slide('media/banners/demo-009.jpg'), b: slide('media/banners/demo-008.jpg'), c: slide() });
  const ids = addBlocksWithImages('hero', 'slide', 'image', [...PICKED, 'media/banners/demo-004.jpg']);

  assert.equal(ids.length, 2, 'five at most, three already there');
  assert.deepEqual(paths().slice(3), PICKED.slice(0, 2));
  assert.deepEqual(addBlocksWithImages('hero', 'slide', 'image', PICKED), [], 'a full section takes none');
  assert.equal(S.history.length, 1, 'nothing is recorded for the refused call');
  assert.equal(photoBlockOf('hero'), null, 'and no button when full');
});

test('the button shows only for a block type that names its photo setting', () => {
  page('slides', { a: slide('media/banners/demo-009.jpg'), b: slide('') });
  assert.deepEqual(photoBlockOf('hero'), { type: 'slide', key: 'image', room: 3, inUse: ['media/banners/demo-009.jpg'] });

  page('slides');
  assert.equal(photoBlockOf('hero').room, 5);

  page('twin');
  assert.deepEqual(photoBlockOf('hero'), { type: 'slide', key: 'image', room: 5, inUse: [] }, 'two image settings: the named one is filled');
  page('twin', { a: { type: 'slide', label: 'Slide', disabled: false, settings: { image: 'media/banners/demo-001.jpg' } } });
  addBlocksWithImages('hero', 'slide', 'image', ['media/banners/demo-002.jpg']);
  assert.deepEqual(blocks()[order()[1]].settings, { image: 'media/banners/demo-002.jpg' }, 'the other image setting stays empty');
  page('unnamed');
  assert.equal(photoBlockOf('hero'), null, 'one image setting that is not named');
  page('mixed');
  assert.equal(addableBlocks('hero').types.length, 2);
  assert.equal(photoBlockOf('hero'), null, 'more than one addable type');
  page('plain');
  assert.equal(photoBlockOf('hero'), null, 'no image setting');
});

test('a section with no block limit has no room count', () => {
  CATALOG.slides.blocks.max = null;
  page();
  assert.equal(photoBlockOf('hero').room, null);
  CATALOG.slides.blocks.max = 5;
});

test('Add photos opens the library for several images and adds what is chosen', () => {
  page('slides', { a: slide('media/banners/demo-009.jpg') });
  const opened = [];
  window.VentaCart = { media: { open: (options) => { opened.push(options); return { close() {} }; } } };
  const opener = document.createElement('button');
  document.body.append(opener);

  addPhotos('hero', opener);
  addPhotos('hero', opener);

  assert.equal(opened.length, 1);
  const { mode, limit, inUse, kind, title } = opened[0];
  assert.deepEqual({ mode, limit, inUse, kind, title }, {
    mode: 'multi', limit: 4, inUse: ['media/banners/demo-009.jpg'], kind: 'theme', title: 'Add photos',
  });

  opened[0].onChoose(PICKED.map((path) => ({ path })));
  assert.deepEqual(paths().slice(1), PICKED);
  assert.equal(S.history.length, 1);
  delete window.VentaCart;
});
