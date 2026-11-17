/*
 * The section picker: one window to choose a section, for adding at a
 * spot of the board and for replacing a section in its own spot. Section
 * types are pictured tiles in groups, with a search over type and layout
 * names; choosing a tile shows that type's layouts beside the tiles, and
 * one click on a layout adds the section there and opens its settings.
 * A type with a single layout adds on the tile itself.
 *
 * Types that cannot go in the spot keep their place in their group,
 * greyed, with the reason on the tile; so does a layout too wide for it.
 * From the Add line between rows, "A row of columns" comes first and is
 * chosen at open, its layouts being the splits.
 *
 * Replace mode puts a section of another type in the same spot, with its
 * own defaults and the old section's color scheme. The type in the spot
 * is greyed "Now here": changing its layout belongs in its settings.
 *
 * The window keeps the keyboard inside it: the search has focus at open,
 * Tab cycles through it, the arrow keys move between tiles by where they
 * sit, Enter goes into the layouts and adds, Escape closes and puts focus
 * back where the window was opened. On a phone it is one full-height
 * sheet: the tiles, then a type's layouts with a way back.
 */

import { $, $$, h, icon, schematic, toast, announce } from '../dom.js';
import { S, ui, doc, groupHandle, labelOf, sec } from '../state.js';
import { sectionTypes, sectionDef, allowedIn, variantDrawing } from '../catalog.js';
import { rulesFor, layoutPayload, undo } from '../operations.js';
import { addSection, addRowAt, replaceSection } from '../actions.js';
import { select, isPhone } from '../selection.js';
import { rowsOf, spanAt, roomOf } from './model.js';
import {
  addableTypes, pickerGroups, matches, choicesOf, layoutsFor, pillWords, needsWords, replacedWords, wholeSpan, NARROW_BESIDE_SIDES,
  ROW_TILE, TOO_WIDE, NOW_HERE, ALREADY_THERE,
} from './choices.js';
import { pickerWhere, splitOf, movedBesideSideWords } from './words.js';
import { targetKey } from './drag.js';

const ROW_NAME = 'A row of columns';
const ROW_WORDS = 'a row of columns';

/* What the window shows while it is open:
   { mode: 'add' | 'replace', target, sid, span, type, query, step } */
let P = null;

const dialog = () => $('#section-picker');
const template = () => groupHandle('template');
const content = () => doc(template())?.content || { order: [], sections: {} };

/** The spot the window adds at while it is open, for the board to mark. */
export const pickerSpot = () => (P?.mode === 'add' ? P.target : null);

/** The section the window replaces while it is open, for the board to mark. */
export const pickerReplacing = () => (P?.mode === 'replace' ? P.sid : null);

export const isPickerOpen = () => Boolean(P);

/** The section types the page open now may hold; main sections are never added. */
export function availableTypes() {
  const page = S.page || {};
  return Object.fromEntries(Object.entries(sectionTypes())
    .filter(([, def]) => !def.main && allowedIn(def, page.pageType || page.handle, Boolean(page.custom), Boolean(page.extension))));
}

/* ------------------------------------------------------------------------
   Opening and closing
   ------------------------------------------------------------------------ */

/**
 * Opens the window to add at a spot of the board (`{target}`, a row model
 * target) or to replace a section (`{replace: sid}`).
 */
export function openPicker(spot) {
  const dlg = dialog();
  if (!dlg || !S.loaded || !spot) return;
  const rules = rulesFor(template());
  if (spot.replace) {
    const s = sec(spot.replace);
    const room = roomOf(content(), spot.replace, rules);
    if (!s || room === null) return;
    P = { mode: 'replace', sid: spot.replace, span: room, whole: roomOf(content(), spot.replace, { ...rules, sidesAllowed: false }), type: null };
  } else {
    if (!spot.target) return;
    P = { mode: 'add', target: spot.target, span: spanAt(content(), spot.target, rules), whole: wholeSpan(content(), spot.target, rules), type: spot.target.drop === 'gap' ? ROW_TILE : null };
  }
  P.query = '';
  P.step = 'types';
  ui.renderBoard?.();
  render();
  if (!dlg.open) dlg.showModal();
  window.requestAnimationFrame(() => {
    if (!P) return;
    if (isPhone()) $('.spick__title', dlg)?.focus({ preventScroll: true });
    else $('.spick__search input', dlg)?.focus({ preventScroll: true });
  });
}

