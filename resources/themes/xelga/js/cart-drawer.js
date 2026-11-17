/*
 * The cart drawer. It reads core's cart (cart.mini) when it opens and after
 * every change, and changes it only through core's cart routes (cart.update,
 * cart.remove, and the coupons extension's apply route). Amounts are core's;
 * this script only writes them out. Core's cart.mini does not carry the
 * coupon, so a discount is shown only while it is the one core last worked
 * out (at page load or on apply); after the cart changes only the code
 * stays, and checkout shows the amount. When an extension grouped the lines
 * (cart.mini `groups`), each group is drawn under its header. The drawer is a native dialog, so focus
 * stays inside it and Escape closes it.
 */
import { $, $$, root, inEditorPreview } from './util.js';
import { send, messageOf, money, setCartCount, toast, cartChanged } from './cart.js';

let drawer = null;
let config = {};
let opener = null;
let busy = false;

const el = (tag, className, text) => {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
};

const svgIcon = (name) => {
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('class', 'icon icon--sm');
  svg.setAttribute('aria-hidden', 'true');
  const use = document.createElementNS(ns, 'use');
  use.setAttribute('href', `#i-${name}`);
  svg.append(use);
  return svg;
};

const storageUrl = (path) => (path ? (config.storage || '/storage/') + String(path).split('/').map(encodeURIComponent).join('/') : '');

function announce(message) {
  const live = $('[data-cart-live]');
  if (!live) return;
  live.textContent = '';
  window.setTimeout(() => { live.textContent = message; }, 60);
}

function lineNode(item) {
  const label = item.variant_label ? `${item.name}, ${item.variant_label}` : item.name;
  const li = el('li', 'line');
  li.dataset.line = String(item.id);

  const media = el('span', `line__media${item.image ? '' : ' line__media--empty'}`);
  media.setAttribute('aria-hidden', 'true');
  if (item.image) {
    const img = el('img');
    img.src = storageUrl(item.image);
    img.alt = '';
    img.loading = 'lazy';
    media.append(img);
  }

  const body = el('div', 'line__body');
  body.append(el('p', 'line__title', item.name));
  if (item.variant_label) body.append(el('p', 'line__variant', item.variant_label));
  const price = el('p', 'price');
  price.append(el('span', 'visually-hidden', 'Price each '), el('span', 'price__now', money(item.price)));
  body.append(price);

  const total = el('p', 'line__total');
  total.append(el('span', 'visually-hidden', 'Line total '), el('span', '', money(item.line_total)));

  const actions = el('div', 'line__actions');
  const qty = el('div', 'qty qty--sm');
  const minus = el('button', 'qty__btn');
  minus.type = 'button';
  minus.dataset.lineStep = '-1';
  minus.setAttribute('aria-label', `One fewer ${label}`);
  minus.append(svgIcon('minus'));
  const inputId = `drawer-line-${item.id}`;
  const lab = el('label', 'visually-hidden', `Quantity of ${label}`);
  lab.htmlFor = inputId;
  const input = el('input', 'qty__input');
  input.id = inputId;
  input.type = 'number';
  input.inputMode = 'numeric';
  input.min = '1';
  input.value = String(item.quantity);
  input.dataset.lineQty = '';
  const plus = el('button', 'qty__btn');
  plus.type = 'button';
  plus.dataset.lineStep = '1';
  plus.setAttribute('aria-label', `One more ${label}`);
  plus.append(svgIcon('plus'));
  qty.append(minus, lab, input, plus);
  const remove = el('button', 'line__remove', 'Remove');
  remove.type = 'button';
  remove.dataset.lineRemove = '';
  remove.setAttribute('aria-label', `Remove ${label} from your cart`);
  actions.append(qty, remove);

  li.append(media, body, total, actions);
  return li;
}

function renderShipNote(subtotal) {
  const note = $('[data-ship-note]', drawer);
  if (!note) return;
  note.replaceChildren();
  const from = Number(config.free_from);
  if (!from || subtotal <= 0) {
    note.hidden = true;
    return;
  }
  note.hidden = false;
  if (subtotal >= from) {
    const p = el('p', 'ship-note__done');
    p.append(svgIcon('check'), document.createTextNode(config.free_reached || 'Your order qualifies for free shipping.'));
    note.append(p);
    return;
  }
  const p = el('p');
  // The merchant writes the message; {amount} becomes what is left to spend.
  const [before, after] = String(config.free_message || 'Add {amount} more for free shipping.').split('{amount}');
  if (after === undefined) p.append(document.createTextNode(before));
  else p.append(document.createTextNode(before), el('strong', '', money(from - subtotal)), document.createTextNode(after));
  const meter = el('progress', 'ship-meter');
  meter.max = from;
  meter.value = subtotal;
  meter.setAttribute('aria-hidden', 'true');
  note.append(p, meter);
}

