/*
 * The words the board uses for rows, columns and drop targets, shared by
 * the board, its drag and drop and the section picker, so a spot is named
 * the same way wherever it is shown.
 */

import { doc, groupHandle, labelOf } from '../state.js';
import { layoutPayload, rulesFor } from '../operations.js';
import { rowsOf, locate, gapAt, MOVE_REASONS } from './model.js';
import { AREA_NAMES } from './choices.js';

export { AREA_NAMES };

export const splitOf = (split) => layoutPayload()?.splits?.[split] || { parts: [12], label: 'One column' };

export function listWords(names) {
  if (names.length <= 1) return names[0] || '';
  return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
}

/**
 * The sections a side filled for the first time pushed out of their
 * columns, as a sentence starting with a space; empty when none.
 */
export function movedBesideSideWords(names) {
  if (!names.length) return '';
  return names.length === 1
    ? ` ${names[0]} needs a wider column beside the side, so it moved to a row of its own.`
    : ` ${listWords(names)} need wider columns beside the side, so they moved to rows of their own.`;
}

/**
 * The sections a side filled for the first time took out of Content
 * (`[{name, area}]`), as a sentence starting with a space; empty when none.
 */
export function leftContentWords(entries) {
  const list = entries || [];
  const parts = ['top', 'bottom']
    .map((area) => list.filter((e) => e.area === area).map((e) => e.name))
    .map((names, i) => (names.length ? `${listWords(names)} moved to ${AREA_NAMES[i ? 'bottom' : 'top']}` : null))
    .filter(Boolean);
  return parts.length ? ` To make room for the side, ${parts.join(' and ')}.` : '';
}

const article = (word) => (/^[aeiou]/i.test(word) ? 'an' : 'a');

/**
 * Why a type cannot be added again: the area (`area`) or band (`band`)
 * that already has as many as it may hold, else the page.
 */
export function fullWords(def, { area = null, band = null } = {}) {
  const label = String(def?.label || 'section');
  const one = def?.limit === 1;
  const lower = label.toLowerCase();
  const holder = area ? AREA_NAMES[area] : band ? `The ${band}` : null;
  if (holder) return one ? `${holder} already has ${article(lower)} ${lower}.` : `${holder} already has ${def.limit} ${label} sections, the most it can have.`;
  return `This page already has ${one ? 'a' : def?.limit} ${label}${one ? '' : ' sections'}, the most it can have.`;
}

export function columnName(parts, index) {
  const n = parts.length;
  if (n === 2 && parts[0] === parts[1]) return ['Left half', 'Right half'][index];
  const names = {
    2: ['Left column', 'Right column'],
    3: ['Left column', 'Middle column', 'Right column'],
    4: ['First column', 'Second column', 'Third column', 'Last column'],
  };
  return (names[n] || ['Column'])[index];
}

/** A row named by its sections, leaving out one that is moving. */
export function rowName(row, skip = null) {
  const content = doc(groupHandle('template'))?.content;
  const names = row.columns.flat().filter((sid) => sid !== skip && content?.sections?.[sid]).map((sid) => labelOf(sid));
  return names.length ? listWords(names) : 'an empty row';
}

/**
 * Where a target is, as the end of a sentence: "between Banner and Text",
 * "to the left half, under Text", "beside Banner, on its left". `skip` is
 * a section being moved, left out of the names around it.
 */
