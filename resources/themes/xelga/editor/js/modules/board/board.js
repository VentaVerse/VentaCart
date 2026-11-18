/*
 * The layout board: the page drawn in the shape the shopper sees it, at
 * most as wide as the store. The header on top, marked as on every page;
 * the page's rows, each column at its true share of the width; the footer
 * at the bottom. On a page that may have sides, a left and a right side
 * stand beside every row: an empty one is a thin strip that adds its first
 * section, a filled one holds its sections at three twelfths, and the rows
 * between them draw at the content width the sides leave. Each section is
 * a plain box of the same height as every other: its name bar over the
 * drawing of its layout. The board is for arranging; Preview shows the
 * store itself.
 *
 * Clicking a section selects it, and its settings show in the panel beside
 * the board; a click on the empty board goes back to the page's own
 * settings. Every change redraws the board. On a phone the page is drawn as
 * a list instead (see phone.js).
 *
 * Sections are added where they will show: an Add line between rows (a
 * row of columns, or one section full width), an Add under each column
 * and side, and the strip of an empty side, all opening the section
 * picker. Each section but the page's main one has a Change control on
 * its name bar that opens the same picker to replace it in its spot.
 * Sections and rows move by drag and drop or Alt plus the arrow keys (see
 * drag.js); every spot that takes a drop carries its target in
 * `data-target`.
 */

import { $, h, icon, announce } from '../dom.js';
import { sketch } from './sketch.js';
import {
  S, ui, doc, groupHandle, groupList, isFixed, labelOf, pageTitle, valueOf, schemes,
} from '../state.js';
import { sectionDef, sectionIcon } from '../catalog.js';
import { layoutPayload, rulesFor, sidesFor } from '../operations.js';
import { openSheet, isPhone } from '../selection.js';
import { refreshNeeds, needTag } from '../needs.js';
import { rowsOf, contentSpan } from './model.js';
import { fixedPanel } from '../layouts.js';
import { openPicker, pickerSpot, pickerReplacing } from './picker.js';
import { splitOf, listWords, columnName } from './words.js';
import { barWords } from './labels.js';
import {
  startDrag, keyMove, keyMoveRow, keyMoves, rowKeyMoves, dragJustEnded, afterRender, targetKey, initDrag, keyMoveBand, bandKeyMoves,
} from './drag.js';
import { renderPhoneBoard } from './phone.js';
import { addLineMode } from './place.js';
import { pageHeading } from '../page-picker.js';

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

/**
 * One section: a box with its name bar over the drawing of its layout. Its
 * width is its column's; its height is the same as every other box's.
 */
