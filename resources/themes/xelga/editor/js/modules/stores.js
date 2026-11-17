/*
 * Start from a store: the starting stores, offered from the first row
 * of Theme settings. Choosing a card asks, in the same panel, whether to
 * bring the look only (colors, fonts, buttons) or the look and the home page
 * (header, footer and home page sections). Applying writes the draft only,
 * with the version of every document it replaces; nothing goes live until
 * Publish. A quiet notice over the preview offers one-step Undo, which puts
 * the exact drafts back; it goes away at the next change.
 */

import { $, h, icon, toast, announce } from './dom.js';
import { S, ctx, ui, doc, addDocument, settingsDoc } from './state.js';
import { flush, setFatal } from './drafts.js';
import { detachDocument } from './operations.js';
import { post, setNav, showPanel, isPhone, openSheet, closeSheet } from './selection.js';
import { loadFrame } from './preview.js';

/* The cards' pictures, built with the editor so they are served like its other files. */
const THUMBS = import.meta.glob('../../../store-templates/*/thumbnail.jpg', { eager: true, query: '?url', import: 'default' });

const HANDLES = { all: ['settings', 'header', 'footer', 'home'], look: ['settings'] };
const DEFAULT_STORE = 'gadgets';

/* The chosen card waiting for Apply, and what to bring from it. */
const picker = { choice: null, bring: 'all', busy: false };
let lastApply = null; // { token, name }: the last Apply, for its one-step undo

const stores = () => ctx.boot?.store_templates || [];
const storeBy = (slug) => stores().find((s) => s.slug === slug) || stores().find((s) => s.slug === DEFAULT_STORE) || stores()[0] || null;
const thumbOf = (store) => (store ? THUMBS[`../../../store-templates/${store.slug}/thumbnail.jpg`] || '' : '');

/** Which store the draft's look and its sections came from. */
function basedOn() {
  const based = settingsDoc()?.content?.based_on || {};
  return { look: storeBy(based.look)?.slug || DEFAULT_STORE, sections: storeBy(based.sections)?.slug || DEFAULT_STORE };
}

/* Store names are plain words (Coffee, Parts), so copy says "the Coffee store". */
const theStore = (name) => `the ${name} store`;
const possessive = (name) => `${theStore(name)}'s`;

/* ------------------------------------------------------------------------
   The door: first row of Theme settings
   ------------------------------------------------------------------------ */

export function startRow() {
  if (!stores().length || !settingsDoc()) return null;
  const based = basedOn();
  const look = storeBy(based.look);
  const selected = S.sel?.kind === 'stores';
  return h('div', { class: 't-start' }, h('button', {
    type: 'button', class: `t-item t-item--store${selected ? ' is-selected' : ''}`, id: 'stores-row',
    'aria-current': selected ? 'true' : null, onclick: openStores,
  }, h('img', { class: 't-item__thumb', src: thumbOf(look), alt: '' }),
  h('span', { class: 't-item__text' }, h('span', { class: 't-item__title', text: 'Start from a store' }),
    h('span', { class: 't-item__desc', text: based.look === based.sections ? `Based on ${theStore(look.name)}` : `Look from ${theStore(look.name)}` })),
  icon('chevron-right')));
}

export function openStores() {
  picker.choice = null;
  S.sel = { kind: 'stores' };
  post('select', { sectionId: null, blockId: null });
  if (S.nav !== 'theme') setNav('theme', { keepSelection: true });
  ui.renderThemeNav();
  ui.renderOutline();
  ui.renderInspector();
  showPanel('inspector');
  if (isPhone()) openSheet('inspector');
  ui.updateDock();
  window.setTimeout(() => $('.store-card[aria-current="true"], .store-card', $('#inspector-body'))?.focus({ preventScroll: true }), 30);
}

/* ------------------------------------------------------------------------
   The settings panel: cards, then the confirmation
   ------------------------------------------------------------------------ */

export function storesInspector(inspectorHead) {
  const head = inspectorHead({ title: 'Start from a store', kind: 'Applies to the whole store' });
  const store = picker.choice ? storeBy(picker.choice) : null;
  const body = h('div', { class: 'insp-body' }, store ? applyConfirm(store) : storeGrid());
  return h('div', { class: 'inspector' }, head, body);
}

