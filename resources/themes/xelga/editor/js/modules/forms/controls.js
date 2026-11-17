/*
 * One control per setting type (ARCHITECTURE 4). The `control` hint of a
 * select picks how it is drawn: segmented buttons, option cards with a small
 * layout drawing, an icon grid or font pair samples; without one it is a
 * native select.
 */

import { h, icon, schematic, toast } from '../dom.js';
import { sec } from '../state.js';
import { optionsOf, variantDrawing, SCHEMATICS, sectionDef } from '../catalog.js';
import { humanize } from '../util.js';
import { radioItems, radioKeys } from '../selection.js';
import { variantFits } from '../board/drawer.js';
import { richText } from './richtext.js';
import { schemePicker, schemeEditor } from './schemes.js';
import { fontPairPicker } from './fonts.js';
import { imagePicker } from '../pickers/image.js';
import { linkPicker } from '../pickers/link.js';
import { menuPicker } from '../pickers/menu.js';
import { entityPicker } from '../pickers/entity.js';

const TEXT_MAX = 255;
const TEXTAREA_MAX = 2000;

export function buildControl(setting, value, change, id, fctx) {
  if (setting.readonly) return readonlyValue(setting, value);
  switch (setting.type) {
    case 'text':
      return h('input', {
        class: 'input', id, type: 'text', value: value ?? '', placeholder: setting.placeholder || '',
        maxlength: setting.max_length || TEXT_MAX,
        oninput: (e) => change(e.target.value, { merge: true }),
      });
    case 'textarea': {
      const ta = h('textarea', {
        class: 'textarea', id, rows: 3, maxlength: setting.max_length || TEXTAREA_MAX,
        oninput: (e) => change(e.target.value, { merge: true }),
      });
      ta.value = value ?? '';
      return ta;
    }
    case 'richtext':
      return richText(value ?? '', change, id);
    case 'toggle':
      return h('button', {
        type: 'button', class: 'switch', id, role: 'switch', 'aria-checked': String(Boolean(value)),
        'aria-label': setting.label,
        onclick: (e) => {
          const next = e.currentTarget.getAttribute('aria-checked') !== 'true';
          e.currentTarget.setAttribute('aria-checked', String(next));
          change(next);
        },
      });
    case 'range':
      return rangeControl(setting, value, change, id);
    case 'select':
      return selectControl(setting, value, change, id, fctx);
    case 'color-scheme':
      return schemePicker(value, change);
    case 'scheme-colors':
      return schemeEditor();
    case 'image':
      return imagePicker(value, change, setting);
    case 'url':
      return linkPicker(value, change, id, setting);
    case 'menu-picker':
      return menuPicker(value, change, id);
    case 'product-picker':
      return entityPicker('products', value, change, setting);
    case 'category-picker':
      return entityPicker('categories', value, change, setting);
    case 'brand-picker':
      return entityPicker('brands', value, change, setting);
    case 'page-picker':
      return entityPicker('pages', value, change, setting);
    case 'blog-post-picker':
      return entityPicker('blog-posts', value, change, setting);
    default:
      return h('input', {
        class: 'input', id, type: 'text', value: typeof value === 'string' ? value : '',
        maxlength: TEXT_MAX, oninput: (e) => change(e.target.value, { merge: true }),
      });
  }
}

/* Values another screen owns (the store's phone, email and address), shown read-only with a way there. */
function readonlyValue(setting, value) {
  return h('div', { class: 'readonly' },
    h('p', { class: 'readonly__value', text: value || 'Not set' }),
    setting.edit_url ? h('a', { class: 'link-btn', href: setting.edit_url, target: '_blank', rel: 'noopener' }, setting.edit_label || 'Change in Settings') : null);
}

export function nativeSelect(id, options, value, change) {
  const sel = h('select', { class: 'select', id, onchange: (e) => change(e.target.value) },
    ...options.map((o) => h('option', { value: o.value, selected: String(o.value) === String(value) }, o.label)));
  if (value && !options.some((o) => String(o.value) === String(value))) {
    sel.prepend(h('option', { value, selected: true }, humanize(value)));
  }
  return sel;
}

