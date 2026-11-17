/*
 * The add section panel: every section type this page may hold, searchable.
 * Opening a type shows its presets (named starting points with settings and
 * blocks), or its layouts when it has none. The new section's markup comes
 * from the server, so it looks exactly as it will on the store.
 */

import { $, h, icon, schematic, toast } from './dom.js';
import { S, ui, groupList, labelOf } from './state.js';
import { sectionTypes, allowedIn, sectionIcon, variantDrawing } from './catalog.js';
import { setNav, showPanel, select } from './selection.js';
import { addSection, countOfType } from './actions.js';

const panel = () => $('#nav-add');

export function openAdd(index) {
  S.addAt = { index, open: null, query: '' };
  setNav('add');
  showPanel('nav');
}

function closeAdd() {
  S.addAt = null;
  setNav('sections');
}

function available() {
  const page = S.page || {};
  // A page's main section is always there, once; it is never added.
  return Object.entries(sectionTypes()).filter(([, def]) => !def.main && allowedIn(def, page.pageType || page.handle, Boolean(page.custom), Boolean(page.extension)));
}

export function renderAdd() {
  if (!S.addAt) return;
  const list = groupList('template');
  const after = list[S.addAt.index - 1];
  const where = after ? `Goes below ${labelOf(after.id)}.` : 'Goes at the top of the page.';
  const listEl = h('ul', { class: 'add-list', role: 'list' });
  const search = h('input', {
    class: 'input', type: 'search', placeholder: 'Search sections', 'aria-label': 'Search sections', value: S.addAt.query,
    oninput: (e) => {
      S.addAt.query = e.target.value;
      renderList(listEl);
    },
  });
  const head = h('div', { class: 'add-head' },
    h('div', { class: 'add-head__top' },
      h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Back to sections', onclick: closeAdd }, icon('arrow-left')),
      h('h2', { class: 'add-head__title', text: 'Add a section' })),
    h('p', { class: 'add-head__where', text: `${where} Pick a type, then a starting layout.` }),
    search);
  renderList(listEl);
  panel().replaceChildren(head, listEl);
  window.setTimeout(() => search.focus({ preventScroll: true }), 30);
}

function renderList(listEl) {
  const q = S.addAt.query.trim().toLowerCase();
  const items = available().filter(([, def]) => !q || def.label.toLowerCase().includes(q) || (def.description || '').toLowerCase().includes(q));
  // The theme's own sections first, then one list per extension, labelled
  // by its name, so a screen reader announces whose sections they are.
  items.sort(([, a], [, b]) => (a.source ? 1 : 0) - (b.source ? 1 : 0) || String(a.source_label || '').localeCompare(String(b.source_label || '')));
  const own = [];
  const groups = new Map();
  items.forEach((item) => {
    const label = item[1].source ? item[1].source_label || item[1].source : null;
    if (!label) own.push(item);
    else {
      if (!groups.has(label)) groups.set(label, []);
      groups.get(label).push(item);
    }
  });
  let n = 0;
  const grouped = [...groups].map(([label, list]) => {
    n += 1;
    const id = `add-group-${n}`;
    return h('li', {},
      h('h3', { class: 'add-group', id, text: label }),
      h('ul', { class: 'add-sublist', role: 'list', 'aria-labelledby': id }, ...list.map((entry) => typeRow(entry, listEl))));
  });
  listEl.replaceChildren(...own.map((entry) => typeRow(entry, listEl)), ...grouped);
  if (!items.length) listEl.append(h('li', { class: 'add-type__unavailable', text: q ? 'No section matches that search.' : 'No more sections can be added to this page.' }));
}

/* One section type to add, opening to its starting layouts. */
function typeRow(entry, listEl) {
  const handle = S.page?.handle;
  const [type, def] = entry;
  const open = S.addAt.open === type;
  const atLimit = Boolean(def.limit) && countOfType(handle, type) >= def.limit;
  const li = h('li', { class: `add-type${open ? ' is-open' : ''}${atLimit ? ' is-full' : ''}` },
    h('button', {
      type: 'button', class: 'add-type__head', 'aria-expanded': String(open),
      onclick: () => {
        S.addAt.open = open ? null : type;
        renderList(listEl);
      },
    }, icon(sectionIcon(type), 'o-row__icon'),
    h('span', { class: 'add-type__text' },
      h('span', { class: 'add-type__name', text: def.label }),
      h('span', { class: 'add-type__desc', text: atLimit ? `This page already has ${def.limit === 1 ? 'one' : def.limit}, the most it can hold.` : def.description || '' }))));
  if (open && !atLimit) li.append(choices(type, def));
  return li;
}

function choices(type, def) {
  const presets = def.presets || [];
  if (presets.length) {
    return h('div', { class: 'add-type__variants' }, ...presets.map((p) => h('button', {
      type: 'button', class: 'add-variant', title: p.description || p.label,
      onclick: () => add(type, { preset: p }),
    }, schematic(variantDrawing(type, p.settings?.variant) || 'm0,0,64,40'), h('span', { text: p.label }))));
  }
  const variants = def.variants?.length ? def.variants : [{ value: null, label: def.label }];
  return h('div', { class: 'add-type__variants' }, ...variants.map((v) => h('button', {
    type: 'button', class: 'add-variant', title: v.description || v.label,
    onclick: () => add(type, { variant: v.value }),
  }, schematic(variantDrawing(type, v.value) || 'm0,0,64,40'), h('span', { text: v.label }))));
}

function add(type, { variant = null, preset = null }) {
  const index = Math.min(S.addAt?.index ?? groupList('template').length, groupList('template').length);
  const sid = addSection(type, { variant, preset, index });
  if (!sid) return;
  closeAdd();
  toast(`${labelOf(sid)} added. Change its text and photos to your own.`);
  select(sid);
}

export function initAdd() {
  ui.renderAdd = renderAdd;
}