export function targetWords(target, skip = null) {
  const handle = groupHandle('template');
  const content = doc(handle)?.content;
  if (!content || !target) return '';
  const all = rowsOf(content, layoutPayload(), rulesFor(handle));
  // A full-width row that holds only the moving section is not named.
  const rows = all.filter((r) => r.split !== '12' || r.columns.flat().some((sid) => sid !== skip));
  if (target.drop === 'gap') {
    const { index, area } = gapAt(all, target);
    if (index < 0) return '';
    // On a page with areas, only the rows of the gap's own area are its neighbours.
    const near = (r) => rows.includes(r) && (r.area ?? null) === area;
    const above = all.slice(0, index).reverse().find(near);
    const below = all.slice(index).find(near);
    if (area) {
      const to = `to ${AREA_NAMES[area]}`;
      if (above && below) return `${to}, between ${rowName(above, skip)} and ${rowName(below, skip)}`;
      if (below) return `${to}, above ${rowName(below, skip)}`;
      if (above) return `${to}, below ${rowName(above, skip)}`;
      return to;
    }
    if (above && below) return `between ${rowName(above, skip)} and ${rowName(below, skip)}`;
    if (below) return `to the top, above ${rowName(below, skip)}`;
    if (above) return `to the bottom, below ${rowName(above, skip)}`;
    return 'onto the page';
  }
  if (target.drop === 'beside') {
    const r = rows.find((x) => x.id === target.rowId);
    const other = r?.columns[0]?.[0];
    return other ? `beside ${labelOf(other)}, on its ${target.side}` : 'beside it';
  }
  if (target.drop === 'side') {
    const list = (content.sides?.[target.side] || []).filter((sid) => sid !== skip);
    const where = `to the ${target.side} side`;
    return sideWords(where, list, target.before);
  }
  if (target.drop === 'column') {
    const r = all.find((x) => x.id === target.rowId);
    if (!r) return '';
    const parts = splitOf(r.split).parts;
    const where = `to the ${columnName(parts, target.column).toLowerCase()}`;
    const column = (r.columns[target.column] || []).filter((sid) => sid !== skip);
    if (!column.length) {
      const others = r.columns.flat().filter((sid) => sid !== skip);
      return others.length ? `${where}, beside ${labelOf(others[0])}` : where;
    }
    return sideWords(where, column, target.before);
  }
  return '';
}

function sideWords(where, list, before) {
  if (!list.length) return where;
  const at = before == null ? list.length : list.indexOf(before);
  if (at <= 0) return `${where}, above ${labelOf(list[0])}`;
  return `${where}, under ${labelOf(list[at - 1])}`;
}

/* Which sides stand beside Content, as words after its name; empty without sides. */
function besideSides(content) {
  const filled = ['left', 'right'].filter((side) => (content.sides?.[side] || []).length > 0);
  if (filled.length === 2) return ', between the sides';
  return filled.length ? `, beside the ${filled[0]} side` : '';
}

/*
 * Where the section picker puts a section, as the parts of a sentence:
 * plain words, then each name to show in bold at the odd places. On a
 * page with areas the words start with the area ("Goes in Top", "Goes in
 * Content, beside the left side"); a band says it is on every page.
 * `{target}` a spot of the board ("Goes in Bottom, above Text"),
 * `{replace: sid}` the section being replaced ("Stays in Top, above
 * Breadcrumbs").
 */
