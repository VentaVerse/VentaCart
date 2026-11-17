/*
 * The top bar and the controls around the panels: undo and redo, the save
 * status, Save draft, Publish (with its confirmation and refusal), the More
 * menu (preview, earlier versions, throw away draft), the page picker, the
 * presence line, the panel tabs, the phone dock and keyboard shortcuts.
 */

import { $, $$, h, icon, toast, announce } from './dom.js';
import {
  S, ui, anyDirty, anySaving, publishable, docLabel, isDirty, sec, blk, labelOf,
} from './state.js';
import { themeGroups, ownThemeGroups } from './catalog.js';
import { undo, redo, canUndo, canRedo, describe } from './operations.js';
import { flush, publish, discard, retryNow } from './drafts.js';
import { openPopover, openMenu, confirmBox, confirmFrom, closePopover, isPopoverOpen } from './popover.js';
import { setNav, openSheet, closeSheet, deselect, isNarrow, openThemeGroup, radioKeys, showPanel } from './selection.js';
import { reloadPreview } from './preview.js';
import { openRevisions } from './revisions.js';
import { pageMenu } from './layouts.js';
import { duplicateSection, duplicateBlock, canDuplicate, canDuplicateBlock } from './actions.js';
import { schemeGroupKey } from './forms/schemes.js';
import { formatTime, plural } from './util.js';

/* ------------------------------------------------------------------------
   Status
   ------------------------------------------------------------------------ */

function setStatus(tone, text, title = text) {
  const el = $('#status');
  el.dataset.tone = tone;
  $('.status__text', el).textContent = text;
  el.title = title;
}

function status() {
  if (S.fatal) return ['error', S.fatal.kind === 'unauthenticated' ? 'Signed out: not saving' : 'No access: not saving'];
  if (!S.loaded) return ['', 'Loading store'];
  const docs = Object.values(S.docs);
  const conflict = docs.find((d) => d.conflict);
  if (conflict) return ['error', 'Not saved: someone else saved', `${docLabel(conflict.handle)} was changed by someone else. Choose whose version to keep.`];
  if (anySaving()) return ['', 'Saving'];
  const failed = docs.find((d) => d.error);
  if (failed) {
    if (!S.online) return ['error', 'Offline: changes kept here', 'You are offline. Your changes stay in this tab and save when the connection is back.'];
    if (failed.error.kind === 'validation') return ['error', 'Not saved: check the marked settings', failed.error.message];
    if (['network', 'server', 'too-many'].includes(failed.error.kind)) return ['error', 'Not saved: retrying', failed.error.message];
    return ['error', 'Not saved', failed.error.message];
  }
  if (anyDirty()) return ['dirty', 'Unsaved changes'];
  const drafts = docs.filter((d) => d.hasDraft);
  if (drafts.length) {
    const last = drafts.map((d) => d.lastSavedAt).filter(Boolean).sort().pop();
    return ['saved', last ? `Draft saved ${formatTime(last)}` : 'Draft saved'];
  }
  if (S.publishedFlash) return ['live', `Published ${formatTime(S.publishedFlash)}`];
  const last = docs.map((d) => d.publishedAt).filter(Boolean).sort().pop();
  if (last) return ['live', `Published ${formatTime(last)}`, 'What you see is what shoppers see.'];
  return ['', 'No changes yet', 'What you see is what shoppers see.'];
}

export function updateToolbar() {
  $('#undo-btn').disabled = !canUndo() || Boolean(S.fatal);
  $('#redo-btn').disabled = !canRedo() || Boolean(S.fatal);
  const [tone, text, title] = status();
  setStatus(tone, text, title);

  const save = $('#save-btn');
  if (!save.classList.contains('is-done') && !save.classList.contains('is-busy')) {
    save.disabled = !S.loaded || Boolean(S.fatal) || !(anyDirty() || Object.values(S.docs).some((d) => d.error && d.error.kind !== 'validation'));
  }
  const pub = $('#publish-btn');
  if (!pub.classList.contains('is-done') && !pub.classList.contains('is-busy')) {
    pub.disabled = !S.loaded || Boolean(S.fatal) || !publishable().length || Object.values(S.docs).some((d) => d.conflict);
  }

  const presence = $('#presence');
  const others = S.presence;
  presence.hidden = !others.length;
  if (others.length) {
    const names = others.map((p) => p.name);
    const who = names.length === 1
      ? `${names[0]} is also editing`
      : `${names[0]} and ${plural(names.length - 1, 'other')} are also editing`;
    // Where the top bar is crowded the notice shows as its icon only, so
    // the save status keeps its room; the tip and the hidden text say who.
    $('.presence__text', presence).textContent = who;
    presence.dataset.tip = `${who}. You share one draft.`;
  }
}

/* ------------------------------------------------------------------------
   Save draft and Publish
   ------------------------------------------------------------------------ */

