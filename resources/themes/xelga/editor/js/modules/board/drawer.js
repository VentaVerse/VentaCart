/*
 * The settings drawer at the right of the layout board. It shows what is
 * selected: a section or a block (their usual forms, drawn by the
 * inspector), a row of columns (its four settings, drawn here), or the
 * theme (its groups, then a group's form). The board narrows beside it and
 * keeps its shape. It closes with its X or Escape; nothing in it opens a
 * dialog.
 *
 * A row's settings are its columns, width, background and the space
 * between its columns. A row of one column has none: the settings of the
 * section in it cover it.
 */

import { $, h, icon, toast, announce } from '../dom.js';
import { S, ui, doc, sec, groupHandle, labelOf, isFixed, templateOf, locate as locateSection } from '../state.js';
import { ownThemeGroups } from '../catalog.js';
import { commit, undo, layoutPayload, rulesFor } from '../operations.js';
import { select, deselect, post, radioItems, radioKeys } from '../selection.js';
import { rowsOf, locate } from './model.js';
import { splitOf, listWords } from './words.js';
import { schemePicker, schemeGroupKey, schemeChoices } from '../forms/schemes.js';

export { schemeChoices };

/** The settings a row of columns has, in the order the drawer shows them. */
export const rowFields = () => ['split', 'width', 'scheme', 'space'];

const WIDTH_WORDS = { page: 'Page width', full: 'Full width' };
const SPACE_WORDS = { sm: 'Small', md: 'Medium', lg: 'Large' };

const app = () => $('#app');
const template = () => groupHandle('template');

/** The drawer is used while the board is shown; the store preview keeps the settings panel. */
export const inDrawer = () => S.canvas === 'board' && Boolean($('#drawer'));
export const drawerBody = () => $('#drawer-body');

/* ------------------------------------------------------------------------
   Where a section sits, and what fits there
   ------------------------------------------------------------------------ */

/** How wide a section's place is, in twelfths: its column, its side, or the full page. */
export function roomOf(sid) {
  const at = locateSection(sid);
  const layout = layoutPayload();
  if (!at || at.group !== 'template' || !layout) return 12;
  const where = locate(at.doc.content, sid, layout);
  if (!where) return 12;
  if (where.where === 'side') return layout.side_span;
  const row = rowsOf(at.doc.content, layout).find((r) => r.id === where.rowId);
  return splitOf(row?.split).parts[where.column] ?? 12;
}

/** Whether a section would still fit its place with another layout (variant). */
export function variantFits(sid, variant) {
  const at = locateSection(sid);
  if (!at || !layoutPayload()) return true;
  const section = { type: at.data.type, settings: { ...(at.data.settings || {}), variant } };
  return rulesFor(at.handle).minSpan(section) <= roomOf(sid);
}

/* ------------------------------------------------------------------------
   Open, closed, and what it shows
   ------------------------------------------------------------------------ */

/** A row of two or more columns on the page open now, or null. */
function rowNow(rowId) {
  const content = doc(template())?.content;
  const layout = layoutPayload();
  if (!content || !layout || isFixed()) return null;
  const row = rowsOf(content, layout).find((r) => r.id === rowId);
  return row && splitOf(row.split).parts.length > 1 ? row : null;
}

/** What the drawer shows now: 'selection' (drawn by the inspector), 'row', 'theme', or null when closed. */
function drawerView() {
  if (!inDrawer() || !S.loaded) return null;
  if (S.sel) return 'selection';
  if (S.view.type === 'row' && rowNow(S.view.rowId)) return 'row';
  if (S.view.type === 'theme') return 'theme';
  return null;
}

export const isDrawerOpen = () => Boolean(drawerView());

let shownKey = '';

/**
 * Draws what has no form in the inspector (a row, the theme list), or
 * empties the drawer. The inspector calls this when nothing is selected.
 */
export function renderDrawer() {
  const body = drawerBody();
  if (!body) return;
  const view = drawerView();
  const key = view === 'row' ? `row:${S.view.rowId}` : view || '';
  const scroll = key === shownKey ? $('.insp-body', body)?.scrollTop || 0 : 0;
  if (view === 'row') body.replaceChildren(rowView(rowNow(S.view.rowId)));
  else if (view === 'theme') body.replaceChildren(themeList());
  else if (!view) body.replaceChildren();
  shownKey = key;
  const inner = $('.insp-body', body);
  if (inner && scroll) inner.scrollTop = scroll;
  syncDrawer();
}

/** Opens or closes the drawer to match what is selected. */
export function syncDrawer() {
  const a = app();
  if (!a) return;
  const open = isDrawerOpen();
  a.dataset.drawer = open ? 'open' : 'closed';
  const drawer = $('#drawer');
  if (drawer) drawer.hidden = !open;
  if (!open) shownKey = '';
  $('#theme-btn')?.setAttribute('aria-pressed', String(open && (S.view.type === 'theme' || S.sel?.kind === 'theme')));
}