function renderTotals(subtotal) {
  const dl = $('[data-cart-totals]', drawer);
  if (!dl) return;
  dl.replaceChildren(el('dt', '', 'Subtotal'), el('dd', '', money(subtotal)));
  const coupon = config.coupon;
  if (coupon && coupon.code) {
    const dt = el('dt', '', `Coupon ${coupon.code} `);
    const rm = el('button', 'totals__remove', 'Remove');
    rm.type = 'button';
    rm.dataset.couponRemove = '';
    rm.setAttribute('aria-label', `Remove coupon ${coupon.code}`);
    dt.append(rm);
    const known = typeof coupon.discount === 'number';
    dl.append(dt, el('dd', known ? 'totals__discount' : 'totals__discount muted', known ? `-${money(coupon.discount)}` : 'At checkout'));
  }
}

/*
 * A group's header in the list (cart.mini `groups`, which core checked:
 * one group per seller, say). Text goes in as text; the address is one core
 * already accepted as a link.
 */
function groupHead(group) {
  const li = el('li', 'cart-group__head');
  li.dataset.cartGroup = group.key;
  const title = el('h3', 'cart-group__title');
  if (group.url) {
    const a = el('a', '', group.label);
    a.href = group.url;
    title.append(a);
  } else {
    title.textContent = group.label;
  }
  li.append(title);
  const ship = group.shipping;
  if (ship) {
    const p = el('p', 'cart-group__ship');
    if (ship.label) p.append(el('span', '', ship.label));
    if (ship.amount !== null && ship.amount !== undefined) p.append(el('strong', '', money(ship.amount)));
    if (ship.note) p.append(el('span', 'muted', ship.note));
    li.append(p);
  }
  return li;
}

/*
 * The lines in cart order, or under their groups' headers when an extension
 * grouped them. Core puts the lines no group took (`other`) first; they get
 * a neutral header when named groups follow, and alone are the plain list.
 */
function lineNodes(items, groups) {
  const named = Array.isArray(groups) ? groups.filter((g) => g.key !== 'other') : [];
  if (!named.length) return items.map(lineNode);
  const byId = new Map(items.map((item) => [Number(item.id), item]));
  const nodes = [];
  groups.forEach((group) => {
    const lines = (group.lines || []).map((id) => byId.get(Number(id))).filter(Boolean);
    if (!lines.length) return;
    if (group.key === 'other') nodes.push(groupHead({ key: 'other', label: 'Other items', url: null, shipping: null }));
    else if (group.label) nodes.push(groupHead(group));
    lines.forEach((item) => nodes.push(lineNode(item)));
  });
  return nodes;
}

function render(cart) {
  const items = Array.isArray(cart.items) ? cart.items : [];
  const count = Number(cart.count) || 0;
  const subtotal = Number(cart.subtotal) || 0;
  $('[data-cart-loading]', drawer).hidden = true;
  $('[data-cart-lines]', drawer).replaceChildren(...lineNodes(items, cart.groups));
  $('[data-cart-empty]', drawer).hidden = items.length > 0;
  $('[data-cart-foot]', drawer).hidden = items.length === 0;
  $$('[data-cart-count-text]', drawer).forEach((n) => { n.textContent = count ? `(${count})` : ''; });
  setCartCount(count);
  renderShipNote(subtotal);
  renderTotals(subtotal);
}