/** Closes the window; focus goes back to the Add or Change that opened it, unless `focus` is false. */
export function closePicker({ focus = true } = {}) {
  const dlg = dialog();
  const from = P;
  P = null;
  if (dlg?.open) dlg.close();
  dlg?.replaceChildren();
  ui.renderBoard?.();
  if (focus && from) openerOf(from)?.focus({ preventScroll: true });
}

/* The control that opened the window, found again after the board was drawn anew. */
function openerOf(from) {
  if (from.mode === 'replace') return $(`#board .piece-wrap[data-sid="${CSS.escape(from.sid)}"] .piece__change`);
  const key = targetKey(from.target);
  const spot = $$('#board [data-target]').find((el) => {
    try {
      return targetKey(JSON.parse(el.dataset.target)) === key;
    } catch {
      return false;
    }
  });
  if (!spot) return null;
  return spot.matches('button') ? spot : $('button', spot);
}

/* ------------------------------------------------------------------------
   What is offered
   ------------------------------------------------------------------------ */

function states() {
  const rules = rulesFor(template());
  const types = availableTypes();
  const now = P.mode === 'replace' ? sec(P.sid)?.type : null;
  return addableTypes(content(), P.target || null, types, rules, { span: P.span, now, whole: P.whole });
}

const typeName = (type) => (type === ROW_TILE ? ROW_NAME : sectionDef(type)?.label || type);

function stateOf(type) {
  if (type === ROW_TILE) return { type, disabled: false };
  return states().find((s) => s.type === type) || { type, disabled: true };
}

const rowSplits = () => Object.entries(layoutPayload()?.splits || {}).filter(([, s]) => s.parts.length > 1);

const rowMatches = (q) => !q || ROW_WORDS.includes(q) || rowSplits().some(([, s]) => s.label.toLowerCase().includes(q));

/** The type's layouts with their fit, or the splits for the row tile. */
function layoutsOf(type) {
  if (type === ROW_TILE) return rowSplits().map(([split, s]) => ({ label: s.label, split, parts: s.parts, fits: true }));
  return layoutsFor(type, sectionDef(type), P.span, rulesFor(template()));
}

function defaultVariant(def) {
  return (def?.settings || []).find((s) => s.key === 'variant')?.default ?? def?.variants?.[0]?.value ?? null;
}

const drawing = (type, variant) => schematic(variantDrawing(type, variant ?? defaultVariant(sectionDef(type))) || 'm0,0,64,40');

/* ------------------------------------------------------------------------
   Drawing the window
   ------------------------------------------------------------------------ */

function title() {
  if (P.mode === 'replace') return `Replace ${labelOf(P.sid)}`;
  return P.target.drop === 'gap' ? 'Add a row or a section' : 'Add a section';
}

function render() {
  const dlg = dialog();
  const replace = P.mode === 'replace';
  const where = pickerWhere(replace ? { replace: P.sid } : { target: P.target });
  const lost = replace ? replacedWords(sec(P.sid), sectionDef(sec(P.sid)?.type)) : null;
  const head = h('header', { class: 'spick__head' },
    h('div', { class: 'spick__heading' },
      h('h2', { class: 'spick__title', id: 'spick-title', tabindex: '-1', text: title() }),
      h('p', { class: 'spick__where', id: 'spick-where' },
        icon(replace ? 'replace' : 'map-pin', 'icon--sm'),
        h('span', null, ...where.map((w, i) => (i % 2 ? h('strong', { text: w }) : w))))),
    h('button', { type: 'button', class: 'icon-btn spick__close', 'aria-label': 'Close', title: 'Close (Esc)', onclick: () => closePicker() }, icon('close')));
  const warn = lost ? h('p', { class: 'spick__warn', id: 'spick-warn' }, icon('alert', 'icon--sm'),
    h('span', null, `The ${lost} in `, h('strong', { text: labelOf(P.sid) }), ' will be replaced.')) : null;
  const search = h('div', { class: 'spick__search' }, icon('search', 'icon--sm'),
    h('input', {
      class: 'input', type: 'search', placeholder: 'Search sections', 'aria-label': 'Search sections',
      value: P.query, autocomplete: 'off', 'aria-controls': 'spick-list',
      oninput: (e) => {
        P.query = e.target.value;
        renderTiles();
      },
    }));
  const types = h('section', { class: 'spick__types', 'aria-label': 'Section types' }, search, h('div', { class: 'spick__list', id: 'spick-list' }));
  const pane = h('section', { class: 'spick__pane', id: 'spick-pane', 'aria-label': 'Layouts' });
  // Only the chosen type's name is announced as the choice moves, never the whole list of layouts.
  const status = h('p', { class: 'visually-hidden', id: 'spick-status', role: 'status' });
  dlg.dataset.mode = P.mode;
  dlg.setAttribute('aria-labelledby', 'spick-title');
  dlg.setAttribute('aria-describedby', warn ? 'spick-where spick-warn' : 'spick-where');
  dlg.replaceChildren(h('div', { class: 'spick__frame' }, head, warn,
    h('div', { class: 'spick__body', dataset: { step: P.step } }, types, pane), status));
  renderTiles();
  renderPane();
}

