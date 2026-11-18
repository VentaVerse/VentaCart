// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The tools on a section's name bar: Change, Hide and Delete. Which
 * sections have which, Hide as one undo step, and the question Delete asks
 * (from the board and from the panel) before anything is removed.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Element } from './support/mini-dom.mjs';
import { useWindow, openPage, drawBoard, documents, S } from './support/board-page.mjs';

const { undo } = await import('../../editor/js/modules/operations.js');
const { openPicker, closePicker } = await import('../../editor/js/modules/board/picker.js');

const text = (el) => el?.textContent.replace(/\s+/g, ' ').trim() ?? null;
const wrapOf = (board, sid) => board.querySelector(`.piece[data-sid="${sid}"]`).closest('.piece-wrap');
const toolsOf = (board, sid) => wrapOf(board, sid).querySelectorAll('.piece__tool').map((b) => b.getAttribute('aria-label'));
const click = (el) => el.dispatchEvent({ type: 'click' });
const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

function messages() {
  ['toasts', 'announcer'].forEach((id) => {
    document.querySelector(`#${id}`)?.remove();
    const el = new Element('div');
    el.setAttribute('id', id);
    document.body.append(el);
  });
  return { toast: () => document.querySelector('#toasts').children.at(-1) };
}

const dialogs = () => document.body.querySelectorAll('dialog');
const orderOf = () => S.docs.product.content.order;

test('each kind of section has its own tools', () => {
  useWindow('computer');
  openPage('product');
  const board = drawBoard();
  assert.deepEqual(toolsOf(board, 'words'), ['Change Text to another section', 'Hide Text', 'Delete Text'], 'A page section: all three.');
  assert.deepEqual(toolsOf(board, 'bar'), ['Hide Announcement bar', 'Delete Announcement bar'], 'A band section: Hide and Delete.');
  assert.deepEqual(toolsOf(board, 'head'), ['Hide Header'], 'The Header only hides.');
  assert.deepEqual(toolsOf(board, 'foot'), ['Hide Footer'], 'The Footer only hides.');
  assert.equal(board.querySelector('.piece[data-sid="main"]').closest('.piece-wrap'), null, 'The page content has none.');
  assert.equal(wrapOf(board, 'words').dataset.tools, '3');
});

test('Hide toggles the section as one undo step', () => {
  useWindow('computer');
  openPage('product');
  let board = drawBoard();
  click(board.querySelector('.piece-wrap[data-sid="words"] .piece__hide'));
  assert.equal(S.docs.product.content.sections.words.disabled, true);
  board = drawBoard();
  const hide = board.querySelector('.piece-wrap[data-sid="words"] .piece__hide');
  assert.equal(hide.getAttribute('aria-label'), 'Show Text');
  assert.equal(hide.getAttribute('title'), 'Show section');
  assert.equal(hide.querySelector('use').getAttribute('href'), '#i-eye-off');
  assert.ok(board.querySelector('.piece[data-sid="words"]').className.includes('is-hidden'));
  click(hide);
  assert.equal(S.docs.product.content.sections.words.disabled, false, 'Show brings it back.');
  click(drawBoard().querySelector('.piece-wrap[data-sid="words"] .piece__hide'));
  undo();
  assert.equal(S.docs.product.content.sections.words.disabled, false, 'One undo restores it.');
});

test('Delete asks first: Cancel and Escape change nothing', async () => {
  useWindow('computer');
  openPage('product');
  messages();
  const board = drawBoard();
  click(board.querySelector('.piece-wrap[data-sid="words"] .piece__delete'));
  const dialog = dialogs().at(-1);
  assert.equal(text(dialog.querySelector('h2')), 'Delete Text?');
  assert.equal(text(dialog.querySelector('.dialog__body')), 'Its settings and content are removed from this page.');
  const [cancel, remove] = dialog.querySelectorAll('.dialog__actions button');
  assert.equal(text(cancel), 'Cancel');
  assert.equal(text(remove), 'Delete');
  assert.ok(remove.className.includes('btn--danger'), 'Delete is in the danger style.');
  assert.equal(document.activeElement, cancel, 'Cancel has the focus.');
  click(cancel);
  await settle();
  assert.ok(orderOf().includes('words'), 'Cancel keeps it.');

  click(board.querySelector('.piece-wrap[data-sid="words"] .piece__delete'));
  dialogs().at(-1).dispatchEvent({ type: 'cancel' });
  await settle();
  assert.ok(orderOf().includes('words'), 'Escape keeps it.');
  assert.equal(dialogs().length, 0, 'The question is gone.');
});

test('Delete removes the section, with Undo in a toast', async () => {
  useWindow('computer');
  openPage('product');
  const said = messages();
  click(drawBoard().querySelector('.piece-wrap[data-sid="words"] .piece__delete'));
  click(dialogs().at(-1).querySelectorAll('.dialog__actions button')[1]);
  await settle();
  assert.ok(!orderOf().includes('words'));
  const toast = said.toast();
  assert.equal(text(toast.querySelector('span')), 'Text removed.');
  click(toast.querySelector('button'));
  assert.ok(orderOf().includes('words'), 'Undo puts it back.');
});

test('a band section is deleted the same way', async () => {
  useWindow('computer');
  openPage('product');
  messages();
  click(drawBoard().querySelector('.piece-wrap[data-sid="bar"] .piece__delete'));
  click(dialogs().at(-1).querySelectorAll('.dialog__actions button')[1]);
  await settle();
  assert.deepEqual(S.docs.header.content.order, ['head']);
});

test('the phone band picker has no way back when the band offers one type', () => {
  useWindow('phone');
  const docs = documents();
  docs.header = { schema: 1, order: ['head'], sections: { head: docs.header.sections.head } };
  openPage('product', { docs });
  const dialog = new Element('dialog');
  dialog.setAttribute('id', 'section-picker');
  dialog.showModal = () => { dialog.open = true; };
  dialog.close = () => { dialog.open = false; };
  document.body.append(dialog);
  openPicker({ target: { drop: 'band', group: 'header', before: 'head' } });
  assert.equal(dialog.querySelector('.spick__back'), null);
  closePicker({ focus: false });
  // A page gap opens on the row layouts, with the tiles to go back to.
  openPicker({ target: { drop: 'gap', area: 'bottom', before: 'end' } });
  assert.ok(dialog.querySelector('.spick__back'));
  closePicker({ focus: false });
  dialog.remove();
});
