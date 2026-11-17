/*
 * The layout board: the page drawn in the shape the shopper sees it, at
 * most as wide as the store. The header on top, marked as on every page;
 * the page's rows, each column at its true share of the width; the footer
 * at the bottom. Each section is a name bar over a picture of how the
 * store draws it from the draft (see pictures.js); a section the page draws
 * nothing for shows the drawing of its layout instead.
 *
 * Clicking a section selects it, and its settings open as they do from the
 * section list. Every change redraws the board, then the pictures it
 * touched.
 */

import { $, h, icon, schematic, toast } from '../dom.js';
import {
  S, ctx, ui, doc, groupHandle, groupList, isFixed, labelOf, pageTitle, valueOf, schemes,
} from '../state.js';
import { sectionDef, sectionIcon, variantDrawing } from '../catalog.js';
import { onChange, layoutPayload } from '../operations.js';
import { flush, setFatal } from '../drafts.js';
import { select, deselect } from '../selection.js';
import { fitFrame } from '../preview.js';
import { rowsOf } from './model.js';
import { createPictures } from './pictures.js';
import { boardSource } from '../layouts.js';

const REFRESH_WAIT_MS = 700;

let pictures = null;
let loadedKey = '';
let pictureState = 'idle'; // idle | loading | ready | failed

const app = () => $('#app');

/* ------------------------------------------------------------------------
   Words
   ------------------------------------------------------------------------ */

const splitOf = (split) => layoutPayload()?.splits?.[split] || { parts: [12], label: 'One column' };

function listWords(names) {
  if (names.length <= 1) return names[0] || '';
  return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
}

function columnName(parts, index) {
  const n = parts.length;
  if (n === 2 && parts[0] === parts[1]) return ['Left half', 'Right half'][index];
  const names = {
    2: ['Left column', 'Right column'],
    3: ['Left column', 'Middle column', 'Right column'],
    4: ['First column', 'Second column', 'Third column', 'Last column'],
  };
  return (names[n] || ['Column'])[index];
}

const variantOf = (sid) => valueOf(sid, null, 'variant');

function variantLabel(sid, type) {
  const value = variantOf(sid);
  return sectionDef(type)?.variants?.find((v) => v.value === value)?.label || '';
}

/* ------------------------------------------------------------------------
   Pieces: one section on the board
   ------------------------------------------------------------------------ */

function piece(section, span) {
  const def = sectionDef(section.type);
  const selected = S.sel?.kind === 'section' && S.sel.sid === section.id;
  const name = labelOf(section.id);
  const hidden = Boolean(section.disabled);
  const main = def?.main === true;
  const picture = pictures?.pictureFor(section.id, span) || null;
  const flags = [];
  if (main) flags.push(['lock', 'Page content', 'The page content: it can be moved, never removed']);
  if (hidden) flags.push(['eye-off', 'Hidden', 'Hidden on the store']);
  if (!def) flags.push(['alert', 'Not shown', 'The part of the store this section belongs to is not installed']);
  return h('button', {
    type: 'button',
    class: `piece${selected ? ' is-selected' : ''}${main ? ' piece--main' : ''}${hidden ? ' is-hidden' : ''}`,
    dataset: { sid: section.id, type: section.type },
    'aria-pressed': String(selected),
    onclick: () => {
      S.view = { type: 'section', sid: section.id };
      select(section.id);
    },
  },
  h('span', { class: 'piece__bar' },
    icon(sectionIcon(section.type), 'icon--sm piece__icon'),
    h('span', { class: 'piece__name', text: name }),
    h('span', { class: 'piece__variant', text: variantLabel(section.id, section.type) }),
    ...flags.map(([iconName, text, title]) => h('span', { class: 'piece__flag', title }, icon(iconName, 'icon--xs'), h('span', { class: 'piece__flagtext', text })))),
  h('span', { class: 'piece__shot' },
    h('span', { class: 'piece__sketch' }, schematic(variantDrawing(section.type, variantOf(section.id)))),
    picture));
}

/* ------------------------------------------------------------------------
   The page: header, rows, footer
   ------------------------------------------------------------------------ */

function sharedBand(group) {
  const list = groupList(group);
  const band = h('div', { class: `shared shared--${group}` },
    h('p', { class: 'shared__tag' }, icon('layers', 'icon--xs'), group === 'header' ? 'Header, on every page' : 'Footer, on every page'));
  list.forEach((s) => band.append(piece(s, 12)));
  return band;
}

/** A row's background: its color scheme's, or the page's. */
function rowBackground(row) {
  const id = row.settings?.scheme;
  if (!id) return null;
  const scheme = schemes().find((s) => s.id === id);
  return scheme?.colors?.bg ? { background: scheme.colors.bg } : null;
}