function renderTiles() {
  const list = $('#spick-list');
  if (!list) return;
  const q = P.query.trim().toLowerCase();
  const types = availableTypes();
  const shown = states().filter((s) => matches(types[s.type], q));
  const groups = pickerGroups(shown, types);
  if (P.mode === 'add' && P.target.drop === 'gap' && rowMatches(q)) {
    groups.unshift({ id: 'rows', name: 'Rows', types: [{ type: ROW_TILE, disabled: false }] });
  }
  if (!groups.length) {
    list.replaceChildren(h('div', { class: 'spick__none' },
      h('p', { class: 'spick__none-title' }, 'No section matches ', h('strong', { text: `“${P.query.trim()}”` })),
      h('button', {
        type: 'button', class: 'btn btn--secondary btn--sm',
        onclick: () => {
          P.query = '';
          const input = $('.spick__search input');
          input.value = '';
          renderTiles();
          input.focus();
        },
      }, 'Clear search')));
    announce(`No section matches ${P.query.trim()}`);
    return;
  }
  const visible = groups.flatMap((g) => g.types.map((t) => t.type));
  // A chosen type the search hides is let go, so the layouts shown always belong to a tile in view.
  if (P.type && !visible.includes(P.type) && !isPhone()) {
    P.type = null;
    renderPane();
  }
  const tabStop = visible.includes(P.type) ? P.type : visible[0];
  list.replaceChildren(...groups.map((g) => h('div', { class: 'spick__group', role: 'group', 'aria-labelledby': `spick-g-${g.id}` },
    h('h3', { class: 'spick__group-title', id: `spick-g-${g.id}`, text: g.name }),
    h('div', { class: 'spick__tiles' }, ...g.types.map((state) => tile(state, state.type === tabStop))))));
}

function splitPicture(parts) {
  const el = h('span', { class: 'splitpic', 'aria-hidden': 'true' });
  parts.forEach((p) => el.append(h('span', { class: 'splitpic__col', style: { gridColumn: `span ${p}` } })));
  return el;
}

function tilePicture(type) {
  if (type === ROW_TILE) {
    const pic = h('span', { class: 'stile__splits', 'aria-hidden': 'true' });
    ['6-6', '4-4-4', '8-4'].filter((id) => layoutPayload()?.splits?.[id]).forEach((id) => pic.append(splitPicture(splitOf(id).parts)));
    return pic;
  }
  return drawing(type, choicesOf(sectionDef(type))[0]?.variant);
}

let tileSeq = 0;

