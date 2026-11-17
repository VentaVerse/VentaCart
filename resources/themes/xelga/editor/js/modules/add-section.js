/*
 * The add section panel: every section type this page may hold, searchable.
 * Opening a type shows its presets (named starting points with settings and
 * blocks), or its layouts when it has none. The new section's markup comes
 * from the server, so it looks exactly as it will on the store.
 *
 * Opened from the board, the panel adds at one spot: a gap between rows (a
 * row of columns, or one section full width) or the end of a column. Only
 * the types that fit the spot can be picked; the others stay listed, greyed,
 * under the reason: already on this page, or too wide for the spot.
 */

import { $, h, icon, schematic, toast } from './dom.js';
import { S, ui, doc, groupHandle, groupList, labelOf } from './state.js';
import { sectionTypes, allowedIn, sectionIcon, variantDrawing } from './catalog.js';
import { setNav, showPanel, select } from './selection.js';
import { addSection, addRowAt } from './actions.js';
import { rulesFor, layoutPayload } from './operations.js';
import { spanAt } from './board/model.js';
import { targetWords } from './board/words.js';

/** Why a type cannot be added at a spot; also the heading of its greyed group. */
export const ALREADY_THERE = 'Already on this page';
export const TOO_WIDE = 'Need a wider spot';

const FULL_WIDTH = { drop: 'gap', before: 'end' };

const panel = () => $('#nav-add');

/** The layouts a type is added with: its presets' layouts, or its own. */
function choicesOf(def) {
  if (def.presets?.length) return def.presets.map((p) => p.settings?.variant ?? null);
  return def.variants?.length ? def.variants.map((v) => v.value) : [null];
}

const spanOfChoice = (type, variant, rules) => rules.minSpan({ type, settings: variant == null ? {} : { variant } });

/**
 * Each section type of `catalog` (type => definition, already limited to
 * the types this page may hold) as `{type, disabled, reason?}` for a spot:
 * a type at its limit on the page is disabled as already there, one whose
 * narrowest layout is wider than the spot as too wide. Main sections are
 * never added, so they are left out.
 */
export function addableTypes(content, target, catalog, rules) {
  const span = spanAt(content, target || FULL_WIDTH, rules);
  const order = Array.isArray(content?.order) ? content.order : [];
  return Object.entries(catalog || {}).filter(([, def]) => def && !def.main).map(([type, def]) => {
    const count = order.filter((id) => content.sections?.[id]?.type === type).length;
    if (def.limit && count >= def.limit) return { type, disabled: true, reason: ALREADY_THERE };
    const narrowest = Math.min(...choicesOf(def).map((variant) => spanOfChoice(type, variant, rules)));
    if (narrowest > span) return { type, disabled: true, reason: TOO_WIDE };
    return { type, disabled: false };
  });
}

/**
 * Opens the panel. From the section list it adds at a place in the page's
 * order (`index`); from the board at a spot (`target`, a row model target).
 */
export function openAdd(index, target = null) {
  S.addAt = { index, target, open: null, query: '' };
  if (target) S.view = { type: 'add', target };
  setNav('add');
  showPanel('nav');
  ui.renderBoard?.();
}

function closeAdd() {
  const fromBoard = Boolean(S.addAt?.target);
  S.addAt = null;
  if (S.view.type === 'add') S.view = { type: 'board' };
  setNav('sections');
  if (fromBoard) ui.renderBoard?.();
}

function available() {
  const page = S.page || {};
  // A page's main section is always there, once; it is never added.
  return Object.fromEntries(Object.entries(sectionTypes())
    .filter(([, def]) => !def.main && allowedIn(def, page.pageType || page.handle, Boolean(page.custom), Boolean(page.extension))));
}

const template = () => groupHandle('template');
const content = () => doc(template())?.content || { order: [], sections: {} };

function whereText() {
  if (S.addAt.target) return `Goes ${targetWords(S.addAt.target)}.`;
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
  const gap = S.addAt.target?.drop === 'gap';
  const head = h('div', { class: 'add-head' },
    h('div', { class: 'add-head__top' },
      h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Back to sections', onclick: closeAdd }, icon('arrow-left')),
      h('h2', { class: 'add-head__title', text: gap ? 'Add here' : 'Add a section' })),
    h('p', { class: 'add-head__where', text: gap ? whereText() : `${whereText()} Pick a type, then a starting layout.` }),
    gap ? null : search);
  const parts = [head];
  // At a gap, a row of columns comes first; the search then looks through sections only.
  if (gap) {
    parts.push(rowChoices(S.addAt.target),
      h('div', { class: 'add-search' }, h('p', { class: 'add-cols__label', text: 'Or one section, full width' }), search));
  }
  parts.push(listEl);
  renderList(listEl);
  panel().replaceChildren(...parts);
  window.setTimeout(() => search.focus({ preventScroll: true }), 30);
}

