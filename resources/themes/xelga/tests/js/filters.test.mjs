// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The Filters section below 990px: the listing's Filter button opens it
 * as a drawer. Focus moves in and stays in, Escape closes it, focus goes
 * back to the button. From 990px nothing happens.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './support/mini-dom.mjs';

installDom();

let wideNow = false;
const mediaListeners = [];
globalThis.window = {
  matchMedia: () => ({
    get matches() { return wideNow; },
    addEventListener: (_, fn) => mediaListeners.push(fn),
    removeEventListener: () => {},
  }),
  setTimeout,
  clearTimeout,
};

const { initFiltersDrawer } = await import('../../js/sections/filters.js');

function page() {
  const { body } = document;
  body.childNodes = [];
  body.innerHTML = `
    <button data-filter-open aria-controls="filters-1" aria-expanded="false">Filter</button>
    <section id="filters-1">
      <div><h2 id="filters-1-title" tabindex="-1" data-filters-title>Filter</h2><button data-filters-close>Close</button></div>
      <form data-filters><input type="checkbox" name="brand[]" value="1"><button type="submit" data-filters-submit>Show products</button></form>
    </section>`;
  body.children.forEach((el) => { el.parentNode = body; });
  const section = body.querySelector('[id="filters-1"]');
  const button = body.querySelector('[data-filter-open]');
  return { section, button, heading: section.querySelector('[data-filters-title]'), close: section.querySelector('[data-filters-close]'), submit: section.querySelector('[data-filters-submit]'), box: section.querySelector('[type="checkbox"]') };
}

const key = (name, extra = {}) => document.listeners.filter((l) => l.type === 'keydown').forEach((l) => l.fn({ key: name, shiftKey: false, preventDefault() { this.stopped = true; }, ...extra }));

test('opening moves focus into the drawer', () => {
  wideNow = false;
  const { section, button, heading } = page();
  const undo = initFiltersDrawer(section, button);

  button.dispatchEvent({ type: 'click' });

  assert.equal(button.getAttribute('aria-expanded'), 'true');
  assert.equal(section.getAttribute('data-open'), 'true');
  assert.equal(section.getAttribute('role'), 'dialog');
  assert.equal(document.activeElement, heading);
  undo();
});

test('Tab stays inside the open drawer', () => {
  wideNow = false;
  const { section, button, close, submit } = page();
  const undo = initFiltersDrawer(section, button);
  button.dispatchEvent({ type: 'click' });

  submit.focus();
  key('Tab');
  assert.equal(document.activeElement, close, 'forward from the last stop wraps to the first');

  key('Tab', { shiftKey: true });
  assert.equal(document.activeElement, submit, 'back from the first stop wraps to the last');
  undo();
});

test('Esc closes and returns focus to the button', () => {
  wideNow = false;
  const { section, button } = page();
  const undo = initFiltersDrawer(section, button);
  button.dispatchEvent({ type: 'click' });

  key('Escape');

  assert.equal(button.getAttribute('aria-expanded'), 'false');
  assert.equal(section.getAttribute('data-open'), null);
  assert.equal(section.getAttribute('role'), null);
  assert.equal(document.activeElement, button);
  undo();
});

test('the close button closes and returns focus to the button', () => {
  wideNow = false;
  const { section, button, close } = page();
  const undo = initFiltersDrawer(section, button);
  button.dispatchEvent({ type: 'click' });

  close.dispatchEvent({ type: 'click' });

  assert.equal(section.getAttribute('data-open'), null);
  assert.equal(document.activeElement, button);
  undo();
});

test('nothing happens from 990px', () => {
  wideNow = true;
  const { section, button } = page();
  const undo = initFiltersDrawer(section, button);

  button.dispatchEvent({ type: 'click' });

  assert.equal(button.getAttribute('aria-expanded'), 'false');
  assert.equal(section.getAttribute('data-open'), null);
  assert.notEqual(document.activeElement, section.querySelector('[data-filters-title]'));
  undo();
});
