/*
 * Moving sections and rows on the board: by dragging with a mouse or pen,
 * by pressing a moment then dragging on a touch screen, or with Alt plus
 * the arrow keys. Both work out the same targets from the
 * row model (targetsFor), so a key never puts a section somewhere a drag
 * could not.
 *
 * While a section is held, the spots it fits are lit, a column it does not
 * fit fades and refuses the drop with the reason, and the label under the
 * pointer says where it would go. Each move is one undo step, offered in
 * the message that confirms it.
 */

import { $, $$, h, icon, toast, announce } from '../dom.js';
import { S, doc, groupHandle, labelOf } from '../state.js';
import { sectionIcon } from '../catalog.js';
import { commit, undo, layoutPayload, rulesFor } from '../operations.js';
import { rowsOf, fits, locate, spanAt } from './model.js';
import { rowName, targetWords } from './words.js';
import { createPress, PRESS_DELAY } from './phone.js';

const SIDES = ['left', 'right'];
const START_DISTANCE = 5;
const SNAP_DISTANCE = 56;
const EDGE = 48;
const CLICK_GUARD_MS = 250;

/* ------------------------------------------------------------------------
   Targets (pure)
   ------------------------------------------------------------------------ */

/** A stable text for a target, to compare targets and to keep one on an element. */
export const targetKey = (t) => JSON.stringify([t.drop, t.before ?? null, t.rowId ?? null, t.column ?? null, t.side ?? null]);

function gapsOf(rows) {
  return [...rows.map((r) => ({ drop: 'gap', before: r.id })), { drop: 'gap', before: 'end' }];
}

/**
 * Every spot an item may be dropped on, in board order. A section: the
 * gaps between rows, each place in a column, the left and right edge of a
 * full-width section and each place in a side that is drawn, kept to those
 * it fits. A row: every gap but the two around it.
 */
export function targetsFor(content, item, rules) {
  const layout = rules.layout;
  const rows = rowsOf(content, layout);
  if (item?.kind === 'row') {
    const index = rows.findIndex((r) => r.id === item.rowId);
    if (index < 0) return [];
    const next = rows[index + 1]?.id ?? 'end';
    return gapsOf(rows).filter((t) => t.before !== item.rowId && t.before !== next);
  }
  if (item?.kind !== 'section') return [];
  const all = gapsOf(rows);
  rows.forEach((r) => {
    if (r.split === '12') {
      SIDES.forEach((side) => all.push({ drop: 'beside', rowId: r.id, side }));
      return;
    }
    r.columns.forEach((column, c) => {
      column.forEach((sid) => all.push({ drop: 'column', rowId: r.id, column: c, before: sid }));
      all.push({ drop: 'column', rowId: r.id, column: c, before: null });
    });
  });
  SIDES.forEach((side) => {
    const list = Array.isArray(content.sides?.[side]) ? content.sides[side] : [];
    // A side shows on the board while it holds a section.
    if (!list.length) return;
    list.forEach((sid) => all.push({ drop: 'side', side, before: sid }));
    all.push({ drop: 'side', side, before: null });
  });
  return all.filter((t) => fits(content, item.sid, t, rules));
}

/**
 * The spot Alt plus an arrow key moves a section to, or null. Up and down
 * move one place within a column or side, then out of it into the gap
 * above or below its row; a full-width section moves its row. Left and
 * right move to the end of the nearest column in that direction it fits.
 */
