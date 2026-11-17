/*
 * The layout board: the page drawn in the shape the shopper sees it, at
 * most as wide as the store. The header on top, marked as on every page;
 * the page's rows, each column at its true share of the width; the footer
 * at the bottom. Each section is a name bar over a picture of how the
 * store draws it from the draft (see pictures.js); a section the page draws
 * nothing for shows the drawing of its layout instead.
 *
 * Clicking a section selects it, and its settings open in the drawer.
 * Every change redraws the board, then the pictures it touched. On a phone
 * the page is drawn as a list instead (see phone.js).
 *
 * Sections are added where they will show: an Add line between rows (a
 * row of columns, or one section full width) and an Add under each column,
 * both opening the section picker. Each section but the page's main one
 * has a Change control on its name bar that opens the same picker to
 * replace it in its spot.
 * Sections and rows move by drag and drop or Alt plus the arrow keys (see
 * drag.js); every spot that takes a drop carries its target in
 * `data-target`.
 */

import { $, h, icon, schematic, toast, announce } from '../dom.js';
import {
  S, ctx, ui, doc, groupHandle, groupList, isFixed, labelOf, pageTitle, valueOf, schemes,
} from '../state.js';
import { sectionDef, sectionIcon, variantDrawing } from '../catalog.js';
import { onChange, layoutPayload, rulesFor } from '../operations.js';
import { flush, setFatal } from '../drafts.js';
import { openSheet, closeSheet, isPhone } from '../selection.js';
import { refreshNeeds, needTag } from '../needs.js';
import { rowsOf } from './model.js';
import { createPictures } from './pictures.js';
import { boardSource, layoutButton, fixedPanel } from '../layouts.js';
import { openPicker, pickerSpot, pickerReplacing } from './picker.js';
import { splitOf, listWords, columnName } from './words.js';
import { barWords } from './labels.js';
import {
  startDrag, keyMove, keyMoveRow, keyMoves, rowKeyMoves, dragJustEnded, afterRender, targetKey, initDrag, keyMoveBand, bandKeyMoves,
} from './drag.js';
import { renderPhoneBoard } from './phone.js';

const REFRESH_WAIT_MS = 700;

let pictures = null;
let loadedKey = '';
let pictureState = 'idle'; // idle | loading | ready | failed

const app = () => $('#app');

/* ------------------------------------------------------------------------
   Words
   ------------------------------------------------------------------------ */

const variantOf = (sid) => valueOf(sid, null, 'variant');

function variantLabel(sid, type) {
  const value = variantOf(sid);
  return sectionDef(type)?.variants?.find((v) => v.value === value)?.label || '';
}

/* ------------------------------------------------------------------------
   Pieces: one section on the board
   ------------------------------------------------------------------------ */

const ARROWS = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

/* The Alt and arrow keys a section or row really moves by, for aria-keyshortcuts; null when none. */
const shortcuts = (arrows) => (arrows.length ? arrows.map((a) => `Alt+${a}`).join(' ') : null);

function sectionShortcuts(sid) {
  const handle = groupHandle('template');
  const content = doc(handle)?.content;
  return content ? shortcuts(keyMoves(content, sid, rulesFor(handle))) : null;
}

/** A spot that takes a drop: the row model's target, kept on the element. */
const dropData = (target) => ({ target: JSON.stringify(target), drop: target.drop });

