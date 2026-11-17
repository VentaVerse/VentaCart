/*
 * Menu picker. Menus are core data, edited in core's menus screen; the
 * setting stores the menu's key. The list comes with the boot payload, or
 * from the `pick` route, kind menus.
 */

import { h } from '../dom.js';
import { ctx } from '../state.js';
import { humanize } from '../util.js';

let menus = null;
let loading = null;

function loadMenus() {
  if (menus) return Promise.resolve(menus);
  // The boot payload already lists the store's menus.
  const fromBoot = ctx.boot.option_sources?.menus;
  if (Array.isArray(fromBoot) && fromBoot.length) {
    menus = fromBoot.map((m) => ({ value: String(m.value ?? m.key), label: m.label || m.name || humanize(m.value ?? m.key) }));
    return Promise.resolve(menus);
  }
  if (!loading) {
    loading = ctx.api.pick('menus')
      .then((res) => {
        menus = res.items.map((m) => ({ value: String(m.key ?? m.value ?? m.id), label: m.name || m.label || humanize(m.key) }));
        return menus;
      })
      .catch((error) => {
        loading = null;
        throw error;
      });
  }
  return loading;
}

/* The menus screen opens on the menu named by ?menu=<key>, or on the main
 * menu when none is named. */
export function menuEditHref(base, key) {
  if (!base) return null;
  if (!key) return base;
  const url = new URL(base, window.location.href);
  url.searchParams.set('menu', String(key));
  return url.toString();
}

export function menuPicker(value, change, id) {
  const editUrl = ctx.boot.user?.can?.menus_edit === false ? null : ctx.boot.routes?.menus_edit || ctx.boot.routes?.menus || null;
  const edit = editUrl ? h('a', { class: 'link-btn', href: menuEditHref(editUrl, value), target: '_blank', rel: 'noopener' }, 'Edit menus') : null;
  const select = h('select', {
    class: 'select',
    id,
    onchange: (e) => {
      if (edit) edit.href = menuEditHref(editUrl, e.target.value);
      change(e.target.value);
    },
  });
  const note = h('p', { class: 'field__help field__help--after', hidden: true });
  const fill = (list, busy = false) => {
    const options = [...list];
    if (value && !options.some((o) => o.value === String(value))) options.unshift({ value: String(value), label: humanize(value) });
    if (!value) options.unshift({ value: '', label: 'No menu' });
    select.replaceChildren(...options.map((o) => h('option', { value: o.value, selected: o.value === String(value ?? '') }, o.label)));
    select.disabled = busy;
  };
  fill([], true);
  loadMenus()
    .then((list) => fill(list))
    .catch((error) => {
      fill([]);
      note.textContent = `The menu list could not be loaded. ${error.message}`;
      note.hidden = false;
    });
  return h('div', { class: 'menu-pick' }, select, note,
    edit ? h('p', { class: 'field__help field__help--after' }, edit) : null);
}
