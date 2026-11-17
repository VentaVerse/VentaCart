/*
 * Buying from a section: quick add, the wishlist heart and the quantity
 * stepper. Every change goes through core's own routes (cart.add and the
 * wishlist routes) with the page's CSRF token; the header's cart count is
 * set from core's answer, never counted here. Listeners are delegated, so
 * a section drawn again in the editor needs nothing re-bound. In the editor
 * preview nothing is bought or saved.
 */
import { $, $$, root, icon } from './util.js';

const csrf = () => $('meta[name="csrf-token"]')?.content || '';

/** A JSON request to one of core's storefront routes. */
export async function send(url, method, body) {
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  let data = {};
  try {
    data = await response.json();
  } catch {
    data = {};
  }
  return { ok: response.ok, status: response.status, data };
}

/** The first message in a validation answer, or a plain fallback. */
export function messageOf(result, fallback) {
  if (result.status === 429) return 'Too many tries in a short time. Wait a minute, then try again.';
  if (result.status === 419) return 'This page has been open a long time. Reload it, then try again.';
  const errors = result.data?.errors;
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat()[0];
    if (typeof first === 'string') return first;
  }
  return typeof result.data?.message === 'string' && !result.ok ? result.data.message : fallback;
}

/**
 * An amount from core (in the store's currency) written the way core's
 * money() helper writes it in the shopper's currency.
 */
export function money(amount) {
  const rate = Number(root.dataset.moneyRate || 1) || 1;
  const value = (Number(amount) || 0) * rate;
  return (root.dataset.moneySymbol || '') + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let toastTimer = null;
export function toast(message, tone = 'ok') {
  let el = $('.toast');
  if (!el) {
    el = document.createElement('div');
    el.className = 'toast scheme-1';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    document.body.append(el);
  }
  el.innerHTML = `${icon(tone === 'ok' ? 'check' : 'alert')}<span></span>`;
  el.querySelector('span').textContent = message;
  el.dataset.tone = tone;
  el.classList.add('is-visible');
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => el.classList.remove('is-visible'), tone === 'ok' ? 2800 : 5000);
}

export function setCartCount(count) {
  if (!Number.isFinite(Number(count))) return;
  $$('[data-cart-count]:not(html)').forEach((el) => { el.textContent = String(count); });
}

/** Tell the drawer and the page that core's cart changed. */
export function cartChanged(detail = {}) {
  document.dispatchEvent(new CustomEvent('xelga:cart-changed', { detail }));
}

/**
 * Add to core's cart and then do what the store chose: open the drawer, go
 * to the cart page, or show a short message. Resolves true when core took it.
 */
export async function addItem({ productId, variantId = null, quantity = 1, name = 'The product' }) {
  const url = root.dataset.cartAdd;
  if (!url || !productId) return false;
  try {
    const result = await send(url, 'POST', { product_id: Number(productId), quantity, variant_id: variantId });
    if (!result.ok || result.data?.success === false) {
      toast(messageOf(result, `${name} could not be added. Try again.`), 'error');
      return false;
    }
    setCartCount(result.data.count);
    const type = root.dataset.cartType || 'drawer';
    if (type === 'page' && root.dataset.cartPage) {
      window.location.assign(root.dataset.cartPage);
      return true;
    }
    const drawer = type === 'drawer' ? $('[data-cart-drawer]') : null;
    cartChanged({ count: result.data.count, open: Boolean(drawer), added: name });
    if (!drawer) toast(`Added ${quantity > 1 ? `${quantity} x ` : ''}${name} to your cart`);
    return true;
  } catch {
    toast('The store could not be reached. Check your connection, then try again.', 'error');
    return false;
  }
}

async function quickAdd(button) {
  const quantity = Math.max(1, Math.floor(Number(button.dataset.quantity) || 1));
  button.disabled = true;
  button.classList.add('is-loading');
  button.setAttribute('aria-busy', 'true');
  const ok = await addItem({ productId: button.dataset.productId, quantity, name: button.dataset.productName || 'The product' });
  button.classList.remove('is-loading');
  button.removeAttribute('aria-busy');
  button.disabled = false;
  if (ok) {
    button.classList.add('is-done');
    window.setTimeout(() => button.classList.remove('is-done'), 1600);
  }
}

async function toggleWishlist(button) {
  const id = Number(button.dataset.productId);
  const on = button.getAttribute('aria-pressed') !== 'true';
  const url = on ? root.dataset.wishlistAdd : (root.dataset.wishlistRemove || '').replace('{product}', String(id));
  if (!url || !id) return;

  button.setAttribute('aria-pressed', String(on));
  button.disabled = true;
  try {
    const result = await send(url, on ? 'POST' : 'DELETE', on ? { product_id: id } : null);
    if (!result.ok) {
      button.setAttribute('aria-pressed', String(!on));
      toast(messageOf(result, 'The wishlist could not be changed. Try again.'), 'error');
      return;
    }
    const inList = Boolean(result.data?.in);
    // Every heart for this product on the page follows.
    $$(`[data-wishlist][data-product-id="${id}"]`).forEach((b) => b.setAttribute('aria-pressed', String(inList)));
    $$('[data-wishlist-count]').forEach((el) => { el.textContent = String(result.data?.count ?? ''); });
    toast(inList ? 'Saved to your wishlist' : 'Removed from your wishlist');
  } catch {
    button.setAttribute('aria-pressed', String(!on));
    toast('The store could not be reached. Check your connection, then try again.', 'error');
  } finally {
    button.disabled = false;
  }
}

export function bindBuying() {
  document.addEventListener('click', (event) => {
    const add = event.target.closest('[data-add-to-cart]');
    if (add) {
      event.preventDefault();
      if (!add.disabled) quickAdd(add);
      return;
    }

    const wish = event.target.closest('[data-wishlist]');
    if (wish) {
      event.preventDefault();
      if (!wish.disabled) toggleWishlist(wish);
      return;
    }

    const step = event.target.closest('[data-qty-step]');
    if (step) {
      const input = step.closest('.qty')?.querySelector('.qty__input');
      if (!input) return;
      const min = Number(input.min) || 1;
      const max = Number(input.max) || 999;
      const value = (Number(input.value) || min) + Number(step.dataset.qtyStep);
      input.value = String(Math.min(max, Math.max(min, value)));
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
}