export function keyTarget(content, sid, arrow, rules) {
  const layout = rules.layout;
  const at = locate(content, sid, layout);
  if (!at) return null;
  const rows = rowsOf(content, layout);
  const up = arrow === 'ArrowUp';
  const down = arrow === 'ArrowDown';
  const sideways = arrow === 'ArrowLeft' || arrow === 'ArrowRight';
  if (!up && !down && !sideways) return null;
  const ok = (t) => (t && fits(content, sid, t, rules) ? t : null);

  if (at.where === 'side') {
    if (sideways) return null;
    const list = content.sides[at.side];
    const index = at.index + (up ? -1 : 1);
    if (index < 0 || index >= list.length) return null;
    return ok({ drop: 'side', side: at.side, before: up ? list[index] : list[index + 1] ?? null });
  }

  const ri = rows.findIndex((r) => r.id === at.rowId);
  const r = rows[ri];
  if (r.split === '12') {
    if (sideways) return null;
    if (up) return ri > 0 ? ok({ drop: 'gap', before: rows[ri - 1].id }) : null;
    return ri < rows.length - 1 ? ok({ drop: 'gap', before: rows[ri + 2]?.id ?? 'end' }) : null;
  }

  if (sideways) {
    const step = arrow === 'ArrowLeft' ? -1 : 1;
    for (let c = at.column + step; c >= 0 && c < r.columns.length; c += step) {
      const t = ok({ drop: 'column', rowId: r.id, column: c, before: null });
      if (t) return t;
    }
    return null;
  }
  const column = r.columns[at.column];
  const index = at.index + (up ? -1 : 1);
  if (index >= 0 && index < column.length) {
    return ok({ drop: 'column', rowId: r.id, column: at.column, before: up ? column[index] : column[index + 1] ?? null });
  }
  return ok({ drop: 'gap', before: up ? r.id : rows[ri + 1]?.id ?? 'end' });
}

const ARROW_KEYS = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

/** The arrows Alt moves a section by from where it is now: only those that lead somewhere. */
export function keyMoves(content, sid, rules) {
  return ARROW_KEYS.filter((arrow) => keyTarget(content, sid, arrow, rules) !== null);
}

/** The arrows Alt moves a row of columns by: up below the first row, down above the last. */
export function rowKeyMoves(rows, rowId) {
  const index = rows.findIndex((r) => r.id === rowId);
  if (index < 0) return [];
  return [index > 0 ? 'ArrowUp' : null, index < rows.length - 1 ? 'ArrowDown' : null].filter(Boolean);
}

/* ------------------------------------------------------------------------
   Making the move
   ------------------------------------------------------------------------ */

const template = () => groupHandle('template');
const contentNow = () => doc(template())?.content || null;

function itemName(item) {
  const content = contentNow();
  if (item.kind === 'section') return labelOf(item.sid);
  const r = content ? rowsOf(content, layoutPayload()).find((x) => x.id === item.rowId) : null;
  return r ? `The row of ${rowName(r)}` : 'The row';
}

/**
 * Commits a move as one operation. A section alone in a full-width row
 * moves with its row, so the row keeps its settings.
 */
export function moveItem(item, target) {
  const handle = template();
  const content = contentNow();
  if (!content || !target) return false;
  const rules = rulesFor(handle);
  if (!targetsFor(content, item, rules).some((t) => targetKey(t) === targetKey(target))) return false;
  const skip = item.kind === 'section' ? item.sid : null;
  const words = targetWords(target, skip);
  const name = itemName(item);
  let op;
  if (item.kind === 'row') {
    op = { t: 'row-move', doc: handle, rowId: item.rowId, before: target.before };
  } else {
    const at = locate(content, item.sid, layoutPayload());
    const own = at?.where === 'row' ? rowsOf(content, layoutPayload()).find((r) => r.id === at.rowId) : null;
    op = own?.split === '12' && target.drop === 'gap'
      ? { t: 'row-move', doc: handle, rowId: own.id, before: target.before, name }
      : { t: 'place', doc: handle, sid: item.sid, target };
  }
  commit(op);
  if (!op.prior) return false;
  const text = `${name} moved ${words}.`;
  toast(text, { action: { label: 'Undo', run: undo } });
  announce(text);
  return true;
}

/* ------------------------------------------------------------------------
   Keyboard
   ------------------------------------------------------------------------ */

const ARROWS = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