function flashButton(btn, text, ms) {
  btn.classList.add('is-done');
  btn.disabled = false;
  btn.replaceChildren(icon('check', 'icon--sm'), h('span', { class: 'btn__label', text }));
  window.setTimeout(() => {
    btn.classList.remove('is-done');
    btn.replaceChildren(h('span', { class: 'btn__label', text: btn.id === 'save-btn' ? 'Save draft' : 'Publish' }));
    updateToolbar();
  }, ms);
}

async function onSave() {
  const btn = $('#save-btn');
  const label = $('.btn__label', btn);
  btn.classList.add('is-busy');
  label.textContent = 'Saving...';
  retryNow();
  const ok = await flush();
  btn.classList.remove('is-busy');
  if (!ok) {
    label.textContent = 'Save draft';
    updateToolbar();
    return;
  }
  announce('Draft saved');
  flashButton(btn, 'Saved', 1800);
}

function changeSummary() {
  const list = publishable().map((d) => docLabel(d.handle));
  if (!list.length) return 'nothing';
  if (list.length === 1) return list[0];
  return `${list.slice(0, -1).join(', ')} and ${list[list.length - 1]}`;
}

function refusalBox(refusal) {
  const groupKey = schemeGroupKey();
  const parts = [];
  if (refusal.schemes.length) {
    parts.push(h('p', { text: 'Some colors are too hard to read, so shoppers would struggle with them. Fix these, then publish again:' }));
    parts.push(h('ul', { class: 'refusal__list', role: 'list' }, ...refusal.schemes.map((s) => h('li', null,
      h('strong', { text: s.label }),
      h('span', { text: s.pairs.map((p) => `${p.label}: ${Number(p.ratio).toFixed(1)} to 1, needs ${p.min} to 1`).join('. ') }),
      groupKey ? h('button', {
        type: 'button', class: 'link-btn',
        onclick: () => {
          closePopover();
          openThemeGroup(groupKey, { scheme: s.id });
        },
      }, `Fix ${s.label}`) : null))));
  }
  refusal.other.filter(Boolean).forEach((m) => parts.push(h('p', { text: m })));
  if (!parts.length) parts.push(h('p', { text: refusal.message || 'The server did not accept the draft.' }));
  return h('div', { class: 'confirm refusal', role: 'alertdialog', 'aria-label': 'Publish stopped' },
    h('h3', null, icon('alert'), 'Nothing was published'),
    ...parts,
    h('div', { class: 'confirm__actions' },
      h('button', { type: 'button', class: 'btn btn--secondary btn--sm', onclick: () => closePopover() }, 'Close')));
}

async function runPublish() {
  const btn = $('#publish-btn');
  const label = $('.btn__label', btn);
  btn.classList.add('is-busy');
  btn.disabled = true;
  label.textContent = 'Publishing...';
  const res = await publish();
  btn.classList.remove('is-busy');
  if (res.ok) {
    toast('Published. Shoppers now see these changes.', { tone: 'success' });
    announce('Published');
    flashButton(btn, 'Published', 2200);
    return;
  }
  label.textContent = 'Publish';
  updateToolbar();
  if (res.refusal) {
    openPopover(btn, refusalBox(res.refusal), { align: 'end', label: 'Publish stopped' });
    return;
  }
  if (res.error?.message) toast(res.error.message, { tone: 'error', timeout: 8000 });
}

/* ------------------------------------------------------------------------
   Menus
   ------------------------------------------------------------------------ */

function moreMenu(anchor) {
  const anyDraft = Object.values(S.docs).some((d) => d.hasDraft || isDirty(d));
  openMenu(anchor, [
    { label: 'Preview store in a new tab', iconName: 'external', hint: 'Shows what shoppers see now', run: () => window.open($('#preview-link').href, '_blank', 'noopener') },
    { label: 'Reload preview', iconName: 'rotate-ccw', run: () => reloadPreview() },
    { label: 'Earlier versions', iconName: 'clock', hint: 'Go back to something you published', run: () => openRevisions() },
    { sep: true },
    {
      label: 'Throw away draft', iconName: 'trash', danger: true, disabled: !anyDraft,
      hint: 'Go back to what is published now',
      run: () => confirmFrom(anchor, {
        title: 'Throw away the draft?',
        text: `Every change not yet published is thrown away, for everyone editing this store: ${changeSummary()}. The editor goes back to the published store. This cannot be undone.`,
        confirmLabel: 'Throw away draft',
        danger: true,
        onConfirm: discard,
      }),
    },
  ], { align: 'end' });
}

/* ------------------------------------------------------------------------
   Dock, tabs and keyboard
   ------------------------------------------------------------------------ */