function rangeControl(setting, value, change, id) {
  const min = Number(setting.min ?? 0);
  const max = Number(setting.max ?? 100);
  const step = Number(setting.step ?? 1);
  const current = Number(value ?? setting.default ?? min);
  const fill = (v) => `${((v - min) / (max - min || 1)) * 100}%`;
  const valueText = (v) => `${v}${setting.unit ? ` ${setting.unit}` : ''}`;
  const slider = h('input', { type: 'range', id, min, max, step, value: current, 'aria-valuetext': valueText(current) });
  slider.style.setProperty('--fill', fill(current));
  const num = h('input', { type: 'number', min, max, step, value: current, 'aria-label': `${setting.label} value` });
  const sync = (v, from) => {
    let n = Number(v);
    if (!Number.isFinite(n)) return;
    // Same rule as the server: clamp to the bounds and snap to the step.
    n = Math.max(min, Math.min(max, Math.round((n - min) / step) * step + min));
    n = Number(n.toFixed(4));
    if (from !== slider) slider.value = n;
    if (from !== num || Number(num.value) !== n) num.value = n;
    slider.style.setProperty('--fill', fill(n));
    slider.setAttribute('aria-valuetext', valueText(n));
    change(n, { merge: true });
  };
  slider.addEventListener('input', () => sync(slider.value, slider));
  num.addEventListener('change', () => sync(num.value, num));
  return h('div', { class: 'range' }, slider,
    h('span', { class: 'range__num' }, num, setting.unit ? h('span', { class: 'range__unit', text: setting.unit }) : null));
}

function selectControl(setting, value, change, id, fctx) {
  const options = optionsOf(setting);
  const control = setting.control;

  if (control === 'font-pairs') return fontPairPicker(setting, options, value, change);

  if (control === 'segmented' && options.length <= 4) {
    const group = h('div', { class: 'seg seg--fill', role: 'radiogroup', 'aria-labelledby': `${id}-label` });
    options.forEach((o) => group.append(h('button', {
      type: 'button', role: 'radio', dataset: { value: o.value },
      onclick: () => {
        radioItems(group, o.value);
        change(o.value);
      },
    }, o.label)));
    radioItems(group, value);
    radioKeys(group);
    return group;
  }

  if (control === 'cards') {
    const type = fctx.sid ? sec(fctx.sid)?.type : null;
    const isVariant = setting.key === 'variant' && type;
    const drawingOf = (v) => (isVariant ? variantDrawing(type, v) : SCHEMATICS[`${type || 'theme'}:${setting.key}`]?.[v]) || null;
    const hasDrawings = options.some((o) => drawingOf(o.value));
    const wrap = h('div', null);
    const group = h('div', { class: `cards${hasDrawings ? '' : ' cards--list'}`, role: 'radiogroup', 'aria-labelledby': `${id}-label` });
    // A layout wider than the section's column is shown, but says it needs a wider one.
    options.forEach((o) => {
      const fits = !isVariant || variantFits(fctx.sid, o.value);
      group.append(h('button', {
        type: 'button', role: 'radio', class: `opt-card${hasDrawings ? '' : ' opt-card--text'}`, dataset: { value: o.value },
        'aria-disabled': fits ? null : 'true', title: fits ? null : 'Needs a wider column',
        'aria-description': fits ? null : 'Needs a wider column',
        onclick: () => {
          if (!fits) {
            toast(`${o.label} needs a wider column than this one.`, { tone: 'error' });
            return;
          }
          radioItems(group, o.value);
          change(o.value);
        },
      }, hasDrawings ? schematic(drawingOf(o.value)) : null, h('span', { class: 'opt-card__label', text: o.label })));
    });
    radioItems(group, value);
    radioKeys(group);
    wrap.append(group);
    return wrap;
  }

  if (control === 'icons') {
    const group = h('div', { class: 'icon-grid', role: 'radiogroup', 'aria-labelledby': `${id}-label` });
    options.forEach((o) => group.append(h('button', {
      type: 'button', role: 'radio', dataset: { value: o.value }, 'aria-label': o.label, title: o.label,
      onclick: () => {
        radioItems(group, o.value);
        change(o.value);
      },
    }, icon(o.value))));
    radioItems(group, value);
    radioKeys(group);
    return group;
  }

  return nativeSelect(id, options.map((o) => ({ value: o.value ?? o.key ?? o.id, label: o.label ?? o.name })), value, change);
}
