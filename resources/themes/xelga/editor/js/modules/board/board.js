/*
 * The layout board: the page drawn in the shape the shopper sees it, at
 * most as wide as the store, inside one frame on a warm canvas. The
 * frame's top bar names the store address the layout serves; inside it
 * stand three groups with a gap between them: the Header band, the page
 * and the Footer band. The bands are shared by every page, so they share
 * one tint and a link mark, and each opens with its reach ("Header · on
 * every page"); the page opens with its own reach ("All 24 products", or
 * "This page only").
 *
 * A page with page content (a product, a category...) has three areas,
 * each named once on a thin rule above it: Top, Content and Bottom. The
 * left and right sides stand beside Content only, always drawn at a
 * side's full width, empty or not, so it is plain where they start and
 * stop. A page without page content (Home, the cart) is just rows. Each
 * section is a plain box of the same height as every other: its name bar
 * over the drawing of its layout. The board is for arranging; Preview
 * shows the store itself.
 *
 * Clicking a section selects it, and its settings show in the panel beside
 * the board; a click on the empty board goes back to the page's own
 * settings. Every change redraws the board. On a phone the page is drawn as
 * a list instead (see phone.js).
 *
 * Sections are added where they will show: an Add line at the start and
 * end of every area and between its rows (a row of columns, or one section
 * full width), an Add under each column and side, the box of an empty area
 * or side, and the Add of a band, all opening the section picker. The page
 * content, the Header and the Footer are locked: they never move and are
 * never changed for another section. Every other section has a Change
 * control on its name bar that opens the same picker to replace it in its
 * spot. Sections and rows move by drag and drop or Alt plus the arrow keys
 * (see drag.js); every spot that takes a drop carries its target in
 * `data-target`.
 */

import { $, h, icon, announce } from '../dom.js';
import { sketch } from './sketch.js';
import {
  S, ui, doc, groupHandle, groupList, isFixed, labelOf, pageTitle, valueOf, schemes, pageDef, currentLayout,
} from '../state.js';
import { sectionDef, sectionIcon, sectionTypes, isLocked, allowedIn } from '../catalog.js';
import { layoutPayload, rulesFor } from '../operations.js';
import { openSheet, isPhone } from '../selection.js';
import { refreshNeeds, needTag } from '../needs.js';
import {
  rowsOf, contentSpan, gapTargets, hasAreas, countInPlace, AREAS,
} from './model.js';
import { fixedPanel, subjectWords, pageReach, defaultUsers } from '../layouts.js';
import { openPicker, pickerSpot, pickerReplacing } from './picker.js';
import {
  splitOf, listWords, columnName, AREA_NAMES,
} from './words.js';
import { barWords } from './labels.js';
import {
  startDrag, keyMove, keyMoveRow, keyMoves, rowKeyMoves, dragJustEnded, afterRender, targetKey, initDrag, keyMoveBand, bandKeyMoves,
} from './drag.js';
import { renderPhoneBoard } from './phone.js';
import { addLineMode } from './place.js';
import { pageHeading } from '../page-picker.js';
import { storeAddressFor, ANY_RECORD } from '../store-address.js';

const app = () => $('#app');

/* Whether the Add lines show on hover ('hover', computers) or always ('always'); kept on #app for the CSS. */
const lineMode = () => (app()?.dataset.addline === 'always' ? 'always' : 'hover');

/* ------------------------------------------------------------------------
   Words
   ------------------------------------------------------------------------ */

const variantOf = (sid) => valueOf(sid, null, 'variant');

function variantLabel(sid, type) {
  const value = variantOf(sid);
  return sectionDef(type)?.variants?.find((v) => v.value === value)?.label || '';
}

const BAND_NAMES = { header: 'Header', footer: 'Footer' };

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

