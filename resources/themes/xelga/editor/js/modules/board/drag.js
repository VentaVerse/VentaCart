/*
 * Moving sections and rows on the board: by dragging with a mouse or pen,
 * by pressing a moment then dragging on a touch screen, or with Alt plus
 * the arrow keys. Both work out the same targets from the
 * row model (targetsFor), so a key never puts a section somewhere a drag
 * could not.
 *
 * While a section is held, the spots it fits are lit, an area, column or
 * band it does not fit fades and refuses the drop with the reason (said in
 * a toast and in the live region), and the label under the pointer says
 * where it would go. A header or footer section moves within its band
 * only: the page fades while it is held. Each move is one undo step, offered in
 * the message that confirms it.
 */

import { $, $$, h, icon, toast, announce } from '../dom.js';
import { S, doc, groupHandle, labelOf, locate as locateSection } from '../state.js';
import { sectionIcon, sectionDef, isLocked } from '../catalog.js';
import { commit, undo, layoutPayload, rulesFor } from '../operations.js';
import { rowsOf, fits, locate, spanAt, gapTargets, rowFits, areaAt, areaFullAt, AREAS } from './model.js';
import { rowName, targetWords, movedBesideSideWords, leftContentWords, fullWords, AREA_NAMES } from './words.js';
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
export const targetKey = (t) => JSON.stringify([t.drop, t.before ?? null, t.rowId ?? null, t.column ?? null, t.side ?? null, t.group ?? null, t.area ?? null]);

/**
 * Every spot an item may be dropped on, in board order. A section: the
 * gaps between rows (at the start and the end of every area, on a page
 * with areas), each place in a column, the left and right edge of a
 * full-width section, and each place in the left and right side (an empty
 * side is one place, and a drop there turns the side on), kept to those it
 * fits (see fits()); a page without areas offers no side, and a locked
 * section no spot at all. A row: every gap but the two around it, kept to
 * those its sections fit.
 */
export function targetsFor(content, item, rules) {
  const layout = rules.layout;
  const rows = rowsOf(content, layout, rules);
  if (item?.kind === 'row') {
    if (!rows.some((r) => r.id === item.rowId)) return [];
    return gapTargets(rows).filter((t) => rowFits(content, item.rowId, t, rules));
  }
  if (item?.kind !== 'section') return [];
  const all = gapTargets(rows);
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
    list.forEach((sid) => all.push({ drop: 'side', side, before: sid }));
    all.push({ drop: 'side', side, before: null });
  });
  return all.filter((t) => fits(content, item.sid, t, rules));
}

/**
 * The gap a full-width row goes to with Alt and up or down: one row up or
 * down within its area, and at the edge of an area into the next one (up
 * from the first row of Content to the end of Top, down from the last row
 * of Top to the start of Content, and so on). Null at the top or bottom
 * of the page. `rows` carry their area (rowsOf with rules).
 */
export function rowStep(rows, index, up) {
  const r = rows[index];
  if (!r) return null;
  const area = r.area ?? null;
  if (!area) {
    if (up) return index > 0 ? { drop: 'gap', area: null, before: rows[index - 1].id } : null;
    return index < rows.length - 1 ? { drop: 'gap', area: null, before: rows[index + 2]?.id ?? 'end' } : null;
  }
  const inArea = (i) => rows[i]?.area === area;
  const rank = AREAS.indexOf(area);
  if (up) {
    if (inArea(index - 1)) return { drop: 'gap', area, before: rows[index - 1].id };
    return rank > 0 ? { drop: 'gap', area: AREAS[rank - 1], before: 'end' } : null;
  }
  if (inArea(index + 1)) return { drop: 'gap', area, before: inArea(index + 2) ? rows[index + 2].id : 'end' };
  const next = AREAS[rank + 1];
  return next ? { drop: 'gap', area: next, before: rows.find((x) => x.area === next)?.id ?? 'end' } : null;
}

/**
 * The spot Alt plus an arrow key moves a section to, or null. Up and down
 * move one place within a column or side, then out of it into the gap
 * above or below its row, in the row's area; from the top of a side into
 * the gap at the start of Content, from its end into the gap at the end
 * of Content. A full-width section moves its row (see rowStep()). Left
 * and right move to the end of the nearest column in that direction it
 * fits; past the first column (or from a full-width row) Left goes to the
 * end of the left side, past the last Right goes to the right side, when
 * the section fits there. With `loose`, the step is answered whether or
 * not the section fits it, so a refusal can name the reason.
 */