function piece(section, span, { movable = false, changeable = false, band = null } = {}) {
  const def = sectionDef(section.type);
  const selected = S.sel?.kind === 'section' && S.sel.sid === section.id;
  const name = labelOf(section.id);
  const words = barWords(name, def?.label || '', variantLabel(section.id, section.type));
  const hidden = Boolean(section.disabled);
  const main = def?.main === true;
  const picture = pictures?.pictureFor(section.id, span) || null;
  const flags = [];
  const need = def && !hidden ? needTag(section.id) : null;
  if (main) flags.push(['lock', 'Page content', 'The page content: it can be moved, never removed']);
  if (hidden) flags.push(['eye-off', 'Hidden', 'Hidden on the store']);
  if (need && !need.quiet) flags.push(['alert', need.text, `${need.text}: the store shows this section once something is picked`]);
  if (!def) flags.push(['alert', 'Not shown', 'The part of the store this section belongs to is not installed']);
  const el = h('button', {
    type: 'button',
    class: `piece${selected ? ' is-selected' : ''}${main ? ' piece--main' : ''}${hidden ? ' is-hidden' : ''}`,
    dataset: { sid: section.id, type: section.type },
    'aria-pressed': String(selected),
    'aria-keyshortcuts': band ? shortcuts(bandKeyMoves(band, section.id)) : movable ? sectionShortcuts(section.id) : null,
    onclick: () => {
      if (dragJustEnded()) return;
      openSheet({ type: 'section', sid: section.id });
    },
    onpointerdown: band ? (e) => startDrag(e, { kind: 'band', group: band, sid: section.id })
      : movable ? (e) => startDrag(e, { kind: 'section', sid: section.id }) : null,
    onkeydown: band ? (e) => keyMoveBand(e, band, section.id) : movable ? (e) => keyMove(e, section.id) : null,
  },
  // Name, then the type when the section has a name of its own, then the layout.
  h('span', { class: 'piece__bar', title: words.title },
    h('span', { class: 'piece__line' },
      icon(sectionIcon(section.type), 'icon--sm piece__icon'),
      h('span', { class: 'piece__name', text: words.name }),
      words.type ? h('span', { class: 'piece__type', text: words.type }) : null,
      words.layout ? h('span', { class: 'piece__variant', text: words.layout }) : null,
      ...flags.map(([iconName, text, title]) => h('span', { class: 'piece__flag', title }, icon(iconName, 'icon--xs'), h('span', { class: 'piece__flagtext', text }))))),
  h('span', { class: 'piece__shot' },
    h('span', { class: 'piece__sketch' }, schematic(variantDrawing(section.type, variantOf(section.id)))),
    picture));
  // The header, the footer and the page's main section keep their section: they have no Change.
  if (!changeable || main || !def) return el;
  const replacing = pickerReplacing() === section.id;
  return h('div', { class: `piece-wrap${replacing ? ' is-replacing' : ''}`, dataset: { sid: section.id } },
    el,
    h('button', {
      type: 'button', class: 'piece__change', 'aria-label': `Change ${name} to another section`, title: 'Change section',
      onclick: () => { if (!dragJustEnded()) openPicker({ replace: section.id }); },
    }, icon('replace', 'icon--sm')));
}

/* ------------------------------------------------------------------------
   The page: header, rows, footer
   ------------------------------------------------------------------------ */

function sharedBand(group) {
  const list = groupList(group);
  const band = h('div', { class: `shared shared--${group}` },
    h('p', { class: 'shared__tag' }, icon('layers', 'icon--xs'), group === 'header' ? 'Header, on every page' : 'Footer, on every page'));
  // Its sections move within the band only: up and down, never into the page or the other band.
  const movable = list.length > 1;
  list.forEach((s) => {
    if (movable) band.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData({ drop: 'band', group, before: s.id }) }));
    band.append(piece(s, 12, { band: movable ? group : null }));
  });
  if (movable) band.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData({ drop: 'band', group, before: null }) }));
  return band;
}

/** A row's background: its color scheme's, or the page's. */
function rowBackground(row) {
  const id = row.settings?.scheme;
  if (!id) return null;
  const scheme = schemes().find((s) => s.id === id);
  return scheme?.colors?.bg ? { background: scheme.colors.bg } : null;
}

const isAddTarget = (target) => {
  const t = pickerSpot();
  return Boolean(t) && targetKey(t) === targetKey(target);
};

function newSpot() {
  return h('div', { class: 'newspot', 'aria-hidden': 'true' }, icon('plus', 'icon--sm'), 'New section goes here');
}