function reveal(selector) {
  window.requestAnimationFrame(() => $(selector)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
}

/** A theme group's form, with the drawer's way back to the theme list. */
function openGroup(group, extra = {}) {
  if (!group) return;
  S.view = { type: 'theme' };
  S.sel = { kind: 'theme', group, ...extra };
  post('select', { sectionId: null, blockId: null });
  ui.renderOutline();
  ui.renderThemeNav();
  ui.renderInspector();
  ui.updateDock();
}

/**
 * Opens the drawer on one view. Answers false when the board is not shown,
 * so the caller uses the settings panel instead.
 *   {type: 'section', sid, bid?}  a section or one of its blocks
 *   {type: 'row', rowId}          a row of columns
 *   {type: 'theme'}               the theme's groups
 *   {type: 'theme-group', group}  one group's form
 *   {type: 'schemes', scheme?}    the color schemes, one of them open
 */
export function openDrawer(view) {
  if (!inDrawer() || !S.loaded || !view) return false;
  if (view.type === 'section') {
    if (!sec(view.sid)) return false;
    S.view = { type: 'section', sid: view.sid };
    select(view.sid, view.bid || null);
    reveal(`#board .piece[data-sid="${CSS.escape(view.sid)}"]`);
  } else if (view.type === 'row') {
    if (!rowNow(view.rowId)) return false;
    S.view = { type: 'row', rowId: view.rowId };
    // A row and a section are never selected together.
    if (S.sel) deselect();
    else {
      ui.renderBoard?.();
      ui.renderInspector();
    }
    reveal(`#board .row[data-row-id="${CSS.escape(view.rowId)}"]`);
  } else if (view.type === 'theme') {
    S.view = { type: 'theme' };
    if (S.sel) deselect();
    else ui.renderInspector();
  } else if (view.type === 'theme-group') {
    openGroup(view.group);
  } else if (view.type === 'schemes') {
    openGroup(schemeGroupKey(), view.scheme ? { scheme: String(view.scheme) } : {});
  } else {
    return false;
  }
  return true;
}

/** Closes the drawer and puts focus back on what it was opened from. */
export function closeDrawer() {
  const from = { sel: S.sel, view: S.view };
  S.view = { type: 'board' };
  if (S.sel) deselect();
  else {
    ui.renderBoard?.();
    ui.renderInspector();
  }
  let back = null;
  if (from.sel?.kind === 'section') back = $(`#board .piece[data-sid="${CSS.escape(from.sel.sid)}"]`);
  else if (from.view.type === 'row') back = $(`#board .row[data-row-id="${CSS.escape(from.view.rowId)}"] .row__tab`);
  else if (from.sel?.kind === 'theme' || from.view.type === 'theme') back = $('#theme-btn');
  back?.focus({ preventScroll: true });
}

/**
 * Shows one place a color scheme is used: opens its page when it is on
 * another one, then selects the section, block or row there.
 *   use  { handle, section, block, row }
 */
export async function goToPlace(use) {
  if (!use?.handle) return;
  // The places are on the board: show it if the store preview is up.
  if (S.canvas !== 'board') ui.setCanvas?.('board');
  const shared = use.handle === 'header' || use.handle === 'footer';
  if (!shared && use.handle !== template()) {
    const custom = templateOf(use.handle);
    const type = custom ? custom.pageType : S.pages.find((p) => p.handle === use.handle)?.type;
    if (!type) return;
    const opened = await ui.openPage?.(custom ? { type, layout: custom.handle } : { type });
    if (!opened || !S.loaded) return;
  }
  if (use.row && openDrawer({ type: 'row', rowId: use.row })) return;
  if (use.section && openDrawer({ type: 'section', sid: use.section, bid: use.block && sec(use.section)?.blocks?.[use.block] ? use.block : null })) return;
  toast('That place is no longer on the page.');
}

/* ------------------------------------------------------------------------
   Drawn here: the head, a row's settings, the theme list
   ------------------------------------------------------------------------ */

function drawerHead(title, kind) {
  return h('div', { class: 'insp-head' },
    h('div', { class: 'insp-head__row' },
      h('h2', { class: 'insp-title' }, h('span', { text: title })),
      h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Close settings', 'data-tip': 'Close (Esc)', onclick: closeDrawer }, icon('close'))),
    kind ? h('p', { class: 'insp-kind' }, h('span', { class: 'insp-kind__dot', 'aria-hidden': 'true' }), kind) : null);
}

function field(label, id, control) {
  return h('div', { class: 'field', role: 'group', 'aria-labelledby': id },
    h('div', { class: 'field__label', id }, h('span', { text: label })),
    control);
}

function splitPicture(parts) {
  const el = h('span', { class: 'splitpic', 'aria-hidden': 'true' });
  parts.forEach((p) => el.append(h('span', { class: 'splitpic__col', style: { gridColumn: `span ${p}` } })));
  return el;
}

function choiceGroup(labelId, values, words, value, onPick) {
  const group = h('div', { class: 'seg seg--fill', role: 'radiogroup', 'aria-labelledby': labelId });
  values.forEach((v) => group.append(h('button', {
    type: 'button', role: 'radio', dataset: { value: v },
    onclick: () => {
      radioItems(group, v);
      onPick(v);
    },
  }, words[v] || v)));
  radioItems(group, value);
  radioKeys(group);
  return group;
}

const sectionNames = (row) => row.columns.flat().filter((sid) => sec(sid)).map((sid) => labelOf(sid));

function setRow(row, key, value) {
  if (row.settings?.[key] === value) return;
  commit({ t: 'row-set', doc: template(), rowId: row.id, key, from: row.settings?.[key], to: value });
}

/**
 * Gives a row other columns. Sections that no longer fit move to rows of
 * their own below, and the message says which; one column puts each
 * section in a full-width row and shows the first.
 */
function changeColumns(row, split) {
  if (row.split === split) return;
  const first = row.columns.flat().find((sid) => sec(sid)) || null;
  const op = { t: 'row-split', doc: template(), rowId: row.id, split };
  commit(op);
  if (!op.prior) return;
  const next = splitOf(split);
  const moved = (op.moved || []).filter((sid) => sec(sid)).map((sid) => labelOf(sid));
  let text;
  if (next.parts.length === 1) {
    text = 'The row is back to one column. Its sections now sit one under the other.';
  } else if (moved.length === 1) {
    text = `${moved[0]} did not fit its new column, so it moved to a row of its own below.`;
  } else if (moved.length) {
    text = `${listWords(moved)} did not fit their new columns, so they moved to rows of their own below.`;
  } else {
    text = `Columns changed to ${next.label.toLowerCase()}.`;
  }
  toast(text, { action: { label: 'Undo', run: undo }, timeout: moved.length ? 8000 : 4200 });
  announce(text);
  if (next.parts.length === 1) {
    if (!(first && openDrawer({ type: 'section', sid: first }))) closeDrawer();
  }
}

function rowView(row) {
  const layout = layoutPayload();
  const split = splitOf(row.split);
  const names = sectionNames(row);
  const choices = layout.row_choices || {};

  const splits = h('div', { class: 'splits splits--row', role: 'group', 'aria-labelledby': 'row-columns-label' });
  Object.entries(layout.splits).forEach(([id, sp]) => splits.append(h('button', {
    type: 'button', class: 'split-opt', 'aria-pressed': String(row.split === id), dataset: { value: id },
    onclick: () => changeColumns(row, id),
  }, splitPicture(sp.parts), h('span', { class: 'split-opt__label', text: sp.label }))));

  const form = h('div', { class: 'form' },
    field('Columns', 'row-columns-label', splits),
    field('Width', 'row-width-label', choiceGroup('row-width-label', choices.width || ['page', 'full'], WIDTH_WORDS, row.settings.width, (v) => setRow(row, 'width', v))),
    field('Background', 'row-scheme-label', schemePicker(row.settings.scheme || '', (v) => setRow(row, 'scheme', v), { allowNone: true })),
    field('Space between columns', 'row-space-label', choiceGroup('row-space-label', choices.space || ['sm', 'md', 'lg'], SPACE_WORDS, row.settings.space, (v) => setRow(row, 'space', v))));

  const body = h('div', { class: 'insp-body' }, form);
  if (!names.length) {
    body.append(h('div', { class: 'insp-foot' },
      h('button', {
        type: 'button', class: 'btn btn--danger-text btn--sm',
        onclick: () => {
          commit({ t: 'row-remove', doc: template(), rowId: row.id });
          closeDrawer();
        },
      }, icon('trash', 'icon--sm'), 'Remove empty row')));
  }
  return h('div', { class: 'inspector' },
    drawerHead('Row', `${split.label}: ${names.length ? listWords(names) : 'no sections yet'}`),
    body);
}

function themeList() {
  const list = h('ul', { class: 'tlist', role: 'list' }, ...ownThemeGroups().map((group) => h('li', null, h('button', {
    type: 'button', class: 'tlist__item',
    onclick: () => openGroup(group.group),
  },
  h('span', { class: 'tlist__icon' }, icon(group.icon || 'settings', 'icon--sm')),
  h('span', { class: 'tlist__text' },
    h('span', { class: 'tlist__title', text: group.label }),
    group.description ? h('span', { class: 'tlist__desc', text: group.description }) : null),
  icon('chevron-right', 'icon--sm')))));
  return h('div', { class: 'inspector' },
    drawerHead('Theme', 'Applies to every page of the store'),
    h('div', { class: 'insp-body' }, list));
}

export function initDrawer() {
  ui.inDrawer = inDrawer;
  ui.renderDrawer = renderDrawer;
  ui.syncDrawer = syncDrawer;
  ui.openDrawer = openDrawer;
  ui.closeDrawer = closeDrawer;
  ui.isDrawerOpen = isDrawerOpen;
  ui.goToPlace = goToPlace;
  $('#theme-btn')?.addEventListener('click', () => {
    if (S.view.type === 'theme' || S.sel?.kind === 'theme') closeDrawer();
    else openDrawer({ type: 'theme' });
  });
}
