/*
 * The words the board uses for rows, columns and drop targets, shared by
 * the board, its drag and drop and the add panel, so a spot is named the
 * same way wherever it is shown.
 */

import { doc, groupHandle, labelOf } from '../state.js';
import { layoutPayload } from '../operations.js';
import { rowsOf } from './model.js';

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