export function keyTarget(content, sid, arrow, rules, { loose = false } = {}) {
  const layout = rules.layout;
  const at = locate(content, sid, layout);
  if (!at) return null;
  const rows = rowsOf(content, layout, rules);
  const up = arrow === 'ArrowUp';
  const down = arrow === 'ArrowDown';
  const sideways = arrow === 'ArrowLeft' || arrow === 'ArrowRight';
  if (!up && !down && !sideways) return null;
  const ok = (t) => (t && (loose || fits(content, sid, t, rules)) ? t : null);

  const intoSide = () => ok({ drop: 'side', side: arrow === 'ArrowLeft' ? 'left' : 'right', before: null });

  if (at.where === 'side') {
    if (sideways) return null;
    const list = content.sides[at.side];
    const index = at.index + (up ? -1 : 1);
    // The sides stand beside Content: out of one is the start or the end of Content.
    const area = rows.some((r) => r.area) ? 'content' : null;
    if (index < 0) return ok({ drop: 'gap', area, before: rows.find((r) => (r.area ?? null) === area)?.id ?? 'end' });
    if (index >= list.length) return ok({ drop: 'gap', area, before: 'end' });
    return ok({ drop: 'side', side: at.side, before: up ? list[index] : list[index + 1] ?? null });
  }

  const ri = rows.findIndex((r) => r.id === at.rowId);
  const r = rows[ri];
  if (r.split === '12') {
    if (sideways) return intoSide();
    return ok(rowStep(rows, ri, up));
  }

  if (sideways) {
    const step = arrow === 'ArrowLeft' ? -1 : 1;
    for (let c = at.column + step; c >= 0 && c < r.columns.length; c += step) {
      const t = ok({ drop: 'column', rowId: r.id, column: c, before: null });
      if (t) return t;
    }
    return intoSide();
  }
  const column = r.columns[at.column];
  const index = at.index + (up ? -1 : 1);
  if (index >= 0 && index < column.length) {
    return ok({ drop: 'column', rowId: r.id, column: at.column, before: up ? column[index] : column[index + 1] ?? null });
  }
  const area = r.area ?? null;
  const next = rows[ri + 1];
  return ok({ drop: 'gap', area, before: up ? r.id : (next && (next.area ?? null) === area ? next.id : 'end') });
}

const ARROW_KEYS = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

/*
 * The header and footer bands: a list of full-width sections each, on
 * every page. A section in one moves only within it, never into the
 * other band or the page. `order` is the band document's section order.
 */

/**
 * Every place in its band a section may be dropped on: all but the two
 * around where it is now. A locked section (the Header, the Footer) has
 * none: it stays, and the other sections of its band move around it.
 */
export function bandTargets(group, order, sid, locked = false) {
  const index = order.indexOf(sid);
  if (index < 0 || locked) return [];
  return [...order, null]
    .filter((before, k) => k !== index && k !== index + 1)
    .map((before) => ({ drop: 'band', group, before }));
}

/** The place Alt and up or down moves a band section to, or null at either end and for a locked one. */
export function bandKeyTarget(group, order, sid, arrow, locked = false) {
  const index = order.indexOf(sid);
  if (index < 0 || locked) return null;
  if (arrow === 'ArrowUp') return index > 0 ? { drop: 'band', group, before: order[index - 1] } : null;
  if (arrow === 'ArrowDown') return index < order.length - 1 ? { drop: 'band', group, before: order[index + 2] ?? null } : null;
  return null;
}

/** The index in the band's order a section dropped before `before` (null: the end) ends up at. */
export function bandIndex(order, sid, before) {
  const rest = order.filter((id) => id !== sid);
  return before === null ? rest.length : rest.indexOf(before);
}

/**
 * Where a held item of a vertical list goes: the number of the other
 * items whose middle is above the finger. `mids` are the middles of every
 * item, the held one (at `from`) included.
 */
export function listDropIndex(mids, y, from) {
  return mids.filter((mid, i) => i !== from && mid < y).length;
}

/** The arrows Alt moves a section by from where it is now: only those that lead somewhere. */
export function keyMoves(content, sid, rules) {
  return ARROW_KEYS.filter((arrow) => keyTarget(content, sid, arrow, rules) !== null);
}

