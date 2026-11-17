/*
 * The inspector: the settings of what is selected. Nothing selected teaches
 * the interface; a section shows its settings and its blocks; a block shows
 * its own; a theme settings group shows the group.
 *
 * A section's panel may also hold theme settings that belong with it (the
 * header's cart icon, `edited_in` in the theme settings schema). Those fields
 * read and save the theme settings document, as under Theme settings.
 */

import { $, h, icon, emptyArt, helpTip } from './dom.js';
import { S, ui, sec, blk, locate, valueOf, labelOf, themeValue, settingsDoc, isBusy } from './state.js';
import { sectionDef, blockDef, themeGroups, ownThemeGroups, themeGroupsIn, isFormSetting } from './catalog.js';
import { commit } from './operations.js';
import { same, humanize } from './util.js';
import { select, deselect, showPanel } from './selection.js';
import { buildForm, dependsOn, refreshVisibility, refreshFieldErrors } from './forms/form.js';
import { toggleVisible, removeSection, removeBlock, canRemoveBlock, duplicateSection, canDuplicate, duplicateBlock, canDuplicateBlock } from './actions.js';

const body = () => $('#inspector-body');

const selKey = (sel) => {
  if (sel.kind === 'theme') return `theme:${sel.group}:${sel.scheme || ''}`;
  if (sel.kind === 'layout') return `layout:${S.page?.handle || ''}`;
  if (sel.kind === 'stores') return 'stores';
  return `${sel.sid}/${sel.bid || ''}`;
};
let shownKey = null;

export function renderInspector() {
  const el = body();
  if (!S.loaded) return;
  const scroll = $('.insp-body', el)?.scrollTop || 0;
  const key = S.sel ? selKey(S.sel) : null;
  const sameTarget = key && key === shownKey;
  // Keep focus where it was when the same form is drawn again (undo, redo).
  const focusedField = sameTarget ? document.activeElement?.closest?.('[data-field]')?.dataset.field : null;
  if (!S.sel) {
    el.replaceChildren(emptyInspector());
  } else if (S.sel.kind === 'theme') {
    el.replaceChildren(themeInspector(S.sel.group));
  } else if (S.sel.kind === 'stores') {
    el.replaceChildren(ui.storesInspector?.(inspectorHead) || emptyInspector());
  } else if (S.sel.kind === 'layout') {
    const layout = ui.layoutInspector?.(inspectorHead);
    if (!layout) S.sel = null;
    el.replaceChildren(layout || emptyInspector());
  } else {
    const s = sec(S.sel.sid);
    if (!s || (S.sel.bid && !blk(S.sel.sid, S.sel.bid))) {
      S.sel = null;
      el.replaceChildren(emptyInspector());
    } else {
      el.replaceChildren(sectionInspector(S.sel.sid, S.sel.bid));
    }
  }
  shownKey = S.sel ? selKey(S.sel) : null;
  if (sameTarget) {
    const b = $('.insp-body', el);
    if (b) b.scrollTop = scroll;
    if (focusedField) el.querySelector(`[data-field="${CSS.escape(focusedField)}"] input, [data-field="${CSS.escape(focusedField)}"] textarea, [data-field="${CSS.escape(focusedField)}"] select`)?.focus({ preventScroll: true });
  }
  ui.updateDock();
}

function emptyInspector() {
  return h('div', { class: 'empty' },
    emptyArt(),
    h('h2', { text: 'Click anything on your store to edit it' }),
    h('p', { text: 'The settings for what you click open here. Changes show on the preview straight away and stay private until you publish.' }),
    h('ul', { class: 'tips', role: 'list' },
      h('li', null, h('span', { class: 'tips__icon' }, icon('layers')), h('span', null, h('strong', { text: 'Sections list on the left' }), 'Hover a row to find it on the page. Drag rows to change the order.')),
      h('li', null, h('span', { class: 'tips__icon' }, icon('eye')), h('span', null, h('strong', { text: 'Hide instead of delete' }), 'The eye button hides a section and keeps its settings.')),
      h('li', null, h('span', { class: 'tips__icon' }, icon('palette')), h('span', null, h('strong', { text: 'Theme settings' }), 'Colors, fonts and buttons for the whole store.')),
      h('li', null, h('span', { class: 'tips__icon' }, icon('undo')), h('span', null, h('strong', { text: 'Nothing is final' }), 'Undo with Ctrl+Z. Your work saves itself as a draft until you publish.')),
      h('li', null, h('span', { class: 'tips__icon' }, icon('external')), h('span', null, h('strong', { text: 'Visit another page' }), 'Hold Alt and click a link in the preview to follow it.'))),
    h('div', { class: 'legend' },
      h('span', null, h('i', null), 'Section outline'),
      h('span', null, h('i', { class: 'is-block' }), 'Block outline')));
}

