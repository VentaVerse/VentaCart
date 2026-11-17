/*
 * Content pages: the blog's grid or list switch, tables in page and post
 * text that scroll sideways on a phone, and the contact form's checks
 * beside each field before core checks the message again. Inside the
 * editor's preview a click selects instead of acting, so nothing here
 * runs on a click there.
 */
import { $, $$ } from '../util.js';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Grid or list: switch the section's layout and keep the choice in the address and the pager. */
function viewSwitch(section) {
  const buttons = $$('[data-view]', section);
  if (!buttons.length) return null;

  const onClick = (event) => {
    const button = event.target.closest('[data-view]');
    if (!button || !section.contains(button)) return;
    const view = button.dataset.view;
    section.dataset.variant = view;
    buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('view', view);
      window.history.replaceState(window.history.state, '', url);
      $$('.pager a[href]', section).forEach((link) => {
        const next = new URL(link.href, window.location.href);
        next.searchParams.set('view', view);
        link.href = next.toString();
      });
    } catch {
      // An address the browser will not change: the layout still switches.
    }
  };
  section.addEventListener('click', onClick);

  return () => section.removeEventListener('click', onClick);
}

/** Wrap each table of rich text in a region that scrolls sideways, once. */
function scrollTables(section) {
  $$('.rte table', section).forEach((table) => {
    if (table.parentElement?.classList.contains('table-wrap')) return;
    const wrap = document.createElement('div');
    wrap.className = 'table-wrap';
    wrap.setAttribute('role', 'region');
    wrap.setAttribute('tabindex', '0');
    const caption = table.querySelector('caption')?.textContent?.trim();
    wrap.setAttribute('aria-label', caption || 'Table');
    table.before(wrap);
    wrap.append(table);
  });
}

/** The contact form: the same checks core makes, shown beside the field, before sending. */
function contactForm(section) {
  const form = $('[data-contact-form]', section);
  if (!form) return null;
  const status = $('[data-form-status]', form);

  const setError = (field, on) => {
    const error = $(`[data-error-for="${field.name}"]`, form);
    if (on) field.setAttribute('aria-invalid', 'true');
    else field.removeAttribute('aria-invalid');
    if (error) error.hidden = !on;
  };

  const onSubmit = (event) => {
    const fields = $$('[name="name"], [name="email"], [name="subject"], [name="message"]', form);
    const bad = fields.filter((field) => {
      const value = field.value.trim();
      const wrong = value === '' || (field.name === 'email' && !EMAIL.test(value));
      setError(field, wrong);
      return wrong;
    });
    if (!bad.length) return;
    event.preventDefault();
    if (status) {
      status.hidden = false;
      status.className = 'form-status form-status--error';
      status.setAttribute('role', 'alert');
      status.textContent = 'Check the fields marked below, then send again.';
    }
    bad[0].focus();
  };

  const onInput = (event) => {
    const field = event.target;
    if (field.getAttribute?.('aria-invalid') === 'true' && field.value.trim()) setError(field, false);
  };

  form.addEventListener('submit', onSubmit);
  form.addEventListener('input', onInput);
  form.addEventListener('change', onInput);

  return () => {
    form.removeEventListener('submit', onSubmit);
    form.removeEventListener('input', onInput);
    form.removeEventListener('change', onInput);
  };
}

export function initPageParts(section) {
  scrollTables(section);
  const done = [viewSwitch(section), contactForm(section)];

  return () => done.forEach((cleanup) => cleanup && cleanup());
}
