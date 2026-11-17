/*
 * Color schemes: the swatch picker a section uses, and the scheme editor in
 * Theme settings. Every scheme is checked for readability while editing; a
 * low pair is a warning here and a refusal at Publish.
 */

import { h, icon, helpTip } from '../dom.js';
import { S, ui, schemes, pageHandles, doc } from '../state.js';
import { themeGroups } from '../catalog.js';
import { readability, isHex } from '../util.js';
import { commit } from '../operations.js';
import { radioItems, radioKeys, openThemeGroup } from '../selection.js';
import { highlight } from '../preview.js';

const schemeName = (scheme) => scheme.label || scheme.id;

export function schemeRoles() {
  for (const group of themeGroups()) {
    const setting = (group.settings || []).find((s) => s.type === 'scheme-colors');
    if (setting) return setting.roles || [];
  }
  return [];
}

/** Which group holds the scheme editor, for "Edit scheme colors". */
export function schemeGroupKey() {
  return themeGroups().find((g) => (g.settings || []).some((s) => s.type === 'scheme-colors'))?.group || null;
}

export function schemeChip(scheme, small = false) {
  const c = scheme.colors || {};
  const chip = h('span', { class: 'scheme-chip' }, 'Aa', small ? null : h('span', { class: 'scheme-chip__btn' }));
  paintChip(chip, c);
  return chip;
}

function paintChip(chip, c) {
  chip.style.background = c.bg || '#ffffff';
  chip.style.color = c.text || '#000000';
  const btn = chip.querySelector('.scheme-chip__btn');
  if (btn) btn.style.background = c.button || c.text || '#000000';
}

export function schemePicker(value, change) {
  const list = schemes();
  const wrap = h('div', null);
  if (!list.length) {
    wrap.append(h('p', { class: 'field__help', text: 'This store has no color schemes yet. Add them in Theme settings.' }));
    return wrap;
  }
  const group = h('div', { class: 'schemes', role: 'radiogroup', 'aria-label': 'Color scheme' });
  list.forEach((scheme) => group.append(h('button', {
    type: 'button', role: 'radio', class: 'scheme-swatch', dataset: { value: scheme.id }, title: schemeName(scheme),
    'aria-label': schemeName(scheme),
    onclick: () => {
      radioItems(group, scheme.id);
      change(scheme.id);
    },
  }, schemeChip(scheme), h('span', { class: 'scheme-swatch__name', text: schemeName(scheme) }))));
  radioItems(group, value);
  radioKeys(group);
  const groupKey = schemeGroupKey();
  wrap.append(group);
  if (groupKey) wrap.append(h('p', { class: 'field__help field__help--after' },
    h('button', {
      type: 'button', class: 'link-btn',
      onclick: () => openThemeGroup(groupKey, { scheme: String(value || list[0].id) }),
    }, 'Edit scheme colors')));
  return wrap;
}

/** Sections on this page that use a scheme, themselves or through a block. */
function usersOf(id) {
  const users = [];
  pageHandles().forEach((handle) => {
    const c = doc(handle)?.content;
    (c?.order || []).forEach((sid) => {
      const s = c.sections[sid];
      if (s.settings?.scheme === id || Object.values(s.blocks || {}).some((b) => b.settings?.scheme === id)) users.push({ id: sid, label: s.label || s.type });
    });
  });
  return users;
}

function readabilityNote(colors) {
  const pairs = readability(colors);
  const low = pairs.filter((p) => !p.ok);
  const text = pairs.find((p) => p.fg === 'text');
  const button = pairs.find((p) => p.fg === 'button-text');
  if (!low.length) {
    return h('p', { class: 'contrast' }, icon('check'),
      `Easy to read: text ${(text?.ratio || 21).toFixed(1)} to 1, buttons ${(button?.ratio || 21).toFixed(1)} to 1.`);
  }
  return h('div', { class: 'contrast is-low', role: 'status' }, icon('alert'),
    h('div', null,
      h('p', { text: 'Hard to read. Publishing is refused until this is fixed.' }),
      h('ul', { role: 'list' }, ...low.map((p) => h('li', { text: `${p.label}: ${p.ratio.toFixed(1)} to 1, needs ${p.min} to 1` })))));
}

export function schemeEditor() {
  const roles = schemeRoles();
  const openScheme = S.sel?.scheme || null;
  const wrap = h('div', { class: 'scheme-rows' });
  schemes().forEach((scheme) => {
    const users = usersOf(scheme.id);
    const open = openScheme === scheme.id;
    const low = readability(scheme.colors).some((p) => !p.ok);
    const chip = schemeChip(scheme, true);
    const row = h('div', { class: `scheme-row${open ? ' is-open' : ''}${low ? ' is-low' : ''}` });
    const headBtn = h('button', {
      type: 'button', class: 'scheme-row__head', 'aria-expanded': String(open),
      onmouseenter: () => users[0] && highlight(users[0].id),
      onmouseleave: () => highlight(null),
      onclick: () => {
        S.sel = { ...S.sel, scheme: open ? null : scheme.id };
        ui.renderInspector();
      },
    }, chip,
    h('span', { class: 'scheme-row__text' }, h('span', { class: 'scheme-row__name', text: schemeName(scheme) }),
      h('span', { class: 'scheme-row__use', text: users.length ? `Used by ${users.map((u) => u.label).join(', ')}` : 'Not used on this page' })),
    low ? h('span', { class: 'scheme-row__warn', 'aria-label': 'Hard to read' }, icon('alert')) : null,
    icon('chevron-down'));
    row.append(headBtn);
    if (open) {
      const body = h('div', { class: 'scheme-row__body' });
      const check = h('div', { 'aria-live': 'polite' });
      const drawCheck = () => {
        check.replaceChildren(readabilityNote(scheme.colors));
        paintChip(chip, scheme.colors);
      };
      roles.forEach(({ role, label, info }) => {
        const start = isHex(scheme.colors[role]) ? scheme.colors[role] : '#000000';
        const colorInput = h('input', { type: 'color', value: start, 'aria-label': `${schemeName(scheme)}: ${label}` });
        const well = h('span', { class: 'color-well' }, colorInput);
        well.style.background = start;
        const hex = h('input', { class: 'input color-hex', type: 'text', value: scheme.colors[role] || '', maxlength: 7, 'aria-label': `${label} hex code`, spellcheck: 'false' });
        const apply = (color) => {
          const from = scheme.colors[role];
          if (from === color) return;
          well.style.background = color;
          commit({ t: 'color', scheme: scheme.id, role, from, to: color, mergeKey: `color/${scheme.id}/${role}` }, { merge: true });
          drawCheck();
        };
        colorInput.addEventListener('input', () => {
          hex.value = colorInput.value;
          hex.removeAttribute('aria-invalid');
          apply(colorInput.value.toLowerCase());
        });
        hex.addEventListener('input', () => {
          const v = hex.value.trim().toLowerCase();
          const full = /^#?[0-9a-f]{6}$/.test(v) ? (v.startsWith('#') ? v : `#${v}`) : null;
          if (!full) {
            hex.setAttribute('aria-invalid', 'true');
            return;
          }
          hex.removeAttribute('aria-invalid');
          colorInput.value = full;
          apply(full);
        });
        body.append(h('div', { class: 'color-row' },
          h('span', { class: 'color-row__label' }, label, helpTip(info)), well, hex));
      });
      drawCheck();
      body.append(check);
      row.append(body);
    }
    wrap.append(row);
  });
  if (!schemes().length) wrap.append(h('p', { class: 'field__help', text: 'No color schemes are stored yet.' }));
  return wrap;
}