function storeGrid() {
  const based = basedOn();
  return h('ul', { class: 'store-grid', role: 'list', 'aria-label': 'Starting stores' }, ...stores().map((store) => {
    const isLook = based.look === store.slug;
    const isSections = based.sections === store.slug;
    const tag = isLook && isSections ? 'Current' : isLook ? 'Current look' : isSections ? 'Current sections' : null;
    return h('li', null, h('button', {
      type: 'button', class: `store-card${tag ? ' is-current' : ''}`, dataset: { store: store.slug },
      'aria-current': tag ? 'true' : null,
      onclick: () => chooseStore(store.slug),
    }, h('span', { class: 'store-card__shot' }, h('img', { src: thumbOf(store), alt: '', loading: 'lazy', width: 720, height: 450 }),
      tag ? h('span', { class: 'store-card__tag' }, icon('check'), tag) : null),
    h('span', { class: 'store-card__name', text: store.name }),
    h('span', { class: 'store-card__line', text: store.line })));
  }));
}

function chooseStore(slug) {
  picker.choice = slug;
  picker.bring = 'all';
  ui.renderInspector();
  window.setTimeout(() => $('#apply-title', $('#inspector-body'))?.focus({ preventScroll: true }), 10);
}

function cancelChoice() {
  const slug = picker.choice;
  picker.choice = null;
  ui.renderInspector();
  window.setTimeout(() => $(`.store-card[data-store="${CSS.escape(slug || '')}"]`, $('#inspector-body'))?.focus(), 10);
}

/** Escape on the confirmation goes back to the cards. Answers whether it did. */
export function cancelStoreChoice() {
  if (S.sel?.kind !== 'stores' || !picker.choice || picker.busy) return false;
  cancelChoice();
  return true;
}

/* Says plainly what Apply does with the options as they are now set. */
function applySentence(store) {
  const of = possessive(store.name);
  if (picker.bring === 'look') {
    return `Replaces your draft's colors, fonts and buttons with ${of}. Your sections, products, prices and live store do not change until you Publish.`;
  }
  return `Replaces your draft's look and home page sections with ${of}. Your products, prices and live store do not change until you Publish.`;
}

function applyConfirm(store) {
  const sentence = h('p', { class: 'apply__what', id: 'apply-what', text: applySentence(store) });
  const refresh = () => { sentence.textContent = applySentence(store); };
  const option = (value, title, detail) => h('label', { class: 'apply__opt' },
    h('input', {
      type: 'radio', name: 'apply-bring', value, checked: picker.bring === value || null,
      onchange: () => { picker.bring = value; refresh(); },
    }),
    h('span', { class: 'apply__opt-text' }, h('span', { class: 'apply__opt-title', text: title }), h('small', { text: detail })));

  return h('div', { class: 'apply', role: 'group', 'aria-labelledby': 'apply-title', 'aria-describedby': 'apply-what' },
    h('div', { class: 'apply__store' },
      h('img', { src: thumbOf(store), alt: '', width: 720, height: 450 }),
      h('div', null, h('h3', { class: 'apply__title', id: 'apply-title', tabindex: '-1', text: `Apply ${theStore(store.name)}?` }),
        h('p', { class: 'apply__line', text: store.line }))),
    h('fieldset', { class: 'apply__bring' },
      h('legend', { text: 'What to bring' }),
      option('all', 'Look and home page', 'Colors, fonts, buttons, header, footer and home page sections'),
      option('look', 'Look only', 'Colors, fonts and buttons')),
    sentence,
    h('div', { class: 'apply__actions' },
      h('button', { type: 'button', class: 'btn btn--secondary', onclick: cancelChoice }, 'Cancel'),
      h('button', { type: 'button', class: 'btn btn--primary', id: 'apply-btn', onclick: () => applyStore(store) }, 'Apply to draft')));
}

/* ------------------------------------------------------------------------
   Apply and undo
   ------------------------------------------------------------------------ */

function setBusy(busy) {
  picker.busy = busy;
  const btn = $('#apply-btn');
  if (!btn) return;
  btn.disabled = busy;
  btn.setAttribute('aria-busy', String(busy));
  btn.textContent = busy ? 'Applying' : 'Apply to draft';
}

