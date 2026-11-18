/**
 * The picture a section's box shows on the board: the drawing of its
 * layout. A divider with a label draws its label too, so two dividers on
 * one page can be told apart.
 */

import { h, schematic } from '../dom.js';
import { variantDrawing } from '../catalog.js';

/** The divider's own picture: a line, its label and a line, in the box. */
function labeledDivider(label) {
  return h('span', { class: 'piece__divider', 'aria-hidden': 'true' },
    h('span', { class: 'piece__divider-line' }),
    h('span', { class: 'piece__divider-label', text: label }),
    h('span', { class: 'piece__divider-line' }));
}

/**
 * What a box draws: the divider's own label, or the drawing of the layout.
 *
 * @param {string} type     the section's type
 * @param {string} variant  its layout
 * @param {string} label    the divider's label setting, ignored by other types
 * @returns {{label: string}|{drawing: string|null}}
 */
export function sketchOf(type, variant, label = '') {
  const words = String(label ?? '').trim();
  if (type === 'divider' && variant === 'label' && words !== '') return { label: words };
  return { drawing: variantDrawing(type, variant) };
}

/** The picture itself: see sketchOf. */
export function sketch(type, variant, label = '') {
  const what = sketchOf(type, variant, label);
  return 'label' in what ? labeledDivider(what.label) : schematic(what.drawing);
}
