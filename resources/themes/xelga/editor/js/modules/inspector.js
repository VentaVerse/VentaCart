/*
 * The inspector: the settings of what is selected, drawn in the panel
 * beside the board. A section shows its settings and its blocks; a block
 * shows its own; a theme settings group shows the group. With nothing
 * selected the panel shows a row's settings, the theme list or the page's
 * own settings (drawer.js).
 *
 * A section's panel may also hold theme settings that belong with it (the
 * header's cart icon, `edited_in` in the theme settings schema). Those fields
 * read and save the theme settings document, as under Theme.
 */

import { $, h, icon, helpTip } from './dom.js';
import { S, ctx, ui, sec, blk, locate, valueOf, labelOf, themeValue, settingsDoc, blockList } from './state.js';
import { sectionDef, sectionIcon, blockDef, blockIcon, themeGroups, themeGroupsIn, isFormSetting, extensionOf, absentName, absentLabel, isLocked } from './catalog.js';
import { commit, layoutPayload, rulesFor } from './operations.js';
import { typeLine, placeWords } from './board/labels.js';
import { same, humanize } from './util.js';
import { select } from './selection.js';
import { openMenu } from './popover.js';
import { needTag } from './needs.js';
import { buildForm, dependsOn, refreshVisibility, refreshFieldErrors } from './forms/form.js';
import { startListDrag } from './board/drag.js';
import {
  toggleVisible, startRename, rename, askRemoveSection, removeBlock, canRemoveBlock, duplicateSection, canDuplicate, duplicateHint, duplicateBlock, canDuplicateBlock,
  addableBlocks, addBlock, addPhotos, photoBlockOf, moveBlockBy, moveBlockTo,
} from './actions.js';

const body = () => $('#drawer-body');

const selKey = (sel) => {
  if (sel.kind === 'theme') return `theme:${sel.group}:${sel.scheme || ''}`;
  if (sel.kind === 'stores') return 'stores';
  return `${sel.sid}/${sel.bid || ''}`;
};
let shownKey = null;

export function renderInspector() {
  if (!S.loaded) return;
  const el = body();
  if (!el) return;
  // Nothing selected: the panel shows a row, the theme list or the page.
  if (!S.sel) {
    shownKey = null;
    ui.renderDrawer();
    return;
  }
  const scroll = $('.insp-body', el)?.scrollTop || 0;
  const key = selKey(S.sel);
  const sameTarget = key === shownKey;
  // Keep focus where it was when the same form is drawn again (undo, redo).
  const focusedField = sameTarget ? document.activeElement?.closest?.('[data-field]')?.dataset.field : null;
  // The same goes for a block of the list: a saved draft draws the panel again.
  const focusedBlock = sameTarget && document.activeElement?.classList?.contains('blist__main')
    ? document.activeElement.closest('.blist__item')?.dataset.bid : null;
  let view = null;
  if (S.sel.kind === 'theme') {
    view = themeInspector(S.sel.group);
  } else if (S.sel.kind === 'stores') {
    view = ui.storesInspector?.(inspectorHead) || null;
  } else if (sec(S.sel.sid) && (!S.sel.bid || blk(S.sel.sid, S.sel.bid))) {
    view = sectionInspector(S.sel.sid, S.sel.bid);
  }
  if (!view) {
    S.sel = null;
    shownKey = null;
    ui.renderDrawer();
    return;
  }
  el.replaceChildren(view);
  shownKey = selKey(S.sel);
  if (sameTarget) {
    const b = $('.insp-body', el);
    if (b) b.scrollTop = scroll;
    if (focusedField) el.querySelector(`[data-field="${CSS.escape(focusedField)}"] input, [data-field="${CSS.escape(focusedField)}"] textarea, [data-field="${CSS.escape(focusedField)}"] select`)?.focus({ preventScroll: true });
  }
  if (focusedBlock) el.querySelector(`.blist__item[data-bid="${CSS.escape(focusedBlock)}"] .blist__main`)?.focus({ preventScroll: true });
  if (S.renaming) {
    const input = $('.insp-rename', el);
    input?.focus();
    input?.select();
  }
  ui.syncDrawer?.();
}

/*
 * The panel's head: back (from a block to its section, from a theme group
 * or a starting store to Theme) or else the page's name above the title,
 * the title, its actions, and on a phone the sheet's close button.
 */