function rowEl(row, sections) {
  const split = splitOf(row.split);
  const parts = split.parts;
  const single = parts.length === 1;
  const named = row.columns.flat().map((sid) => sections[sid] && labelOf(sid)).filter(Boolean);
  const selected = S.view.type === 'row' && S.view.rowId === row.id;
  const el = h('div', {
    class: `row ${single ? 'row--single' : 'row--multi'}${selected ? ' is-selected' : ''}`,
    dataset: { rowId: row.id, split: row.split },
  });
  if (!single) {
    el.append(h('button', {
      type: 'button', class: 'row__tab', 'aria-pressed': String(selected),
      'aria-label': `Row, ${split.label.toLowerCase()}${named.length ? `: ${listWords(named)}` : ''}`,
      onclick: () => {
        S.view = selected ? { type: 'board' } : { type: 'row', rowId: row.id };
        // A row and a section are never selected together.
        if (S.sel?.kind === 'section') deselect();
        else renderBoard();
      },
    }, splitPicture(parts), h('span', { text: `Row, ${split.label.toLowerCase()}` })));
  }
  const cols = h('div', {
    class: 'row__cols',
    dataset: { space: row.settings?.space || 'md', width: row.settings?.width || 'page' },
    style: single ? null : { gridTemplateColumns: parts.map((p) => `minmax(0, ${p}fr)`).join(' '), ...rowBackground(row) },
  });
  parts.forEach((part, index) => {
    const column = h('div', { class: 'col', dataset: { span: String(part) } });
    const list = (row.columns[index] || []).map((sid) => (sections[sid] ? { id: sid, ...sections[sid] } : null)).filter(Boolean);
    list.forEach((s) => column.append(piece(s, part)));
    if (!list.length) column.append(h('p', { class: 'col__empty', text: `${columnName(parts, index)}, empty` }));
    cols.append(column);
  });
  el.append(cols);
  return el;
}

function sideEl(which, ids, sections) {
  const el = h('div', { class: `side side--${which}` },
    h('p', { class: 'side__tag', text: which === 'left' ? 'Left side' : 'Right side' }));
  ids.forEach((sid) => { if (sections[sid]) el.append(piece({ id: sid, ...sections[sid] }, 3)); });
  return el;
}

function pageRows() {
  const content = doc(groupHandle('template'))?.content;
  if (!content) return h('div', { class: 'rows' });
  const sections = content.sections || {};
  const rows = h('div', { class: 'rows' });
  rowsOf(content, layoutPayload()).forEach((row) => rows.append(rowEl(row, sections)));
  const left = (content.sides?.left || []).filter((sid) => sections[sid]);
  const right = (content.sides?.right || []).filter((sid) => sections[sid]);
  if (!left.length && !right.length) return rows;
  // Sides run the full height of the page content, beside every row.
  return h('div', { class: 'zone', dataset: { sides: `${left.length ? 'l' : ''}${right.length ? 'r' : ''}` } },
    left.length ? sideEl('left', left, sections) : null,
    rows,
    right.length ? sideEl('right', right, sections) : null);
}

function splitPicture(parts) {
  const el = h('span', { class: 'splitpic', 'aria-hidden': 'true' });
  parts.forEach((p) => el.append(h('span', { class: 'splitpic__col', style: { gridColumn: `span ${p}` } })));
  return el;
}

function pictureNote() {
  if (pictureState === 'loading') return h('p', { class: 'boardhead__meta', role: 'status', text: 'Drawing the sections...' });
  if (pictureState !== 'failed') return null;
  return h('p', { class: 'boardhead__meta boardhead__meta--warn', role: 'status' },
    'The pictures of the sections could not be drawn, so their layouts are shown instead. ',
    h('button', { type: 'button', class: 'link-btn', onclick: () => { loadedKey = ''; renderBoard(); } }, 'Try again'));
}

function head() {
  const source = boardSource();
  return h('div', { class: 'boardhead' },
    h('div', { class: 'boardhead__text' },
      h('h1', { class: 'boardhead__title', text: pageTitle() }),
      source?.meta ? h('p', { class: 'boardhead__meta', text: source.meta }) : null,
      pictureNote()),
    source?.note || null);
}

/** Draws the board again from the documents. */
export function renderBoard() {
  const board = $('#board');
  if (!board) return;
  app().dataset.loaded = String(Boolean(S.loaded));
  // The board follows the selection made anywhere: the section list, the preview, undo.
  if (S.sel?.kind === 'section') S.view = { type: 'section', sid: S.sel.sid };
  else if (S.view.type === 'section') S.view = { type: 'board' };
  if (S.view.type === 'row' && !rowExists(S.view.rowId)) S.view = { type: 'board' };

  if (!S.loaded || !S.page) {
    board.replaceChildren(h('div', { class: 'board__inner' }, h('div', { class: 'sk-rows', 'aria-hidden': 'true' }, ...Array.from({ length: 6 }, () => h('div', { class: 'sk sk--piece' })))));
    return;
  }
  ensurePictures();
  const keep = board.scrollTop;
  const sheet = h('div', { class: 'sheet', role: 'group', 'aria-label': `${pageTitle()}, drawn as the page` });
  sheet.append(sharedBand('header'));
  if (isFixed()) {
    sheet.append(h('div', { class: 'fixed-page' }, icon('lock'), h('p', { text: S.page.note || 'This page keeps a fixed layout. Theme settings change how it looks.' })));
  } else {
    sheet.append(pageRows());
  }
  sheet.append(sharedBand('footer'));
  board.replaceChildren(h('div', { class: 'board__inner' }, head(), sheet));
  board.scrollTop = keep;
}

