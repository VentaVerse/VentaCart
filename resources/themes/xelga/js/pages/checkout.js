/*
 * Checkout, in core's two steps: delivery address, then payment.
 *
 * Every change goes through core's routes, named in the page's data:
 * a new address through checkout.address.save, an edited one through
 * account.addresses.update, delivery quotes through checkout.shipping-rates
 * (asked again whenever the address, the lines or the coupon change), line
 * quantities through cart.update, coupons through the coupons extension.
 * The order is placed by posting core's own fields to checkout.place, after
 * a last look at the order in a dialog. The totals here are for reading:
 * core works out what is charged, including the coupon and store credit.
 */
import { $, $$ } from '../util.js';
import { bindAddressFields } from './address-fields.js';
import { setCartCount, cartChanged } from '../cart.js';

const csrf = () => $('meta[name="csrf-token"]')?.content || '';

async function send(url, method, body) {
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  return { ok: response.ok, status: response.status, data };
}

/** Every message in a validation answer, or one plain sentence. */
function messages(result, fallback) {
  if (result.status === 419) return ['This page has been open a long time. Reload it, then try again.'];
  if (result.status === 429) return ['Too many tries in a short time. Wait a minute, then try again.'];
  const errors = result.data?.errors;
  if (errors && typeof errors === 'object') return Object.values(errors).flat().filter((m) => typeof m === 'string');
  if (typeof result.data?.message === 'string' && result.data.message !== '') return [result.data.message];
  return [fallback];
}

function el(tag, attrs = {}, ...children) {
  const node = document.createElement(tag);
  Object.entries(attrs).forEach(([key, value]) => {
    if (value === null || value === undefined || value === false) return;
    if (key === 'class') node.className = value;
    else if (key === 'text') node.textContent = value;
    else node.setAttribute(key, value === true ? '' : String(value));
  });
  children.flat().forEach((child) => { if (child !== null && child !== undefined && child !== false) node.append(child); });
  return node;
}