/**
 * The arrows Alt moves a row of columns by: up below the first row, down
 * above the last, across the edges of the areas (see rowStep()).
 */
export function rowKeyMoves(rows, rowId) {
  const index = rows.findIndex((r) => r.id === rowId);
  if (index < 0) return [];
  return [rowStep(rows, index, true) ? 'ArrowUp' : null, rowStep(rows, index, false) ? 'ArrowDown' : null].filter(Boolean);
}

/* ------------------------------------------------------------------------
   Making the move
   ------------------------------------------------------------------------ */

const template = () => groupHandle('template');
const contentNow = () => doc(template())?.content || null;

function itemName(item) {
  const content = contentNow();
  if (item.kind === 'section' || item.kind === 'band') return labelOf(item.sid);
  const r = content ? rowsOf(content, layoutPayload()).find((x) => x.id === item.rowId) : null;
  return r ? `The row of ${rowName(r)}` : 'The row';
}

/**
 * Commits a move as one operation. A section alone in a full-width row
 * moves with its row, so the row keeps its settings.
 */
export function moveItem(item, target) {
  if (item.kind === 'band') return moveInBand(item, target);
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
    op = { t: 'row-move', doc: handle, rowId: item.rowId, area: target.area ?? null, before: target.before };
  } else {
    const at = locate(content, item.sid, layoutPayload());
    const own = at?.where === 'row' ? rowsOf(content, layoutPayload()).find((r) => r.id === at.rowId) : null;
    op = own?.split === '12' && target.drop === 'gap'
      ? { t: 'row-move', doc: handle, rowId: own.id, area: target.area ?? null, before: target.before, name }
      : { t: 'place', doc: handle, sid: item.sid, target };
  }
  commit(op);
  if (!op.prior) return false;
  const left = (op.left || []).filter((m) => locateSection(m.sid)).map((m) => ({ name: labelOf(m.sid), area: m.area }));
  const text = `${name} moved ${words}.${movedWords(op.moved)}${leftContentWords(left)}`;
  toast(text, { action: { label: 'Undo', run: undo }, timeout: op.moved?.length || left.length ? 8000 : undefined });
  announce(text);
  return true;
}

/* The sections a new side pushed out of their columns, in a sentence; empty when none. */
function movedWords(ids) {
  return movedBesideSideWords((ids || []).filter((sid) => locateSection(sid)).map((sid) => labelOf(sid)));
}

const bandOrder = (group) => doc(group)?.content?.order || [];

/* Whether a band section is locked there (the Header, the Footer). */
const lockedInBand = (group, sid) => isLocked(sectionDef(doc(group)?.content?.sections?.[sid]?.type));

/* Where a band section goes, in words. */
function bandWords(target) {
  const band = target.group === 'header' ? 'header' : 'footer';
  if (target.before === null) return `to the end of the ${band}`;
  return `above ${labelOf(target.before)}, in the ${band}`;
}

/** Moves a header or footer section within its band, as one undo step. */
function moveInBand(item, target) {
  const order = bandOrder(item.group);
  if (!target || target.group !== item.group || !bandTargets(item.group, order, item.sid, lockedInBand(item.group, item.sid)).some((t) => targetKey(t) === targetKey(target))) return false;
  const from = order.indexOf(item.sid);
  const to = bandIndex(order, item.sid, target.before);
  commit({ t: 'move', doc: item.group, sid: item.sid, from, to });
  const text = `${labelOf(item.sid)} moved ${bandWords(target)}.`;
  toast(text, { action: { label: 'Undo', run: undo } });
  announce(text);
  return true;
}

/** The arrows Alt moves a band section by. */
export function bandKeyMoves(group, sid) {
  const order = bandOrder(group);
  const locked = lockedInBand(group, sid);
  return ['ArrowUp', 'ArrowDown'].filter((arrow) => bandKeyTarget(group, order, sid, arrow, locked));
}

/* ------------------------------------------------------------------------
   Keyboard
   ------------------------------------------------------------------------ */