/** Between two rows: an Add line, shown on hover or focus (always on touch screens). */
function gapEl(before, above, below) {
  const target = { drop: 'gap', before };
  if (isAddTarget(target)) return h('div', { class: 'gap gap--target', dataset: dropData(target) }, newSpot());
  const where = above && below ? `between ${above} and ${below}` : below ? `above ${below}` : above ? `below ${above}` : '';
  return h('div', { class: 'gap', dataset: dropData(target) },
    h('button', {
      type: 'button', class: 'gap__add', 'aria-label': `Add a section or a row of columns${where ? ` ${where}` : ''}`,
      onclick: () => { if (!dragJustEnded()) openPicker({ target }); },
    }, h('span', { class: 'gap__line', 'aria-hidden': 'true' }), h('span', { class: 'gap__pill' }, icon('plus', 'icon--xs'), 'Add')));
}

function rowEl(row, sections) {
  const split = splitOf(row.split);
  const parts = split.parts;
  const single = parts.length === 1;
  const named = row.columns.flat().map((sid) => sections[sid] && labelOf(sid)).filter(Boolean);
  const selected = S.view.type === 'row' && S.view.rowId === row.id;
  const main = row.columns.flat().some((sid) => sectionDef(sections[sid]?.type)?.main === true);
  const el = h('div', {
    class: `row ${single ? 'row--single' : 'row--multi'}${selected ? ' is-selected' : ''}`,
    dataset: { rowId: row.id, split: row.split },
  });
  if (!single) {
    el.append(h('button', {
      type: 'button', class: 'row__tab', 'aria-pressed': String(selected),
      'aria-label': `Row, ${split.label.toLowerCase()}${named.length ? `: ${listWords(named)}` : ''}`,
      'aria-keyshortcuts': shortcuts(rowKeyMoves(rowsOf(doc(groupHandle('template')).content, layoutPayload()), row.id)),
      title: 'Row settings. Drag to move the row.',
      onclick: () => {
        if (dragJustEnded()) return;
        if (selected) closeSheet();
        else openSheet({ type: 'row', rowId: row.id });
      },
      onpointerdown: (e) => startDrag(e, { kind: 'row', rowId: row.id }),
      onkeydown: (e) => keyMoveRow(e, row.id),
    }, splitPicture(parts), h('span', { text: `Row, ${split.label.toLowerCase()}` })));
  }
  const cols = h('div', {
    class: 'row__cols',
    dataset: { space: row.settings?.space || 'md', width: row.settings?.width || 'page' },
    style: single ? null : { gridTemplateColumns: parts.map((p) => `minmax(0, ${p}fr)`).join(' '), ...rowBackground(row) },
  });
  const max = layoutPayload()?.max_per_column ?? 6;
  parts.forEach((part, index) => {
    const column = h('div', { class: 'col', dataset: { span: String(part) } });
    const list = (row.columns[index] || []).map((sid) => (sections[sid] ? { id: sid, ...sections[sid] } : null)).filter(Boolean);
    if (single) {
      list.forEach((s) => column.append(piece(s, part, { movable: true, changeable: true })));
      // Two sections side by side: a drop on the left or right edge of a full-width one.
      if (list.length === 1 && !main) {
        ['left', 'right'].forEach((side) => column.append(h('div', {
          class: `beside beside--${side}`, 'aria-hidden': 'true', dataset: dropData({ drop: 'beside', rowId: row.id, side }),
        }, h('span', { text: `Put ${side === 'left' ? 'on the left' : 'on the right'}` }))));
      }
    } else {
      const name = columnName(parts, index).toLowerCase();
      list.forEach((s) => {
        const spot = { drop: 'column', rowId: row.id, column: index, before: s.id };
        if (isAddTarget(spot)) column.append(newSpot());
        column.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData(spot) }), piece(s, part, { movable: true, changeable: true }));
      });
      const end = { drop: 'column', rowId: row.id, column: index, before: null };
      if (isAddTarget(end)) {
        column.append(newSpot());
      } else if (!list.length) {
        column.append(h('button', {
          type: 'button', class: 'col__empty', dataset: dropData(end), 'aria-label': `Add a section to the ${name}`,
          onclick: () => { if (!dragJustEnded()) openPicker({ target: end }); },
        }, icon('plus', 'icon--sm'), h('span', { text: 'Add a section' })));
      } else if (list.length < max) {
        column.append(h('button', {
          type: 'button', class: 'col__add', dataset: dropData(end),
          'aria-label': `Add a section to the ${name}, under ${labelOf(list[list.length - 1].id)}`,
          onclick: () => { if (!dragJustEnded()) openPicker({ target: end }); },
        }, icon('plus', 'icon--xs'), h('span', { text: 'Add' })));
      } else {
        column.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData(end) }));
      }
    }
    cols.append(column);
  });
  el.append(cols);
  return el;
}

