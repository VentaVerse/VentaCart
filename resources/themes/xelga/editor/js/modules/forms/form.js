/*
 * Inspector forms, generated from catalog settings. Basic settings come
 * first; settings marked `advanced` sit behind the Advanced disclosure.
 */

import { h, icon, helpTip } from '../dom.js';
import { isFormSetting } from '../catalog.js';
import { humanize } from '../util.js';
import { buildControl } from './controls.js';

/** The form currently shown, for visible_if refreshes and error marks. */
let current = null;

export function visibleIf(setting, read) {
  if (!setting.visible_if) return true;
  return Object.entries(setting.visible_if).every(([k, expected]) => {
    const v = read(k);
    return Array.isArray(expected) ? expected.map(String).includes(String(v)) : String(v) === String(expected);
  });
}

export const dependsOn = (settings, key) => settings.some((d) => d.visible_if && key in d.visible_if);

/** Shows and hides fields whose visible_if depends on a changed value. */
export function refreshVisibility() {
  if (!current?.form?.isConnected) return;
  current.settings.forEach((setting) => {
    const row = current.form.querySelector(`[data-field="${CSS.escape(setting.key)}"]`);
    if (row) row.hidden = !visibleIf(setting, current.read);
  });
}

/** Marks fields the server refused, with its message under each. */
export function refreshFieldErrors() {
  if (!current?.form?.isConnected) return;
  const errors = current.errors?.() || {};
  current.form.querySelectorAll('[data-field]').forEach((row) => {
    const messages = errors[row.dataset.field];
    let note = row.querySelector(':scope > .field__error');
    const control = row.querySelector('input, textarea, select, [contenteditable], [role="radiogroup"], button');
    if (!messages?.length) {
      note?.remove();
      row.classList.remove('has-error');
      control?.removeAttribute('aria-invalid');
      return;
    }
    if (!note) {
      note = h('p', { class: 'field__error', id: `${row.dataset.fieldId}-error` });
      row.append(note);
    }
    note.replaceChildren(icon('alert', 'icon--sm'), h('span', { text: messages[0] }));
    row.classList.add('has-error');
    control?.setAttribute('aria-invalid', 'true');
    control?.setAttribute('aria-errormessage', note.id);
  });
}

/**
 * Builds the form for a list of settings.
 *   fctx: { kind: 'section' | 'block' | 'theme', sid, bid, group }
 *   read(key): the current value
 *   onChange(setting, value, { merge }): records the change
 *   errors(): { key: [messages] } from the last refused save
 */
export function buildForm(settings, fctx, read, onChange, errors = null) {
  const list = settings.filter(isFormSetting);
  const form = h('div', { class: 'form' });
  const basic = list.filter((d) => !d.advanced);
  const advanced = list.filter((d) => d.advanced);
  basic.forEach((d) => form.append(fieldRow(d, fctx, read, onChange)));
  if (advanced.length) {
    const details = h('details', { class: 'advanced' },
      h('summary', null, icon('chevron-right'), 'Advanced', h('span', { class: 'advanced__count', text: `${advanced.length} more` })));
    advanced.forEach((d) => details.append(fieldRow(d, fctx, read, onChange)));
    form.append(details);
  }
  current = { form, settings: list, read, errors };
  window.setTimeout(refreshFieldErrors, 0);
  return form;
}

const GROUPED = new Set(['color-scheme', 'image', 'product-picker', 'category-picker', 'brand-picker', 'page-picker', 'blog-post-picker', 'scheme-colors', 'richtext', 'url']);

function fieldRow(setting, fctx, read, onChange) {
  const id = `f-${fctx.sid || 'theme'}-${fctx.bid || ''}-${setting.key}`.replace(/[^a-z0-9-_]/gi, '-');
  const value = read(setting.key);
  const change = (v, opts) => onChange(setting, v, opts);
  const isToggle = setting.type === 'toggle';
  const usesFieldset = (setting.type === 'select' && setting.control !== undefined) || GROUPED.has(setting.type);
  const row = h('div', {
    class: `field${isToggle ? ' field--toggle' : ''}`,
    dataset: { field: setting.key, fieldId: id },
    role: usesFieldset ? 'group' : null,
    'aria-labelledby': usesFieldset ? `${id}-label` : null,
  });

  const labelText = setting.label || humanize(setting.key);
  const tip = helpTip(setting.info, `${id}-help`);
  const label = usesFieldset
    ? h('div', { class: 'field__label', id: `${id}-label` }, h('span', { text: labelText }), tip)
    : h('label', { class: 'field__label', for: id, id: `${id}-label` }, h('span', { text: labelText }), tip);
  row.append(label);

  const control = buildControl(setting, value, change, id, fctx);
  row.append(control);
  if (tip) {
    const focusable = control.matches?.('input, select, textarea, button') ? control : control.querySelector?.('input, select, textarea, [contenteditable]');
    focusable?.setAttribute('aria-describedby', `${id}-help`);
  }

  if (!visibleIf(setting, read)) row.hidden = true;
  return row;
}