function piece(section, { movable = false, changeable = false, band = null } = {}) {
  const def = sectionDef(section.type);
  const selected = S.sel?.kind === 'section' && S.sel.sid === section.id;
  const name = labelOf(section.id);
  const words = barWords(name, def?.label || '', variantLabel(section.id, section.type));
  const hidden = Boolean(section.disabled);
  const main = def?.main === true;
  const flags = [];
  const need = def && !hidden ? needTag(section.id) : null;
  if (main) flags.push(['lock', 'Page content', 'The page content: it can be moved, never removed']);
  if (hidden) flags.push(['eye-off', 'Hidden', 'Hidden on the store']);
  if (need && !need.quiet) flags.push(['alert', need.text, `${need.text}: the store shows this section once something is picked`]);
  if (!def) flags.push(['alert', 'Not shown', 'The part of the store this section belongs to is not installed']);
  const el = h('button', {
    type: 'button',
    class: `piece piece--box${selected ? ' is-selected' : ''}${main ? ' piece--main' : ''}${hidden ? ' is-hidden' : ''}`,
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
    h('span', { class: 'piece__sketch' }, sketch(section.type, variantOf(section.id), section.type === 'divider' ? valueOf(section.id, null, 'label') : ''))));
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
    band.append(piece(s, { band: movable ? group : null }));
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

/** Between two rows: an Add line, shown on hover or focus (always on touch and narrow screens). */
function gapEl(before, above, below) {
  const target = { drop: 'gap', before };
  if (isAddTarget(target)) return h('div', { class: 'gap gap--target', dataset: dropData(target) }, newSpot());
  const where = above && below ? `between ${above} and ${below}` : below ? `above ${below}` : above ? `below ${above}` : '';
  return h('div', { class: 'gap', dataset: dropData(target) },
    h('button', {
      type: 'button', class: 'gap__add', 'aria-label': `Add a section or a row of columns${where ? ` ${where}` : ''}`,
      onclick: () => { if (!dragJustEnded()) openPicker({ target }); },
    }, h('span', { class: 'gap__line', 'aria-hidden': 'true' }), h('span', { class: 'gap__pill' }, icon('plus', 'icon--xs'), 'Add section')));
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
        if (selected) ui.showPage?.();
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
      list.forEach((s) => column.append(piece(s, { movable: true, changeable: true })));
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
        column.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData(spot) }), piece(s, { movable: true, changeable: true }));
      });
      const end = { drop: 'column', rowId: row.id, column: index, before: null };
      if (isAddTarget(end)) {
        column.append(newSpot());
      } else if (!list.length) {
        column.append(h('button', {
          type: 'button', class: 'col__empty', dataset: dropData(end), 'aria-label': `Add a section to the ${name}`,
          onclick: () => { if (!dragJustEnded()) openPicker({ target: end }); },
        }, icon('plus', 'icon--sm'), h('span', { text: 'Add section' })));
      } else if (list.length < max) {
        column.append(h('button', {
          type: 'button', class: 'col__add', dataset: dropData(end),
          'aria-label': `Add a section to the ${name}, under ${labelOf(list[list.length - 1].id)}`,
          onclick: () => { if (!dragJustEnded()) openPicker({ target: end }); },
        }, icon('plus', 'icon--xs'), h('span', { text: 'Add section' })));
      } else {
        column.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData(end) }));
      }
    }
    cols.append(column);
  });
  el.append(cols);
  return el;
}

const SIDE_NAMES = { left: 'Left side', right: 'Right side' };

/**
 * A side of the page. Empty, it is a thin strip whose one button adds the
 * side's first section (and takes a dropped one); a section there turns
 * the side on. With sections, it holds them one under the other, with an
 * Add under the last while there is room.
 */
function sideEl(which, ids, sections) {
  const name = SIDE_NAMES[which];
  const end = { drop: 'side', side: which, before: null };
  if (!ids.length) {
    const el = h('div', { class: `side side--${which} is-empty`, dataset: { side: which } });
    if (isAddTarget(end)) {
      el.classList.remove('is-empty');
      el.append(newSpot());
      return el;
    }
    el.append(h('button', {
      type: 'button', class: 'side__add', dataset: dropData(end), 'aria-label': `Add a section to the ${which} side`, title: `Add a ${which} side`,
      onclick: () => { if (!dragJustEnded()) openPicker({ target: end }); },
    }, h('span', { class: 'side__addlabel' }, icon('plus', 'icon--sm'), h('span', { class: 'side__addtext', text: name }))));
    return el;
  }
  const el = h('div', { class: `side side--${which}`, dataset: { side: which } },
    h('p', { class: 'side__tag', text: name }));
  ids.forEach((sid) => {
    const spot = { drop: 'side', side: which, before: sid };
    if (isAddTarget(spot)) el.append(newSpot());
    el.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData(spot) }),
      piece({ id: sid, ...sections[sid] }, { movable: true, changeable: true }));
  });
  const max = layoutPayload()?.max_per_side ?? 6;
  if (isAddTarget(end)) {
    el.append(newSpot());
  } else if (ids.length < max) {
    el.append(h('button', {
      type: 'button', class: 'col__add', dataset: dropData(end),
      'aria-label': `Add a section to the ${which} side, under ${labelOf(ids[ids.length - 1])}`,
      onclick: () => { if (!dragJustEnded()) openPicker({ target: end }); },
    }, icon('plus', 'icon--xs'), h('span', { text: 'Add section' })));
  } else {
    el.append(h('div', { class: 'slot', 'aria-hidden': 'true', dataset: dropData(end) }));
  }
  return el;
}