function tile(state, tabStop) {
  const { type } = state;
  const count = type === ROW_TILE ? rowSplits().length : choicesOf(sectionDef(type)).length;
  const pill = pillWords(state);
  tileSeq += 1;
  const id = `stile-${tileSeq}`;
  return h('button', {
    type: 'button', class: `stile${state.disabled ? ' is-grey' : ''}`, id, dataset: { type },
    tabindex: tabStop ? '0' : '-1',
    'aria-pressed': String(P.type === type), 'aria-disabled': state.disabled ? 'true' : null,
    'aria-describedby': pill ? `${id}-why` : null,
    onclick: () => chooseType(type, { act: true }),
    onkeydown: (e) => tileKeys(e, type),
  },
  h('span', { class: 'stile__pic' }, tilePicture(type)),
  h('span', { class: 'stile__text' },
    h('span', { class: 'stile__name', text: typeName(type) }),
    pill ? h('span', { class: 'stile__pill', id: `${id}-why`, text: pill }) : h('span', { class: 'stile__meta', text: `${count} ${count === 1 ? 'layout' : 'layouts'}` })));
}

const tileEl = (type) => $(`#spick-list .stile[data-type="${CSS.escape(type)}"]`);

/**
 * Chooses a type: its layouts show beside the tiles (on a phone, instead
 * of them). `act` is a click or Enter: a type with one layout that fits
 * is then added at once.
 */
function chooseType(type, { act = false, focusCard = false } = {}) {
  if (!P) return;
  if (act && type !== ROW_TILE && !stateOf(type).disabled) {
    const layouts = layoutsOf(type);
    if (layouts.length === 1 && layouts[0].fits) {
      pick(type, layouts[0]);
      return;
    }
  }
  if (P.type !== type) {
    const status = $('#spick-status');
    if (status) status.textContent = typeName(type);
  }
  P.type = type;
  $$('#spick-list .stile').forEach((el) => {
    el.setAttribute('aria-pressed', String(el.dataset.type === type));
    el.tabIndex = el.dataset.type === type ? 0 : -1;
  });
  const toPane = act && isPhone();
  if (toPane) {
    P.step = 'layouts';
    $('.spick__body').dataset.step = 'layouts';
  }
  renderPane();
  if (toPane) $('.spick__pane-title')?.focus({ preventScroll: true });
  if (focusCard) $('#spick-pane .lcard[tabindex="0"]:not([aria-disabled])')?.focus();
}

/* Arrow keys move between tiles by where they sit; Home and End go to the ends; Enter chooses. */
function tileKeys(e, type) {
  const tiles = $$('#spick-list .stile');
  const i = tiles.findIndex((el) => el.dataset.type === type);
  let next = null;
  if (e.key === 'ArrowRight') next = tiles[i + 1];
  else if (e.key === 'ArrowLeft') next = tiles[i - 1];
  else if (e.key === 'Home') next = tiles[0];
  else if (e.key === 'End') next = tiles[tiles.length - 1];
  else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') next = tileBelowOrAbove(tiles, i, e.key === 'ArrowDown');
  else if (e.key === 'Enter') {
    e.preventDefault();
    chooseType(type, { act: true, focusCard: !isPhone() });
    return;
  } else return;
  e.preventDefault();
  if (!next) return;
  tiles.forEach((el) => { el.tabIndex = el === next ? 0 : -1; });
  next.focus();
  next.scrollIntoView({ block: 'nearest' });
  // Beside the tiles, the layouts follow the focus; on a phone they open on Enter.
  if (!isPhone()) chooseType(next.dataset.type);
}

function tileBelowOrAbove(tiles, i, down) {
  const r = tiles[i].getBoundingClientRect();
  const cx = r.left + r.width / 2;
  const boxes = tiles.map((el) => ({ el, box: el.getBoundingClientRect() }));
  const candidates = boxes.filter(({ box }) => (down ? box.top >= r.bottom - 2 : box.bottom <= r.top + 2));
  if (!candidates.length) return null;
  const tops = candidates.map(({ box }) => box.top);
  const lineTop = down ? Math.min(...tops) : Math.max(...tops);
  const line = candidates.filter(({ box }) => Math.abs(box.top - lineTop) < 4);
  const distance = ({ box }) => Math.abs(box.left + box.width / 2 - cx);
  return line.reduce((a, b) => (distance(b) < distance(a) ? b : a)).el;
}

function statusWords(type, state) {
  const name = typeName(type);
  if (state.reason === NOW_HERE) return `${name} is in this spot now. Change its layout in its settings.`;
  if (state.reason === ALREADY_THERE) return `${name} is already on this page.`;
  if (state.reason === TOO_WIDE) {
    const words = needsWords(state.need, state.whole ?? null);
    if (words === NARROW_BESIDE_SIDES) return `${name} needs a wider spot: the sides leave this one too narrow.`;
    return `${name} needs a wider spot: ${words.replace('Needs ', '')}.`;
  }
  return null;
}