/** Takes the server's documents into the editor; undo history of what they replaced no longer fits. */
function adopt(documents) {
  Object.values(documents).forEach((fresh) => {
    detachDocument(fresh.handle, Infinity);
    addDocument(fresh);
  });
}

function failed(error, what) {
  if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
    setFatal(error);
    return;
  }
  toast(`${what} ${error.message}`, { tone: 'error', timeout: 9000 });
}

async function applyStore(store) {
  if (picker.busy) return;
  setBusy(true);
  try {
    // Unsaved changes are saved first, so the versions sent are the ones on the server.
    if (!(await flush())) {
      toast('Your changes are not saved yet, so the store was not applied. Check the status at the top.', { tone: 'error' });
      return;
    }
    const bring = picker.bring;
    const handles = HANDLES[bring];
    // A page not opened in this visit is loaded, so its version is known.
    const unknown = handles.filter((handle) => !doc(handle));
    if (unknown.length) (await Promise.all(unknown.map((handle) => ctx.api.getDocument(handle)))).forEach(addDocument);
    const versions = Object.fromEntries(handles.map((handle) => [handle, doc(handle).draftVersion]));

    let res;
    try {
      res = await ctx.api.applyStore(store.slug, { bring, versions });
    } catch (error) {
      if (error.kind === 'conflict') {
        // Someone else saved one of these drafts: show theirs, and let the merchant decide again.
        adopt(Object.fromEntries((await Promise.all(handles.map((handle) => ctx.api.getDocument(handle).catch(() => null)))).filter(Boolean).map((d) => [d.handle, d])));
        ui.renderOutline();
        ui.renderThemeNav();
        ui.reloadPreview?.();
        toast(`${error.message} Their changes are loaded; apply again if you still want ${theStore(store.name)}.`, { tone: 'error', timeout: 10000 });
        return;
      }
      failed(error, `The ${store.name} store was not applied.`);
      return;
    }

    adopt(res.documents);
    lastApply = res.undo ? { token: res.undo, name: store.name } : null;
    picker.choice = null;

    // Back to the section list and the preview: missing categories and products are picked from there.
    S.sel = null;
    S.open.clear();
    setNav('sections');
    showPanel('nav');
    if (isPhone()) closeSheet();
    showApplyNote(`Applied ${theStore(store.name)} to your draft.`);
    if (S.page?.type !== 'home' || S.page?.layout) {
      await ui.openPage({ type: 'home' }, { saved: true });
    } else {
      S.scope = 'page';
      ui.rememberPage();
      ui.updatePageButton();
      ui.renderOutline();
      ui.renderThemeNav();
      ui.renderInspector();
      loadFrame();
    }
    ui.updateToolbar();
    ui.updateDock();
    announce(`Applied ${theStore(store.name)} to your draft.`);
    $('#tab-sections').focus({ preventScroll: true });
  } catch (error) {
    failed(error, `The ${store.name} store was not applied.`);
  } finally {
    setBusy(false);
  }
}

function showApplyNote(text) {
  $('#apply-note-text').textContent = text;
  $('#apply-note').hidden = false;
}

/** The notice and its undo end at the next change, or when dismissed. */
export function endApplyNote() {
  lastApply = null;
  const note = $('#apply-note');
  if (note) note.hidden = true;
}

async function undoApply() {
  if (!lastApply) return;
  const { token, name } = lastApply;
  endApplyNote();
  let res;
  try {
    res = await ctx.api.undoStore(token);
  } catch (error) {
    failed(error, `The ${name} store was not taken off your draft.`);
    return;
  }
  adopt(res.documents);
  S.sel = null;
  ui.renderOutline();
  ui.renderThemeNav();
  ui.renderInspector();
  ui.updateToolbar();
  ui.updateDock();
  loadFrame();
  $('#tab-sections').focus({ preventScroll: true });
  toast(res.message || `The ${name} store is taken off your draft. Your draft is back as it was.`, { tone: 'success' });
}

export function initStores() {
  ui.storeStartRow = startRow;
  ui.storesInspector = storesInspector;
  ui.cancelStoreChoice = cancelStoreChoice;
  ui.endApplyNote = endApplyNote;
  $('#apply-undo').addEventListener('click', undoApply);
  $('#apply-close').addEventListener('click', () => {
    endApplyNote();
    $('#tab-sections').focus({ preventScroll: true });
  });
}
