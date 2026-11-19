/*
 * Link picker for `url` settings. A link is stored as a structured value
 * (ARCHITECTURE 4.2) that the store turns into an address when it renders,
 * so a renamed category never breaks a button:
 *
 *   { "type": "category", "id": 12 }      products, categories, brands, pages, posts
 *   { "type": "url", "href": "https://..." }  a web address
 *   { "type": "home" }                    also search, cart, account, wishlist, blog, brands, contact
 *   { "type": "these-products" }          the listing of what the section shows
 *
 * One field searches the store (`pick` kind links) and also takes a pasted
 * web address.
 *
 * A setting with `follows_products` sits beside a list of products. It
 * offers "These products" first: the store page that lists what the
 * section's "Products to show" picks, so the link follows that choice. Its
 * line says where it goes now, and changes as soon as the choice does.
 */

import { h, icon } from '../dom.js';
import { ctx } from '../state.js';
import { openPopover, closePopover, reposition } from '../popover.js';
import { debounce, humanize } from '../util.js';
import { isSafeLink } from '../forms/richtext.js';

const PLACES = [
  { type: 'home', label: 'Home page', iconName: 'layout' },
  { type: 'search', label: 'All products', iconName: 'search' },
  { type: 'cart', label: 'Cart', iconName: 'cart' },
  { type: 'account', label: 'Customer account', iconName: 'user' },
  { type: 'wishlist', label: 'Wishlist', iconName: 'heart' },
  { type: 'blog', label: 'Blog', iconName: 'align-left' },
  { type: 'brands', label: 'All brands', iconName: 'plus-square' },
  { type: 'contact', label: 'Contact page', iconName: 'mail' },
];

export const THESE_PRODUCTS = 'these-products';

/** Where "These products" goes for each "Products to show" choice that has a page. */
const SOURCE_WORDS = {
  newest: 'Newest',
  'best-sellers': 'Best sellers',
  'most-viewed': 'Most viewed',
  'on-sale': 'On sale',
};

export const HAND_PICKED_NOTE = 'Picked products have no page of their own, so the link is hidden. Choose another page to show it.';

const KIND_WORDS = { product: 'Product', category: 'Category', brand: 'Brand', page: 'Page', blog_post: 'Blog post', post: 'Blog post' };
const KIND_ICONS = { product: 'tag', category: 'package', brand: 'plus-square', page: 'align-left', blog_post: 'align-left', post: 'align-left' };

const labels = new Map();
/** Records named by the store as switched off: a link to one is not shown. */
const inactive = new Set();
/** Records the store no longer has. */
const missing = new Set();
const keyOf = (v) => `${v.type}:${v.id}`;

const recordId = (value) => {
  const n = Number(value);
  return Number.isInteger(n) && n > 0 ? n : null;
};

/**
 * Where "These products" leads for the section's current settings
 * (`read(key)`): a meta line, whether the link is shown, and the record
 * whose name is still to be asked for.
 */
export function followTarget(read) {
  const source = read?.('source');
  if (SOURCE_WORDS[source]) return { meta: SOURCE_WORDS[source], shown: true };
  if (source === 'category' || source === 'brand') {
    const word = source === 'category' ? 'Category' : 'Brand';
    const id = recordId(read(source));
    if (id === null) return { meta: `${word}: none chosen yet, so the link is hidden`, shown: false };
    const record = { type: source, id };
    if (missing.has(keyOf(record))) return { meta: `${word}: no longer in the store, so the link is hidden`, shown: false };
    const name = labels.get(keyOf(record));
    if (!name) return { meta: `${word} ${id}`, shown: true, ask: record };
    if (inactive.has(keyOf(record))) return { meta: `${word}: ${name}, switched off, so the link is hidden`, shown: false };
    return { meta: `${word}: ${name}`, shown: true };
  }
  if (source === 'hand-picked') return { meta: 'Picked by hand', shown: false, note: HAND_PICKED_NOTE };
  return { meta: 'All products', shown: true };
}

/** Ask the store for the names of records, then run `done` if any came back. */
function learn(records, done) {
  if (!records.length || !ctx.api?.pick) return;
  ctx.api.pick('links', { ids: records.map(keyOf) })
    .then((res) => {
      res.items.forEach((item) => {
        labels.set(keyOf(item), item.name || item.label);
        if (item.active === false) inactive.add(keyOf(item));
        else inactive.delete(keyOf(item));
      });
      records.filter((r) => !labels.has(keyOf(r))).forEach((r) => missing.add(keyOf(r)));
      done();
    })
    .catch(() => { /* the plain description stays */ });
}