/** Alt plus up or down on a header or footer section moves it within its band. */
export function keyMoveBand(event, group, sid) {
  if (!event.altKey || event.ctrlKey || event.metaKey || !ARROWS.includes(event.key)) return false;
  event.preventDefault();
  event.stopPropagation();
  const band = group === 'header' ? 'header' : 'footer';
  if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    announce(`${labelOf(sid)} stays in the ${band}: it moves up or down only.`);
    return false;
  }
  const target = bandKeyTarget(group, bandOrder(group), sid, event.key, lockedInBand(group, sid));
  if (!target) {
    // Past the band's edge toward the page: a band section never moves onto it.
    const towardPage = (group === 'header') === (event.key === 'ArrowDown');
    if (towardPage) {
      say(refusalWords({ kind: 'band', group, sid }, { zone: 'page' }));
      return false;
    }
    announce(`${labelOf(sid)} cannot move ${event.key === 'ArrowUp' ? 'up' : 'down'}: it is ${event.key === 'ArrowUp' ? 'first' : 'last'} in the ${band}.`);
    return false;
  }
  const moved = moveItem({ kind: 'band', group, sid }, target);
  if (moved) refocus(`#board .band--${group} .piece[data-sid="${CSS.escape(sid)}"]`);
  return moved;
}

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
  const rules = rulesFor(template());
  const target = keyTarget(content, sid, event.key, rules);
  if (!target) {
    const words = keyRefusal(content, sid, event.key, rules);
    if (words) {
      say(words);
      return false;
    }
    announce(`${labelOf(sid)} cannot move ${{ ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right' }[event.key]} from here.`);
    return false;
  }
  const moved = moveItem({ kind: 'section', sid }, target);
  if (moved) refocus(`#board .piece[data-sid="${CSS.escape(sid)}"]`);
  return moved;
}

/*
 * Why Alt and an arrow do not move a section, when there is a reason to
 * give: the step is into an area it does not fit (too wide beside the
 * sides, Content only, the area has one already), or past the top or the
 * bottom of the page toward a band that holds its type. Null otherwise.
 */
function keyRefusal(content, sid, arrow, rules) {
  const step = keyTarget(content, sid, arrow, rules, { loose: true });
  if (step) return step.drop === 'gap' ? refusalWords({ kind: 'section', sid }, step) : null;
  if (arrow !== 'ArrowUp' && arrow !== 'ArrowDown') return null;
  const group = arrow === 'ArrowUp' ? 'header' : 'footer';
  const def = sectionDef(content.sections?.[sid]?.type);
  return (def?.shared_groups || []).includes(group) ? refusalWords({ kind: 'section', sid }, { zone: 'band', group }) : null;
}

/* A refusal is shown and said: a toast for the eye, the live region for a screen reader. */
function say(words) {
  if (!words) return;
  toast(words, { tone: 'error' });
  announce(words);
}

/**
 * Alt and an arrow on a locked section (the page content, the Header, the
 * Footer): nothing moves, and the message says it stays where it is.
 */
export function keyStay(event, sid) {
  if (!event.altKey || event.ctrlKey || event.metaKey || !ARROWS.includes(event.key)) return false;
  event.preventDefault();
  event.stopPropagation();
  const at = locateSection(sid);
  if (!at) return false;
  say(at.group === 'template' ? refusalWords({ kind: 'section', sid }) : refusalWords({ kind: 'band', group: at.group, sid }));
  return false;
}