function renderPane() {
  const pane = $('#spick-pane');
  if (!pane) return;
  if (!P.type) {
    pane.replaceChildren(h('div', { class: 'spick__spot' },
      h('h3', { class: 'spick__pane-title spick__pane-title--quiet', tabindex: '-1', text: P.mode === 'replace' ? 'Stays here' : 'Goes here' }),
      spotMap()));
    return;
  }
  const type = P.type;
  const state = stateOf(type);
  const name = typeName(type);
  const back = h('button', {
    type: 'button', class: 'btn btn--ghost btn--sm spick__back',
    onclick: () => {
      P.step = 'types';
      $('.spick__body').dataset.step = 'types';
      tileEl(type)?.focus({ preventScroll: true });
    },
  }, icon('chevron-left', 'icon--sm'), 'All sections');
  const cards = h('div', { class: `lcards${type === ROW_TILE ? ' lcards--rows' : ''}`, role: 'group', 'aria-label': `Layouts of ${name}` });
  let first = -1;
  layoutsOf(type).forEach((layout, i) => {
    const fits = !state.disabled && layout.fits;
    if (fits && first === -1) first = i;
    const pic = type === ROW_TILE ? h('span', { class: 'lcard__split' }, splitPicture(layout.parts)) : drawing(type, layout.variant);
    const why = !fits && !state.disabled ? needsWords(layout.need, P.whole ?? null) : null;
    cards.append(h('button', {
      type: 'button', class: `lcard${fits ? '' : ' is-grey'}`, 'aria-disabled': fits ? null : 'true',
      tabindex: '-1',
      onclick: () => { if (fits) pick(type, layout); },
      onkeydown: cardKeys,
    }, h('span', { class: 'lcard__pic' }, pic),
    h('span', { class: 'lcard__label', text: layout.label }),
    why ? h('span', { class: 'stile__pill', text: why }) : null));
  });
  const list = $$('.lcard', cards);
  if (list.length) list[Math.max(first, 0)].tabIndex = 0;
  const status = statusWords(type, state);
  pane.replaceChildren(...[
    h('div', { class: 'spick__pane-head' }, back, h('h3', { class: 'spick__pane-title', tabindex: '-1', text: name })),
    status ? h('p', { class: 'spick__status' }, icon('info', 'icon--sm'), status) : null,
    cards].filter(Boolean));
}

/* Arrow keys move between layouts across and down their grid. */
function cardKeys(e) {
  const cards = $$('#spick-pane .lcard');
  const i = cards.indexOf(e.currentTarget);
  const across = Math.max(1, Math.round(cards[0].parentElement.offsetWidth / cards[0].offsetWidth));
  const step = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: across, ArrowUp: -across }[e.key];
  if (!step) return;
  e.preventDefault();
  const next = cards[i + step];
  if (!next) return;
  cards.forEach((el) => { el.tabIndex = el === next ? 0 : -1; });
  next.focus();
}

/** A small drawing of the page with the spot marked: where the section goes, or the one being replaced. */
function spotMap() {
  const c = content();
  const layout = layoutPayload();
  const mark = () => h('div', { class: 'smap__new' }, icon(P.mode === 'replace' ? 'replace' : 'plus', 'icon--xs'));
  const t = P.target || {};
  const sectionEl = (sid) => (sid === P.sid ? mark() : h('span', { class: 'smap__sec' }));
  const rows = h('div', { class: 'smap__rows' });
  const list = layout ? rowsOf(c, layout) : [];
  list.forEach((r) => {
    if (t.drop === 'gap' && t.before === r.id) rows.append(mark());
    const line = h('div', { class: 'smap__row' });
    splitOf(r.split).parts.forEach((part, column) => {
      const col = h('div', { class: 'smap__col', style: { gridColumn: `span ${part}` } });
      (r.columns[column] || []).forEach((sid) => col.append(sectionEl(sid)));
      if (t.drop === 'column' && t.rowId === r.id && t.column === column) col.append(mark());
      line.append(col);
    });
    rows.append(line);
  });
  if (t.drop === 'gap' && t.before === 'end') rows.append(mark());
  const side = (which) => {
    const ids = c.sides?.[which] || [];
    const here = t.drop === 'side' && t.side === which;
    if (!ids.length && !here) return null;
    const el = h('div', { class: 'smap__side' });
    ids.forEach((sid) => {
      if (here && t.before === sid) el.append(mark());
      el.append(sectionEl(sid));
    });
    if (here && (t.before ?? null) === null) el.append(mark());
    return el;
  };
  return h('div', { class: 'smap', 'aria-hidden': 'true' },
    h('div', { class: 'smap__band' }),
    h('div', { class: 'smap__zone' }, side('left'), rows, side('right')),
    h('div', { class: 'smap__band smap__band--foot' }));
}