export function updateDock() {
  const btn = $('#dock-selected');
  const label = $('#dock-selected-label');
  if (S.sel?.kind === 'section') {
    const exists = S.sel.bid ? blk(S.sel.sid, S.sel.bid) : sec(S.sel.sid);
    label.textContent = exists ? `Edit ${labelOf(S.sel.sid, S.sel.bid)}` : 'Tap the store to edit';
    btn.disabled = !exists;
  } else if (S.sel?.kind === 'theme') {
    label.textContent = themeGroups().find((g) => g.group === S.sel.group)?.label || 'Theme settings';
    btn.disabled = false;
  } else if (S.sel?.kind === 'layout') {
    label.textContent = 'Layout';
    btn.disabled = false;
  } else if (S.sel?.kind === 'stores') {
    label.textContent = 'Start from a store';
    btn.disabled = false;
  } else {
    label.textContent = 'Tap the store to edit';
    btn.disabled = true;
  }
}

function doUndo() {
  const op = undo();
  if (op) announce(`Undid: ${describe(op)}`);
}

function doRedo() {
  const op = redo();
  if (op) announce(`Redid: ${describe(op)}`);
}

/** Undo and redo everywhere except inside text fields; Escape closes; Ctrl+S saves. */
export function onKeydown(e) {
  const mod = e.ctrlKey || e.metaKey;
  const t = e.target;
  const typing = t && (t.isContentEditable || (t.matches && t.matches('input:not([type="range"]):not([type="color"]):not([type="checkbox"]), textarea, select')));
  const key = (e.key || '').toLowerCase();
  if (mod && !typing && key === 'z') {
    e.preventDefault();
    if (e.shiftKey) doRedo();
    else doUndo();
  } else if (mod && !typing && key === 'y') {
    e.preventDefault();
    doRedo();
  } else if (mod && !typing && !e.shiftKey && key === 'd' && S.sel?.kind === 'section') {
    // Copies what is selected: a block, or a section of the page.
    e.preventDefault();
    if (S.sel.bid) {
      if (canDuplicateBlock(S.sel.sid, S.sel.bid)) duplicateBlock(S.sel.sid, S.sel.bid);
      else toast('This section holds as many of these as it can.', { tone: 'error' });
    } else if (canDuplicate(S.sel.sid)) {
      duplicateSection(S.sel.sid);
    } else {
      toast('This section cannot be copied.', { tone: 'error' });
    }
  } else if (mod && key === 's') {
    e.preventDefault();
    if (!$('#save-btn').disabled) onSave();
  } else if (e.key === 'Escape') {
    if (document.querySelector('dialog[open]')) return;
    if (isPopoverOpen()) closePopover();
    else if (ui.cancelStoreChoice?.()) e.preventDefault();
    else if (S.sheetOpen) closeSheet();
  }
}

export function initTopbar() {
  $('#undo-btn').addEventListener('click', doUndo);
  $('#redo-btn').addEventListener('click', doRedo);
  $('#save-btn').addEventListener('click', onSave);
  $('#publish-btn').addEventListener('click', (e) => {
    openPopover(e.currentTarget, confirmBox({
      title: 'Publish your changes?',
      text: `Shoppers will see the changes to ${changeSummary()} right away. Your draft is saved first.`,
      confirmLabel: 'Publish now',
      onConfirm: runPublish,
    }), { align: 'end', label: 'Publish' });
  });
  $('#more-btn').addEventListener('click', (e) => moreMenu(e.currentTarget));
  $('#page-btn').addEventListener('click', (e) => pageMenu(e.currentTarget));
  radioKeys($('#device-toggle'));

  $('#tab-sections').addEventListener('click', () => {
    setNav('sections');
    if (S.sel?.kind === 'theme') deselect();
  });
  $('#tab-theme').addEventListener('click', () => {
    setNav('theme');
    if (!isNarrow() && S.loaded && S.sel?.kind !== 'theme') openThemeGroup(ownThemeGroups()[0]?.group);
  });
  $$('[role="tab"]').forEach((tab) => tab.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
    const other = tab.id === 'tab-sections' ? $('#tab-theme') : $('#tab-sections');
    other.focus();
    other.click();
  }));

  $$('[data-sheet-close]').forEach((b) => b.addEventListener('click', closeSheet));
  $$('.dock__btn').forEach((b) => b.addEventListener('click', () => {
    const which = b.dataset.dock;
    if (which === 'sections') {
      setNav('sections', { keepSelection: true });
      openSheet('nav');
    } else if (which === 'theme') {
      setNav('theme', { keepSelection: true });
      openSheet('nav');
    } else {
      openSheet('inspector');
    }
  }));

  document.addEventListener('keydown', onKeydown);

  ui.updateToolbar = updateToolbar;
  ui.updateDock = updateDock;
  ui.onKeydown = onKeydown;
  ui.setNav = setNav;
  ui.showPanel = showPanel;
  ui.openThemeGroup = openThemeGroup;
}
