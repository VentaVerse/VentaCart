/*
 * Product, category and brand pickers (also pages and blog posts), on the
 * editor's read-only lookup route `pick`. They show what a picker needs:
 * name, thumbnail, for products the price as the store writes it, for
 * categories the parents' names, and whether the record is switched off.
 *
 * A product picker with `multiple` holds a hand-picked list in the
 * merchant's order. The list stays open while products are ticked; the
 * chosen ones are dragged into order, moved with Alt and the arrow keys, or
 * with the move buttons on touch screens.
 */

import { h, icon, announce } from '../dom.js';
import { ctx } from '../state.js';
import { openPopover, closePopover, reposition } from '../popover.js';
import { debounce, humanize, plural } from '../util.js';

const WORDS = {
  products: { one: 'product', many: 'products', choose: 'Choose a product', chooseMany: 'Choose products', add: 'Add products', none: 'No product' },
  categories: { one: 'category', many: 'categories', choose: 'Choose a category', none: 'No category' },
  brands: { one: 'brand', many: 'brands', choose: 'Choose a brand', none: 'No brand' },
  pages: { one: 'page', many: 'pages', choose: 'Choose a page', none: 'No page' },
  'blog-posts': { one: 'blog post', many: 'blog posts', choose: 'Choose a blog post', none: 'No blog post' },
};

const ICONS = { products: 'tag', categories: 'package', brands: 'plus-square', pages: 'align-left', 'blog-posts': 'align-left' };

/** Names of things already seen, so a chosen id shows its name. */
const known = new Map();
const keyOf = (kind, id) => `${kind}:${id}`;

export function remember(kind, item) {
  if (item && item.id !== undefined) known.set(keyOf(kind, item.id), item);
}

export const describe = (kind, id) => known.get(keyOf(kind, id)) || { id, name: `${humanize(WORDS[kind].one)} ${id}`, unknown: true };

/** Ids whose names were asked for and not found: deleted since they were chosen. */
const gone = new Set();

/** Looks up names for ids the editor has not seen yet. */
export async function fetchNames(kind, ids) {
  const missing = ids.filter((id) => id !== null && id !== '' && !known.has(keyOf(kind, id)) && !gone.has(keyOf(kind, id)));
  if (!missing.length) return;
  try {
    const res = await ctx.api.pick(kind, { ids: missing });
    res.items.forEach((item) => remember(kind, item));
    missing.filter((id) => !known.has(keyOf(kind, id))).forEach((id) => gone.add(keyOf(kind, id)));
  } catch {
    /* The id stays visible; the merchant can still change it. */
  }
}

export const isGone = (kind, id) => gone.has(keyOf(kind, id));

function metaOf(kind, item) {
  if (!item) return `Pick one from your ${WORDS[kind].many}`;
  if (gone.has(keyOf(kind, item.id))) return 'No longer in the store. Choose another.';
  if (item.unknown) return 'Loading the name';
  return [item.path ? `In ${item.path}` : null, item.price || null, item.active === false ? 'Switched off, not shown in the store' : null].filter(Boolean).join(' · ');
}

export function row(kind, item, { button = false, checked = null, onclick = null, trailing = null, attrs = {}, meta = null } = {}) {
  const tag = button ? 'button' : 'div';
  const thumb = item?.thumbnail || item?.image || item?.thumb;
  const missing = item && gone.has(keyOf(kind, item.id));
  return h(tag, {
    type: button ? 'button' : null,
    class: `pick-row${item?.active === false || missing ? ' is-inactive' : ''}`,
    onclick,
    role: checked === null ? null : 'menuitemcheckbox',
    'aria-checked': checked === null ? null : String(checked),
    ...attrs,
  },
  thumb ? h('img', { src: thumb, alt: '', loading: 'lazy', width: 40, height: 40 }) : h('span', { class: 'pick-row__ph' }, icon(missing ? 'alert' : ICONS[kind] || 'tag')),
  h('span', { class: 'pick-row__text' },
    h('span', { class: 'pick-row__name', text: item?.name || WORDS[kind].choose }),
    h('span', { class: 'pick-row__meta', text: meta ?? metaOf(kind, item) })),
  trailing);
}