export function inspectorHead({ title, help = null, kind, kindBlock = false, type = null, actions = [], renames = null }) {
  const head = h('div', { class: 'insp-head' });
  const back = S.sel?.kind === 'section' && S.sel.bid
    ? { label: `Back to ${labelOf(S.sel.sid)}`, go: () => select(S.sel.sid) }
    : S.sel?.kind === 'theme' || S.sel?.kind === 'stores'
      ? { label: 'Back to Theme', go: () => ui.openDrawer({ type: 'theme' }) }
      : null;
  const editing = renames && S.renaming && S.renaming.sid === renames.sid && (S.renaming.bid || null) === (renames.bid || null);
  if (!back) head.append(ui.pageCrumb());
  const top = h('div', { class: 'insp-head__row' },
    back ? h('button', { type: 'button', class: 'icon-btn insp-back', 'aria-label': back.label, 'data-tip': back.label, onclick: back.go }, icon('arrow-left')) : null,
    editing ? renameInput(renames.sid, renames.bid, title) : h('h2', { class: 'insp-title', tabindex: -1 }, h('span', { text: title }), helpTip(help)),
    renames && !editing ? h('button', {
      type: 'button', class: 'icon-btn', 'aria-label': `Rename ${title}`, 'data-tip': 'Rename',
      onclick: () => startRename(renames.sid, renames.bid),
    }, icon('type')) : null,
    ...actions,
    ui.sheetClose());
  head.append(top);
  if (kind) head.append(h('p', { class: 'insp-kind' }, h('span', { class: `insp-kind__dot${kindBlock ? ' insp-kind__dot--block' : ''}`, 'aria-hidden': 'true' }), kind));
  if (type) {
    head.append(h('p', { class: `insp-type${type.shared ? ' insp-type--shared' : ''}` },
      type.type ? icon(type.icon, 'icon--sm insp-type__icon') : null,
      // A band's sections show on every page: the link mark of the bands says so.
      type.shared ? icon('link', 'icon--sm insp-type__icon insp-type__link') : null,
      type.type ? h('span', { class: 'insp-type__name', text: type.type }) : null,
      type.where ? h('span', { class: `insp-type__where${type.type || type.shared ? '' : ' insp-type__where--alone'}`, text: type.where }) : null));
  }
  return head;
}