/** Older and default values may still be plain strings; read them as web addresses. */
export function toLink(value) {
  if (!value) return null;
  if (typeof value === 'string') return { type: 'url', href: value };
  if (typeof value === 'object' && value.type) return value;
  return null;
}

export function describe(value, read = null) {
  const v = toLink(value);
  if (!v) return { label: 'No link', meta: 'Pick a page or paste a web address', iconName: 'external', empty: true };
  if (v.type === THESE_PRODUCTS) {
    const target = followTarget(read);
    return { label: 'These products', meta: target.meta, iconName: 'tag', note: target.note || null, ask: target.ask || null };
  }
  if (v.type === 'url') return { label: v.href, meta: 'Web address', iconName: 'external' };
  const place = PLACES.find((p) => p.type === v.type);
  if (place) return { label: place.label, meta: 'Store page', iconName: place.iconName };
  const known = labels.get(keyOf(v));
  return {
    label: known || `${KIND_WORDS[v.type] || humanize(v.type)} ${v.id}`,
    meta: KIND_WORDS[v.type] || humanize(v.type),
    iconName: KIND_ICONS[v.type] || 'external',
    unknown: !known && !missing.has(keyOf(v)),
  };
}

/** A web address typed by the merchant, made into a link value, or null. */
function addressFrom(text) {
  let t = String(text || '').trim();
  if (!t) return null;
  if (/^www\./i.test(t)) t = `https://${t}`;
  return isSafeLink(t) ? { type: 'url', href: t } : null;
}

export function linkPicker(value, change, id, setting = null, fctx = null) {
  const wrap = h('div', { class: 'picked' });
  const follows = setting?.follows_products === true;
  const read = follows ? fctx?.read || null : null;
  let shown = value;
  const draw = (v) => {
    shown = v;
    const d = describe(v, read);
    const noteId = `${id}-note`;
    wrap.replaceChildren(h('button', {
      type: 'button', class: 'pick-row', id, 'aria-haspopup': 'dialog',
      'aria-label': `Link: ${d.label}, ${d.meta}. Change`,
      'aria-describedby': d.note ? noteId : null,
      onclick: (e) => openLinks(e.currentTarget, v, (next) => {
        draw(next);
        change(next);
      }, follows ? read : null),
    }, h('span', { class: 'pick-row__ph' }, icon(d.iconName)),
    h('span', { class: 'pick-row__text' }, h('span', { class: 'pick-row__name', text: d.label }), h('span', { class: 'pick-row__meta', text: d.meta })),
    icon('chevron-down')));
    if (d.note) wrap.append(h('p', { class: 'field__help field__help--after', id: noteId, text: d.note }));
    const ask = d.ask || (d.unknown && v?.id !== undefined ? v : null);
    if (ask) learn([ask], () => { if (wrap.isConnected && shown === v) draw(v); });
  };
  draw(value);
  // "These products" describes the section's other settings: draw it again when they change.
  if (follows) fctx?.onRefresh?.(() => { if (toLink(shown)?.type === THESE_PRODUCTS) draw(shown); });
  return wrap;
}

/**
 * The store pages offered for a search (all of them when it is empty):
 * "These products" first where the field follows the section's products
 * (`followRead` reads the section's settings), then the fixed pages.
 */
export function placeRows(q = '', followRead = null) {
  const words = q.trim().toLowerCase();
  const rows = [];
  if (followRead && (!words || 'these products'.includes(words))) {
    rows.push({ label: 'These products', meta: followTarget(followRead).meta, iconName: 'tag', value: { type: THESE_PRODUCTS } });
  }
  PLACES.filter((p) => !words || p.label.toLowerCase().includes(words))
    .forEach((p) => rows.push({ label: p.label, meta: 'Store page', iconName: p.iconName, value: { type: p.type } }));
  return rows;
}

