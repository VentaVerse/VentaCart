/*
 * The words the board uses for rows, columns and drop targets, shared by
 * the board, its drag and drop and the section picker, so a spot is named
 * the same way wherever it is shown.
 */

import { doc, groupHandle, labelOf } from '../state.js';
import { layoutPayload } from '../operations.js';
import { rowsOf, locate } from './model.js';

export const splitOf = (split) => layoutPayload()?.splits?.[split] || { parts: [12], label: 'One column' };

export function listWords(names) {
  if (names.length <= 1) return names[0] || '';
  return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
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
  const content = doc(groupHandle('template'))?.content;
  if (!content || !target) return '';
  const all = rowsOf(content, layoutPayload());
  // A full-width row that holds only the moving section is not named.
  const rows = all.filter((r) => r.split !== '12' || r.columns.flat().some((sid) => sid !== skip));
  if (target.drop === 'gap') {
    const index = target.before === 'end' ? all.length : all.findIndex((r) => r.id === target.before);
    if (index < 0) return '';
    const above = all.slice(0, index).reverse().find((r) => rows.includes(r));
    const below = all.slice(index).find((r) => rows.includes(r));
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

/*
 * Where the section picker puts a section, as the parts of a sentence:
 * plain words, then each name to show in bold at the odd places.
 * `{target}` a spot of the board ("Goes in the left half, under Text"),
 * `{replace: sid}` the section being replaced ("Stays at the top, above
 * Store promises").
 */
export function pickerWhere(spot) {
  const content = doc(groupHandle('template'))?.content;
  const layout = layoutPayload();
  if (!content || !layout || !spot) return [];
  const rows = rowsOf(content, layout);
  const verb = spot.replace ? 'Stays' : 'Goes';
  const between = (above, below) => {
    if (above && below) return [`${verb} between `, rowName(above), ' and ', rowName(below)];
    if (below) return [`${verb} at the top, above `, rowName(below)];
    if (above) return [`${verb} at the bottom, below `, rowName(above)];
    return null;
  };
  const inList = (where, list, at, skip) => {
    const others = list.filter((sid) => sid !== skip);
    if (!others.length) return [`${verb} ${where}`];
    if (at <= 0) return [`${verb} ${where}, above `, labelOf(others[0])];
    return [`${verb} ${where}, under `, labelOf(list[at - 1])];
  };

  if (spot.replace) {
    const sid = spot.replace;
    const at = locate(content, sid, layout);
    if (!at) return ['Stays in its place'];
    if (at.where === 'side') return inList(`in the ${at.side} side`, content.sides?.[at.side] || [], at.index, sid);
    const index = rows.findIndex((r) => r.id === at.rowId);
    const r = rows[index];
    if (r.split === '12') return between(rows[index - 1], rows[index + 1]) || ['Stays in its place'];
    const where = `in the ${columnName(splitOf(r.split).parts, at.column).toLowerCase()}`;
    const column = r.columns[at.column];
    if (column.length > 1) return inList(where, column, at.index, sid);
    const other = r.columns.flat().find((x) => x !== sid);
    return other ? [`Stays ${where}, beside `, labelOf(other)] : [`Stays ${where}`];
  }

  const target = spot.target;
  if (target?.drop === 'gap') {
    const index = target.before === 'end' ? rows.length : rows.findIndex((r) => r.id === target.before);
    return (index >= 0 && between(rows[index - 1], rows[index])) || ['Goes on the page'];
  }
  if (target?.drop === 'side') {
    const list = content.sides?.[target.side] || [];
    return inList(`in the ${target.side} side`, list, target.before == null ? list.length : list.indexOf(target.before), null);
  }
  if (target?.drop === 'column') {
    const r = rows.find((x) => x.id === target.rowId);
    if (!r) return ['Goes on the page'];
    const where = `in the ${columnName(splitOf(r.split).parts, target.column).toLowerCase()}`;
    const column = r.columns[target.column] || [];
    if (!column.length) {
      const others = r.columns.flat();
      return others.length ? [`Goes ${where}, beside `, labelOf(others[0])] : [`Goes ${where}`];
    }
    return inList(where, column, target.before == null ? column.length : column.indexOf(target.before), null);
  }
  return ['Goes on the page'];
}
