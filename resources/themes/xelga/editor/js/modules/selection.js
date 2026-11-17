/*
 * What is selected and which panel shows: the outline, theme settings, the
 * add section list or earlier versions on the left, the selection's settings
 * on the right. Under 1180px the two share one slot; on a phone they come up
 * as a bottom sheet.
 */

import { $, $$ } from './dom.js';
import { S, ctx, ui, sec, blk, openOnly } from './state.js';

export const isPhone = () => window.matchMedia('(max-width: 719px)').matches;
export const isNarrow = () => window.matchMedia('(max-width: 1179px)').matches;

const app = () => $('#app');

export function post(type, payload) {
  if (!S.frameReady || !ctx.bridge) return false;
  return ctx.bridge.post(type, payload);
}

export function showPanel(which) {
  app().dataset.panel = which;
}

export function openSheet(which) {
  showPanel(which);
  app().dataset.sheet = 'open';
  S.sheetOpen = true;
}

export function closeSheet() {
  app().dataset.sheet = 'closed';
  S.sheetOpen = false;
}

export function setNav(nav, { keepSelection = false } = {}) {
  S.nav = nav;
  $('#tab-sections').setAttribute('aria-selected', String(nav !== 'theme'));
  $('#tab-theme').setAttribute('aria-selected', String(nav === 'theme'));
  $('#tab-sections').tabIndex = nav === 'theme' ? -1 : 0;
  $('#tab-theme').tabIndex = nav === 'theme' ? 0 : -1;
  $('#nav-sections').hidden = nav !== 'sections';
  $('#nav-theme').hidden = nav !== 'theme';
  $('#nav-add').hidden = nav !== 'add';
  $('#nav-revisions').hidden = nav !== 'revisions';
  if (!keepSelection && nav === 'theme' && S.sel?.kind === 'section') {
    S.sel = null;
    post('select', { sectionId: null, blockId: null });
    ui.renderOutline();
    ui.renderInspector();
  }
  if (nav === 'theme') ui.renderThemeNav();
  if (nav === 'add') ui.renderAdd();
  if (nav === 'revisions') ui.renderRevisions();
}

function scrollRowIntoView(id) {
  const nav = $('#nav-sections');
  const row = nav.querySelector(`[data-row="${CSS.escape(id)}"]`);
  if (!row) return;
  // Scroll only the outline, never the editor page itself.
  const box = nav.getBoundingClientRect();
  const r = row.getBoundingClientRect();
  if (r.top < box.top) nav.scrollTop -= box.top - r.top + 8;
  else if (r.bottom > box.bottom) nav.scrollTop += r.bottom - box.bottom + 8;
}

export function select(sid, bid = null, { fromPreview = false, focusPanel = true, field = null } = {}) {
  if (!sec(sid) || (bid && !blk(sid, bid))) return;
  S.sel = { kind: 'section', sid, bid, field };
  openOnly(sid);
  // A header or footer section clicked on a page (or a page section while
  // editing the header and footer) shows the list it belongs to.
  if (ui.setScope?.(ui.scopeOf(sid))) ui.flashPicker();
  if (S.nav !== 'sections') setNav('sections', { keepSelection: true });
  if (!fromPreview) post('select', { sectionId: sid, blockId: bid });
  ui.renderOutline();
  ui.renderInspector();
  if (focusPanel) showPanel('inspector');
  scrollRowIntoView(bid || sid);
  ui.updateDock();
  if (field) focusField(field);
}

/** Puts focus on the field the merchant clicked in the preview. */
function focusField(key) {
  window.setTimeout(() => {
    const row = $(`#inspector-body [data-field="${CSS.escape(key)}"]`);
    if (!row || row.hidden) return;
    const details = row.closest('details');
    if (details) details.open = true;
    const control = row.querySelector('input:not([type="hidden"]), textarea, select, [contenteditable], button');
    row.scrollIntoView({ block: 'nearest' });
    control?.focus({ preventScroll: true });
  }, 40);
}

export function deselect() {
  S.sel = null;
  post('select', { sectionId: null, blockId: null });
  ui.renderOutline();
  ui.renderThemeNav();
  ui.renderInspector();
  showPanel('nav');
  ui.updateDock();
}

export function openThemeGroup(groupKey, extra = {}) {
  if (!groupKey) return;
  S.sel = { kind: 'theme', group: groupKey, ...extra };
  post('select', { sectionId: null, blockId: null });
  if (S.nav !== 'theme') setNav('theme', { keepSelection: true });
  ui.renderThemeNav();
  ui.renderOutline();
  ui.renderInspector();
  showPanel('inspector');
  if (isPhone()) openSheet('inspector');
  ui.updateDock();
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