/** Alt plus up or down on a row's tab moves the row. */
export function keyMoveRow(event, rowId) {
  if (!event.altKey || event.ctrlKey || event.metaKey || !ARROWS.includes(event.key)) return false;
  event.preventDefault();
  event.stopPropagation();
  const content = contentNow();
  if (!content || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return false;
  const rows = rowsOf(content, layoutPayload(), rulesFor(template()));
  const index = rows.findIndex((r) => r.id === rowId);
  const step = index < 0 ? null : rowStep(rows, index, event.key === 'ArrowUp');
  if (!step) {
    announce(`${index < 0 ? 'The row' : `The row of ${rowName(rows[index])}`} cannot move ${event.key === 'ArrowUp' ? 'up' : 'down'} from here.`);
    return false;
  }
  const moved = moveItem({ kind: 'row', rowId }, step);
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
  if (active) announce('Move canceled.');
}

function begin() {
  const content = contentNow();
  if (!content && D.item.kind !== 'band') {
    cancel();
    return;
  }
  D.active = true;
  const spots = D.item.kind === 'band'
    ? bandTargets(D.item.group, bandOrder(D.item.group), D.item.sid, lockedInBand(D.item.group, D.item.sid))
    : targetsFor(content, D.item, rulesFor(template()));
  D.keys = new Set(spots.map(targetKey));
  document.body.classList.add('is-dragging');
  const iconName = D.item.kind === 'row' ? 'grip' : sectionIcon(locateSection(D.item.sid)?.data?.type);
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
  else say(refused);
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
  // A band section stays in its band; nothing on the page goes into a band.
  const band = D.item.kind === 'band' ? D.item.group : null;
  $$('.band', board).forEach((el) => el.classList.toggle('is-blocked', el.dataset.group !== band));
  $('.sheet', board)?.classList.toggle('is-blocked', Boolean(band));
  if (D.item.kind !== 'section') return;
  const holds = (el) => Boolean($(`.piece[data-sid="${CSS.escape(D.item.sid)}"]`, el));
  const lit = (el) => $$('[data-target]', el).some((x) => x.classList.contains('is-valid'));
  // An area the section fits nowhere in fades as a whole, with the reason; its columns then need no mark of their own.
  $$('.area[data-area]', board).forEach((el) => el.classList.toggle('is-blocked', !lit(el) && !holds(el)));
  $$('.row--multi .col, .side', board).forEach((col) => {
    col.classList.toggle('is-blocked', !col.closest('.area.is-blocked') && !lit(col) && !holds(col));
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

/** "Bottom already has an announcement bar." when the target's area is full for the section's type, else null. */
function fullAreaWords(content, sid, target, rules) {
  if (!target || !areaFullAt(content, sid, target, rules)) return null;
  return fullWords(sectionDef(content.sections?.[sid]?.type), { area: areaAt(content, target, rules) });
}

/** Why a held item cannot go where the pointer is: a faded band, page, area, column or side. */
function refusal(el) {
  if (el.matches('.band')) return refusalWords(D.item, { zone: 'band', group: el.dataset.group });
  if (el.matches('.sheet')) return refusalWords(D.item, { zone: 'page' });
  if (el.matches('.area')) return refusalWords(D.item, { drop: 'gap', area: el.dataset.area, before: 'end' });
  const side = el.classList.contains('side');
  const t = targetOf($('[data-target]', el) || el);
  return refusalWords(D.item, t) || (side ? 'That side is full.' : 'That column is full.');
}

/*
 * How many sides narrow Content, as words: "the side" or "the sides".
 * `sid`, a section about to leave a side, does not count.
 */
function sidesWord(content, sid) {
  const filled = SIDES.filter((which) => (content.sides?.[which] || []).some((id) => id !== sid && content.sections?.[id]));
  return filled.length === 2 ? 'the sides' : 'the side';
}

/**
 * Why an item may not go to a place, in a sentence, or null when there
 * is nothing to say. `place` is a spot of the board, or a zone: `{zone:
 * 'band', group}` for the header or footer band, `{zone: 'page'}` for the
 * page between them. Without a place, why the item never moves at all.
 *   item  {kind: 'section', sid}, {kind: 'band', group, sid} or {kind: 'row', rowId}
 */
export function refusalWords(item, place = null) {
  if (item?.kind === 'band') {
    const name = labelOf(item.sid);
    if (lockedInBand(item.group, item.sid)) return `${name} is fixed in the ${item.group} band.`;
    if (place?.zone === 'band' && place.group !== item.group) return `${name} stays in the ${item.group} band.`;
    if (place?.zone === 'page') return `${name} shows on every page. To show a bar on this page only, add one here.`;
    return null;
  }
  const content = contentNow();
  if (!content) return null;
  if (item?.kind === 'row') return place?.zone === 'band' ? `${itemName(item)} is on this page only.` : null;
  const sid = item?.sid;
  const section = content.sections?.[sid];
  if (!section) return null;
  const rules = rulesFor(template());
  const name = labelOf(sid);
  if (rules.isMain(section)) return `${name} is the page content. It stays where it is.`;
  if (place?.zone === 'band') {
    const shared = (sectionDef(section.type)?.shared_groups || []).includes(place.group);
    return shared
      ? `${name} is on this page only. To show a bar on every page, add one to the ${place.group}.`
      : `${name} is on this page only. The ${place.group} holds what every page shares.`;
  }
  if (!place || place.zone) return null;
  const area = areaAt(content, place, rules);
  if (rules.ownRow(section) && ['column', 'side', 'beside'].includes(place.drop)) return `${name} needs a row of its own.`;
  if (area && area !== 'content' && rules.contentOnly(section)) return `${name} goes beside the page content, not in ${AREA_NAMES[area]}.`;
  const full = fullAreaWords(content, sid, place, rules);
  if (full) return full;
  const need = rules.minSpan(section);
  if (need <= spanAt(content, place, rules, sid)) return null;
  if (place.drop === 'side') return `${name} is too wide for a side.`;
  // Wide enough on a page without sides: the sides are what narrow it.
  const whole = spanAt(content, place, { ...rules, sidesAllowed: false }, sid);
  if (area === 'content' && need <= whole) return `${name} needs more width than Content has beside ${sidesWord(content, sid)}.`;
  return place.drop === 'gap' ? null : `${name} needs a wider column than that one.`;
}

function pickTarget() {
  const board = $('#board');
  // The board was drawn again while the item was held: light the new spots.
  if (board && board.firstElementChild !== D.marked) markTargets();
  $$('#board .is-over').forEach((el) => el.classList.remove('is-over'));
  D.over = null;
  // The innermost faded place under the pointer says why.
  const blocked = $$('#board .is-blocked').filter((el) => inside(el, D.x, D.y)).pop();
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
  const words = D.over && (D.over.drop === 'band' ? bandWords(D.over) : targetWords(D.over, D.item.kind === 'section' ? D.item.sid : null));
  D.where.textContent = words ? `Goes ${words}` : 'Move to a lit spot';
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

/* ------------------------------------------------------------------------
   A list in the drawer (a section's blocks) by touch: press its grip a
   moment, then drag it up or down. A mouse or pen drags it natively.
   ------------------------------------------------------------------------ */

let L = null;

/**
 * Starts watching a finger on the grip of a list item.
 *   items   () => the list's items, in order
 *   from    the index of the item held
 *   onDrop  (to) => called with the item's new index when it moved
 * Answers whether the press is watched (a finger only).
 */
export function startListDrag(event, { items, from, onDrop }) {
  if (L || D || event.pointerType !== 'touch' || !event.isPrimary) return false;
  const held = items()[from];
  if (!held) return false;
  L = { items, from, onDrop, held, sx: event.clientX, sy: event.clientY, y: event.clientY, active: false, to: from, press: createPress({ delay: PRESS_DELAY }) };
  L.press.down(performance.now());
  held.classList.add('is-pressed');
  L.timer = window.setTimeout(() => {
    if (L && !L.active && L.press.move(performance.now(), 0, L.y - L.sy) === 'drag') liftList();
  }, PRESS_DELAY);
  window.addEventListener('pointermove', onListMove);
  window.addEventListener('pointerup', onListUp);
  window.addEventListener('pointercancel', endList);
  return true;
}

function liftList() {
  L.active = true;
  navigator.vibrate?.(10);
  L.held.classList.add('is-dragging');
}

function onListMove(event) {
  if (!L) return;
  L.y = event.clientY;
  if (!L.active) {
    const decided = L.press.move(performance.now(), event.clientX - L.sx, L.y - L.sy);
    if (decided === 'scroll') {
      endList();
      return;
    }
    if (decided !== 'drag') return;
    liftList();
  }
  event.preventDefault();
  const list = L.items();
  const mids = list.map((el) => {
    const r = el.getBoundingClientRect();
    return r.top + r.height / 2;
  });
  // The held item follows the finger; its own middle counts where it started.
  mids[L.from] -= L.y - L.sy;
  L.to = listDropIndex(mids, L.y, L.from);
  L.held.style.translate = `0 ${L.y - L.sy}px`;
}

function onListUp() {
  if (!L) return;
  const { active, to, from, onDrop } = L;
  endList();
  if (!active) return;
  lastDragEnd = Date.now();
  if (to !== from) onDrop(to);
}

function endList() {
  if (!L) return;
  window.clearTimeout(L.timer);
  L.held.classList.remove('is-pressed', 'is-dragging');
  L.held.style.translate = '';
  window.removeEventListener('pointermove', onListMove);
  window.removeEventListener('pointerup', onListUp);
  window.removeEventListener('pointercancel', endList);
  L = null;
}

/*
 * While a finger holds an item, the page must not scroll under it, nor
 * open the browser's menu for a long press. The listener is there from the
 * start: a browser decides whether a touch may scroll when it begins.
 */
export function initDrag() {
  document.addEventListener('touchmove', (event) => { if (D?.active || L?.active) event.preventDefault(); }, { passive: false });
  document.addEventListener('contextmenu', (event) => { if (D?.touch || L) event.preventDefault(); });
}
