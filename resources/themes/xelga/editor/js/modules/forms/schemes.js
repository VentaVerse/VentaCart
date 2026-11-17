/*
 * Color schemes: the swatch picker a section or a row uses, and the scheme
 * editor in Theme settings. A scheme is shown by its colors and the places
 * that use it across the store, never by a name: a stored label stays in
 * the settings document but is not shown. Every scheme is checked for
 * readability while editing; a low pair is a warning here and a refusal at
 * Publish.
 *
 * Where each scheme is used comes from the server (every page, read from
 * the saved drafts), so it is asked for once the draft is saved, and again
 * after a change while a count is on screen.
 */

import { $$, h, icon, helpTip } from '../dom.js';
import { S, ctx, ui, schemes, templateOf } from '../state.js';
import { themeGroups } from '../catalog.js';
import { readability, isHex, humanize } from '../util.js';
import { commit, onChange } from '../operations.js';
import { radioItems, radioKeys, openThemeGroup } from '../selection.js';
import { flush } from '../drafts.js';

/** The schemes a picker offers: id and colors, never a stored name. */
export function schemeChoices(list) {
  return (Array.isArray(list) ? list : [])
    .filter((s) => s && typeof s === 'object' && typeof s.id === 'string')
    .map((s) => ({ id: s.id, colors: { ...(s.colors && typeof s.colors === 'object' ? s.colors : {}) } }));
}

/** A scheme's number, from its id, for screen readers and messages ("Scheme 2"), as the server words it. */
const numberOf = (id) => String(id).replace(/^scheme-/, '');

/* ------------------------------------------------------------------------
   Where each scheme is used
   ------------------------------------------------------------------------ */

const USAGE_WAIT_MS = 1200;
// `changes` counts changes, so an answer asked for before one is still known to be old.
const usage = { data: null, stale: true, loading: null, failed: false, timer: 0, changes: 0 };

const usesOf = (id) => usage.data?.[id] || [];

const timesText = (n) => (n === 0 ? 'Not used' : `Used ${n} ${n === 1 ? 'time' : 'times'}`);

/** Asks the server where each scheme is used, once the draft is saved. */
function loadUsage() {
  if (usage.loading || !S.loaded || S.fatal || typeof ctx.api?.schemeUsage !== 'function') return;
  const asked = usage.changes;
  usage.loading = (async () => {
    try {
      await flush();
      usage.data = await ctx.api.schemeUsage();
      usage.stale = usage.changes !== asked;
      usage.failed = false;
    } catch {
      usage.failed = true;
    } finally {
      usage.loading = null;
      paintUsage();
      if (usage.stale && !usage.failed && usageShown()) {
        window.clearTimeout(usage.timer);
        usage.timer = window.setTimeout(loadUsage, USAGE_WAIT_MS);
      }
    }
  })();
}

/** Counts and places on screen, so the server is only asked when one shows. */
const usageShown = () => $$('[data-uses-of], [data-places-of], .scheme-swatch[data-value]').length > 0;

// A change may move a scheme's uses: ask again a moment later, if any are on screen.
onChange((op) => {
  if (op.t === 'rename') return;
  usage.stale = true;
  usage.changes += 1;
  window.clearTimeout(usage.timer);
  usage.timer = window.setTimeout(() => { if (usageShown()) loadUsage(); }, USAGE_WAIT_MS);
});

/** Writes the counts and places into whatever shows them now. */
function paintUsage() {
  $$('.scheme-swatch[data-value]').forEach((b) => {
    const id = b.dataset.value;
    if (!id) return;
    const n = usage.data ? usesOf(id).length : null;
    const words = `Scheme ${numberOf(id)}${n === null ? '' : `, ${timesText(n).toLowerCase()}`}`;
    b.setAttribute('aria-label', words);
    b.title = n === null ? '' : n === 0 ? 'Not used anywhere yet' : `${timesText(n)} across the store`;
  });
  $$('[data-uses-of]').forEach((el) => {
    const id = el.dataset.usesOf;
    if (usage.data) el.textContent = timesText(usesOf(id).length);
    else el.textContent = usage.failed ? 'Uses could not be counted' : 'Counting uses...';
  });
  $$('[data-places-of]').forEach((el) => el.replaceChildren(...placesList(el.dataset.placesOf)));
}

/** The page a document is drawn on, in the merchant's words. */
function pageName(handle) {
  if (handle === 'header' || handle === 'footer') return 'Every page';
  const custom = templateOf(handle);
  if (custom) return custom.label;
  return S.pages.find((p) => p.handle === handle)?.label || humanize(handle);
}