/** Words for a lookup that failed, by what went wrong. */
function failure(kind, error) {
  if (error.kind === 'not-found') return { title: 'Not available here', text: `This store cannot list ${WORDS[kind].many} yet. The current choice is kept.` };
  if (error.kind === 'network') return { title: 'The store could not be reached', text: 'Check your connection, then search again.' };
  if (error.kind === 'too-many') return { title: 'Too many searches at once', text: 'Wait a moment, then type again.' };
  return { title: `${humanize(WORDS[kind].many)} could not be loaded`, text: error.message || 'Try again in a moment.' };
}

const stateBox = (iconName, title, text, action = null) => h('div', { class: 'pick-state', role: 'status' },
  h('span', { class: 'pick-state__icon' }, icon(iconName)),
  h('p', { class: 'pick-state__title', text: title }),
  h('p', { class: 'pick-state__text', text }),
  action);

/** Arrow keys move between the search box and the rows of a list. */
function listKeys(input, list) {
  const rows = () => Array.from(list.querySelectorAll('.pick-row:not(:disabled)'));
  const onKey = (e) => {
    const all = rows();
    if (!all.length) return;
    const i = all.indexOf(document.activeElement);
    let next = null;
    if (e.key === 'ArrowDown') next = i < 0 ? all[0] : all[Math.min(i + 1, all.length - 1)];
    else if (e.key === 'ArrowUp') next = i <= 0 ? input : all[i - 1];
    else if (e.key === 'Home' && i >= 0) next = all[0];
    else if (e.key === 'End' && i >= 0) next = all[all.length - 1];
    if (!next) return;
    e.preventDefault();
    next.focus();
  };
  input.addEventListener('keydown', onKey);
  list.addEventListener('keydown', onKey);
}

/**
 * The search list. `multiple` keeps it open while rows are ticked; `onPick`
 * answers whether the row is now chosen.
 */
export function openList(anchor, kind, { isChosen, onPick, onClear = null, multiple = false, full = () => false, metaFor = null, label = null }) {
  const words = WORDS[kind];
  const input = h('input', { class: 'input pop-search', type: 'search', placeholder: `Search ${words.many}`, 'aria-label': `Search ${words.many}`, autocomplete: 'off' });
  const list = h('div', { class: 'pick-list', role: 'menu', 'aria-label': humanize(words.many), 'aria-busy': 'false' });
  const note = h('p', { class: 'pop-foot', role: 'status' });
  const foot = multiple ? h('div', { class: 'pick-foot' }, note, h('button', { type: 'button', class: 'btn btn--primary btn--sm', onclick: () => closePopover() }, 'Done')) : note;
  let token = 0;
  let last = [];

  const drawRows = (items) => {
    list.replaceChildren(...items.map((item) => {
      const chosen = isChosen(item.id);
      return row(kind, item, {
        button: true,
        meta: metaFor ? metaFor(item) : null,
        checked: chosen,
        trailing: chosen ? icon('check') : null,
        attrs: { disabled: !chosen && full() ? true : null, title: !chosen && full() ? 'The list is full. Remove one to add another.' : null },
        onclick: () => {
          const nowChosen = onPick(item);
          if (!multiple) return;
          // The list stays open; redraw the ticks and keep focus on the row.
          drawRows(last);
          list.querySelectorAll('.pick-row')[items.indexOf(item)]?.focus({ preventScroll: true });
          announce(`${item.name} ${nowChosen ? 'added' : 'removed'}`);
          note.textContent = full() ? 'The list is full. Remove one to add another.' : '';
        },
      });
    }));
    if (onClear) {
      list.append(h('div', { class: 'menu__sep', role: 'separator' }), h('button', {
        type: 'button', class: 'pick-row', role: 'menuitem', onclick: () => { closePopover(); onClear(); },
      }, h('span', { class: 'pick-row__ph' }, icon('close')), h('span', { class: 'pick-row__text' }, h('span', { class: 'pick-row__name', text: words.none }))));
    }
  };

  const draw = async () => {
    const mine = ++token;
    const q = input.value.trim();
    list.setAttribute('aria-busy', 'true');
    list.replaceChildren(...Array.from({ length: 4 }, () => h('span', { class: 'sk pick-row__sk', 'aria-hidden': 'true' })));
    note.textContent = '';
    try {
      const res = await ctx.api.pick(kind, { q });
      if (mine !== token) return;
      res.items.forEach((item) => remember(kind, item));
      last = res.items;
      if (res.available === false) {
        list.replaceChildren(stateBox('info', `No ${words.many} on this store`, 'Install the blog in Extensions to choose posts here.'));
      } else if (!res.items.length) {
        list.replaceChildren(q
          ? stateBox('search', `No ${words.one} matches "${q}"`, 'Check the spelling, or search for one word of the name.')
          : stateBox(ICONS[kind] || 'tag', `No ${words.many} yet`, `Add ${words.many} in the admin first, then choose them here.`));
        if (onClear) drawRows([]);
      } else {
        drawRows(res.items);
      }
      const notes = [];
      if (res.more) notes.push('Showing the first 20. Type more of the name to narrow them down.');
      if (multiple && full()) notes.push('The list is full.');
      note.textContent = notes.join(' ');
    } catch (error) {
      if (mine !== token) return;
      const f = failure(kind, error);
      list.replaceChildren(stateBox('alert', f.title, f.text, error.kind === 'not-found' ? null : h('button', { type: 'button', class: 'btn btn--secondary btn--sm', onclick: draw }, 'Try again')));
    } finally {
      if (mine === token) list.setAttribute('aria-busy', 'false');
      reposition();
    }
  };

  input.addEventListener('input', debounce(draw, 250));
  input.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    list.querySelector('.pick-row:not(:disabled)')?.click();
  });
  listKeys(input, list);
  openPopover(anchor, h('div', { class: 'pick-pop' }, input, list, foot), { wide: true, label: label || (multiple ? words.chooseMany : words.choose) });
  draw();
  window.setTimeout(() => input.focus(), 20);
}