function sideEl(which, ids, sections) {
  const el = h('div', { class: `side side--${which}` },
    h('p', { class: 'side__tag', text: which === 'left' ? 'Left side' : 'Right side' }));
  ids.forEach((sid) => {
    if (!sections[sid]) return;
    el.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData({ drop: 'side', side: which, before: sid }) }),
      piece({ id: sid, ...sections[sid] }, 3, { movable: true, changeable: true }));
  });
  el.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData({ drop: 'side', side: which, before: null }) }));
  return el;
}

function pageRows() {
  const content = doc(groupHandle('template'))?.content;
  if (!content) return h('div', { class: 'rows' });
  const sections = content.sections || {};
  const rows = h('div', { class: 'rows' });
  const list = rowsOf(content, layoutPayload());
  const nameOf = (row) => (row ? listWords(row.columns.flat().filter((sid) => sections[sid]).map((sid) => labelOf(sid))) || 'an empty row' : null);
  list.forEach((row, i) => rows.append(gapEl(row.id, nameOf(list[i - 1]), nameOf(row)), rowEl(row, sections)));
  rows.append(gapEl('end', nameOf(list[list.length - 1]), null));
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

function head({ pictures: withPictures = true } = {}) {
  const source = boardSource();
  const settings = layoutButton();
  return h('div', { class: 'boardhead' },
    h('div', { class: 'boardhead__text' },
      h('h1', { class: 'boardhead__title', text: pageTitle() }),
      source?.meta ? h('p', { class: 'boardhead__meta', text: source.meta }) : null,
      withPictures ? pictureNote() : null),
    settings ? h('div', { class: 'boardhead__actions' }, settings) : null,
    source?.note || null);
}

/** Draws the board again from the documents. */
export function renderBoard() {
  const board = $('#board');
  if (!board) return;
  app().dataset.loaded = String(Boolean(S.loaded));
  // The board follows the selection made anywhere: the drawer, the picker, undo.
  if (S.sel?.kind === 'section') S.view = { type: 'section', sid: S.sel.sid };
  else if (S.view.type === 'section') S.view = { type: 'board' };
  if (S.view.type === 'row' && !rowExists(S.view.rowId)) S.view = { type: 'board' };

  if (!S.loaded || !S.page) {
    board.replaceChildren(h('div', { class: 'board__inner' }, h('div', { class: 'sk-rows', 'aria-hidden': 'true' }, ...Array.from({ length: 6 }, () => h('div', { class: 'sk sk--piece' })))));
    return;
  }
  refreshNeeds();
  const phone = isPhone();
  board.dataset.view = phone ? 'list' : 'board';
  // A picture of a whole section is unreadable on a phone: the list shows the drawing of its layout.
  if (!phone) ensurePictures();
  const keep = board.scrollTop;
  const focused = focusKey(board);
  const page = isFixed() ? fixedPanel() : pageRows();
  if (phone) {
    board.replaceChildren(renderPhoneBoard({
      title: pageTitle(), head: head({ pictures: false }), header: sharedBand('header'), page, footer: sharedBand('footer'),
    }));
  } else {
    const sheet = h('div', { class: 'sheet', role: 'group', 'aria-label': `${pageTitle()}, drawn as the page` },
      sharedBand('header'), page, sharedBand('footer'));
    board.replaceChildren(h('div', { class: 'board__inner' }, head(), sheet));
  }
  board.scrollTop = keep;
  if (focused) refocusKey(board, focused);
  afterRender();
}

/*
 * The board is drawn anew after every change and when its pictures come
 * in. The control that had the keyboard focus gets it back, so a keyboard
 * user never drops to the top of the page.
 */
function focusKey(board) {
  const el = document.activeElement;
  if (!el || el === board || !board.contains(el)) return null;
  const wrap = el.closest('[data-sid]');
  if (el.matches('.piece__change') && wrap) return { sid: wrap.dataset.sid, change: true };
  if (el.matches('.piece')) return { sid: el.dataset.sid };
  if (el.matches('.row__tab')) return { rowId: el.closest('.row')?.dataset.rowId };
  const spot = el.closest('[data-target]');
  return spot ? { target: spot.dataset.target } : null;
}

function refocusKey(board, key) {
  let el = null;
  if (key.sid) {
    el = key.change
      ? board.querySelector(`.piece-wrap[data-sid="${CSS.escape(key.sid)}"] .piece__change`)
      : board.querySelector(`.piece[data-sid="${CSS.escape(key.sid)}"]`);
  } else if (key.rowId) {
    el = board.querySelector(`.row[data-row-id="${CSS.escape(key.rowId)}"] .row__tab`);
  } else if (key.target) {
    const spot = Array.from(board.querySelectorAll('[data-target]')).find((x) => x.dataset.target === key.target);
    el = spot && (spot.matches('button') ? spot : spot.querySelector('button'));
  }
  el?.focus({ preventScroll: true });
}

function rowExists(rowId) {
  const content = doc(groupHandle('template'))?.content;
  return Boolean(content) && rowsOf(content, layoutPayload()).some((r) => r.id === rowId);
}

/* ------------------------------------------------------------------------
   Pictures
   ------------------------------------------------------------------------ */

const pageKey = () => `${S.page?.handle || ''}|${S.page?.url || ''}|${ctx.boot?.preview?.nonce || ''}`;

/** Draws every picture again, from the saved draft: after a store is applied, a draft thrown away or a version restored. */
export function redrawPictures() {
  loadedKey = '';
  pending = null;
  window.clearTimeout(timer);
  renderBoard();
}

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

function requeue(job) {
  pending = pending || { rows: new Set(), sections: new Set(), styles: false };
  job.rows.forEach((id) => pending.rows.add(id));
  job.sections.forEach((id) => pending.sections.add(id));
  pending.styles = pending.styles || job.styles;
  window.clearTimeout(timer);
  timer = window.setTimeout(runRefresh, REFRESH_WAIT_MS * 4);
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
    if (!await flush()) {
      // Not saved yet, so nothing new to draw: keep the job for the next try.
      requeue(job);
      return;
    }
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
   Keys
   ------------------------------------------------------------------------ */

/*
 * Alt with an arrow key never reaches the browser from the board, so it
 * never goes back a page. Sections and rows that move handle it themselves;
 * anything else says why nothing moved.
 */
function boardKeys(event) {
  if (!event.altKey || event.ctrlKey || event.metaKey || !ARROWS.includes(event.key)) return;
  event.preventDefault();
  const piece = event.target.closest?.('.piece');
  const sid = piece?.dataset.sid;
  if (sid && piece.closest('.shared')) {
    const band = piece.closest('.shared--header') ? 'header' : 'footer';
    announce(`${labelOf(sid)} is the only section in the ${band}, so it stays where it is.`);
  } else if (sid) {
    announce(`${labelOf(sid)} cannot move from here.`);
  } else {
    announce('Alt and the arrow keys move a section or a row. Move the focus to one first.');
  }
}

export function initBoard() {
  pictures = createPictures({ api: ctx.api, stylesheets: [] });
  ui.renderBoard = renderBoard;
  ui.redrawPictures = redrawPictures;
  onChange((op, { effects }) => queueRefresh(op, effects));
  $('#board')?.addEventListener('keydown', boardKeys);
  initDrag();
  // The board and the list are drawn differently: draw again when the window crosses the phone width.
  window.matchMedia('(max-width: 719px)').addEventListener('change', () => renderBoard());
}