async function refresh() {
  if (!config.endpoints?.mini) return;
  try {
    const response = await fetch(config.endpoints.mini, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(String(response.status));
    render(await response.json());
  } catch {
    $('[data-cart-loading]', drawer).hidden = false;
    $('[data-cart-loading]', drawer).textContent = 'Your cart could not be loaded. Close this and try again.';
  }
}

/* The cart changed, so the discount core worked out earlier may no longer be right. */
function forgetDiscount() {
  if (config.coupon) config.coupon.discount = null;
}

function showWarning(text) {
  const box = $('[data-cart-warning]', drawer);
  if (!box) return;
  box.textContent = text || '';
  box.hidden = !text;
}

async function changeLine(li, quantity) {
  if (busy) return;
  const id = li.dataset.line;
  const url = quantity <= 0 ? config.endpoints.remove : config.endpoints.update;
  if (!url) return;
  busy = true;
  li.classList.add('is-busy');
  showWarning('');
  try {
    const result = quantity <= 0
      ? await send(url.replace('{id}', id), 'DELETE')
      : await send(url.replace('{id}', id), 'PATCH', { quantity });
    if (!result.ok) {
      showWarning(messageOf(result, 'That change could not be saved. Try again.'));
    } else {
      forgetDiscount();
      if (result.data.stock_warning) showWarning(result.data.stock_warning);
      announce(quantity <= 0 ? 'Removed from your cart.' : 'Cart updated.');
      cartChanged({ count: result.data.count, source: 'drawer' });
    }
  } catch {
    showWarning('The store could not be reached. Check your connection, then try again.');
  } finally {
    busy = false;
    await refresh();
  }
}

async function applyCoupon(form) {
  const input = form.querySelector('input');
  const msg = form.querySelector('[data-coupon-msg]');
  const code = input.value.trim();
  if (!code) {
    msg.textContent = 'Enter a coupon code first.';
    msg.dataset.tone = 'error';
    input.setAttribute('aria-invalid', 'true');
    input.focus();
    return;
  }
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    const result = await send(config.endpoints.coupon_apply, 'POST', { code });
    if (!result.ok || !result.data.success) {
      msg.textContent = messageOf(result, 'That code could not be applied.');
      msg.dataset.tone = 'error';
      input.setAttribute('aria-invalid', 'true');
      return;
    }
    input.removeAttribute('aria-invalid');
    input.value = '';
    config.coupon = { code: result.data.coupon_code, discount: Number(result.data.discount) || 0, free_shipping: Boolean(result.data.free_shipping) };
    msg.textContent = result.data.message || `${result.data.coupon_code} applied.`;
    msg.dataset.tone = 'ok';
    await refresh();
  } catch {
    msg.textContent = 'The store could not be reached. Check your connection, then try again.';
    msg.dataset.tone = 'error';
  } finally {
    button.disabled = false;
  }
}

async function removeCoupon() {
  if (!config.endpoints.coupon_remove) return;
  const result = await send(config.endpoints.coupon_remove, 'DELETE');
  if (result.ok) {
    config.coupon = null;
    toast('Coupon removed');
    await refresh();
  }
}

export function openCartDrawer(trigger = null) {
  if (!drawer || drawer.open) {
    if (drawer?.open) refresh();
    return;
  }
  opener = trigger || document.activeElement;
  drawer.showModal();
  refresh();
}

export function bindCartDrawer() {
  drawer = $('[data-cart-drawer]');
  if (!drawer) return;
  try {
    config = JSON.parse($('[data-cart-config]', drawer)?.textContent || '{}');
  } catch {
    config = {};
  }
  config.endpoints = config.endpoints || {};

  document.addEventListener('click', (event) => {
    const open = event.target.closest('[data-cart-open]');
    if (open) {
      // The cart page setting sends the icon to the cart page, as a plain link.
      if (inEditorPreview() || root.dataset.cartType === 'page') return;
      event.preventDefault();
      openCartDrawer(open);
    }
  });

  drawer.addEventListener('click', (event) => {
    if (event.target === drawer || event.target.closest('[data-cart-close]')) {
      drawer.close();
      return;
    }
    const step = event.target.closest('[data-line-step]');
    if (step) {
      const li = step.closest('[data-line]');
      const input = $('[data-line-qty]', li);
      changeLine(li, Math.max(0, (Number(input.value) || 1) + Number(step.dataset.lineStep)));
      return;
    }
    const remove = event.target.closest('[data-line-remove]');
    if (remove) {
      changeLine(remove.closest('[data-line]'), 0);
      return;
    }
    if (event.target.closest('[data-coupon-remove]')) removeCoupon();
  });

  drawer.addEventListener('change', (event) => {
    const input = event.target.closest('[data-line-qty]');
    if (input) changeLine(input.closest('[data-line]'), Math.max(0, Math.floor(Number(input.value) || 0)));
  });

  drawer.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-coupon-form]');
    if (!form) return;
    event.preventDefault();
    applyCoupon(form);
  });

  drawer.addEventListener('close', () => {
    if (opener && document.contains(opener)) opener.focus();
    opener = null;
  });

  document.addEventListener('xelga:cart-changed', (event) => {
    if (event.detail?.source === 'drawer') return;
    forgetDiscount();
    if (event.detail?.open) {
      openCartDrawer();
      if (event.detail.added) announce(`${event.detail.added} added to your cart.`);
    } else if (drawer.open) {
      refresh();
    }
  });
}
