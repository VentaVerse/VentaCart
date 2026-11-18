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

const { imagePicker, setLibraryWait } = await import('../../editor/js/modules/pickers/image.js');

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

/* The library's settings are on the page but its script has not run yet. */
function loadingLibrary() {
  delete window.VentaCart;
  const config = document.createElement('script');
  config.setAttribute('id', 'media-library-config');
  document.body.append(config);
  return config;
}

const readyListeners = () => (document.listeners || []).filter((l) => l.type === 'ventacart:media-ready');

function fireReady(detail) {
  readyListeners().forEach((l) => l.fn({ type: 'ventacart:media-ready', detail }));
}

test('while the library is still loading, Change waits for it and opens once', () => {
  openPage('home');
  const config = loadingLibrary();
  const { button } = field('media/banners/demo-001.jpg');
  const opener = button();

  click(opener);
  click(opener);
  click(opener);

  assert.equal(readyListeners().length, 1, 'one pending open, however often Change is pressed');
  assert.equal(opener.getAttribute('aria-busy'), 'true');

  const opened = [];
  window.VentaCart = { media: { open: (options) => { opened.push(options); return { close() {} }; } } };
  fireReady(window.VentaCart.media);

  assert.equal(opened.length, 1);
  assert.equal(opened[0].value, 'media/banners/demo-001.jpg');
  assert.equal(opener.hasAttribute('aria-busy'), false);
  assert.equal(readyListeners().length, 0);
  config.remove();
});

test('a library that never loads shows the note after the wait', async () => {
  openPage('home');
  const config = loadingLibrary();
  setLibraryWait(10);
  const { el, changes, button } = field('media/banners/demo-001.jpg');

  click(button());
  assert.doesNotMatch(el.html(), /not available/);
  await new Promise((resolve) => setTimeout(resolve, 40));

  assert.match(el.html(), /The media library is not available on this page\./);
  assert.equal(readyListeners().length, 0);
  assert.deepEqual(changes, []);
  setLibraryWait(8000);
  config.remove();
});
