/*
 * Sign-in, account, checkout and review pages: loaded only on those pages.
 * Every form here is core's own form and works without this script; the
 * script adds conveniences (show password, copy a tracking number, close a
 * confirm step, only digits in a mobile number), the address lookup lists,
 * the reset link for accounts moved from an earlier store, and checkout.
 */
import { $, $$ } from '../util.js';
import { bindAddressFields } from './address-fields.js';

const csrf = () => $('meta[name="csrf-token"]')?.content || '';

function passwordToggles() {
  $$('[data-password-toggle]').forEach((button) => {
    const field = document.getElementById(button.getAttribute('aria-controls'));
    if (!field) return;
    button.hidden = false;
    button.addEventListener('click', () => {
      const show = field.type === 'password';
      field.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      button.querySelector('use')?.setAttribute('href', show ? '#i-eye-off' : '#i-eye');
    });
  });
}

function copyButtons() {
  if (!navigator.clipboard) return;
  $$('[data-copy]').forEach((button) => {
    button.hidden = false;
    const status = button.parentElement?.querySelector('[data-copy-status]');
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(button.dataset.copy);
        if (status) status.textContent = 'Tracking number copied';
        button.setAttribute('aria-label', 'Copied');
        window.setTimeout(() => {
          button.setAttribute('aria-label', 'Copy tracking number');
          if (status) status.textContent = '';
        }, 2000);
      } catch {
        if (status) status.textContent = 'Could not copy. Select the number to copy it.';
      }
    });
  });
}

/** "Keep it" closes the confirm step it sits in and returns focus to its opener. */
function closeButtons() {
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-close-details]');
    if (!button) return;
    const details = button.closest('details');
    if (!details) return;
    details.open = false;
    details.querySelector('summary')?.focus();
  });
  $$('details.cancel').forEach((details) => {
    details.addEventListener('toggle', () => {
      if (details.open) details.querySelector('input[type="text"], button[type="submit"]')?.focus();
    });
  });
}

/** Mobile numbers are digits only, as core's form asks. */
function digitsOnly() {
  document.addEventListener('input', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLInputElement) || !field.hasAttribute('data-digits-only')) return;
    const clean = field.value.replace(/[^0-9]/g, '');
    if (clean !== field.value) field.value = clean;
  });
}

/** An account moved from an earlier store has no password; core emails a link to set one. */
function migrationReset() {
  const box = $('[data-migration-reset]');
  const send = box ? $('[data-migration-send]', box) : null;
  if (!box || !send) return;
  const message = $('[data-migration-msg]', box);
  send.addEventListener('click', async () => {
    send.disabled = true;
    send.classList.add('is-loading');
    message.textContent = '';
    try {
      const response = await fetch(box.dataset.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ email: box.dataset.email }),
      });
      const data = await response.json().catch(() => ({}));
      if (response.ok && data.success) {
        message.textContent = 'Check your email: the link to set your new password is on its way.';
        send.hidden = true;
      } else {
        message.textContent = data.message || 'The link could not be sent. Try the password reset page.';
        send.disabled = false;
      }
    } catch {
      message.textContent = 'The store could not be reached. Check your connection, then try again.';
      send.disabled = false;
    } finally {
      send.classList.remove('is-loading');
    }
  });
}

function addressForms() {
  $$('form[data-address-form] [data-address-fields]').forEach((root) => bindAddressFields(root, { form: root.closest('form') }));
}

export function initFixedPages() {
  passwordToggles();
  copyButtons();
  closeButtons();
  digitsOnly();
  migrationReset();
  addressForms();
  const checkout = $('[data-checkout]');
  if (checkout) {
    import('./checkout.js').then((m) => m.initCheckout(checkout));
  }
}