export function entityPicker(kind, value, change, setting = {}) {
  return setting.multiple && kind === 'products'
    ? multiPicker(kind, Array.isArray(value) ? value : [], change, setting)
    : singlePicker(kind, value, change);
}

function singlePicker(kind, value, change) {
  const wrap = h('div', { class: 'picked' });
  const draw = (v) => {
    const item = v !== null && v !== undefined && v !== '' ? describe(kind, v) : null;
    const btn = row(kind, item, {
      button: true,
      trailing: icon('chevron-down'),
      attrs: { 'aria-haspopup': 'dialog', 'aria-label': `${humanize(WORDS[kind].one)}: ${item?.name || 'none chosen'}. Change` },
      onclick: (e) => openList(e.currentTarget, kind, {
        isChosen: (id) => String(id) === String(v),
        onPick: (picked) => {
          closePopover();
          draw(picked.id);
          change(picked.id);
          return true;
        },
        onClear: item ? () => { draw(null); change(null); } : null,
      }),
    });
    wrap.replaceChildren(btn);
    if (item?.unknown) fetchNames(kind, [v]).then(() => wrap.isConnected && draw(v));
  };
  draw(value);
  return wrap;
}

function multiPicker(kind, value, change, setting) {
  const wrap = h('div', { class: 'picked picked--list' });
  const max = Math.min(Number(setting.max) || 48, 48);
  let chosen = [...value];
  let dragFrom = -1;

  const commitList = (next, message) => {
    chosen = next;
    draw();
    change([...chosen]);
    if (message) announce(message);
  };

  const move = (from, to, focus = true) => {
    if (to < 0 || to >= chosen.length || from === to) return;
    const next = [...chosen];
    next.splice(to, 0, next.splice(from, 1)[0]);
    commitList(next, `${describe(kind, chosen[from]).name} moved to position ${to + 1} of ${chosen.length}`);
    if (focus) window.setTimeout(() => wrap.querySelectorAll('.pick-row')[to]?.focus(), 0);
  };

  const draw = () => {
    const rows = chosen.map((id, i) => {
      const item = describe(kind, id);
      const r = row(kind, item, {
        trailing: h('span', { class: 'pick-row__actions' },
          h('button', { type: 'button', class: 'icon-btn icon-btn--sm pick-row__move', 'aria-label': `Move ${item.name} up`, disabled: i === 0 || null, onclick: () => move(i, i - 1, false) }, icon('chevron-up')),
          h('button', { type: 'button', class: 'icon-btn icon-btn--sm pick-row__move', 'aria-label': `Move ${item.name} down`, disabled: i === chosen.length - 1 || null, onclick: () => move(i, i + 1, false) }, icon('chevron-down')),
          h('button', {
            type: 'button', class: 'icon-btn icon-btn--sm', 'aria-label': `Remove ${item.name}`,
            onclick: () => commitList(chosen.filter((x) => x !== id), `${item.name} removed`),
          }, icon('close'))),
        attrs: {
          draggable: chosen.length > 1 ? 'true' : null,
          tabindex: '0',
          role: 'listitem',
          'aria-label': `${item.name}, ${i + 1} of ${chosen.length}.${chosen.length > 1 ? ' Alt and the arrow keys move it.' : ''}`,
        },
      });
      r.addEventListener('dragstart', (e) => {
        dragFrom = i;
        r.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', String(id)); } catch { /* some browsers refuse custom data */ }
      });
      r.addEventListener('dragover', (e) => {
        if (dragFrom < 0) return;
        e.preventDefault();
        const rect = r.getBoundingClientRect();
        r.classList.toggle('is-drop-before', e.clientY < rect.top + rect.height / 2);
        r.classList.toggle('is-drop-after', e.clientY >= rect.top + rect.height / 2);
      });
      r.addEventListener('dragleave', () => r.classList.remove('is-drop-before', 'is-drop-after'));
      r.addEventListener('drop', (e) => {
        e.preventDefault();
        const after = r.classList.contains('is-drop-after');
        r.classList.remove('is-drop-before', 'is-drop-after');
        if (dragFrom < 0) return;
        let to = after ? i + 1 : i;
        if (to > dragFrom) to -= 1;
        const from = dragFrom;
        dragFrom = -1;
        move(from, to, false);
      });
      r.addEventListener('dragend', () => {
        dragFrom = -1;
        r.classList.remove('is-dragging');
      });
      r.addEventListener('keydown', (e) => {
        if (!e.altKey || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
        e.preventDefault();
        move(i, i + (e.key === 'ArrowUp' ? -1 : 1));
      });
      return r;
    });
    const count = chosen.length
      ? h('p', { class: 'field__help field__help--after', text: `${plural(chosen.length, WORDS[kind].one, WORDS[kind].many)} chosen, shown in this order${chosen.length >= max ? '. The list is full' : ''}.` })
      : h('p', { class: 'pick-empty', text: `No ${WORDS[kind].many} chosen yet. Products you choose show in the order you set here.` });
    // The add button stays the same element, so the open list keeps its place.
    addBtn.disabled = chosen.length >= max;
    addLabel.textContent = chosen.length ? WORDS[kind].add : WORDS[kind].chooseMany;
    wrap.replaceChildren(
      chosen.length ? h('div', { class: 'pick-chosen', role: 'list', 'aria-label': `Chosen ${WORDS[kind].many}` }, ...rows) : '',
      count,
      addBtn);
  };
  const addLabel = h('span');
  const addBtn = h('button', {
    type: 'button', class: 'btn btn--secondary btn--sm', 'aria-haspopup': 'dialog',
    onclick: (e) => openList(e.currentTarget, kind, {
      multiple: true,
      full: () => chosen.length >= max,
      isChosen: (id) => chosen.some((c) => String(c) === String(id)),
      onPick: (picked) => {
        const has = chosen.some((c) => String(c) === String(picked.id));
        if (!has && chosen.length >= max) return false;
        commitList(has ? chosen.filter((c) => String(c) !== String(picked.id)) : [...chosen, picked.id]);
        return !has;
      },
    }),
  }, icon('plus', 'icon--sm'), addLabel);
  draw();
  fetchNames(kind, chosen).then(() => wrap.isConnected && draw());
  return wrap;
}
