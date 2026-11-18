/*
 * How a section is named on the board and in its settings: its own name,
 * then its type, then its layout. The type is said only when the merchant
 * has named the section something else, so a section still called by its
 * type never says it twice. Nothing here touches the DOM or the editor's
 * state.
 */

import { locate, rowsOf } from './model.js';
import { columnName } from './words.js';

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

/**
 * Where a section sits, for its settings header: "On every page" for the
 * header and footer, "In the left side", "In the middle column of a row";
 * null for a full-width row of its own, which needs no words.
 */
export function placeWords(content, sid, layout, group = 'template') {
  if (group === 'header' || group === 'footer') return 'On every page';
  if (!content || !layout) return null;
  const at = locate(content, sid, layout);
  if (at?.where === 'side') return `In the ${at.side} side`;
  if (at?.where !== 'row') return null;
  const row = rowsOf(content, layout).find((r) => r.id === at.rowId);
  const parts = layout.splits?.[row?.split]?.parts || [12];
  return parts.length > 1 ? `In the ${columnName(parts, at.column).toLowerCase()} of a row` : null;
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
