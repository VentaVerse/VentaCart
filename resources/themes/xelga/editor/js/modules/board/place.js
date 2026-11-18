/*
 * Rules the board and Preview share, kept free of the DOM so they can be
 * tested on their own: where the reader is on the page, so switching
 * between Board and Preview keeps that place, and when the Add lines
 * between rows always show.
 */

/** How far past a view's top a section must reach to count as the one at the top. */
const SLACK = 24;

/** Where an open section is put: this far under the top of the view it is shown in. */
export const OPEN_OFFSET = 64;

/** A view scrolled less than this is at the top of the page, and the other view starts at its top too. */
export const AT_TOP = 40;

/**
 * The section at the top of a view: the first box, in page order, whose
 * bottom reaches past the view's top. `boxes` are [{sid, top, bottom}] in
 * the view's coordinates. Answers {sid, offset}, offset being how far
 * below the view's top the section starts (negative when it starts above
 * it), or null when there is none.
 */
export function placeIn(boxes, viewTop = 0) {
  const at = (boxes || []).find((b) => b && b.bottom > viewTop + SLACK);
  return at ? { sid: at.sid, offset: at.top - viewTop } : null;
}

/**
 * The place to keep when switching views: the open section, else the
 * section at the top of a scrolled view, else null for the top of the page.
 *   selected  the id of the section open in the panel, or null
 *   scrolled  how far the view is scrolled
 *   boxes     the view's sections, as for placeIn
 *   viewTop   the view's top, in the boxes' coordinates
 */
export function placeFor({ selected = null, scrolled = 0, boxes = [], viewTop = 0 } = {}) {
  if (selected) return { sid: selected, offset: OPEN_OFFSET, open: true };
  if (scrolled <= AT_TOP) return null;
  return placeIn(boxes, viewTop);
}

/**
 * How far to scroll a view so a place shows as it did in the other one:
 * the section's top `offset` below the view's top, and never closer to
 * the top than `floor`, so the section's start stays in sight.
 */
export function scrollBy(targetTop, viewTop, offset, floor = 16) {
  return targetTop - viewTop - Math.max(floor, offset);
}

/** Screens narrower than this are tablets or phones: the Add lines always show there. */
export const NARROW = 1024;

/**
 * Whether the Add lines between rows always show ('always') or only on
 * hover and keyboard focus ('hover'). A touch screen has no hover, and on
 * a tablet or phone the line is the way to add, so both always show it.
 *   coarse  the main pointer is a finger
 *   hover   the main pointer can hover
 *   width   the window's width in CSS pixels
 */
export function addLineMode({ coarse = false, hover = true, width = 1440 } = {}) {
  return coarse || !hover || width < NARROW ? 'always' : 'hover';
}