function pageRows() {
  const handle = groupHandle('template');
  const content = doc(handle)?.content;
  if (!content) return h('div', { class: 'rows' });
  const sections = content.sections || {};
  const offered = sidesFor(handle);
  const side = (which) => (offered.includes(which) ? (content.sides?.[which] || []).filter((sid) => sections[sid]) : []);
  const left = side('left');
  const right = side('right');
  // Every row draws at the width the sides leave the page's content.
  const area = offered.length ? contentSpan(content, rulesFor(handle)) : 12;
  const rows = h('div', { class: 'rows' });
  const list = rowsOf(content, layoutPayload());
  const nameOf = (row) => (row ? listWords(row.columns.flat().filter((sid) => sections[sid]).map((sid) => labelOf(sid))) || 'an empty row' : null);
  list.forEach((row, i) => rows.append(gapEl(row.id, nameOf(list[i - 1]), nameOf(row)), rowEl(row, sections)));
  rows.append(gapEl('end', nameOf(list[list.length - 1]), null));
  if (!offered.length) return rows;
  // Sides run the full height of the page content, beside every row; on a phone they stack around it.
  return h('div', { class: 'zone', dataset: { sides: `${left.length ? 'l' : ''}${right.length ? 'r' : ''}`, content: String(area) } },
    sideEl('left', left, sections),
    rows,
    sideEl('right', right, sections));
}

function splitPicture(parts) {
  const el = h('span', { class: 'splitpic', 'aria-hidden': 'true' });
  parts.forEach((p) => el.append(h('span', { class: 'splitpic__col', style: { gridColumn: `span ${p}` } })));
  return el;
}

/* The page's heading, which is also the page picker (page-picker.js). */
function head() {
  return h('div', { class: 'boardhead' }, pageHeading('board'));
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
  const keep = board.scrollTop;
  const focused = focusKey(board);
  const page = isFixed() ? fixedPanel() : pageRows();
  if (phone) {
    board.replaceChildren(renderPhoneBoard({
      title: pageTitle(), head: head(), header: sharedBand('header'), page, footer: sharedBand('footer'),
    }));
  } else {
    const sheet = h('div', { class: 'sheet', role: 'group', 'aria-label': `${pageTitle()}, drawn as the page` },
      sharedBand('header'), page, sharedBand('footer'));
    board.replaceChildren(h('div', { class: 'board__inner' }, head(), sheet));
  }
  board.scrollTop = keep;
  if (focused) refocusKey(board, focused);
  ui.remountPageMenu?.();
  afterRender();
}

/*
 * The board is drawn anew after every change. The control that had the keyboard focus gets it back, so a keyboard
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

/*
 * A click on the board outside every box, tab and button goes back to the
 * page's own settings, the usual way to clear a selection.
 */
function emptyClick(event) {
  if (dragJustEnded() || !S.loaded) return;
  if (event.target.closest('button, a, input, select, textarea, label, [role="button"], .piece-wrap, .row__tab, .pagehead')) return;
  if (!S.sel && S.view.type === 'board') return;
  ui.showPage?.();
}

/* Whether the Add lines always show (touch screens, tablets, phones) or on hover and focus; CSS reads it. */
function syncAddLine() {
  const a = app();
  if (!a) return;
  a.dataset.addline = addLineMode({
    coarse: window.matchMedia('(pointer: coarse)').matches,
    hover: window.matchMedia('(hover: hover)').matches,
    width: window.innerWidth,
  });
}

export function initBoard() {
  ui.renderBoard = renderBoard;
  syncAddLine();
  window.addEventListener('resize', syncAddLine);
  ['(pointer: coarse)', '(hover: hover)'].forEach((q) => window.matchMedia(q).addEventListener('change', syncAddLine));
  $('#board')?.addEventListener('keydown', boardKeys);
  $('#board')?.addEventListener('click', emptyClick);
  initDrag();
  // The board and the list are drawn differently: draw again when the window crosses the phone width.
  window.matchMedia('(max-width: 719px)').addEventListener('change', () => renderBoard());
}