/* The name of a section or block, edited in place of the title: Enter or leaving keeps it, Escape does not. */
function renameInput(sid, bid, current) {
  let done = false;
  const finish = (save) => {
    if (done) return;
    done = true;
    const value = input.value.trim();
    S.renaming = null;
    if (save && value && value !== current) rename(sid, bid, value);
    ui.renderInspector();
    window.requestAnimationFrame(() => $('#drawer .insp-head [aria-label^="Rename"]')?.focus({ preventScroll: true }));
  };
  const input = h('input', {
    class: 'input insp-rename', type: 'text', value: current, 'aria-label': 'Name', maxlength: 60,
    onkeydown: (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        finish(true);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        finish(false);
      }
    },
    onblur: () => finish(true),
  });
  return input;
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
  // Locked sections keep their place and type; of them only the page content is never hidden.
  const main = !bid && def.main;
  const locked = !bid && isLocked(def);
  const shared = !bid && at.group !== 'template';

  const eye = main ? null : h('button', {
    type: 'button', class: 'icon-btn', 'aria-label': hidden ? 'Show on the store' : 'Hide on the store',
    'aria-pressed': String(hidden), 'data-tip': hidden ? 'Show' : 'Hide',
    onclick: () => toggleVisible(sid, bid),
  }, icon(hidden ? 'eye-off' : 'eye'));

  const head = inspectorHead({
    title: labelOf(sid, bid) || humanize(target.type),
    help: bid ? bdef?.description || bdef?.info : def.description,
    kind: bid ? `${bdef?.label || humanize(target.type)} in ${labelOf(sid)}` : null,
    type: bid ? null : sectionType(sid, def),
    kindBlock: Boolean(bid),
    actions: [eye],
    renames: { sid, bid },
  });

  const content = h('div', { class: 'insp-body' });
  if (hidden) {
    content.append(h('p', { class: 'notice notice--warn' }, icon('eye-off'), `Hidden on the store. Shoppers do not see this ${bid ? 'block' : 'section'}.`));
  }
  if (shared) content.append(h('p', { class: 'notice notice--shared' }, icon('link'), 'Changes here show on every page.'));

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
  if (!bid) {
    const blocks = blocksField(sid);
    if (blocks) content.append(blocks);
  }

  if (!bid && !locked) {
    content.append(h('div', { class: 'insp-foot' },
      h('button', {
        type: 'button', class: 'btn btn--secondary btn--sm', disabled: !canDuplicate(sid) || null,
        title: duplicateHint(sid),
        onclick: () => duplicateSection(sid),
      }, icon('copy', 'icon--sm'), 'Duplicate'),
      h('button', { type: 'button', class: 'btn btn--danger-text btn--sm', onclick: () => askRemoveSection(sid) }, icon('trash', 'icon--sm'), 'Remove section')));
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

/* ------------------------------------------------------------------------
   A section's blocks: each opens its own settings; the grip moves it (drag,
   a press then drag by touch, or the up and down arrow keys); Add puts a
   new one at the end.
   ------------------------------------------------------------------------ */

function blocksField(sid) {
  const s = { id: sid, ...sec(sid) };
  const def = sectionDef(s.type);
  const blocks = blockList(sid);
  if (!def || (!def.blocks?.types?.length && !blocks.length)) return null;
  const list = h('ul', { class: 'blist', role: 'list', dataset: { sid } });
  blocks.forEach((b, i) => list.append(blockItem(s, b, i, blocks.length)));
  const field = h('div', { class: 'field', role: 'group', 'aria-labelledby': 'blist-label' },
    h('div', { class: 'field__label', id: 'blist-label' }, h('span', { text: 'Blocks' }),
      def.blocks?.max ? h('span', { class: 'field__value', text: `${blocks.filter((b) => blockDef(s.type, b.type)).length} of ${def.blocks.max}` }) : null),
    blocks.length ? list : h('p', { class: 'field__help', text: 'No blocks yet.' }));
  const { types, full, max } = addableBlocks(sid);
  if (types.length) {
    const one = types.length === 1;
    const add = h('button', {
      type: 'button', class: 'btn btn--secondary btn--sm blist__add', 'aria-haspopup': one ? null : 'true',
      onclick: (e) => (one ? addBlock(sid, types[0].type) : openMenu(e.currentTarget, blockMenu(s, types))),
    }, icon('plus', 'icon--sm'), one ? `Add ${String(types[0].label || humanize(types[0].type)).toLowerCase()}` : 'Add block');
    // The photo-first way to add, for a section whose blocks are one photo each.
    field.append(photoBlockOf(sid)
      ? h('div', { class: 'blist__adds' }, add, h('button', {
        type: 'button', class: 'btn btn--secondary btn--sm blist__add-photos', 'aria-haspopup': 'dialog',
        onclick: (e) => addPhotos(sid, e.currentTarget),
      }, icon('image', 'icon--sm'), 'Add photos'))
      : add);
  } else if (full) {
    field.append(h('p', { class: 'field__help', text: `Holds up to ${max}. Remove one to add another.` }));
  }
  return field;
}

/* The theme's own blocks first, then each extension's under its name. */
function blockMenu(s, types) {
  const item = (t) => ({ label: t.label || humanize(t.type), iconName: blockIcon(s.type, t.type), hint: t.description || null, run: () => addBlock(s.id, t.type) });
  const items = [{ heading: 'Add to this section' }, ...types.filter((t) => !t.source).map(item)];
  const groups = new Map();
  types.filter((t) => t.source).forEach((t) => {
    const name = t.source_label || t.source;
    if (!groups.has(name)) groups.set(name, []);
    groups.get(name).push(t);
  });
  groups.forEach((list, name) => items.push({ heading: name }, ...list.map(item)));
  return items;
}

function blockItem(s, b, index, count) {
  const def = blockDef(s.type, b.type);
  const name = labelOf(s.id, b.id) || (def ? humanize(b.type) : absentLabel(b, ctx.boot?.extensions) || 'No longer available');
  const tag = def ? needTag(s.id, b.id) : null;
  const li = h('li', { class: `blist__item${b.disabled ? ' is-hidden' : ''}${def ? '' : ' is-missing'}`, dataset: { bid: b.id } });
  if (count > 1) {
    li.append(h('button', {
      type: 'button', class: 'blist__grip', 'aria-label': `Move ${name}. Use the up and down arrow keys.`,
      onkeydown: (e) => {
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
        e.preventDefault();
        if (moveBlockBy(s.id, b.id, e.key === 'ArrowUp' ? -1 : 1)) {
          window.requestAnimationFrame(() => $(`#drawer .blist__item[data-bid="${CSS.escape(b.id)}"] .blist__grip`)?.focus());
        }
      },
      onpointerdown: (e) => {
        // A finger presses a moment, then drags; a mouse or pen drags the item natively.
        const touch = startListDrag(e, {
          items: () => Array.from(li.parentElement?.children || []),
          from: index,
          onDrop: (to) => moveBlockTo(s.id, b.id, to),
        });
        if (!touch) li.draggable = true;
      },
    }, icon('grip', 'icon--sm')));
  }
  li.append(h('button', {
    type: 'button', class: 'blist__main', onclick: () => select(s.id, b.id),
  },
  icon(def ? blockIcon(s.type, b.type) : 'alert', 'icon--sm blist__icon'),
  h('span', { class: 'blist__text' },
    h('span', { class: 'blist__name', text: name }),
    b.disabled ? h('span', { class: 'blist__sub', text: 'Hidden' }) : null,
    tag ? h('span', { class: `blist__sub blist__sub--need${tag.quiet ? ' is-quiet' : ''}`, text: tag.text }) : null),
  icon('chevron-right', 'icon--sm')));
  if (count > 1) dragBlock(li, s.id, b.id, index);
  return li;
}

/* Dragging a block by its grip, within its section's list. */
function dragBlock(li, sid, bid, index) {
  li.addEventListener('dragstart', (e) => {
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', bid); } catch { /* some browsers refuse custom data */ }
    li.classList.add('is-dragging');
  });
  li.addEventListener('dragend', () => {
    li.draggable = false;
    li.classList.remove('is-dragging');
  });
  li.addEventListener('pointerup', () => { li.draggable = false; });
  li.addEventListener('dragover', (e) => {
    if (!li.parentElement?.querySelector('.is-dragging')) return;
    e.preventDefault();
  });
  li.addEventListener('drop', (e) => {
    const from = li.parentElement?.querySelector('.is-dragging')?.dataset.bid;
    if (!from || from === bid) return;
    e.preventDefault();
    moveBlockTo(sid, from, index);
  });
}

/*
 * The second line of a section's header: its type, when the section has a
 * name of its own, and where it sits; a section of the header or footer
 * band carries the bands' link mark. Changing the type is done on the
 * board, so there is no Change here.
 */
function sectionType(sid, def) {
  const at = locate(sid);
  const rules = at ? rulesFor(at.handle) : null;
  const line = typeLine(labelOf(sid), def.label, placeWords(at?.doc?.content, sid, layoutPayload(), at?.group, rules));
  return line ? { ...line, icon: sectionIcon(at?.data?.type), shared: Boolean(at) && at.group !== 'template' } : null;
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

/*
 * A section the catalog lacks. One of an extension that is switched off or
 * not installed is kept with its settings and comes back with the
 * extension, so nothing here urges removing it. A type the theme dropped
 * is gone for good.
 */
function missingSection(sid) {
  const s = sec(sid);
  const name = extensionOf(s?.type);
  if (name) {
    const ext = ctx.boot?.extensions?.[name] || { title: name, state: 'missing' };
    const off = ext.state === 'off';
    return h('div', { class: 'inspector' },
      inspectorHead({ title: absentName(s), kind: `${ext.title || name} is ${off ? 'switched off' : 'not installed'}` }),
      h('div', { class: 'insp-body' },
        h('p', { class: 'insp-desc', text: `Shoppers do not see it for now. It is kept with its settings and comes back when ${ext.title || name} is ${off ? 'switched on' : 'installed'} again.` }),
        h('div', { class: 'insp-foot' },
          h('button', { type: 'button', class: 'btn btn--danger-text btn--sm', onclick: () => askRemoveSection(sid) }, icon('trash', 'icon--sm'), 'Remove section'))));
  }
  return h('div', { class: 'inspector' },
    inspectorHead({ title: 'Section no longer available', kind: 'Removed from the theme' }),
    h('div', { class: 'insp-body' },
      h('p', { class: 'insp-desc', text: 'This section type is not part of the theme any more, so shoppers do not see it. Remove it to tidy the page.' }),
      h('div', { class: 'insp-foot' },
        h('button', { type: 'button', class: 'btn btn--danger-text btn--sm', onclick: () => askRemoveSection(sid) }, icon('trash', 'icon--sm'), 'Remove section'))));
}

function themeInspector(groupKey) {
  const group = themeGroups().find((g) => g.group === groupKey);
  if (!group || !settingsDoc()) return null;
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

export function initInspector() {
  ui.renderInspector = renderInspector;
  ui.refreshVisibility = refreshVisibility;
  ui.refreshFieldErrors = refreshFieldErrors;
}