export function initCheckout(root) {
  let data;
  try {
    data = JSON.parse($('[data-checkout-data]', root).textContent);
  } catch {
    return;
  }

  const state = {
    step: 1,
    addresses: data.addresses || [],
    selectedAddressId: data.selectedAddressId ?? null,
    items: data.items || [],
    subtotal: Number(data.subtotal) || 0,
    shippingMethods: data.shippingMethods || [],
    selectedShipping: data.shippingMethods?.[0]?.key ?? null,
    selectedPayment: data.paymentMethods?.[0]?.key ?? null,
    coupon: { code: data.coupon?.code || '', discount: Number(data.coupon?.discount) || 0, freeShipping: Boolean(data.coupon?.freeShipping) },
    credit: Number(data.creditBalance) || 0,
    editing: null,
    placing: false,
    loadingShipping: false,
  };
  const routes = data.routes || {};
  const symbol = data.currency?.symbol || '';
  const rate = Number(data.currency?.rate) || 1;
  const money = (amount) => symbol + (Number(amount) * rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const $r = (selector) => $(selector, root);
  const list = $r('[data-address-list]');
  const panel = $r('[data-address-panel]');
  const panelTitle = $r('[data-address-panel-title]');
  const panelErrors = $r('[data-address-errors]');
  const newButton = $r('[data-new-address]');
  const nextButton = $r('[data-next-step]');
  const placeButton = $r('[data-place-order]');
  const backButton = $r('[data-back-step]');
  const cartLink = $r('[data-cart-link]');
  const stepTwoTab = $r('[data-go-step="2"]');
  const stepOneTab = $r('[data-go-step="1"]');
  const billingToggle = $r('[data-different-billing]');
  const billingBox = $r('[data-billing]');
  const shippingBox = $r('[data-shipping]');
  const shippingList = $r('[data-shipping-list]');
  const stockWarning = $r('[data-stock-warning]');
  const confirm = $r('[data-confirm]');
  const qr = $r('[data-qr]');
  const orderForm = $r('[data-order-form]');

  const newAddress = bindAddressFields($('[data-address-fields]', panel), { form: panel });
  const billing = billingBox ? bindAddressFields($('[data-address-fields]', billingBox), { form: billingBox }) : null;

  /* ---------------------------------------------------------------- totals */

  const selectedMethod = () => state.shippingMethods.find((m) => m.key === state.selectedShipping) || null;
  const selectedAddress = () => state.addresses.find((a) => a.id === state.selectedAddressId) || null;
  const selectedPayment = () => (data.paymentMethods || []).find((m) => m.key === state.selectedPayment) || null;

  function totals() {
    const method = selectedMethod();
    const shipping = method ? (state.coupon.freeShipping ? 0 : Number(method.cost) || 0) : 0;
    const total = Math.max(0, state.subtotal + shipping - (state.coupon.code ? state.coupon.discount : 0));
    const credit = Math.min(state.credit, total);
    return { method, shipping, total, credit, payable: Math.max(0, total - credit) };
  }

  function renderTotals() {
    const t = totals();
    $r('[data-total-subtotal]').textContent = money(state.subtotal);
    $r('[data-total-shipping]').textContent = t.method ? (t.shipping === 0 ? 'Free' : money(t.shipping)) : 'Not chosen yet';
    const couponRow = $r('[data-total-coupon-row]');
    couponRow.hidden = !(state.coupon.code && state.coupon.discount > 0);
    $r('[data-total-coupon]').textContent = `-${money(state.coupon.discount)}`;
    const creditRow = $r('[data-total-credit-row]');
    creditRow.hidden = !(t.credit > 0);
    $r('[data-total-credit]').textContent = `-${money(t.credit)}`;
    $r('[data-total-grand]').textContent = money(t.payable);
    placeButton.disabled = !state.selectedPayment || !state.selectedShipping || state.placing;
  }

  /* ------------------------------------------------------------- addresses */

  function placeLine(a) {
    return [a.address_1, a.barangay, a.district, a.city, a.state, a.postcode, data.countryNames?.[a.country_id]].filter(Boolean).join(', ');
  }

  function phoneLine(a) {
    if (!a.phone) return '';
    const code = data.phoneCodes?.[a.country_id];
    return `${code ? `+${code} ` : ''}${a.phone}`;
  }

  function renderAddresses() {
    const rows = state.addresses.map((a) => el('div', { class: 'choice-row', 'data-address-row': a.id },
      el('label', { class: 'choice' },
        el('input', { type: 'radio', name: 'checkout_address', value: a.id, checked: a.id === state.selectedAddressId }),
        el('span', { class: 'choice__body' },
          el('strong', { text: `${a.first_name} ${a.last_name}` }),
          a.is_default ? el('span', { class: 'pill', text: 'Default' }) : null,
          el('span', { class: 'muted', text: placeLine(a) }),
          phoneLine(a) ? el('span', { class: 'muted tabular', text: phoneLine(a) }) : null)),
      el('button', { type: 'button', class: 'text-btn choice-row__edit', 'data-edit-address': a.id, 'aria-label': `Edit the address of ${a.first_name} ${a.last_name}`, text: 'Edit' })));
    $$('.choice-row', list).forEach((row) => row.remove());
    list.append(...rows);
    // The radio's checked state must match the chosen address even when the markup came from the server.
    $$('input[name="checkout_address"]', list).forEach((input) => { input.checked = Number(input.value) === state.selectedAddressId; });
    syncAddressUi();
  }

  function syncAddressUi() {
    const open = !panel.hidden;
    const has = state.addresses.length > 0;
    list.hidden = open || !has;
    newButton.hidden = open || !has;
    $r('[data-cancel-address]').hidden = !has && !state.editing;
    $r('[data-back-to-cart]').hidden = has || Boolean(state.editing);
    $r('[data-billing-toggle-row]').hidden = open;
    if (billingBox) billingBox.hidden = open || !billingToggle.checked;
    const ready = Boolean(state.selectedAddressId) && !open;
    nextButton.disabled = !ready;
    stepTwoTab.disabled = !ready || state.step === 2;
  }

  function fillPanel(a) {
    $$('[data-addr-value]', panel).forEach((input) => { input.value = a ? (a[input.dataset.addrValue] || '') : ''; });
    if (!a) {
      $('[data-addr-value="first_name"]', panel).value = data.customer?.firstName || '';
      $('[data-addr-value="last_name"]', panel).value = data.customer?.lastName || '';
    }
    return newAddress.set(a || {});
  }

  async function openPanel(address) {
    state.editing = address || null;
    panelTitle.textContent = address ? 'Edit address' : 'New address';
    $r('[data-save-address]').textContent = address ? 'Save the address' : 'Save this address';
    panelErrors.hidden = true;
    panel.hidden = false;
    syncAddressUi();
    await fillPanel(address);
    panelTitle.focus({ preventScroll: false });
  }

  function closePanel() {
    if (state.addresses.length === 0) return;
    state.editing = null;
    panel.hidden = true;
    syncAddressUi();
    (newButton.hidden ? list : newButton).focus?.();
  }

  function showPanelErrors(list) {
    panelErrors.replaceChildren(el('ul', { class: 'form-status__list' }, list.map((m) => el('li', { text: m }))));
    panelErrors.hidden = false;
  }

  async function saveAddress(button) {
    const missing = $$('input, select', panel).filter((input) => !input.disabled && !input.closest('[hidden]') && !input.checkValidity());
    if (missing.length > 0) {
      missing[0].reportValidity();
      return;
    }
    const body = { ...newAddress.values() };
    $$('[data-addr-value]', panel).forEach((input) => { body[input.dataset.addrValue] = input.value.trim(); });
    if (!state.editing) body.is_default = state.addresses.length === 0 ? 1 : 0;

    button.disabled = true;
    button.classList.add('is-loading');
    try {
      const result = state.editing
        ? await send(routes.updateAddress.replace('__ID__', encodeURIComponent(state.editing.id)), 'PUT', body)
        : await send(routes.saveAddress, 'POST', body);
      if (!result.ok || !(result.data.success || result.data.addresses)) {
        showPanelErrors(messages(result, 'The address could not be saved. Check every field, then try again.'));
        return;
      }
      if (Array.isArray(result.data.addresses)) {
        state.addresses = result.data.addresses.map((a) => ({ ...a, id: Number(a.id), country_id: a.country_id ? Number(a.country_id) : null }));
      }
      if (result.data.address?.id) state.selectedAddressId = Number(result.data.address.id);
      state.editing = null;
      panel.hidden = true;
      renderAddresses();
      refreshShipping();
      $r('#co-address-title')?.focus();
    } catch {
      showPanelErrors(['The store could not be reached. Check your connection, then try again.']);
    } finally {
      button.disabled = false;
      button.classList.remove('is-loading');
    }
  }

  function selectAddress(id) {
    if (state.selectedAddressId === id) return;
    state.selectedAddressId = id;
    syncAddressUi();
    refreshShipping();
  }

  /* -------------------------------------------------------------- shipping */

  function renderShipping() {
    shippingBox.classList.toggle('is-loading', state.loadingShipping);
    shippingBox.setAttribute('aria-busy', String(state.loadingShipping));
    if (state.loadingShipping) return;
    if (state.shippingMethods.length === 0) {
      shippingList.replaceChildren(el('p', { class: 'muted ship__empty', text: state.selectedAddressId ? 'No delivery option reaches this address. Choose another address, or contact the store.' : 'Add your delivery address to see delivery options.' }));
      renderTotals();
      return;
    }
    shippingList.replaceChildren(...state.shippingMethods.map((m) => el('label', { class: 'ship__opt' },
      el('input', { type: 'radio', name: 'checkout_shipping', value: m.key, checked: m.key === state.selectedShipping }),
      el('span', {}, el('strong', { text: m.name }), m.estimated_days ? el('span', { class: 'muted', text: `About ${m.estimated_days} days` }) : null),
      el('span', { class: 'tabular', text: Number(m.cost) === 0 ? 'Free' : money(m.cost) }))));
    renderTotals();
  }

  let quoteRun = 0;
  async function refreshShipping() {
    if (!state.selectedAddressId || !routes.shippingRates) return;
    quoteRun += 1;
    const run = quoteRun;
    state.loadingShipping = true;
    renderShipping();
    try {
      const result = await send(routes.shippingRates, 'POST', { address_id: state.selectedAddressId });
      if (run !== quoteRun) return;
      if (result.ok && Array.isArray(result.data.methods)) {
        state.shippingMethods = result.data.methods;
        state.selectedShipping = result.data.methods[0]?.key ?? null;
      }
    } catch {
      // Keep the options already shown; placing the order checks them again.
    } finally {
      if (run === quoteRun) {
        state.loadingShipping = false;
        renderShipping();
      }
    }
  }

  /* ----------------------------------------------------------------- lines */

  function warn(text) {
    stockWarning.textContent = text;
    stockWarning.hidden = !text;
    if (text) window.setTimeout(() => { stockWarning.hidden = true; }, 6000);
  }

  async function updateLine(row, quantity) {
    const id = Number(row.dataset.line);
    const item = state.items.find((i) => i.id === id);
    if (!item) return;
    let qty = Math.max(0, Math.floor(Number(quantity) || 0));
    if (qty < 1) {
      // eslint-disable-next-line no-alert
      if (!window.confirm(`Remove ${item.name} from your cart?`)) {
        $('[data-line-qty]', row).value = String(item.quantity);
        return;
      }
      qty = 0;
    }
    row.classList.add('is-loading');
    try {
      const result = await send(routes.cartLine.replace('__ID__', encodeURIComponent(id)), 'PATCH', { quantity: qty });
      if (!result.ok || !result.data.success) {
        warn(messages(result, 'The quantity could not be changed. Try again.')[0]);
        $('[data-line-qty]', row).value = String(item.quantity);
        return;
      }
      if (result.data.stock_warning) {
        warn(result.data.stock_warning);
        qty = Number(result.data.quantity);
      }
      if (qty === 0) {
        state.items = state.items.filter((i) => i.id !== id);
        row.remove();
        if (state.items.length === 0) {
          window.location.assign(routes.cart);
          return;
        }
      } else {
        item.quantity = qty;
        if (result.data.price !== null && result.data.price !== undefined) item.price = Number(result.data.price);
        $('[data-line-qty]', row).value = String(qty);
        $('[data-line-badge]', row).textContent = String(qty);
        $('[data-line-total]', row).textContent = money(item.price * item.quantity);
      }
      state.subtotal = Number(result.data.subtotal) || 0;
      setCartCount(result.data.count);
      cartChanged({ count: result.data.count, subtotal: result.data.subtotal, silent: true });
      renderTotals();
      refreshShipping();
    } catch {
      warn('The store could not be reached. Check your connection, then try again.');
      $('[data-line-qty]', row).value = String(item.quantity);
    } finally {
      row.classList.remove('is-loading');
    }
  }

  /* ---------------------------------------------------------------- coupon */

  const couponForm = $r('[data-coupon-form]');
  const couponApplied = $r('[data-coupon-applied]');
  const couponMsg = $r('[data-coupon-msg]');

  function renderCoupon() {
    if (!couponForm) return;
    couponForm.hidden = Boolean(state.coupon.code);
    couponApplied.hidden = !state.coupon.code;
    $r('[data-coupon-code]').textContent = state.coupon.code;
    $r('[data-coupon-free]').hidden = !state.coupon.freeShipping;
    renderTotals();
  }

  async function applyCoupon(event) {
    event.preventDefault();
    const input = $('input[name="code"]', couponForm);
    const code = input.value.trim();
    if (!code || !routes.couponApply) return;
    const button = $('button[type="submit"]', couponForm);
    button.disabled = true;
    button.classList.add('is-loading');
    couponMsg.hidden = true;
    try {
      const result = await send(routes.couponApply, 'POST', { code });
      if (result.ok && result.data.success) {
        state.coupon = { code: result.data.coupon_code, discount: Number(result.data.discount) || 0, freeShipping: Boolean(result.data.free_shipping) };
        input.value = '';
        input.removeAttribute('aria-invalid');
        renderCoupon();
        refreshShipping();
        $r('[data-coupon-remove]')?.focus();
      } else {
        couponMsg.textContent = messages(result, 'That code is not valid for this order.')[0];
        couponMsg.hidden = false;
        input.setAttribute('aria-invalid', 'true');
      }
    } catch {
      couponMsg.textContent = 'The store could not be reached. Check your connection, then try again.';
      couponMsg.hidden = false;
    } finally {
      button.disabled = false;
      button.classList.remove('is-loading');
    }
  }

  async function removeCoupon() {
    if (!routes.couponRemove) return;
    try {
      await send(routes.couponRemove, 'DELETE');
    } catch {
      // Core checks the coupon again when the order is placed.
    }
    state.coupon = { code: '', discount: 0, freeShipping: false };
    renderCoupon();
    refreshShipping();
    $('input[name="code"]', couponForm)?.focus();
  }

  /* ----------------------------------------------------------------- steps */

  function goToStep(n) {
    if (n === 2 && (!state.selectedAddressId || !panel.hidden)) return;
    state.step = n;
    $$('[data-step]', root).forEach((section) => { section.hidden = section.dataset.step !== String(n); });
    stepOneTab.toggleAttribute('aria-current', n === 1);
    if (n === 1) stepOneTab.setAttribute('aria-current', 'step');
    stepOneTab.classList.toggle('is-done', n === 2);
    stepTwoTab.toggleAttribute('aria-current', n === 2);
    if (n === 2) stepTwoTab.setAttribute('aria-current', 'step');
    nextButton.hidden = n !== 1;
    placeButton.hidden = n !== 2;
    backButton.hidden = n !== 2;
    cartLink.hidden = n !== 1;
    syncAddressUi();
    renderTotals();
    const title = $r(n === 1 ? '#co-address-title' : '#co-pay-title');
    title?.focus({ preventScroll: true });
    root.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }

  /* ----------------------------------------------------------------- place */

  function section(title, ...content) {
    return el('section', {}, el('h3', { text: title }), ...content);
  }

  function openConfirm() {
    if (!state.selectedAddressId || !state.selectedPayment || !state.selectedShipping || state.placing) return;
    const t = totals();
    const a = selectedAddress() || {};
    const comment = $r('[data-order-value="comment"]')?.value.trim() || '';
    const courier = $r('[data-order-value="courier_comment"]')?.value.trim() || '';
    const body = $r('[data-confirm-body]');
    body.replaceChildren(
      section('Products', el('ul', { class: 'lines' }, state.items.map((i) => el('li', { class: 'line line--sm line--noimg' },
        el('div', { class: 'line__text' }, el('span', { text: i.name }), i.variant_label ? el('span', { class: 'muted', text: i.variant_label }) : null, el('span', { class: 'muted', text: `Quantity ${i.quantity}` })),
        el('span', { class: 'line__price tabular', text: money(i.price * i.quantity) }))))),
      section('Delivering to', el('p', { text: `${a.first_name || ''} ${a.last_name || ''}` }), el('p', { class: 'muted', text: placeLine(a) })),
      section('Delivery', el('p', { text: `${t.method?.name || ''}, ${t.shipping === 0 ? 'Free' : money(t.shipping)}` })),
      section('Payment', el('p', { text: selectedPayment()?.name || '' })),
      comment ? section($r('label[for="n-shop"]')?.firstChild?.textContent.trim() || 'Message to the store', el('p', { class: 'muted', text: comment })) : null,
      courier ? section($r('label[for="n-courier"]')?.firstChild?.textContent.trim() || 'Message to the courier', el('p', { class: 'muted', text: courier })) : null,
      el('dl', { class: 'totals' },
        el('div', {}, el('dt', { text: 'Subtotal' }), el('dd', { class: 'tabular', text: money(state.subtotal) })),
        el('div', {}, el('dt', { text: 'Delivery' }), el('dd', { class: 'tabular', text: t.shipping === 0 ? 'Free' : money(t.shipping) })),
        state.coupon.code && state.coupon.discount > 0 ? el('div', {}, el('dt', { text: `Coupon (${state.coupon.code})` }), el('dd', { class: 'tabular', text: `-${money(state.coupon.discount)}` })) : null,
        t.credit > 0 ? el('div', {}, el('dt', { text: 'Store credit' }), el('dd', { class: 'tabular', text: `-${money(t.credit)}` })) : null,
        el('div', { class: 'totals__grand' }, el('dt', { text: 'Total' }), el('dd', { class: 'tabular', text: money(t.payable) }))),
    );
    confirm.showModal();
    $('[data-confirm-place]', confirm).focus();
  }

  function setField(name, value) {
    const input = orderForm.querySelector(`input[name="${name}"]`);
    if (input) input.value = value ?? '';
  }

  function placeOrder() {
    if (state.placing) return;
    const method = selectedMethod();
    const different = Boolean(billingToggle?.checked);
    setField('shipping_address_id', state.selectedAddressId);
    setField('payment_method', state.selectedPayment);
    setField('shipping_method', state.selectedShipping);
    setField('comment', $r('[data-order-value="comment"]')?.value || '');
    setField('courier_comment', $r('[data-order-value="courier_comment"]')?.value || '');
    setField('different_billing', different ? '1' : '0');
    const places = billing ? billing.values() : {};
    const value = (key) => ($(`[data-billing-value="${key}"]`, billingBox)?.value || '').trim();
    setField('billing_first_name', different ? value('first_name') : '');
    setField('billing_last_name', different ? value('last_name') : '');
    setField('billing_address_1', different ? value('address_1') : '');
    setField('billing_postcode', different ? value('postcode') : '');
    ['city', 'district', 'barangay', 'state'].forEach((key) => setField(`billing_${key}`, different ? places[key] : ''));
    setField('billing_country_id', different ? places.country_id : '');

    state.placing = true;
    const confirmButton = $('[data-confirm-place]', confirm);
    confirmButton.disabled = true;
    confirmButton.classList.add('is-loading');
    confirmButton.textContent = 'Placing your order';
    renderTotals();
    orderForm.submit();
  }

  /* ---------------------------------------------------------------- events */

  root.addEventListener('change', (event) => {
    const target = event.target;
    if (target.name === 'checkout_address') selectAddress(Number(target.value));
    else if (target.name === 'checkout_shipping') { state.selectedShipping = target.value; renderTotals(); }
    else if (target.name === 'checkout_payment') {
      state.selectedPayment = target.value;
      $$('[data-payment-qr]', root).forEach((box) => { box.hidden = box.dataset.paymentQr !== target.value; });
      renderTotals();
    } else if (target.matches('[data-line-qty]')) updateLine(target.closest('[data-line]'), target.value);
    else if (target === billingToggle) syncAddressUi();
  });

  root.addEventListener('click', (event) => {
    const target = event.target.closest('button, a');
    if (!target || !root.contains(target)) return;
    if (target.matches('[data-edit-address]')) {
      const a = state.addresses.find((x) => x.id === Number(target.dataset.editAddress));
      if (a) openPanel(a);
    } else if (target === newButton) openPanel(null);
    else if (target.matches('[data-cancel-address]')) closePanel();
    else if (target.matches('[data-save-address]')) saveAddress(target);
    else if (target.matches('[data-line-step]')) {
      const row = target.closest('[data-line]');
      const item = state.items.find((i) => i.id === Number(row.dataset.line));
      if (item) updateLine(row, item.quantity + Number(target.dataset.lineStep));
    } else if (target.matches('[data-line-remove]')) updateLine(target.closest('[data-line]'), 0);
    else if (target.matches('[data-coupon-remove]')) removeCoupon();
    else if (target === nextButton || target === stepTwoTab) goToStep(2);
    else if (target === backButton || target === stepOneTab) { if (state.step === 2) goToStep(1); }
    else if (target === placeButton) openConfirm();
    else if (target.matches('[data-confirm-back]')) confirm.close();
    else if (target.matches('[data-confirm-place]')) placeOrder();
    else if (target.matches('[data-qr-open]')) {
      const img = $('[data-qr-img]', qr);
      img.src = target.dataset.qrSrc;
      img.alt = `QR code for ${target.dataset.qrName}`;
      $('[data-qr-title]', qr).textContent = target.dataset.qrName;
      qr.showModal();
    } else if (target.matches('[data-qr-close]')) qr.close();
  });

  couponForm?.addEventListener('submit', applyCoupon);
  // A tap on the dimmed backdrop closes a dialog, as Escape does.
  [confirm, qr].forEach((dialog) => dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }));
  // Back from a payment page restores this page from the cache: let the shopper try again.
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    state.placing = false;
    const confirmButton = $('[data-confirm-place]', confirm);
    confirmButton.disabled = false;
    confirmButton.classList.remove('is-loading');
    confirmButton.textContent = 'Place my order';
    renderTotals();
  });

  syncAddressUi();
  renderTotals();
  renderCoupon();
}