/** "A row of columns": each split but one column, as a picture. */
function rowChoices(target) {
  const splits = Object.entries(layoutPayload()?.splits || {}).filter(([, s]) => s.parts.length > 1);
  return h('div', { class: 'add-cols' },
    h('p', { class: 'add-cols__label', id: 'add-cols-label', text: 'A row of columns' }),
    h('div', { class: 'splits splits--strip', role: 'group', 'aria-labelledby': 'add-cols-label' },
      ...splits.map(([split, s]) => h('button', {
        type: 'button', class: 'split-opt', 'aria-label': `Add a row: ${s.label.toLowerCase()}`,
        onclick: () => {
          if (!addRowAt(split, target)) return;
          closeAdd();
        },
      }, splitPicture(s.parts), h('span', { class: 'split-opt__label', text: s.label })))));
}

function splitPicture(parts) {
  const el = h('span', { class: 'splitpic splitpic--lg', 'aria-hidden': 'true' });
  parts.forEach((p) => el.append(h('span', { class: 'splitpic__col', style: { gridColumn: `span ${p}` } })));
  return el;
}

function renderList(listEl) {
  const q = S.addAt.query.trim().toLowerCase();
  const types = available();
  const rules = rulesFor(template());
  const states = addableTypes(content(), S.addAt.target, types, rules)
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
      h('ul', { class: 'add-sublist', role: 'list', 'aria-labelledby': id }, ...list.map((entry) => typeRow(entry, listEl, rules))));
  });
  const list = h('ul', { class: 'add-list', role: 'list' }, ...own.map((entry) => typeRow(entry, listEl, rules)), ...grouped);
  const grey = [ALREADY_THERE, TOO_WIDE].map((reason) => greyGroup(reason, states.filter((x) => x.reason === reason).map((x) => [x.type, types[x.type]])));
  listEl.replaceChildren(list, ...grey.filter(Boolean));
  if (!states.length) list.append(h('li', { class: 'add-type__unavailable', text: q ? 'No section matches that search.' : 'No more sections can be added to this page.' }));
}

/* Types that cannot be added here, listed under why. */
function greyGroup(title, entries) {
  if (!entries.length) return null;
  const id = `add-grey-${title === ALREADY_THERE ? 'used' : 'wide'}`;
  return h('div', { class: 'add-grey' },
    h('h3', { class: 'add-grey__title', id, text: title }),
    h('ul', { class: 'add-list add-list--grey', role: 'list', 'aria-labelledby': id },
      ...entries.map(([type, def]) => h('li', { class: 'add-type' },
        h('span', { class: 'add-type__head', 'aria-disabled': 'true' },
          icon(sectionIcon(type), 'o-row__icon'),
          h('span', { class: 'add-type__text' }, h('span', { class: 'add-type__name', text: def.label })))))));
}

/* One section type to add, opening to its starting layouts. */
function typeRow(entry, listEl, rules) {
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
  if (open) li.append(choices(type, def, rules));
  return li;
}

function choices(type, def, rules) {
  const span = spanAt(content(), S.addAt.target || FULL_WIDTH, rules);
  const card = (label, variant, description, run) => {
    const fit = spanOfChoice(type, variant, rules) <= span;
    return h('button', {
      type: 'button', class: `add-variant${fit ? '' : ' is-unfit'}`, title: fit ? description || label : 'Needs a wider spot',
      'aria-disabled': fit ? null : 'true',
      onclick: () => { if (fit) run(); },
    }, schematic(variantDrawing(type, variant) || 'm0,0,64,40'), h('span', { text: label }));
  };
  const presets = def.presets || [];
  if (presets.length) {
    return h('div', { class: 'add-type__variants' }, ...presets.map((p) => card(p.label, p.settings?.variant ?? null, p.description, () => add(type, { preset: p }))));
  }
  const variants = def.variants?.length ? def.variants : [{ value: null, label: def.label }];
  return h('div', { class: 'add-type__variants' }, ...variants.map((v) => card(v.label, v.value, v.description, () => add(type, { variant: v.value }))));
}

function add(type, { variant = null, preset = null }) {
  const target = S.addAt?.target || null;
  const index = Math.min(S.addAt?.index ?? groupList('template').length, groupList('template').length);
  const sid = addSection(type, { variant, preset, index, target });
  if (!sid) return;
  closeAdd();
  toast(`${labelOf(sid)} added. Change its text and photos to your own.`);
  select(sid);
}

export function initAdd() {
  ui.renderAdd = renderAdd;
}