/* ------------------------------------------------------------------------
   Adding and replacing
   ------------------------------------------------------------------------ */

function focusPiece(sid) {
  window.requestAnimationFrame(() => $(`#board .piece[data-sid="${CSS.escape(sid)}"]`)?.focus({ preventScroll: true }));
}

function openSettings(sid) {
  if (!ui.openDrawer?.({ type: 'section', sid })) select(sid);
  focusPiece(sid);
}

function pick(type, layout) {
  const from = P;
  if (!from) return;
  closePicker({ focus: false });
  if (type === ROW_TILE) {
    const rowId = addRowAt(layout.split, from.target);
    if (!rowId) {
      openerOf(from)?.focus({ preventScroll: true });
      return;
    }
    window.requestAnimationFrame(() => $(`#board .row[data-row-id="${CSS.escape(rowId)}"] .col__empty`)?.focus({ preventScroll: true }));
    return;
  }
  const options = { variant: layout.variant, preset: layout.preset };
  if (from.mode === 'add') {
    const added = addSection(type, { ...options, target: from.target });
    if (!added) {
      openerOf(from)?.focus({ preventScroll: true });
      return;
    }
    const { sid, moved } = added;
    // One message for one action: what was added, and what it moved.
    const text = moved.length
      ? `${labelOf(sid)} added.${movedBesideSideWords(moved)}`
      : `${labelOf(sid)} added. Change its text and photos to your own.`;
    toast(text, { action: { label: 'Undo', run: undo }, timeout: moved.length ? 8000 : 4200 });
    if (moved.length) announce(text);
    openSettings(sid);
    return;
  }
  const oldName = labelOf(from.sid);
  const sid = replaceSection(from.sid, type, options);
  if (!sid) {
    openerOf(from)?.focus({ preventScroll: true });
    return;
  }
  const text = `${oldName} replaced by ${labelOf(sid)}.`;
  toast(text, { action: { label: 'Undo', run: undo }, timeout: 8000 });
  announce(text);
  openSettings(sid);
}

/* ------------------------------------------------------------------------
   The window itself
   ------------------------------------------------------------------------ */

/* Tab and Shift+Tab go round the window's controls and never leave it. */
function trapTab(e) {
  if (e.key !== 'Tab') return;
  const dlg = dialog();
  const stops = $$('button, input, [tabindex="0"]', dlg)
    .filter((el) => el.tabIndex >= 0 && el.getClientRects().length > 0 && !el.closest('[hidden]'));
  if (!stops.length) return;
  const first = stops[0];
  const last = stops[stops.length - 1];
  const at = document.activeElement;
  if (e.shiftKey && (at === first || !dlg.contains(at) || at === $('.spick__title', dlg))) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && at === last) {
    e.preventDefault();
    first.focus();
  }
}

export function initPicker() {
  if (dialog()) return;
  const dlg = h('dialog', { class: 'spick', id: 'section-picker', 'aria-labelledby': 'spick-title' });
  dlg.addEventListener('cancel', (e) => {
    e.preventDefault();
    closePicker();
  });
  // A click on the dimmed page around the window closes it.
  dlg.addEventListener('click', (e) => { if (e.target === dlg) closePicker(); });
  dlg.addEventListener('keydown', trapTab);
  document.body.append(dlg);
  ui.openPicker = openPicker;
}