export function pickerWhere(spot) {
  const handle = groupHandle('template');
  const content = doc(handle)?.content;
  const layout = layoutPayload();
  if (!content || !layout || !spot) return [];
  const rows = rowsOf(content, layout, rulesFor(handle));
  const verb = spot.replace ? 'Stays' : 'Goes';
  const lead = (area, sides = true) => `${verb} in ${AREA_NAMES[area]}${area === 'content' && sides ? besideSides(content) : ''}`;
  // A full-width place between rows, named by the rows of its own area around it.
  const between = (area, above, below) => {
    if (!area) {
      if (above && below) return [`${verb} between `, rowName(above), ' and ', rowName(below)];
      if (below) return [`${verb} at the top, above `, rowName(below)];
      if (above) return [`${verb} at the bottom, below `, rowName(above)];
      return [`${verb} on this page`];
    }
    if (above && below) return [`${lead(area)}, between `, rowName(above), ' and ', rowName(below)];
    if (below) return [`${lead(area)}, above `, rowName(below)];
    if (above) return [`${lead(area)}, below `, rowName(above)];
    return [lead(area)];
  };
  const rowsAround = (index, area, skipOwn = false) => {
    const same = (r) => r && (r.area ?? null) === area;
    const above = rows[index - 1];
    const below = rows[skipOwn ? index + 1 : index];
    return [same(above) ? above : null, same(below) ? below : null];
  };
  const inList = (where, list, at, skip) => {
    const others = list.filter((sid) => sid !== skip);
    if (!others.length) return [`${verb} ${where}`];
    if (at <= 0) return [`${verb} ${where}, above `, labelOf(others[0])];
    return [`${verb} ${where}, under `, labelOf(list[at - 1])];
  };
  // A column of a row: "in the left half", after the row's area.
  const inColumn = (r, column) => {
    const name = `in the ${columnName(splitOf(r.split).parts, column).toLowerCase()}`;
    return r.area ? `in ${AREA_NAMES[r.area]}, ${name}` : name;
  };

  if (spot.replace) {
    const sid = spot.replace;
    const at = locate(content, sid, layout);
    if (!at) return ['Stays in its place'];
    if (at.where === 'side') return inList(`in the ${at.side} side`, content.sides?.[at.side] || [], at.index, sid);
    const index = rows.findIndex((r) => r.id === at.rowId);
    const r = rows[index];
    if (r.split === '12') return between(r.area ?? null, ...rowsAround(index, r.area ?? null, true));
    const where = inColumn(r, at.column);
    const column = r.columns[at.column];
    if (column.length > 1) return inList(where, column, at.index, sid);
    const other = r.columns.flat().find((x) => x !== sid);
    return other ? [`Stays ${where}, beside `, labelOf(other)] : [`Stays ${where}`];
  }

  const target = spot.target;
  if (target?.drop === 'band') {
    const list = doc(target.group)?.content?.order || [];
    const where = `${verb} in the ${target.group} on every page`;
    if (target.before != null && list.includes(target.before)) return [`${where}, above `, labelOf(target.before)];
    return list.length ? [`${where}, below `, labelOf(list[list.length - 1])] : [where];
  }
  if (target?.drop === 'gap') {
    const { index, area } = gapAt(rows, target);
    return index >= 0 ? between(area, ...rowsAround(index, area)) : [`${verb} on this page`];
  }
  if (target?.drop === 'side') {
    const list = content.sides?.[target.side] || [];
    return inList(`in the ${target.side} side`, list, target.before == null ? list.length : list.indexOf(target.before), null);
  }
  if (target?.drop === 'column') {
    const r = rows.find((x) => x.id === target.rowId);
    if (!r) return [`${verb} on this page`];
    const where = inColumn(r, target.column);
    const column = r.columns[target.column] || [];
    if (!column.length) {
      const others = r.columns.flat();
      return others.length ? [`Goes ${where}, beside `, labelOf(others[0])] : [`Goes ${where}`];
    }
    return inList(where, column, target.before == null ? column.length : column.indexOf(target.before), null);
  }
  return [`${verb} on this page`];
}

/**
 * What the server moved when it read a page, as one message: the names of
 * the sections, then why, in the words of MOVE_REASONS. `adjusted` is the
 * list the page was opened with (`{path, reason}`); entries that are not
 * moves are left out. Empty when nothing moved.
 */
export function openedMovesWords(adjusted, content) {
  const names = [];
  const reasons = [];
  (Array.isArray(adjusted) ? adjusted : []).forEach((item) => {
    if (!item || !Object.hasOwn(MOVE_REASONS, item.reason || '')) return;
    const match = /^content\.sections\.([^.]+)$/.exec(String(item.path || ''));
    if (!match || !content?.sections?.[match[1]]) return;
    const name = labelOf(match[1]);
    if (!names.includes(name)) names.push(name);
    if (!reasons.includes(MOVE_REASONS[item.reason])) reasons.push(MOVE_REASONS[item.reason]);
  });
  if (!names.length) return '';
  return `${listWords(names)} moved to fit the page as it is now. ${reasons.join(' ')}`;
}