function openLinks(anchor, current, pick, followRead = null) {
  const input = h('input', {
    class: 'input pop-search', type: 'text', inputmode: 'url', autocomplete: 'off',
    placeholder: 'Search the store or paste a web address', 'aria-label': 'Search the store or paste a web address',
  });
  const list = h('div', { class: 'pick-list', role: 'menu', 'aria-label': 'Places to link to', 'aria-busy': 'false' });
  const error = h('p', { class: 'field__error', hidden: true });
  let token = 0;

  const choose = (value) => {
    closePopover();
    pick(value);
  };

  const item = (label, meta, iconName, value, thumb = null, inactive = false) => h('button', {
    type: 'button', class: `pick-row${inactive ? ' is-inactive' : ''}`, role: 'menuitem', onclick: () => choose(value),
  }, thumb ? h('img', { src: thumb, alt: '', loading: 'lazy', width: 40, height: 40 }) : h('span', { class: 'pick-row__ph' }, icon(iconName)),
  h('span', { class: 'pick-row__text' }, h('span', { class: 'pick-row__name', text: label }), h('span', { class: 'pick-row__meta', text: meta })));

  const metaOf = (r) => [
    KIND_WORDS[r.type] || humanize(r.type),
    r.path ? `in ${r.path}` : null,
    r.price || null,
    r.active === false ? 'switched off, the link is not shown' : null,
  ].filter(Boolean).join(' · ');

  const draw = async () => {
    const q = input.value.trim();
    const mine = ++token;
    error.hidden = true;
    const rows = [];
    const address = addressFrom(q);
    if (address) rows.push(item(`Use ${address.href}`, 'Web address', 'external', address));
    else if (/^(javascript|data|vbscript):/i.test(q)) {
      error.textContent = 'That kind of address is not allowed. Use a page address starting with /, or https://.';
      error.hidden = false;
    }
    placeRows(q, followRead).forEach((p) => rows.push(item(p.label, p.meta, p.iconName, p.value)));
    list.replaceChildren(...rows);
    if (current) {
      list.append(h('div', { class: 'menu__sep', role: 'separator' }),
        h('button', { type: 'button', class: 'pick-row', role: 'menuitem', onclick: () => choose(null) },
          h('span', { class: 'pick-row__ph' }, icon('close')),
          h('span', { class: 'pick-row__text' }, h('span', { class: 'pick-row__name', text: 'Remove link' }))));
    }
    reposition();
    if (!q || address) return;
    const loading = h('div', { class: 'pick-sk', 'aria-hidden': 'true' }, ...Array.from({ length: 3 }, () => h('span', { class: 'sk pick-row__sk' })));
    list.prepend(loading);
    list.setAttribute('aria-busy', 'true');
    try {
      const res = await ctx.api.pick('links', { q });
      if (mine !== token) return;
      const found = res.items.map((r) => {
        const value = { type: r.type, id: r.id };
        labels.set(keyOf(value), r.name || r.label);
        return item(r.name || r.label, metaOf(r), KIND_ICONS[r.type] || 'external', value, r.thumbnail || null, r.active === false);
      });
      loading.replaceWith(...found);
      if (!found.length) list.prepend(h('p', { class: 'pop-foot', role: 'status', text: `Nothing in the store is called "${q}". Paste a full web address to link elsewhere.` }));
      else if (res.more) list.append(h('p', { class: 'pop-foot', text: 'Showing the first matches. Type more of the name to narrow them down.' }));
    } catch (err) {
      if (mine !== token) return;
      loading.replaceWith(h('p', { class: 'pop-foot', role: 'status', text: err.kind === 'network' ? 'The store could not be reached, so only web addresses work right now.' : `The store could not be searched. ${err.message}` }));
    } finally {
      if (mine === token) list.setAttribute('aria-busy', 'false');
      reposition();
    }
  };

  input.addEventListener('input', debounce(draw, 250));
  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      list.querySelector('.pick-row')?.focus();
      return;
    }
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const address = addressFrom(input.value);
    if (address) choose(address);
    else list.querySelector('.pick-row')?.click();
  });
  list.addEventListener('keydown', (e) => {
    const rows = Array.from(list.querySelectorAll('.pick-row'));
    const i = rows.indexOf(document.activeElement);
    if (i < 0 || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;
    e.preventDefault();
    (e.key === 'ArrowUp' ? (i === 0 ? input : rows[i - 1]) : rows[Math.min(i + 1, rows.length - 1)]).focus();
  });
  openPopover(anchor, h('div', { class: 'pick-pop' }, input, error, list), { wide: true, label: 'Choose a link' });
  draw();
  window.setTimeout(() => input.focus(), 20);
}