/** The marks on a section's name bar: [icon, words, title]. */
function flagsOf(section, def) {
  const hidden = Boolean(section.disabled);
  const need = def && !hidden ? needTag(section.id) : null;
  const flags = [];
  if (def?.main === true) flags.push(['lock', 'Page content', 'The page content: it stays where it is']);
  else if (def?.fixed === true) flags.push(['lock', 'Fixed', 'Fixed in place: it stays where it is']);
  // On a phone the store shows Filters from the listing's Filter button, not beside the products.
  if (section.type === 'filters' && isPhone()) flags.push(['filter', 'In the Filter button', 'On a phone, shoppers open these filters from the listing\'s Filter button']);
  if (hidden) flags.push(['eye-off', 'Hidden', 'Hidden on the store']);
  if (need && !need.quiet) flags.push(['alert', need.text, `${need.text}: the store shows this section once something is picked`]);
  if (!def) flags.push(['alert', 'Not shown', 'The part of the store this section belongs to is not installed']);
  return flags;
}

/**
 * One section: a box with its name bar over the drawing of its layout. Its
 * width is its column's; its height is the same as every other box's. A
 * locked section (the page content, the Header, the Footer) is never
 * picked up, moved by the keys or changed.
 */
function piece(section, { movable = false, changeable = false, band = null } = {}) {
  const def = sectionDef(section.type);
  const locked = isLocked(def);
  const canMove = movable && !locked;
  const inBand = locked ? null : band;
  const selected = S.sel?.kind === 'section' && S.sel.sid === section.id;
  const name = labelOf(section.id);
  const words = barWords(name, def?.label || '', variantLabel(section.id, section.type));
  const hidden = Boolean(section.disabled);
  const main = def?.main === true;
  const flags = flagsOf(section, def);
  const el = h('button', {
    type: 'button',
    class: `piece piece--box${selected ? ' is-selected' : ''}${main ? ' piece--main' : ''}${locked ? ' is-locked' : ''}${hidden ? ' is-hidden' : ''}`,
    dataset: { sid: section.id, type: section.type },
    'aria-pressed': String(selected),
    'aria-keyshortcuts': inBand ? shortcuts(bandKeyMoves(inBand, section.id)) : canMove ? sectionShortcuts(section.id) : null,
    onclick: () => {
      if (dragJustEnded()) return;
      openSheet({ type: 'section', sid: section.id });
    },
    onpointerdown: inBand ? (e) => startDrag(e, { kind: 'band', group: inBand, sid: section.id })
      : canMove ? (e) => startDrag(e, { kind: 'section', sid: section.id }) : null,
    onkeydown: inBand ? (e) => keyMoveBand(e, inBand, section.id) : canMove ? (e) => keyMove(e, section.id) : null,
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
  // The page content, the Header and the Footer keep their section: they have no Change.
  if (!changeable || locked || !def) return el;
  const replacing = pickerReplacing() === section.id;
  return h('div', { class: `piece-wrap${replacing ? ' is-replacing' : ''}`, dataset: { sid: section.id } },
    el,
    h('button', {
      type: 'button', class: 'piece__change', 'aria-label': `Change ${name} to another section`, title: 'Change section',
      onclick: () => { if (!dragJustEnded()) openPicker({ replace: section.id }); },
    }, icon('replace', 'icon--sm')));
}

/* ------------------------------------------------------------------------
   The groups: a heading with the reach
   ------------------------------------------------------------------------ */

/** A group's heading: its name and, after a dot, who it reaches; the page's names only its reach. */
function groupHead(id, iconName, name, scope = null) {
  return h('h2', { class: 'group-head', id },
    icon(iconName, 'icon--xs group-head__icon'),
    h('span', { class: 'group-head__name', text: name }),
    // The spaces keep the words apart for a screen reader; the dot is only seen.
    ...(scope ? [' ', h('span', { class: 'group-head__dot', 'aria-hidden': 'true', text: '·' }), ' ', h('span', { class: 'group-head__scope', text: scope })] : []));
}

/** Who the open layout reaches, in a few words: its records, or this page alone. */
function reachNow() {
  const page = S.page;
  const words = subjectWords(page?.subjectType);
  const base = pageDef(page?.type) || page || {};
  const layout = currentLayout();
  return pageReach({
    words,
    total: base.subjectCount || 0,
    users: layout ? layout.count : words ? defaultUsers(base) : 0,
    special: Boolean(layout),
  });
}

/* ------------------------------------------------------------------------
   The bands: header and footer, shared by every page
   ------------------------------------------------------------------------ */

const bandSpot = (group, before) => ({ drop: 'band', group, before });

/** Whether a band can take one more section of any type allowed in it. */
function bandHasRoom(group) {
  const content = doc(group)?.content;
  if (!content) return false;
  const rules = rulesFor(group);
  return Object.entries(sectionTypes()).some(([type, def]) => !isLocked(def) && allowedIn(def, null, false, false, group)
    && !(def.limit && countInPlace(content, type, bandSpot(group, null), rules) >= def.limit));
}

/** The words of a band's Add, for its label: where in the band the section goes. */
function bandAddWords(group, before) {
  return `Add a section to the ${group}${before ? `, above ${labelOf(before)}` : ', at the end'}`;
}

/** A band's Add as a line, shown on hover or keyboard focus, like the page's own. */
function bandLine(group, before) {
  return h('button', {
    type: 'button', class: 'gap__add', 'aria-label': bandAddWords(group, before),
    onclick: () => { if (!dragJustEnded()) openPicker({ target: bandSpot(group, before) }); },
  }, h('span', { class: 'gap__line', 'aria-hidden': 'true' }), h('span', { class: 'gap__pill' }, icon('plus', 'icon--xs'), 'Add section'));
}

/**
 * A place in a band, before a section or at its end: a drop spot while a
 * section is held, and, while the band has room, a way to add. Above the
 * Header or Footer (where a bar usually goes) the Add is a button; the
 * Footer's is a line on computers, since nearly every page's footer has
 * room and a standing button would sit on every board. The other places
 * show an Add line on computers only: on touch screens the button is the
 * one way in, and lines would sit on the sections.
 */
function bandSlot(group, before, { room, open, movable }) {
  const t = bandSpot(group, before);
  if (isAddTarget(t)) return h('div', { class: 'bandgap bandgap--target', dataset: dropData(t) }, newSpot());
  const data = movable || room ? dropData(t) : null;
  if (!room) return h('div', { class: 'bandgap', 'aria-hidden': 'true', dataset: data });
  const hover = lineMode() === 'hover';
  if (open && !(group === 'footer' && hover)) {
    return h('div', { class: 'bandgap bandgap--open', dataset: data },
      h('button', {
        type: 'button', class: 'band-add', 'aria-label': bandAddWords(group, before),
        onclick: () => { if (!dragJustEnded()) openPicker({ target: t }); },
      }, icon('plus', 'icon--sm'), h('span', { text: 'Add section' })));
  }
  if (!hover) return h('div', { class: 'bandgap', 'aria-hidden': 'true', dataset: data });
  return h('div', { class: `bandgap bandgap--line${open ? ' bandgap--quiet' : ''}`, dataset: data }, bandLine(group, before));
}

/*
 * A band shared by every page: the header (the Header, and an Announcement
 * bar above or below it) or the footer. The Header and the Footer are
 * fixed; other sections move within the band only, never into the page.
 */
function bandEl(group) {
  const list = groupList(group);
  const room = bandHasRoom(group);
  const movable = list.length > 1;
  const name = BAND_NAMES[group];
  const items = h('div', { class: 'band__items' });
  let fixedAt = list.findIndex((s) => isLocked(sectionDef(s.type)));
  if (fixedAt < 0) fixedAt = 0;
  list.forEach((s, i) => items.append(bandSlot(group, s.id, { room, open: i === fixedAt, movable }), piece(s, { band: movable ? group : null })));
  items.append(bandSlot(group, null, { room, open: !list.length, movable }));
  return h('section', { class: `band band--${group}`, dataset: { group }, 'aria-labelledby': `group-${group}` },
    groupHead(`group-${group}`, 'link', name, 'on every page'), items);
}

/* ------------------------------------------------------------------------
   The page: its areas, rows and sides
   ------------------------------------------------------------------------ */

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

/** Between two rows (a gap of the row model): an Add line, shown on hover or focus (always on touch and narrow screens). */
function gapEl(target, above, below) {
  if (isAddTarget(target)) return h('div', { class: 'gap gap--target', dataset: dropData(target) }, newSpot());
  const where = above && below ? `between ${above} and ${below}` : below ? `above ${below}` : above ? `below ${above}` : '';
  const area = target.area ? ` to ${AREA_NAMES[target.area]}` : '';
  return h('div', { class: 'gap', dataset: dropData(target) },
    h('button', {
      type: 'button', class: 'gap__add', 'aria-label': `Add a section or a row of columns${area}${where ? `, ${where}` : ''}`,
      onclick: () => { if (!dragJustEnded()) openPicker({ target }); },
    }, h('span', { class: 'gap__line', 'aria-hidden': 'true' }), h('span', { class: 'gap__pill' }, icon('plus', 'icon--xs'), 'Add section')));
}

/** An area, or a page, with no rows: one calm box that adds its first section. */
function emptyArea(area) {
  const t = { drop: 'gap', area, before: 'end' };
  if (isAddTarget(t)) return h('div', { class: 'area-empty area-empty--target', dataset: dropData(t) }, newSpot());
  return h('button', {
    type: 'button', class: 'area-empty', dataset: dropData(t), 'aria-label': area ? `Add a section to ${AREA_NAMES[area]}` : 'Add a section to the page',
    onclick: () => { if (!dragJustEnded()) openPicker({ target: t }); },
  }, icon('plus', 'icon--sm'), h('span', { text: 'Add section' }));
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
      'aria-keyshortcuts': shortcuts(rowKeyMoves(rowsOf(doc(groupHandle('template')).content, layoutPayload(), rulesFor(groupHandle('template'))), row.id)),
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
 * A side of the page, beside Content, always drawn at a side's full width
 * and the full height of Content, empty or not. Empty, it is one calm box
 * that adds the side's first section (and takes a dropped one); a section
 * there turns the side on for the store. With sections, it holds them one
 * under the other, with an Add under the last while there is room.
 */
function sideEl(which, ids, sections) {
  const name = SIDE_NAMES[which];
  const end = { drop: 'side', side: which, before: null };
  const el = h('div', { class: `side side--${which}${ids.length ? '' : ' is-empty'}`, dataset: { side: which } },
    h('p', { class: 'side__tag', text: name }));
  if (!ids.length) {
    el.append(isAddTarget(end) ? newSpot() : h('button', {
      type: 'button', class: 'side__empty', dataset: dropData(end), 'aria-label': `Add a section to the ${which} side`,
      onclick: () => { if (!dragJustEnded()) openPicker({ target: end }); },
    }, icon('plus', 'icon--sm'), h('span', { text: 'Add section' })));
    return el;
  }
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

/**
 * Rows with an Add line before each and one at the end; an empty list is
 * one calm box instead. `area` is the rows' area, or null on a page
 * without areas.
 */
function rowList(list, area, sections) {
  const rows = h('div', { class: 'rows' });
  const gaps = gapTargets(list).filter((g) => g.area === area);
  const own = list.filter((r) => (r.area ?? null) === area);
  if (!own.length) {
    rows.append(emptyArea(area));
    return rows;
  }
  const nameOf = (row) => (row ? listWords(row.columns.flat().filter((sid) => sections[sid]).map((sid) => labelOf(sid))) || 'an empty row' : null);
  // Each gap before the row it names, then one at the end.
  gaps.forEach((gap, i) => {
    rows.append(gapEl(gap, nameOf(own[i - 1]), nameOf(own[i])));
    if (gap.before !== 'end') rows.append(rowEl(own[i], sections));
  });
  return rows;
}

/**
 * The page's own part of the board. A page with page content has Top,
 * Content and Bottom, each named once on a rule above it, with the left
 * and right sides beside Content. A page without page content is its rows
 * alone, with no rule and no side.
 */
function pageAreas() {
  const handle = groupHandle('template');
  const content = doc(handle)?.content;
  if (!content) return [h('div', { class: 'rows' })];
  const sections = content.sections || {};
  const rules = rulesFor(handle);
  const list = rowsOf(content, layoutPayload(), rules);
  if (!hasAreas(content, rules)) return [h('div', { class: 'area area--plain' }, rowList(list, null, sections))];
  const side = (which) => (content.sides?.[which] || []).filter((sid) => sections[sid]);
  const left = side('left');
  const right = side('right');
  return AREAS.map((area) => {
    const rows = rowList(list, area, sections);
    // Beside Content, both sides always draw at their full width; on a phone they stack around it.
    const body = area === 'content'
      ? h('div', { class: 'zone', dataset: { sides: `${left.length ? 'l' : ''}${right.length ? 'r' : ''}`, content: String(contentSpan(content, rules)) } },
        sideEl('left', left, sections), rows, sideEl('right', right, sections))
      : rows;
    return h('section', { class: `area area--${area}`, dataset: { area }, 'aria-label': AREA_NAMES[area] },
      h('h3', { class: 'area-rule', text: AREA_NAMES[area] }), body);
  });
}

/** The page between the bands: its reach, then its areas or rows, or the note of a page with a fixed layout. */
function pageSheet() {
  return h('section', { class: 'sheet', 'aria-labelledby': 'group-page', 'aria-describedby': 'pagehead-board' },
    groupHead('group-page', 'file', isFixed() ? 'A fixed layout' : reachNow()),
    ...(isFixed() ? [fixedPanel()] : pageAreas()));
}

/** Whether the open layout is shared by many records, so its address stands for any of them. */
const sharedNow = () => Boolean(subjectWords(S.page?.subjectType)) && !S.page?.record;

/** The address the open layout serves, for the frame's top bar; the store's home when the page has none. */
function frameAddress() {
  const page = S.page;
  return storeAddressFor(page?.url || S.store?.url || '', { shared: sharedNow(), pageType: page?.type || '' })
    || storeAddressFor(S.store?.url || '', { shared: false });
}

/**
 * The frame's top bar: the store address, as a quiet pill, the way a
 * browser bar would name it, without drawing a browser. On a shared layout
 * the words for any record are set apart from the path.
 */
export function addressPill(text, words = null) {
  const at = words && text.endsWith(` ${words}`) ? text.length - words.length - 1 : -1;
  const shown = at < 0 ? text : text.slice(0, at);
  const slash = shown.indexOf('/');
  return h('p', { class: 'frame__address' },
    icon('lock', 'icon--xs frame__lock'),
    h('span', { class: 'frame__domain', text: slash < 0 ? shown : shown.slice(0, slash) }),
    slash < 0 ? null : h('span', { class: 'frame__path', text: shown.slice(slash) }),
    at < 0 ? null : h('span', { class: 'frame__any', text: ` ${words}` }));
}

function frameBar() {
  const address = frameAddress();
  return h('div', { class: 'frame__bar', title: address }, addressPill(address, sharedNow() ? ANY_RECORD[S.page?.type] : null));
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
  // One frame on the canvas: the address on top, then the header band, the page and the footer band, apart.
  const frame = h('div', { class: 'frame', role: 'group', 'aria-label': `${pageTitle()}, drawn as the page` },
    frameBar(), h('div', { class: 'page-groups' }, bandEl('header'), pageSheet(), bandEl('footer')));
  board.replaceChildren(phone ? renderPhoneBoard({ head: head(), frame }) : h('div', { class: 'board__inner' }, head(), frame));
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
  const def = sid ? sectionDef(piece.dataset.type) : null;
  if (def?.main === true) {
    announce(`${labelOf(sid)} is the page content. It stays where it is.`);
  } else if (def?.fixed === true) {
    announce(`${labelOf(sid)} is fixed. It stays where it is.`);
  } else if (sid && piece.closest('.band')) {
    const band = piece.closest('.band--header') ? 'header' : 'footer';
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
  const mode = addLineMode({
    coarse: window.matchMedia('(pointer: coarse)').matches,
    hover: window.matchMedia('(hover: hover)').matches,
    width: window.innerWidth,
  });
  if (a.dataset.addline === mode) return;
  a.dataset.addline = mode;
  // The footer's Add is a line or a button by this mode: draw the board again when it changes.
  if (S.loaded) renderBoard();
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
