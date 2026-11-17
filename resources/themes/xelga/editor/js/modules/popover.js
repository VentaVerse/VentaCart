/*
 * Popovers anchored to a button (menus, pickers, confirmations) and the one
 * true dialog the editor has, for an edit conflict, where the merchant must
 * choose before anything else is saved.
 */

import { $, $$, h, icon } from './dom.js';

let popover = null;

// The anchor of the open popover when the pointer went down on it, so the
// click that follows closes the popover instead of opening it again.
let pressedAnchor = null;

export const isPopoverOpen = () => Boolean(popover);

export function closePopover({ restoreFocus = true } = {}) {
  if (!popover) return;
  const { el, anchor, onClose } = popover;
  anchor?.setAttribute('aria-expanded', 'false');
  el.remove();
  popover = null;
  onClose?.();
  if (restoreFocus && anchor && document.body.contains(anchor) && !anchor.matches(':focus')) anchor.focus({ preventScroll: true });
}

/* A wide popover (a picker) on a phone is a sheet of its own at the bottom. */
const asSheet = (el) => el.classList.contains('popover--wide') && window.matchMedia('(max-width: 719px)').matches;

function place(el, anchor, align) {
  if (asSheet(el)) {
    el.classList.add('popover--sheet');
    el.style.left = '';
    el.style.top = '';
    return;
  }
  el.classList.remove('popover--sheet');
  const r = anchor.getBoundingClientRect();
  const pw = el.offsetWidth;
  const ph = el.offsetHeight;
  let left = align === 'end' ? r.right - pw : r.left;
  left = Math.max(8, Math.min(left, window.innerWidth - pw - 8));
  let top = r.bottom + 6;
  if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 6);
  el.style.left = `${left}px`;
  el.style.top = `${top}px`;
}

export function openPopover(anchor, content, { wide = false, align = 'start', label = null, onClose = null, focus = true } = {}) {
  // Pressing the button of the open popover again closes it, as a toggle.
  const toggled = pressedAnchor && pressedAnchor === anchor && popover?.anchor === anchor;
  pressedAnchor = null;
  if (toggled) {
    closePopover();
    return null;
  }
  closePopover({ restoreFocus: false });
  const el = h('div', { class: `popover${wide ? ' popover--wide' : ''}`, role: 'dialog', 'aria-label': label }, content);
  $('#popover-layer').append(el);
  anchor?.setAttribute('aria-expanded', 'true');
  place(el, anchor, align);
  popover = { el, anchor, align, onClose };
  if (focus) {
    window.setTimeout(() => el.querySelector('[role="menuitem"]:not(:disabled), [role="menuitemradio"]:not(:disabled), input, button:not(:disabled)')?.focus({ preventScroll: true }), 10);
  }
  return el;
}

/** Moves an open popover after its content changed size. */
export function reposition() {
  if (popover?.anchor && document.body.contains(popover.anchor)) place(popover.el, popover.anchor, popover.align);
}

let headingSeq = 0;

export function openMenu(anchor, items, opts = {}) {
  const menu = h('div', { class: 'menu', role: 'menu' });
  // Items after a heading sit in a group the heading names, so a screen
  // reader announces "Pages" or an extension's name with them.
  let into = menu;
  items.forEach((item) => {
    if (item.sep) {
      into = menu;
      menu.append(h('div', { class: 'menu__sep', role: 'separator' }));
      return;
    }
    if (item.heading) {
      headingSeq += 1;
      const id = `menu-heading-${headingSeq}`;
      into = h('div', { class: 'menu__group', role: 'group', 'aria-labelledby': id });
      menu.append(h('p', { class: 'menu__heading', id, role: 'presentation', text: item.heading }), into);
      return;
    }
    if (item.node) {
      into.append(item.node);
      return;
    }
    into.append(h('button', {
      type: 'button', role: item.checked === undefined ? 'menuitem' : 'menuitemradio',
      class: `menu__item${item.danger ? ' menu__item--danger' : ''}`,
      'aria-checked': item.checked === undefined ? null : String(item.checked),
      disabled: item.disabled || null,
      onclick: () => {
        if (item.keepOpen) {
          item.run();
          return;
        }
        closePopover();
        item.run?.();
      },
    }, item.iconName ? icon(item.iconName) : null,
    item.hint ? h('span', { class: 'menu__text' }, item.label, h('small', { text: item.hint })) : h('span', { text: item.label }),
    item.tag ? h('span', { class: 'menu__tag', text: item.tag }) : null,
    item.checked ? icon('check', 'menu__check') : null));
  });
  menu.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
    e.preventDefault();
    const list = $$('.menu__item:not(:disabled)', menu);
    const i = list.indexOf(document.activeElement);
    list[(i + (e.key === 'ArrowDown' ? 1 : -1) + list.length) % list.length]?.focus();
  });
  return openPopover(anchor, menu, opts);
}

export function confirmBox({ title, text, confirmLabel, danger = false, onConfirm, extra = null }) {
  return h('div', { class: 'confirm', role: 'alertdialog', 'aria-label': title },
    h('h3', { text: title }),
    typeof text === 'string' ? h('p', { text }) : text,
    extra,
    h('div', { class: 'confirm__actions' },
      h('button', { type: 'button', class: 'btn btn--secondary btn--sm', onclick: () => closePopover() }, 'Cancel'),
      h('button', {
        type: 'button', class: `btn ${danger ? 'btn--danger' : 'btn--primary'} btn--sm`,
        onclick: () => {
          closePopover();
          onConfirm();
        },
      }, confirmLabel)));
}

/** Opens a confirmation from a menu item: the menu closes first, then the box opens on the same anchor. */
export function confirmFrom(anchor, options, popOpts = {}) {
  window.setTimeout(() => openPopover(anchor, confirmBox(options), { align: 'end', ...popOpts }), 0);
}

/**
 * A modal dialog for decisions that must be made before work continues.
 * Answers a promise with the chosen action's value, or null on Escape
 * (Escape does nothing when the choice is required).
 */
export function choose({ title, body, actions, iconName = 'alert', required = false }) {
  return new Promise((resolve) => {
    const dialog = h('dialog', { class: 'dialog', 'aria-labelledby': 'dialog-title' });
    const finish = (value) => {
      dialog.close();
      dialog.remove();
      resolve(value);
    };
    dialog.append(
      h('div', { class: 'dialog__head' },
        h('span', { class: 'dialog__icon' }, icon(iconName)),
        h('h2', { id: 'dialog-title', text: title })),
      h('div', { class: 'dialog__body' }, ...(Array.isArray(body) ? body : [body])),
      h('div', { class: 'dialog__actions' }, ...actions.map((a) => h('button', {
        type: 'button', class: `btn ${a.primary ? 'btn--primary' : a.danger ? 'btn--danger' : 'btn--secondary'}`,
        onclick: () => finish(a.value),
      }, a.label))));
    dialog.addEventListener('cancel', (e) => {
      e.preventDefault();
      if (!required) finish(null);
    });
    document.body.append(dialog);
    closePopover({ restoreFocus: false });
    dialog.showModal();
  });
}

document.addEventListener('pointerdown', (e) => {
  pressedAnchor = popover?.anchor?.contains(e.target) ? popover.anchor : null;
  if (popover && !popover.el.contains(e.target) && !popover.anchor?.contains(e.target)) closePopover({ restoreFocus: false });
});

window.addEventListener('resize', () => {
  // A phone's keyboard resizes the window while a picker sheet is in use: keep it.
  if (popover && !popover.el.classList.contains('popover--sheet')) closePopover({ restoreFocus: false });
});