function rowExists(rowId) {
  const content = doc(groupHandle('template'))?.content;
  return Boolean(content) && rowsOf(content, layoutPayload()).some((r) => r.id === rowId);
}

/* ------------------------------------------------------------------------
   Pictures
   ------------------------------------------------------------------------ */

const pageKey = () => `${S.page?.handle || ''}|${S.page?.url || ''}|${ctx.boot?.preview?.nonce || ''}`;

/** Loads the pictures of the page open now, once per page and preview window. */
function ensurePictures() {
  if (!pictures || !S.page?.url || !ctx.boot?.preview?.nonce) return;
  const key = pageKey();
  if (key === loadedKey) return;
  loadedKey = key;
  pictureState = 'loading';
  (async () => {
    // The pictures show the saved draft: save what is waiting first.
    await flush();
    await pictures.load(S.page.url);
  })().then(() => {
    if (loadedKey !== key) return;
    pictureState = 'ready';
    renderBoard();
  }).catch((error) => {
    if (loadedKey !== key) return;
    if (error?.kind === 'unauthenticated' || error?.kind === 'forbidden') setFatal(error);
    pictureState = 'failed';
    renderBoard();
  });
}

let pending = null;
let timer = 0;

/** Notes what a change needs drawn again; the pictures follow once the draft is saved. */
function queueRefresh(op, effects) {
  if (!pictures || pictureState !== 'ready' || op.t === 'rename') return;
  pending = pending || { rows: new Set(), sections: new Set(), styles: false };
  const layout = (effects || []).filter((e) => e && e.board);
  if (op.t === 'theme' || op.t === 'color') {
    pending.styles = true;
    pending.sections.add('*');
  } else if (layout.length) {
    layout.forEach((e) => {
      e.rows.forEach((id) => pending.rows.add(id));
      e.sections.forEach((id) => pending.sections.add(id));
    });
  } else {
    pending.sections.add(op.sid || '*');
  }
  window.clearTimeout(timer);
  timer = window.setTimeout(runRefresh, REFRESH_WAIT_MS);
}

async function runRefresh() {
  const job = pending;
  pending = null;
  if (!job) return;
  const key = loadedKey;
  // Sections inside a row drawn again come with it.
  const content = doc(groupHandle('template'))?.content;
  const inRows = new Set(content ? rowsOf(content, layoutPayload()).filter((r) => job.rows.has(r.id)).flatMap((r) => r.columns.flat()) : []);
  const sections = [...job.sections].filter((id) => !inRows.has(id));
  try {
    if (!await flush()) return;
    if (key !== loadedKey) return;
    await pictures.refresh({ rows: [...job.rows], sections, styles: job.styles });
  } catch (error) {
    if (error?.kind === 'unauthenticated' || error?.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    toast('The board could not draw this change. The draft is saved; the pictures catch up on the next change.', { tone: 'error', timeout: 6000 });
  }
  if (key === loadedKey) renderBoard();
}

/* ------------------------------------------------------------------------
   Board or preview
   ------------------------------------------------------------------------ */

export function setCanvas(which) {
  S.canvas = which === 'preview' ? 'preview' : 'board';
  app().dataset.canvas = S.canvas;
  document.querySelectorAll('#canvas-switch [role="radio"]').forEach((b) => {
    const on = b.dataset.canvas === S.canvas;
    b.setAttribute('aria-checked', String(on));
    b.tabIndex = on ? 0 : -1;
  });
  if (S.canvas === 'board') renderBoard();
  else fitFrame();
}

export function initBoard() {
  pictures = createPictures({ api: ctx.api, stylesheets: [] });
  ui.renderBoard = renderBoard;
  ui.setCanvas = setCanvas;
  // Until the board replaces the section list, it follows each of its redraws.
  const renderList = ui.renderOutline;
  ui.renderOutline = () => {
    renderList();
    renderBoard();
  };
  onChange((op, { effects }) => queueRefresh(op, effects));
  const group = $('#canvas-switch');
  group.addEventListener('click', (e) => {
    const b = e.target.closest('[role="radio"]');
    if (b) setCanvas(b.dataset.canvas);
  });
  group.addEventListener('keydown', (e) => {
    if (!['ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown'].includes(e.key)) return;
    e.preventDefault();
    const next = S.canvas === 'board' ? 'preview' : 'board';
    setCanvas(next);
    group.querySelector(`[data-canvas="${next}"]`)?.focus();
  });
  setCanvas('board');
}