function refocus(selector) {
  window.requestAnimationFrame(() => $(selector)?.focus({ preventScroll: false }));
}

/**
 * Alt plus an arrow key on a section of the board moves it. The browser's
 * own Alt plus Left and Right (back and forward) never run here. Answers
 * whether the section moved.
 */
export function keyMove(event, sid) {
  if (!event.altKey || event.ctrlKey || event.metaKey || !ARROWS.includes(event.key)) return false;
  event.preventDefault();
  event.stopPropagation();
  const content = contentNow();
  if (!content) return false;
  const target = keyTarget(content, sid, event.key, rulesFor(template()));
  if (!target) {
    announce(`${labelOf(sid)} cannot move ${{ ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right' }[event.key]} from here.`);
    return false;
  }
  const moved = moveItem({ kind: 'section', sid }, target);
  if (moved) refocus(`#board .piece[data-sid="${CSS.escape(sid)}"]`);
  return moved;
}

/** Alt plus up or down on a row's tab moves the row. */
export function keyMoveRow(event, rowId) {
  if (!event.altKey || event.ctrlKey || event.metaKey || !ARROWS.includes(event.key)) return false;
  event.preventDefault();
  event.stopPropagation();
  const content = contentNow();
  if (!content || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return false;
  const rows = rowsOf(content, layoutPayload());
  const index = rows.findIndex((r) => r.id === rowId);
  const before = event.key === 'ArrowUp' ? rows[index - 1]?.id : index < rows.length - 1 ? rows[index + 2]?.id ?? 'end' : null;
  if (index < 0 || !before) {
    announce(`${index < 0 ? 'The row' : `The row of ${rowName(rows[index])}`} cannot move ${event.key === 'ArrowUp' ? 'up' : 'down'} from here.`);
    return false;
  }
  const moved = moveItem({ kind: 'row', rowId }, { drop: 'gap', before });
  if (moved) refocus(`#board .row[data-row-id="${CSS.escape(rowId)}"] .row__tab`);
  return moved;
}

/* ------------------------------------------------------------------------
   Dragging
   ------------------------------------------------------------------------ */

let D = null;
let lastDragEnd = 0;

/** True just after a drag, so the click that ends it does not also select. */
export const dragJustEnded = () => Date.now() - lastDragEnd < CLICK_GUARD_MS;

/** Whether a drag is under way, so a redraw can keep its marks. */
export const dragging = () => Boolean(D?.active);

/**
 * Starts watching a press on a section or a row's tab. A mouse or pen that
 * moves a few pixels while pressed picks the item up; a plain click stays
 * a click. On a touch screen a finger that moves at once scrolls the board
 * as usual, and one held still for a moment picks the item up (see
 * createPress); a short press stays a tap.
 */
export function startDrag(event, item) {
  if (D || event.button !== 0 || !S.loaded) return;
  const touch = event.pointerType === 'touch';
  D = {
    item, sx: event.clientX, sy: event.clientY, x: event.clientX, y: event.clientY, active: false, keys: new Set(), over: null, refused: null,
    touch, press: touch ? createPress({ delay: PRESS_DELAY }) : null, held: event.currentTarget,
  };
  if (touch) {
    D.press.down(performance.now());
    D.held?.classList.add('is-pressed');
    // Held still long enough: picked up before the finger moves.
    D.timer = window.setTimeout(() => {
      if (!D || D.active || D.press.move(performance.now(), D.x - D.sx, D.y - D.sy) !== 'drag') return;
      begin();
      if (!D) return;
      navigator.vibrate?.(10);
      moveGhost();
      pickTarget();
    }, PRESS_DELAY);
  }
  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
  window.addEventListener('pointercancel', cancel);
  window.addEventListener('keydown', onKey, true);
  window.addEventListener('blur', cancel);
}

function onKey(event) {
  if (event.key !== 'Escape' || !D) return;
  event.preventDefault();
  event.stopPropagation();
  const active = D.active;
  cancel();
  if (active) announce('Move cancelled.');
}

function begin() {
  const content = contentNow();
  if (!content) {
    cancel();
    return;
  }
  D.active = true;
  D.keys = new Set(targetsFor(content, D.item, rulesFor(template())).map(targetKey));
  document.body.classList.add('is-dragging');
  const iconName = D.item.kind === 'row' ? 'grip' : sectionIcon(content.sections[D.item.sid]?.type);
  D.where = h('span', { class: 'ghost__where' });
  D.ghost = h('div', { class: 'ghost', 'aria-hidden': 'true' },
    h('span', { class: 'ghost__name' }, icon(iconName, 'icon--sm'), h('span', { text: itemName(D.item) })),
    D.where);
  document.body.append(D.ghost);
  markTargets();
}

function onMove(event) {
  if (!D) return;
  D.x = event.clientX;
  D.y = event.clientY;
  if (!D.active && D.touch) {
    const decided = D.press.move(performance.now(), D.x - D.sx, D.y - D.sy);
    // The finger moved first: the board scrolls, nothing is held.
    if (decided === 'scroll') {
      cancel();
      return;
    }
    if (decided !== 'drag') return;
    begin();
    if (!D) return;
  } else if (!D.active) {
    if (Math.hypot(D.x - D.sx, D.y - D.sy) < START_DISTANCE) return;
    begin();
    if (!D) return;
  }
  event.preventDefault();
  moveGhost();
  pickTarget();
  autoScroll();
}

function onUp() {
  if (!D) return;
  const { active, over, refused, item } = D;
  cancel();
  if (!active) return;
  lastDragEnd = Date.now();
  if (over) moveItem(item, over);
  else if (refused) toast(refused, { tone: 'error' });
}

function cancel() {
  if (!D) return;
  window.cancelAnimationFrame(D.frame || 0);
  window.clearTimeout(D.timer || 0);
  D.held?.classList.remove('is-pressed');
  D.ghost?.remove();
  window.removeEventListener('pointermove', onMove);
  window.removeEventListener('pointerup', onUp);
  window.removeEventListener('pointercancel', cancel);
  window.removeEventListener('keydown', onKey, true);
  window.removeEventListener('blur', cancel);
  const wasActive = D.active;
  D = null;
  if (wasActive) {
    lastDragEnd = Date.now();
    document.body.classList.remove('is-dragging');
    clearMarks();
  }
}

function clearMarks() {
  $$('#board .is-valid, #board .is-over, #board .is-blocked, #board .is-lifted').forEach((el) => el.classList.remove('is-valid', 'is-over', 'is-blocked', 'is-lifted'));
}

/** The target an element on the board stands for. */
function targetOf(el) {
  try {
    return JSON.parse(el.dataset.target || 'null');
  } catch {
    return null;
  }
}

/** Lights the spots the held item fits, fades the columns it does not. Runs again after a redraw. */
function markTargets() {
  if (!D?.active) return;
  const board = $('#board');
  if (!board) return;
  D.marked = board.firstElementChild;
  $$('[data-target]', board).forEach((el) => {
    const t = targetOf(el);
    el.classList.toggle('is-valid', Boolean(t) && D.keys.has(targetKey(t)));
  });
  const lifted = D.item.kind === 'row'
    ? $(`.row[data-row-id="${CSS.escape(D.item.rowId)}"]`, board)
    : $(`.piece[data-sid="${CSS.escape(D.item.sid)}"]`, board);
  lifted?.classList.add('is-lifted');
  if (D.item.kind !== 'section') return;
  $$('.row--multi .col, .side', board).forEach((col) => {
    const any = $$('[data-target]', col).some((x) => x.classList.contains('is-valid'));
    const own = Boolean($(`.piece[data-sid="${CSS.escape(D.item.sid)}"]`, col));
    col.classList.toggle('is-blocked', !any && !own);
  });
}

function moveGhost() {
  const w = D.ghost.offsetWidth;
  const left = Math.max(8, Math.min(D.x + 14, window.innerWidth - w - 8));
  // Above a finger, so the finger does not hide where the item goes.
  D.ghost.style.translate = `${left}px ${D.touch ? Math.max(8, D.y - 64) : D.y + 14}px`;
}

const inside = (el, x, y) => {
  const r = el.getBoundingClientRect();
  return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
};

/** Why a held section cannot go into a faded column. */
function refusal(col) {
  const content = contentNow();
  const sid = D.item.sid;
  const name = labelOf(sid);
  const section = content?.sections?.[sid];
  const rules = rulesFor(template());
  if (rules.isMain(section)) return `${name} is the page content, so it keeps a row of its own.`;
  const t = targetOf($('[data-target]', col) || col);
  const span = t && content ? spanAt(content, t, rules) : 0;
  if (span && rules.minSpan(section) > span) return `${name} needs a wider column than that one.`;
  return 'That column is full.';
}

function pickTarget() {
  const board = $('#board');
  // The board was drawn again while the item was held: light the new spots.
  if (board && board.firstElementChild !== D.marked) markTargets();
  $$('#board .is-over').forEach((el) => el.classList.remove('is-over'));
  D.over = null;
  const blocked = $$('#board .is-blocked').find((el) => inside(el, D.x, D.y));
  D.refused = blocked ? refusal(blocked) : null;
  D.ghost.classList.toggle('is-refused', Boolean(D.refused));
  if (D.refused) {
    D.where.textContent = D.refused;
    return;
  }
  let best = null;
  let bestDistance = Infinity;
  $$('#board [data-target].is-valid').forEach((el) => {
    const r = el.getBoundingClientRect();
    const dx = Math.max(r.left - D.x, 0, D.x - r.right);
    const dy = Math.max(r.top - D.y, 0, D.y - r.bottom);
    let distance = Math.hypot(dx, dy);
    // The edges of a section win only when the pointer is on them.
    if (el.dataset.drop === 'beside' && distance > 0) distance += SNAP_DISTANCE;
    if (distance < bestDistance) {
      bestDistance = distance;
      best = el;
    }
  });
  if (best && bestDistance < SNAP_DISTANCE) {
    best.classList.add('is-over');
    D.over = targetOf(best);
  }
  D.where.textContent = D.over ? `Goes ${targetWords(D.over, D.item.kind === 'section' ? D.item.sid : null)}` : 'Move to a lit spot';
}

/** Near the top or bottom of the board, the board scrolls while the item is held. */
function autoScroll() {
  window.cancelAnimationFrame(D.frame || 0);
  const board = $('#board');
  if (!board) return;
  const r = board.getBoundingClientRect();
  let step = 0;
  if (D.y < r.top + EDGE) step = -Math.ceil((r.top + EDGE - D.y) / 3);
  else if (D.y > r.bottom - EDGE) step = Math.ceil((D.y - (r.bottom - EDGE)) / 3);
  if (!step) return;
  const tick = () => {
    if (!D?.active) return;
    const before = board.scrollTop;
    board.scrollTop += step;
    if (board.scrollTop === before) return;
    pickTarget();
    D.frame = window.requestAnimationFrame(tick);
  };
  D.frame = window.requestAnimationFrame(tick);
}

/** Keeps a drag's marks when the board is drawn again while an item is held. */
export function afterRender() {
  if (D?.active) markTargets();
}

/*
 * While a finger holds an item, the page must not scroll under it, nor
 * open the browser's menu for a long press. The listener is there from the
 * start: a browser decides whether a touch may scroll when it begins.
 */
export function initDrag() {
  document.addEventListener('touchmove', (event) => { if (D?.active) event.preventDefault(); }, { passive: false });
  document.addEventListener('contextmenu', (event) => { if (D?.touch) event.preventDefault(); });
}
