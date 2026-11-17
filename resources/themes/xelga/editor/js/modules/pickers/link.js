/*
 * Link picker for `url` settings. A link is stored as a structured value
 * (ARCHITECTURE 4.2) that the store turns into an address when it renders,
 * so a renamed category never breaks a button:
 *
 *   { "type": "category", "id": 12 }      products, categories, brands, pages, posts
 *   { "type": "url", "href": "https://..." }  a web address
 *   { "type": "home" }                    also search, cart, account, wishlist, blog, brands, contact
 *
 * One field searches the store (`pick` kind links) and also takes a pasted
 * web address.
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

const KIND_WORDS = { product: 'Product', category: 'Category', brand: 'Brand', page: 'Page', blog_post: 'Blog post', post: 'Blog post' };
const KIND_ICONS = { product: 'tag', category: 'package', brand: 'plus-square', page: 'align-left', blog_post: 'align-left', post: 'align-left' };

const labels = new Map();
const keyOf = (v) => `${v.type}:${v.id}`;

/** Older and default values may still be plain strings; read them as web addresses. */
export function toLink(value) {
  if (!value) return null;
  if (typeof value === 'string') return { type: 'url', href: value };
  if (typeof value === 'object' && value.type) return value;
  return null;
}

function describe(value) {
  const v = toLink(value);
  if (!v) return { label: 'No link', meta: 'Pick a page or paste a web address', iconName: 'external', empty: true };
  if (v.type === 'url') return { label: v.href, meta: 'Web address', iconName: 'external' };
  const place = PLACES.find((p) => p.type === v.type);
  if (place) return { label: place.label, meta: 'Store page', iconName: place.iconName };
  const known = labels.get(keyOf(v));
  return {
    label: known || `${KIND_WORDS[v.type] || humanize(v.type)} ${v.id}`,
    meta: KIND_WORDS[v.type] || humanize(v.type),
    iconName: KIND_ICONS[v.type] || 'external',
    unknown: !known,
  };
}

/** A web address typed by the merchant, made into a link value, or null. */
function addressFrom(text) {
  let t = String(text || '').trim();
  if (!t) return null;
  if (/^www\./i.test(t)) t = `https://${t}`;
  return isSafeLink(t) ? { type: 'url', href: t } : null;
}

export function linkPicker(value, change, id) {
  const wrap = h('div', { class: 'picked' });
  const draw = (v) => {
    const d = describe(v);
    wrap.replaceChildren(h('button', {
      type: 'button', class: 'pick-row', id, 'aria-haspopup': 'dialog',
      'aria-label': `Link: ${d.label}. Change`,
      onclick: (e) => openLinks(e.currentTarget, v, (next) => {
        draw(next);
        change(next);
      }),
    }, h('span', { class: 'pick-row__ph' }, icon(d.iconName)),
    h('span', { class: 'pick-row__text' }, h('span', { class: 'pick-row__name', text: d.label }), h('span', { class: 'pick-row__meta', text: d.meta })),
    icon('chevron-down')));
    if (d.unknown && v?.id !== undefined) {
      ctx.api.pick('links', { ids: [`${v.type}:${v.id}`] })
        .then((res) => {
          res.items.forEach((item) => labels.set(keyOf(item), item.name || item.label));
          if (labels.has(keyOf(v)) && wrap.isConnected) draw(v);
        })
        .catch(() => { /* the plain description stays */ });
    }
  };
  draw(value);
  return wrap;
}

function openLinks(anchor, current, pick) {
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
    if (!q) PLACES.forEach((p) => rows.push(item(p.label, 'Store page', p.iconName, { type: p.type })));
    else PLACES.filter((p) => p.label.toLowerCase().includes(q.toLowerCase())).forEach((p) => rows.push(item(p.label, 'Store page', p.iconName, { type: p.type })));
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
