/*
 * How a section is named on the board and in its settings: its own name,
 * then its type, then its layout. The type is said only when the merchant
 * has named the section something else, so a section still called by its
 * type never says it twice. Nothing here touches the DOM or the editor's
 * state.
 */

import { locate, rowsOf } from './model.js';
import { columnName } from './words.js';
import { AREA_NAMES } from './choices.js';

/** Whether a section's name differs from its type's own name, ignoring case and spaces at the ends. */
export function isRenamed(name, typeLabel) {
  const own = String(name || '').trim().toLowerCase();
  return own !== '' && own !== String(typeLabel || '').trim().toLowerCase();
}

/**
 * The words of a section's name bar: `{name, type, layout, title}`. `type`
 * is null when the section is not renamed, `layout` when it has none;
 * `title` holds them all, for the bar's hover text.
 */
export function barWords(name, typeLabel, layoutLabel = '') {
  const shown = String(name || '').trim() || String(typeLabel || '');
  const type = isRenamed(shown, typeLabel) ? String(typeLabel) : null;
  const layout = String(layoutLabel || '').trim() || null;
  return { name: shown, type, layout, title: [shown, type, layout].filter(Boolean).join(' · ') };
}

/**
 * The second line of a section's settings header: `{type, where}`. `type`
 * names the section's type when the section is renamed (its first line is
 * then the name), else null; `where` says where it sits when that is more
 * than a full-width row of its own. Null when there is nothing to say.
 */
export function typeLine(name, typeLabel, where = null) {
  const type = isRenamed(name, typeLabel) ? String(typeLabel) : null;
  if (!type && !where) return null;
  return { type, where: where || null };
}

/** The bands' names on screen. */
const BAND_NAMES = { header: 'Header', footer: 'Footer' };

/**
 * Where a section sits, for its settings header: "Header, on every page"
 * for the header and footer bands; on a page with areas "In Top, this page
 * only", "In Bottom, the right half of a row", and "The page content,
 * fixed in place" for its main section; "In the left side"; "In the middle
 * column of a row". Null for a full-width row of a page without areas,
 * which needs no words. `rules` (the page's layout rules) tell the areas.
 */
export function placeWords(content, sid, layout, group = 'template', rules = null) {
  if (group === 'header' || group === 'footer') return `${BAND_NAMES[group]}, on every page`;
  if (!content || !layout) return null;
  if (rules && typeof rules.isMain === 'function' && rules.isMain(content.sections?.[sid])) return 'The page content, fixed in place';
  const at = locate(content, sid, layout);
  if (at?.where === 'side') return `In the ${at.side} side`;
  if (at?.where !== 'row') return null;
  const row = rowsOf(content, layout, rules).find((r) => r.id === at.rowId);
  const parts = layout.splits?.[row?.split]?.parts || [12];
  const area = row?.area ? AREA_NAMES[row.area] : null;
  if (parts.length > 1) {
    const column = columnName(parts, at.column).toLowerCase();
    return area ? `In ${area}, the ${column} of a row` : `In the ${column} of a row`;
  }
  return area ? `In ${area}, this page only` : null;
}

/**
 * The name a new block starts with. Only a block type a section can hold
 * more than one of gets a number ("Slide 2"), so the merchant can tell the
 * blocks apart; one of a kind keeps its type's plain name ("Quantity and
 * Add to cart"). `limit` is the type's limit in the section (0 for none),
 * `max` the section's own limit of blocks, and `taken` how many of the
 * type the section holds already.
 */
export function blockLabel(typeLabel, { limit = 0, max = 0, taken = 0 } = {}) {
  const name = String(typeLabel || '').trim();
  const oneOfAKind = Number(limit) === 1 || Number(max) === 1;
  return oneOfAKind ? name : `${name} ${Number(taken) + 1}`;
}