function placesList(id) {
  if (!usage.data) {
    return usage.failed
      ? [h('p', { class: 'places__note' }, 'Where this scheme is used could not be loaded. ',
        h('button', { type: 'button', class: 'link-btn', onclick: () => loadUsage() }, 'Try again'))]
      : [];
  }
  const uses = usesOf(id);
  if (!uses.length) return [h('p', { class: 'places__none', text: 'Not used anywhere yet.' })];
  const groups = new Map();
  uses.forEach((u) => {
    const name = pageName(u.handle);
    if (!groups.has(name)) groups.set(name, []);
    groups.get(name).push(u);
  });
  return [...groups].map(([name, list]) => h('div', { class: 'places__group' },
    h('span', { class: 'places__page', text: name }),
    h('ul', { class: 'places__list', role: 'list' }, ...list.map((u) => h('li', null, h('button', {
      type: 'button', class: 'link-btn places__link',
      'aria-label': `${u.label}, ${name === 'Every page' ? 'on every page' : `on ${name}`}: show it on the board`,
      onclick: () => ui.goToPlace?.(u),
    }, u.label))))));
}

/** Puts the counts on what was just drawn, and asks for them when they are old. */
function wantUsage() {
  window.setTimeout(() => {
    paintUsage();
    if (usage.stale) loadUsage();
  }, 0);
}

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

/**
 * The swatches to choose a scheme from, each painted in its colors with no
 * name. With `allowNone`, the first swatch is no scheme (a row's
 * background left as the page's).
 */
export function schemePicker(value, change, { allowNone = false } = {}) {
  const list = schemeChoices(schemes());
  const wrap = h('div', null);
  if (!list.length) {
    wrap.append(h('p', { class: 'field__help', text: 'This store has no color schemes yet. Add them in Theme settings.' }));
    return wrap;
  }
  const group = h('div', { class: 'schemes schemes--choose', role: 'radiogroup', 'aria-label': allowNone ? 'Background' : 'Color scheme' });
  const pick = (id) => {
    radioItems(group, id);
    change(id);
  };
  if (allowNone) {
    group.append(h('button', {
      type: 'button', role: 'radio', class: 'scheme-swatch', dataset: { value: '' },
      'aria-label': 'No background color', title: 'No background color',
      onclick: () => pick(''),
    }, h('span', { class: 'scheme-chip scheme-chip--none' }, icon('minus', 'icon--sm'))));
  }
  list.forEach((scheme) => group.append(h('button', {
    type: 'button', role: 'radio', class: 'scheme-swatch', dataset: { value: scheme.id },
    'aria-label': `Scheme ${numberOf(scheme.id)}`,
    onclick: () => pick(scheme.id),
  }, schemeChip(scheme))));
  radioItems(group, value ?? '');
  radioKeys(group);
  wrap.append(group);
  if (schemeGroupKey()) {
    wrap.append(h('p', { class: 'field__help field__help--after' },
      h('button', {
        type: 'button', class: 'link-btn',
        onclick: () => openSchemes(value || list[0].id),
      }, 'See where each scheme is used')));
  }
  wantUsage();
  return wrap;
}

/** Opens the scheme editor with one scheme's colors shown: in the drawer beside the board, else in Theme settings. */
function openSchemes(id) {
  if (ui.openDrawer?.({ type: 'schemes', scheme: id })) return;
  openThemeGroup(schemeGroupKey(), { scheme: String(id) });
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

/**
 * The scheme editor: each scheme as its swatch, how often it is used and
 * every place, grouped by page, each a link to that place on the board.
 * Its colors open below on demand.
 */
export function schemeEditor() {
  const roles = schemeRoles();
  const openScheme = S.sel?.scheme || null;
  const wrap = h('div', { class: 'scheme-rows' });
  schemes().forEach((scheme) => {
    const n = numberOf(scheme.id);
    const open = openScheme === scheme.id;
    const low = readability(scheme.colors).some((p) => !p.ok);
    const chip = schemeChip(scheme, true);
    const row = h('div', { class: `scheme-row${open ? ' is-open' : ''}${low ? ' is-low' : ''}`, dataset: { scheme: scheme.id } });
    const headBtn = h('button', {
      type: 'button', class: 'scheme-row__head', 'aria-expanded': String(open),
      onclick: () => {
        S.sel = { ...S.sel, scheme: open ? null : scheme.id };
        ui.renderInspector();
      },
    }, chip,
    h('span', { class: 'scheme-row__text' },
      h('span', { class: 'visually-hidden', text: `Scheme ${n}, colors: ` }),
      h('span', { class: 'scheme-row__use scheme-row__count', dataset: { usesOf: scheme.id } })),
    low ? h('span', { class: 'scheme-row__warn', 'aria-label': 'Hard to read' }, icon('alert')) : null,
    icon('chevron-down'));
    row.append(headBtn, h('div', { class: 'places', dataset: { placesOf: scheme.id } }));
    if (open) {
      const body = h('div', { class: 'scheme-row__body' });
      const check = h('div', { 'aria-live': 'polite' });
      const drawCheck = () => {
        check.replaceChildren(readabilityNote(scheme.colors));
        paintChip(chip, scheme.colors);
      };
      roles.forEach(({ role, label, info }) => {
        const start = isHex(scheme.colors[role]) ? scheme.colors[role] : '#000000';
        const colorInput = h('input', { type: 'color', value: start, 'aria-label': `Scheme ${n}: ${label}` });
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
  wantUsage();
  return wrap;
}
