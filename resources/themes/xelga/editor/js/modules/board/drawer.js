/*
 * The settings panel at the right of the layout board. It is always open,
 * so the board keeps one width whatever is selected, and what it shows
 * changes in place, with no slide and no fade: a section or a block (their
 * usual forms, drawn by the inspector), a row of columns (its four
 * settings, drawn here), the theme (its groups, then a group's form),
 * earlier versions, or, with nothing selected, the page's own settings
 * (layouts.js). The page's name above a title, Escape, a click on the
 * empty board and Theme pressed again all go back to the page. On a phone
 * the panel is a sheet that rests as one line at the bottom, reading
 * "Page settings"; a tap on the line or a selection brings it up. Nothing
 * in it opens a dialog.
 *
 * A row's settings are its columns, width, background and the space
 * between its columns. A row of one column has none: the settings of the
 * section in it cover it.
 */

import { $, h, icon, toast, announce } from '../dom.js';
import { S, ui, doc, sec, groupHandle, labelOf, isFixed, templateOf, pageTitle, locate as locateSection } from '../state.js';
import { ownThemeGroups } from '../catalog.js';
import { commit, undo, layoutPayload, rulesFor } from '../operations.js';
import { select, deselect, radioItems, radioKeys } from '../selection.js';
import { rowsOf, roomOf as roomIn, sharesRow as sharesRowIn } from './model.js';
import { splitOf, listWords } from './words.js';
import { schemePicker, schemeGroupKey, schemeChoices } from '../forms/schemes.js';

export { schemeChoices };

/** The settings a row of columns has, in the order the drawer shows them. */
export const rowFields = () => ['split', 'width', 'scheme', 'space'];

const WIDTH_WORDS = { page: 'Page width', full: 'Full width' };
const SPACE_WORDS = { sm: 'Small', md: 'Medium', lg: 'Large' };

const app = () => $('#app');
const template = () => groupHandle('template');

export const drawerBody = () => $('#drawer-body');

/* ------------------------------------------------------------------------
   Where a section sits, and what fits there
   ------------------------------------------------------------------------ */

/** How wide a section's place is, in twelfths: its column, its side, or the full page. */
export function roomOf(sid) {
  const at = locateSection(sid);
  if (!at || at.group !== 'template' || !layoutPayload()) return 12;
  return roomIn(at.doc.content, sid, rulesFor(at.handle)) ?? 12;
}

