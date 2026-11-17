/*
 * The add section panel of the section list, shown beside the store
 * preview: every section type this page may hold, searchable. Opening a
 * type shows its presets (named starting points with settings and blocks),
 * or its layouts when it has none. The new section's markup comes from the
 * server, so it looks exactly as it will on the store. It adds at a place
 * in the page's order.
 *
 * While the layout board is shown, adding goes through the section picker
 * instead (board/picker.js), at the gap between rows where that place is.
 * Types that cannot be added stay listed, greyed, under the reason.
 */

import { $, h, icon, schematic, toast } from './dom.js';
import { S, ui, doc, groupHandle, groupList, labelOf } from './state.js';
import { sectionIcon, variantDrawing } from './catalog.js';
import { setNav, showPanel, select } from './selection.js';
import { addSection } from './actions.js';
import { rulesFor, hasLayout, layoutPayload } from './operations.js';
import { rowsOf, locate } from './board/model.js';
import { addableTypes, ALREADY_THERE } from './board/choices.js';
import { availableTypes as available } from './board/picker.js';

const panel = () => $('#nav-add');

const template = () => groupHandle('template');
const content = () => doc(template())?.content || { order: [], sections: {} };

/**
 * The gap between rows that holds a place in the page's order: before
 * the row of the section now at that place, or the end of the page.
 */
function gapAt(index) {
  const c = content();
  const layout = layoutPayload();
  const sid = c.order?.[index];
  if (!sid || !layout) return { drop: 'gap', before: 'end' };
  const at = hasLayout(c) ? locate(c, sid, layout) : null;
  if (at?.where === 'side') return { drop: 'gap', before: at.side === 'left' ? rowsOf(c, layout)[0]?.id ?? 'end' : 'end' };
  const rowId = at?.rowId ?? rowsOf(c, layout).find((r) => r.columns.flat().includes(sid))?.id;
  return { drop: 'gap', before: rowId ?? 'end' };
}

/**
 * Opens the panel to add at a place in the page's order (`index`). With
 * the board shown, the section picker opens at that place instead, so the
 * board has one way to add.
 */
export function openAdd(index) {
  if (S.canvas === 'board' && ui.openPicker) {
    ui.openPicker({ target: gapAt(index) });
    return;
  }
  S.addAt = { index, open: null, query: '' };
  setNav('add');
  showPanel('nav');
}

function closeAdd() {
  S.addAt = null;
  setNav('sections');
}

function whereText() {
  const list = groupList('template');
  const after = list[S.addAt.index - 1];
  return after ? `Goes below ${labelOf(after.id)}.` : 'Goes at the top of the page.';
}

export function renderAdd() {
  if (!S.addAt) return;
  const listEl = h('div', { class: 'add-lists' });
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
    h('p', { class: 'add-head__where', text: `${whereText()} Pick a type, then a starting layout.` }),
    search);
  renderList(listEl);
  panel().replaceChildren(head, listEl);
  window.setTimeout(() => search.focus({ preventScroll: true }), 30);
}

function renderList(listEl) {
  const q = S.addAt.query.trim().toLowerCase();
  const types = available();
  const rules = rulesFor(template());
  const states = addableTypes(content(), null, types, rules)
    .filter(({ type }) => !q || types[type].label.toLowerCase().includes(q) || (types[type].description || '').toLowerCase().includes(q));
  const usable = states.filter((x) => !x.disabled).map((x) => [x.type, types[x.type]]);
  // The theme's own sections first, then one list per extension, labelled
  // by its name, so a screen reader announces whose sections they are.
  usable.sort(([, a], [, b]) => (a.source ? 1 : 0) - (b.source ? 1 : 0) || String(a.source_label || '').localeCompare(String(b.source_label || '')));
  const own = [];
  const groups = new Map();
  usable.forEach((item) => {
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
  const list = h('ul', { class: 'add-list', role: 'list' }, ...own.map((entry) => typeRow(entry, listEl)), ...grouped);
  const grey = greyGroup(ALREADY_THERE, states.filter((x) => x.disabled).map((x) => [x.type, types[x.type]]));
  listEl.replaceChildren(list, ...[grey].filter(Boolean));
  if (!states.length) list.append(h('li', { class: 'add-type__unavailable', text: q ? 'No section matches that search.' : 'No more sections can be added to this page.' }));
}

/* Types that cannot be added here, listed under why. */
function greyGroup(title, entries) {
  if (!entries.length) return null;
  const id = 'add-grey-used';
  return h('div', { class: 'add-grey' },
    h('h3', { class: 'add-grey__title', id, text: title }),
    h('ul', { class: 'add-list add-list--grey', role: 'list', 'aria-labelledby': id },
      ...entries.map(([type, def]) => h('li', { class: 'add-type' },
        h('span', { class: 'add-type__head', 'aria-disabled': 'true' },
          icon(sectionIcon(type), 'o-row__icon'),
          h('span', { class: 'add-type__text' }, h('span', { class: 'add-type__name', text: def.label })))))));
}

/* One section type to add, opening to its starting layouts. */
function typeRow(entry, listEl) {
  const [type, def] = entry;
  const open = S.addAt.open === type;
  const li = h('li', { class: `add-type${open ? ' is-open' : ''}` },
    h('button', {
      type: 'button', class: 'add-type__head', 'aria-expanded': String(open),
      onclick: () => {
        S.addAt.open = open ? null : type;
        renderList(listEl);
      },
    }, icon(sectionIcon(type), 'o-row__icon'),
    h('span', { class: 'add-type__text' },
      h('span', { class: 'add-type__name', text: def.label }),
      h('span', { class: 'add-type__desc', text: def.description || '' }))));
  if (open) li.append(choices(type, def));
  return li;
}

/* A place in the order is a full-width row of its own, so every layout fits. */
function choices(type, def) {
  const card = (label, variant, description, run) => h('button', {
    type: 'button', class: 'add-variant', title: description || label, onclick: run,
  }, schematic(variantDrawing(type, variant) || 'm0,0,64,40'), h('span', { text: label }));
  const presets = def.presets || [];
  if (presets.length) {
    return h('div', { class: 'add-type__variants' }, ...presets.map((p) => card(p.label, p.settings?.variant ?? null, p.description, () => add(type, { preset: p }))));
  }
  const variants = def.variants?.length ? def.variants : [{ value: null, label: def.label }];
  return h('div', { class: 'add-type__variants' }, ...variants.map((v) => card(v.label, v.value, v.description, () => add(type, { variant: v.value }))));
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