export function inspectorHead({ title, help = null, kind, kindBlock = false, actions = [] }) {
  const head = h('div', { class: 'insp-head' });
  const top = h('div', { class: 'insp-head__row' },
    h('button', {
      type: 'button', class: 'icon-btn insp-back', 'aria-label': 'Back',
      onclick: () => {
        if (S.sel?.kind === 'section' && S.sel.bid) select(S.sel.sid);
        else showPanel('nav');
      },
    }, icon('arrow-left')),
    h('h2', { class: 'insp-title' }, h('span', { text: title }), helpTip(help)),
    ...actions,
    h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Close settings', 'data-tip': 'Close', onclick: deselect }, icon('close')));
  head.append(top);
  if (kind) head.append(h('p', { class: 'insp-kind' }, h('span', { class: `insp-kind__dot${kindBlock ? ' insp-kind__dot--block' : ''}`, 'aria-hidden': 'true' }), kind));
  return head;
}

/** Field messages from a refused save, for one section or block, and the theme fields it shows. */
function errorsFor(sid, bid, themeKeys = new Set()) {
  return () => {
    const at = locate(sid);
    const out = themeKeys.size ? themeErrors(themeKeys) : {};
    Object.entries(at?.doc?.fieldErrors || {}).forEach(([path, messages]) => {
      const p = path.replace(/^content\./, '').split('.');
      if (p[0] !== 'sections' || p[1] !== sid) return;
      if (bid ? p[2] === 'blocks' && p[3] === bid && p[4] === 'settings' : p[2] === 'settings') {
        out[bid ? p[5] : p[3]] = Array.isArray(messages) ? messages : [messages];
      }
    });
    return out;
  };
}

function sectionInspector(sid, bid) {
  const at = locate(sid);
  const s = at.data;
  const def = sectionDef(s.type);
  if (!def) return missingSection(sid);
  const target = bid ? s.blocks[bid] : s;
  const bdef = bid ? blockDef(s.type, target.type) : null;
  const hidden = Boolean(target.disabled);
  const settings = bid ? bdef?.settings || [] : withThemeFields(s.type, def.settings || []);
  const themeKeys = new Set(settings.filter((d) => d.store === 'theme').map((d) => d.key));
  const main = !bid && def.main;

  const eye = main ? null : h('button', {
    type: 'button', class: 'icon-btn', 'aria-label': hidden ? 'Show on the store' : 'Hide on the store',
    'aria-pressed': String(hidden), 'data-tip': hidden ? 'Show' : 'Hide',
    onclick: () => toggleVisible(sid, bid),
  }, icon(hidden ? 'eye-off' : 'eye'));

  const head = inspectorHead({
    title: labelOf(sid, bid) || humanize(target.type),
    help: bid ? bdef?.description || bdef?.info : def.description,
    kind: bid ? `${bdef?.label || humanize(target.type)} in ${labelOf(sid)}` : `${def.label} section`,
    kindBlock: Boolean(bid),
    actions: [eye],
  });

  const content = h('div', { class: 'insp-body' });
  if (hidden) {
    content.append(h('p', { class: 'notice notice--warn' }, icon('eye-off'), `Hidden on the store. Shoppers do not see this ${bid ? 'block' : 'section'}.`));
  }
  content.append(h('p', { class: 'notice notice--busy', id: 'insp-busy', role: 'status', hidden: !isBusy(sid) || null }, icon('rotate-ccw'), 'Updating the preview with the store\'s data.'));

  const read = (key) => (themeKeys.has(key) ? themeValue(key) : valueOf(sid, bid, key));
  const form = buildForm(settings, { kind: bid ? 'block' : 'section', sid, bid }, read, (setting, value, opts) => {
    if (same(read(setting.key), value)) return;
    if (setting.store === 'theme') {
      commitTheme(setting.key, value, opts);
      if (dependsOn(settings, setting.key)) refreshVisibility();
      return;
    }
    const live = bid ? blk(sid, bid) : sec(sid);
    if (!live) return;
    const from = clone0(live.settings[setting.key]);
    commit({
      t: 'set', doc: locate(sid).handle, sid, bid, key: setting.key, from, to: value,
      name: labelOf(sid, bid),
      mergeKey: opts?.merge ? `${sid}/${bid || ''}/${setting.key}` : null,
    }, opts);
    if (dependsOn(settings, setting.key)) refreshVisibility();
  }, errorsFor(sid, bid, themeKeys));
  content.append(form);

  if (!bid && at.group === 'template' && !main) {
    content.append(h('div', { class: 'insp-foot' },
      h('button', {
        type: 'button', class: 'btn btn--secondary btn--sm', disabled: !canDuplicate(sid) || null,
        title: canDuplicate(sid) ? null : 'This page has as many as it can hold',
        onclick: () => duplicateSection(sid),
      }, icon('copy', 'icon--sm'), 'Duplicate'),
      h('button', { type: 'button', class: 'btn btn--danger-text btn--sm', onclick: () => removeSection(sid) }, icon('trash', 'icon--sm'), 'Remove section')));
  }
  if (bid) {
    content.append(h('div', { class: 'insp-foot' },
      h('button', {
        type: 'button', class: 'btn btn--secondary btn--sm', disabled: !canDuplicateBlock(sid, bid) || null,
        title: canDuplicateBlock(sid, bid) ? null : 'This section holds as many as it can',
        onclick: () => duplicateBlock(sid, bid),
      }, icon('copy', 'icon--sm'), 'Duplicate'),
      h('button', {
        type: 'button', class: 'btn btn--danger-text btn--sm', disabled: !canRemoveBlock(sid) || null,
        title: canRemoveBlock(sid) ? null : 'This section needs at least this many',
        onclick: () => removeBlock(sid, bid),
      }, icon('trash', 'icon--sm'), 'Remove block')));
  }
  return h('div', { class: 'inspector' }, head, content);
}

/* A stored value is copied so the operation's "from" never shares memory with the document. */
const clone0 = (v) => (v === undefined ? undefined : JSON.parse(JSON.stringify(v)));

/**
 * A section's own settings with the theme settings edited in its panel,
 * each group placed after the field it names. They are marked
 * `store: 'theme'` so the form saves them to the theme settings.
 */
function withThemeFields(type, own) {
  const list = [...own];
  themeGroupsIn(type).forEach((group) => {
    const fields = (group.settings || []).filter(isFormSetting).map((d) => ({ ...d, store: 'theme' }));
    const at = list.findIndex((d) => d.key === group.edited_in.after);
    list.splice(at < 0 ? list.length : at + 1, 0, ...fields);
  });
  return list;
}

/* One theme setting changed, from Theme settings or from a section's panel. */
function commitTheme(key, value, opts) {
  if (!settingsDoc()) return;
  const from = clone0(settingsDoc().content.values[key]);
  commit({ t: 'theme', key, from, to: value, mergeKey: opts?.merge ? `theme/${key}` : null }, opts);
}

/* Messages the server gave for theme settings, by key, limited to `keys` when given. */
function themeErrors(keys = null) {
  const out = {};
  Object.entries(settingsDoc()?.fieldErrors || {}).forEach(([path, messages]) => {
    const p = path.replace(/^content\./, '').split('.');
    if (p[0] === 'values' && p[1] && (!keys || keys.has(p[1]))) out[p[1]] = Array.isArray(messages) ? messages : [messages];
  });
  return out;
}

function missingSection(sid) {
  return h('div', { class: 'inspector' },
    inspectorHead({ title: 'Section no longer available', kind: 'Removed from the theme' }),
    h('div', { class: 'insp-body' },
      h('p', { class: 'insp-desc', text: 'This section type is not part of the theme any more, so shoppers do not see it. Remove it to tidy the page.' }),
      h('div', { class: 'insp-foot' },
        h('button', { type: 'button', class: 'btn btn--danger-text btn--sm', onclick: () => removeSection(sid) }, icon('trash', 'icon--sm'), 'Remove section'))));
}

function themeInspector(groupKey) {
  const group = themeGroups().find((g) => g.group === groupKey);
  if (!group || !settingsDoc()) return emptyInspector();
  const head = inspectorHead({
    title: group.label,
    help: group.description,
    kind: 'Applies to the whole store',
  });
  const content = h('div', { class: 'insp-body' });
  const read = (key) => themeValue(key);
  const settings = (group.settings || []).filter(isFormSetting);
  const form = buildForm(settings, { kind: 'theme', group: groupKey }, read, (setting, value, opts) => {
    if (same(read(setting.key), value)) return;
    commitTheme(setting.key, value, opts);
    if (dependsOn(settings, setting.key)) refreshVisibility();
  }, () => themeErrors());
  content.append(form);
  return h('div', { class: 'inspector' }, head, content);
}

/* ------------------------------------------------------------------------
   Theme settings list (left panel)
   ------------------------------------------------------------------------ */

function summarize(group) {
  return (group.settings || []).filter((s) => !s.advanced).slice(0, 3).map((s) => s.label).join(', ');
}

export function renderThemeNav() {
  if (!S.loaded) return;
  const list = h('ul', { class: 't-list', role: 'list' }, ...ownThemeGroups().map((group) => {
    const selected = S.sel?.kind === 'theme' && S.sel.group === group.group;
    return h('li', null, h('button', {
      type: 'button', class: `t-item${selected ? ' is-selected' : ''}`, 'aria-current': selected ? 'true' : null,
      onclick: () => ui.openThemeGroup(group.group),
    }, h('span', { class: 't-item__icon' }, icon(group.icon || 'settings')),
    h('span', { class: 't-item__text' }, h('span', { class: 't-item__title', text: group.label }),
      h('span', { class: 't-item__desc', text: group.description || summarize(group) })),
    icon('chevron-right')));
  }));
  // The first row: the starting store the draft is based on, and the door to the others.
  $('#nav-theme').replaceChildren(...[ui.storeStartRow?.(), list].filter(Boolean));
}

/** Shows or hides the "updating the preview" note without redrawing the form. */
export function updateBusy() {
  const note = $('#insp-busy');
  if (note) note.hidden = !(S.sel?.kind === 'section' && isBusy(S.sel.sid));
}

export function initInspector() {
  ui.renderInspector = renderInspector;
  ui.updateBusy = updateBusy;
  ui.renderThemeNav = renderThemeNav;
  ui.refreshVisibility = refreshVisibility;
  ui.refreshFieldErrors = refreshFieldErrors;
}
