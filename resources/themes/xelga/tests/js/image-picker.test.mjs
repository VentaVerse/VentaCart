// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The image field of the settings form: Change and Choose image open the
 * store's shared media library with the field's image, kind and place, the
 * image chosen there is what the setting stores, and a page without the
 * library says so instead of failing.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { openPage } from './support/board-page.mjs';

const { imagePicker } = await import('../../editor/js/modules/pickers/image.js');

const HERO_PHOTO = { key: 'image', type: 'image', label: 'Photo', default: '' };
const HERO = { kind: 'section', sid: 'hero' };

/** A stand-in for the library's hook that records how it was opened. */
function fakeLibrary() {
  const opened = [];
  window.VentaCart = { media: { open: (options) => { opened.push(options); return { close() {} }; } } };
  return opened;
}

function field(value, setting = HERO_PHOTO) {
  const changes = [];
  const el = imagePicker(value, (v) => changes.push(v), setting, HERO);
  document.body.append(el);
  return { el, changes, button: () => el.querySelectorAll('button')[0] };
}

const click = (button) => button.dispatchEvent({ type: 'click' });

test('choosing opens the core library with the field\'s value and kind', () => {
  openPage('home');
  const opened = fakeLibrary();
  const { button } = field('media/banners/demo-001.jpg', { ...HERO_PHOTO, media_kind: 'banner' });

  click(button());

  assert.equal(opened.length, 1);
  const options = opened[0];
  assert.equal(options.mode, 'single');
  assert.equal(options.value, 'media/banners/demo-001.jpg');
  assert.equal(options.kind, 'banner');
  assert.equal(options.folder, 'media/banners');
  assert.deepEqual(options.where, ['Main banner', 'Photo']);
  assert.equal(options.opener, button());
  assert.equal(typeof options.onChoose, 'function');

  // A field without its own kind shares the theme's memory of the last folder.
  const plain = fakeLibrary();
  click(field('').button());
  assert.equal(plain[0].kind, 'theme');
});

test('the chosen image\'s path is stored', () => {
  openPage('home');
  const opened = fakeLibrary();
  const { el, changes, button } = field('');

  click(button());
  opened[0].onChoose([{ path: 'media/banners/demo-002.jpg', url: '/storage/media/banners/demo-002.jpg', name: 'demo-002.jpg' }]);

  assert.deepEqual(changes, ['media/banners/demo-002.jpg']);
  assert.match(el.html(), /demo-002\.jpg/);
  assert.equal(button().textContent, 'Change');
});

test('a missing library shows a note instead of failing', () => {
  openPage('home');
  delete window.VentaCart;
  const { el, changes, button } = field('media/banners/demo-001.jpg');

  assert.doesNotThrow(() => click(button()));

  assert.match(el.html(), /The media library is not available on this page\./);
  assert.deepEqual(changes, []);
});
