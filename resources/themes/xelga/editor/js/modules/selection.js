/*
 * What is selected on the layout board, and the panel its settings show
 * in. On a phone the panel is a sheet from the bottom, over the board, so
 * opening it puts the focus inside it.
 */

import { $, $$ } from './dom.js';
import { S, ui, sec, blk } from './state.js';

export const isPhone = () => window.matchMedia('(max-width: 719px)').matches;

/** Selects a section, or one of its blocks; its settings show in the panel. */
export function select(sid, bid = null) {
  if (!sec(sid) || (bid && !blk(sid, bid))) return;
  S.sel = { kind: 'section', sid, bid };
  S.renaming = null;
  ui.renderBoard();
  ui.renderInspector();
}

export function deselect() {
  S.sel = null;
  S.renaming = null;
  ui.renderBoard();
  ui.renderInspector();
}

/** Opens one theme settings group in the panel, with the way back to Theme. */
export function openThemeGroup(groupKey, extra = {}) {
  if (!groupKey) return;
  S.view = { type: 'theme' };
  S.sel = { kind: 'theme', group: groupKey, ...extra };
  S.renaming = null;
  ui.renderBoard();
  ui.renderInspector();
}

/**
 * Shows one view in the panel (see openDrawer). On a phone the sheet
 * covers the board, so the focus goes to its title.
 */
export function openSheet(view) {
  if (!ui.openDrawer?.(view)) return false;
  if (isPhone()) window.requestAnimationFrame(() => $('#drawer .insp-title')?.focus({ preventScroll: true }));
  return true;
}


/** Keeps the tab order of a radio group on its checked item. */
export function radioItems(group, value) {
  const items = $$('[role="radio"]', group);
  items.forEach((b) => {
    const on = b.dataset.value === String(value);
    b.setAttribute('aria-checked', String(on));
    b.tabIndex = on ? 0 : -1;
  });
  if (!items.some((b) => b.tabIndex === 0) && items[0]) items[0].tabIndex = 0;
}

/** Arrow keys move through a radio group and choose, like native radios. */
export function radioKeys(group) {
  group.addEventListener('keydown', (e) => {
    const keys = ['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'];
    if (!keys.includes(e.key)) return;
    const items = $$('[role="radio"]', group);
    const i = items.indexOf(document.activeElement);
    if (i < 0) return;
    e.preventDefault();
    const next = items[(i + (e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length];
    next.focus();
    next.click();
  });
}