/** Whether a section stands in a column or a side, which a choice that needs a row of its own cannot use. */
export function sharesRow(sid) {
  const at = locateSection(sid);
  if (!at || at.group !== 'template' || !layoutPayload()) return false;
  return sharesRowIn(at.doc.content, sid, rulesFor(at.handle));
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

/**
 * What the panel shows now: 'selection' (drawn by the inspector), 'row',
 * 'theme', 'revisions', 'page' (nothing selected), or null while the page
 * is loading.
 */
function drawerView() {
  if (!drawerBody() || !S.loaded) return null;
  if (S.sel) return 'selection';
  if (S.view.type === 'row' && rowNow(S.view.rowId)) return 'row';
  if (S.view.type === 'theme') return 'theme';
  if (S.view.type === 'revisions') return 'revisions';
  return 'page';
}

/** Whether the panel shows the page's own settings, the view with nothing selected. */
export const showingPage = () => drawerView() === 'page';

let shownKey = '';

/**
 * Draws what has no form in the inspector: a row, the theme list, earlier
 * versions or the page's own settings. The inspector calls this when
 * nothing is selected.
 */
export function renderDrawer() {
  const body = drawerBody();
  if (!body) return;
  const view = drawerView();
  const key = view === 'row' ? `row:${S.view.rowId}` : view === 'page' ? `page:${S.page?.handle || S.page?.type || ''}` : view || '';
  const scroll = key === shownKey ? $('.insp-body', body)?.scrollTop || 0 : 0;
  if (view === 'row') body.replaceChildren(rowView(rowNow(S.view.rowId)));
  else if (view === 'theme') body.replaceChildren(themeList());
  else if (view === 'revisions' && key !== shownKey) ui.renderRevisions?.(body, drawerHead);
  else if (view === 'page') body.replaceChildren(pageView());
  else if (!view) body.replaceChildren();
  shownKey = key;
  const inner = $('.insp-body', body);
  if (inner && scroll) inner.scrollTop = scroll;
  syncDrawer();
}

/*
 * The panel holds its column at all times. On a phone it is a sheet: it
 * rests as one line while the page is shown and the line was not tapped,
 * and comes up for anything selected.
 */
export function syncDrawer() {
  const a = app();
  if (!a) return;
  a.dataset.drawer = 'open';
  const drawer = $('#drawer');
  if (!drawer) return;
  drawer.hidden = false;
  const view = drawerView();
  if (view !== 'page') S.sheet = false;
  drawer.dataset.kind = view || 'loading';
  drawer.dataset.sheet = view === 'page' && !S.sheet ? 'rest' : 'open';
  const line = $('#sheet-line');
  if (line) {
    line.hidden = !S.loaded;
    line.setAttribute('aria-expanded', String(drawer.dataset.sheet === 'open'));
  }
  $('#theme-btn')?.setAttribute('aria-pressed', String(S.view.type === 'theme' || S.sel?.kind === 'theme' || S.sel?.kind === 'stores'));
}

/** The page's own settings: its layout, what shares it, and the layout's settings (layouts.js). */
function pageView() {
  const body = h('div', { class: 'insp-body' }, ...(ui.pageSettings?.() || []));
  return h('div', { class: 'inspector' },
    drawerHead(pageTitle(), 'Page settings', { page: true }),
    body);
}

function reveal(selector) {
  window.requestAnimationFrame(() => $(selector)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
}

/** A theme group's form, with the drawer's way back to the theme list. */
function openGroup(group, extra = {}) {
  if (!group) return;
  S.view = { type: 'theme' };
  S.sel = { kind: 'theme', group, ...extra };
  ui.renderBoard?.();
  ui.renderInspector();
}

/**
 * Opens the drawer on one view. Answers false when there is nothing to
 * show (the page is loading, the section or row is gone).
 *   {type: 'section', sid, bid?}  a section or one of its blocks
 *   {type: 'row', rowId}          a row of columns
 *   {type: 'theme'}               the theme's groups
 *   {type: 'theme-group', group}  one group's form
 *   {type: 'schemes', scheme?}    the color schemes, one of them open
 *   {type: 'revisions'}           earlier versions
 */
export function openDrawer(view) {
  if (!drawerBody() || !S.loaded || !view) return false;
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
  } else if (view.type === 'revisions') {
    S.view = { type: 'revisions' };
    shownKey = '';
    if (S.sel) deselect();
    else {
      ui.renderBoard?.();
      ui.renderInspector();
    }
  } else {
    return false;
  }
  return true;
}

/**
 * Goes back to the page's own settings, the panel with nothing selected.
 * The focus goes back to what the panel was showing, on the board or in
 * the top bar; with `focus: 'panel'` it goes to the panel's title instead.
 */
export function showPage({ focus = 'origin' } = {}) {
  const from = { sel: S.sel, view: S.view };
  S.view = { type: 'board' };
  S.sheet = false;
  if (S.sel) deselect();
  else {
    ui.renderBoard?.();
    ui.renderInspector();
  }
  if (focus === 'panel') {
    $('#drawer .insp-title')?.focus({ preventScroll: true });
    return;
  }
  let back = null;
  if (from.sel?.kind === 'section') back = $(`#board .piece[data-sid="${CSS.escape(from.sel.sid)}"]`);
  else if (from.view.type === 'row') back = $(`#board .row[data-row-id="${CSS.escape(from.view.rowId)}"] .row__tab`);
  else if (from.sel?.kind === 'theme' || from.sel?.kind === 'stores' || from.view.type === 'theme') back = $('#theme-btn');
  else if (from.view.type === 'revisions') back = $('#history-btn');
  // Hidden behind Preview, a box on the board cannot take the focus: the panel's title does.
  if (back && !back.closest('[hidden]')) back.focus({ preventScroll: true });
  else if (from.sel || from.view.type !== 'board') $('#drawer .insp-title')?.focus({ preventScroll: true });
}

/** On a phone, the line at the bottom brings the page's settings up, or puts them down again. */
export function toggleSheet(open = !S.sheet) {
  S.sheet = Boolean(open);
  syncDrawer();
  if (S.sheet) window.requestAnimationFrame(() => $('#drawer .insp-title')?.focus({ preventScroll: true }));
  else $('#sheet-line')?.focus({ preventScroll: true });
}

/**
 * Shows one place a color scheme is used: opens its page when it is on
 * another one, then selects the section, block or row there.
 *   use  { handle, section, block, row }
 */
export async function goToPlace(use) {
  if (!use?.handle) return;
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

/**
 * The way back to the page, above a title: "← Home page". The page's own
 * settings have none, and neither has a view with its own back arrow.
 */
export function pageCrumb() {
  return h('button', { type: 'button', class: 'panel-crumb', onclick: () => showPage() },
    icon('arrow-left', 'icon--xs'), h('span', { text: pageTitle() }));
}

/*
 * On a phone the panel is a sheet, so it keeps a close button: down to its
 * resting line for the page's settings, back to the page for anything else.
 * A computer has nothing to close to, so the button is not shown there.
 */
export function sheetClose({ page = false } = {}) {
  return page
    ? h('button', { type: 'button', class: 'icon-btn panel-close', 'aria-label': 'Put Page settings down', onclick: () => toggleSheet(false) }, icon('chevron-down'))
    : h('button', { type: 'button', class: 'icon-btn panel-close', 'aria-label': 'Close settings', onclick: () => showPage() }, icon('close'));
}

export function drawerHead(title, kind, { page = false } = {}) {
  return h('div', { class: 'insp-head' },
    page ? null : pageCrumb(),
    h('div', { class: 'insp-head__row' },
      h('h2', { class: 'insp-title', tabindex: -1 }, h('span', { text: title })),
      sheetClose({ page })),
    kind ? h('p', { class: 'insp-kind' }, h('span', { class: `insp-kind__dot${page ? ' insp-kind__dot--page' : ''}`, 'aria-hidden': 'true' }), kind) : null);
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
    if (!(first && openDrawer({ type: 'section', sid: first }))) showPage();
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
          showPage({ focus: 'panel' });
        },
      }, icon('trash', 'icon--sm'), 'Remove empty row')));
  }
  return h('div', { class: 'inspector' },
    drawerHead('Row', `${split.label}: ${names.length ? listWords(names) : 'no sections yet'}`),
    body);
}

function themeList() {
  const start = ui.storeStartRow?.();
  const list = h('ul', { class: 'tlist', role: 'list' }, start ? h('li', null, start) : null, ...ownThemeGroups().map((group) => h('li', null, h('button', {
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
  ui.drawerBody = drawerBody;
  ui.drawerHead = drawerHead;
  ui.renderDrawer = renderDrawer;
  ui.syncDrawer = syncDrawer;
  ui.openDrawer = openDrawer;
  ui.showPage = showPage;
  ui.showingPage = showingPage;
  ui.toggleSheet = toggleSheet;
  ui.goToPlace = goToPlace;
  ui.pageCrumb = pageCrumb;
  ui.sheetClose = sheetClose;
  // Theme pressed again goes back to the page.
  $('#theme-btn')?.addEventListener('click', () => {
    if (S.view.type === 'theme' || S.sel?.kind === 'theme' || S.sel?.kind === 'stores') showPage();
    else openDrawer({ type: 'theme' });
  });
  $('#sheet-line')?.addEventListener('click', () => toggleSheet());
}
