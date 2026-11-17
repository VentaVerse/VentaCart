/*
 * The sidebar outline: the page's own sections, or (Header and footer in the
 * page picker) the header and footer groups, each section with its blocks. Rows are dragged to reorder (within their own group, or within
 * their section for blocks); the grip also moves them with the arrow keys,
 * and a row's main button with Alt and the arrow keys.
 */

import { $, $$, h, icon, dotsIcon } from './dom.js';
import { S, ctx, ui, groupsInScope, groupList, blockList, labelOf, sec, isBusy, isFixed, isShared, openOnly } from './state.js';
import { sectionDef, blockDef, sectionIcon, blockIcon, absentNote } from './catalog.js';
import { select } from './selection.js';
import { openMenu } from './popover.js';
import { highlight } from './preview.js';
import {
  toggleVisible, startRename, rename, moveBy, moveTo, duplicateSection, removeSection, canDuplicate,
  addableBlocks, addBlock, removeBlock, canRemoveBlock, moveBlockBy, moveBlockTo, duplicateBlock, canDuplicateBlock,
} from './actions.js';
import { openAdd } from './add-section.js';
import { humanize } from './util.js';
import { needTag, refreshNeeds } from './needs.js';

/* A row's name, with what it waits for under it ("Pick a category"). */
function rowText(label, tag) {
  return h('span', { class: 'o-row__text' },
    h('span', { class: 'o-row__label', text: label }),
    tag ? h('span', { class: `o-row__need${tag.quiet ? ' is-quiet' : ''}`, text: tag.text }) : null);
}

const MOD = /Mac|iPhone|iPad/.test(navigator.platform || '') ? 'Cmd' : 'Ctrl';

/**
 * Keys on a focused outline row: F2 renames, Delete removes (Undo brings it
 * back), Ctrl or Cmd with D copies. Alt with the arrow keys moves the row.
 */
function rowKeys(e, { sid, bid = null, removable, copyable }) {
  const mod = e.ctrlKey || e.metaKey;
  if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
    e.preventDefault();
    if (bid) moveBlockBy(sid, bid, e.key === 'ArrowUp' ? -1 : 1);
    else moveBy(sid, e.key === 'ArrowUp' ? -1 : 1);
  } else if (e.key === 'F2') {
    e.preventDefault();
    startRename(sid, bid);
  } else if ((e.key === 'Delete' || e.key === 'Backspace') && !mod && removable) {
    e.preventDefault();
    // Focus moves to the row below (or above), not to the page.
    const item = e.currentTarget.closest('.o-item');
    const next = [item?.nextElementSibling, item?.previousElementSibling]
      .map((li) => li?.querySelector(':scope > .o-row')?.dataset.row).find(Boolean);
    if (bid) removeBlock(sid, bid);
    else removeSection(sid);
    window.setTimeout(() => {
      const target = next && nav().querySelector(`[data-row="${CSS.escape(next)}"] .o-row__main`);
      (target || nav().querySelector('.o-add'))?.focus({ preventScroll: true });
    }, 0);
  } else if (mod && !e.shiftKey && (e.key || '').toLowerCase() === 'd' && copyable) {
    e.preventDefault();
    e.stopPropagation();
    if (bid) duplicateBlock(sid, bid);
    else duplicateSection(sid);
  }
}

const nav = () => $('#nav-sections');

/** Section ids with values the server refused, from each document's 422 answer. */
function sectionsWithErrors() {
  const out = new Set();
  Object.values(S.docs).forEach((d) => {
    Object.keys(d.fieldErrors || {}).forEach((path) => {
      const parts = path.replace(/^content\./, '').split('.');
      if (parts[0] === 'sections' && parts[1]) out.add(parts[1]);
    });
  });
  return out;
}

export function renderOutline() {
  if (!S.loaded) return;
  refreshNeeds();
  if (isFixed()) {
    // Checkout, sign in and account: shown as they are, styled by theme settings only.
    nav().replaceChildren(ui.fixedPanel());
    return;
  }
  const errors = sectionsWithErrors();
  const wrap = h('div', { class: 'outline' });
  const layout = isShared() ? null : ui.layoutRow?.();
  if (layout) wrap.append(layout);
  groupsInScope().forEach((group) => {
    const items = groupList(group.key);
    const list = h('ul', { class: 'o-list', role: 'list', dataset: { group: group.key } });
    items.forEach((s, i) => list.append(sectionItem(s, i, items.length, errors, group.key)));
    const title = group.key === 'template' ? (S.page?.label ? `${S.page.label} sections` : group.label) : group.label;
    const g = h('div', { class: 'o-group' }, h('h2', { class: 'o-group__title' }, h('span', { text: title })), list);
    if (!items.length) {
      g.append(h('p', { class: 'o-empty', text: group.key === 'template' ? 'This page has no sections yet. Add the first one.' : `Nothing in the ${group.label.toLowerCase()} yet.` }));
    }
    if (group.key === 'template') {
      g.append(h('button', { type: 'button', class: 'o-add', onclick: () => openAdd(items.length) }, icon('plus'), 'Add section'));
    }
    wrap.append(g);
  });
  const el = nav();
  const scroll = el.scrollTop;
  const focusedRow = document.activeElement?.closest?.('[data-row]')?.dataset.row;
  const focusedClass = document.activeElement?.classList?.contains('o-grip') ? '.o-grip' : '.o-row__main';
  const before = outlineState(el);
  el.replaceChildren(wrap);
  el.scrollTop = scroll;
  if (S.renaming) {
    const input = el.querySelector('.o-rename');
    input?.focus();
    input?.select();
  } else if (focusedRow) {
    el.querySelector(`[data-row="${CSS.escape(focusedRow)}"] ${focusedClass}`)?.focus({ preventScroll: true });
  }
  playOutlineMotion(el, before);
}

/* ------------------------------------------------------------------------
   Motion. The outline is redrawn whole on every change, so what changed is
   found by comparing the old rows with the new ones: a section's blocks
   opening or closing, and a row being hidden or shown. The motion only
   explains the change; the new state is already in place underneath it.
   ------------------------------------------------------------------------ */

const EASE_OUT = 'cubic-bezier(0.16, 1, 0.3, 1)';
const EASE_EXIT = 'cubic-bezier(0.4, 0, 0.2, 1)';
const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/** What the outline shows now: open block lists (with their heights) and hidden rows. */
function outlineState(el) {
  const open = new Map();
  el.querySelectorAll('.o-blocks[data-sid]').forEach((ul) => open.set(ul.dataset.sid, { ul, height: ul.offsetHeight }));
  const rows = new Map();
  el.querySelectorAll('.o-row[data-row]').forEach((row) => rows.set(row.dataset.row, row.classList.contains('is-hidden')));
  return rows.size ? { open, rows } : null;
}

function playOutlineMotion(el, before) {
  if (!before || typeof Element.prototype.animate !== 'function') return;
  const reduce = reducedMotion();

  // Blocks opening: the list grows from its row and the rows below glide down.
  el.querySelectorAll('.o-blocks[data-sid]').forEach((ul) => {
    const sid = ul.dataset.sid;
    if (before.open.has(sid) || !before.rows.has(sid)) return;
    turnChevron(el, sid, true, reduce);
    const height = ul.offsetHeight;
    if (!height) return;
    if (reduce) {
      ul.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 120 });
      return;
    }
    ul.style.overflow = 'hidden';
    const run = ul.animate(
      [{ height: '0px', opacity: 0 }, { height: `${height}px`, opacity: 1 }],
      { duration: Math.min(320, 180 + height / 5), easing: EASE_OUT },
    );
    const done = () => { ul.style.overflow = ''; };
    run.onfinish = done;
    run.oncancel = done;
  });

  // Blocks closing: the old list stays a moment, folds up and is removed.
  before.open.forEach(({ ul, height }, sid) => {
    if (el.querySelector(`.o-blocks[data-sid="${CSS.escape(sid)}"]`)) return;
    const li = el.querySelector(`.o-item[data-sid="${CSS.escape(sid)}"]`);
    if (!li) return;
    turnChevron(el, sid, false, reduce);
    if (reduce || !height) return;
    const ghost = leavingCopy(ul);
    li.append(ghost);
    const run = ghost.animate(
      [{ height: `${height}px`, opacity: 1 }, { height: '0px', opacity: 0 }],
      { duration: Math.min(220, 120 + height / 8), easing: EASE_EXIT },
    );
    const done = () => ghost.remove();
    run.onfinish = done;
    run.oncancel = done;
  });

  // Hidden or shown: the name fades to gray while the line is drawn through it, or back.
  el.querySelectorAll('.o-row[data-row]').forEach((row) => {
    const was = before.rows.get(row.dataset.row);
    const now = row.classList.contains('is-hidden');
    if (was === undefined || was === now) return;
    fadeHidden(row, was, now, reduce);
  });
}

/** The closing list, kept only to be seen: no focus, no clicks, no ids to find. */
function leavingCopy(ul) {
  ul.classList.add('is-leaving');
  ul.inert = true;
  ul.setAttribute('aria-hidden', 'true');
  ul.removeAttribute('data-sid');
  ul.querySelectorAll('[data-row], [data-bid], [data-sid]').forEach((n) => {
    n.removeAttribute('data-row');
    n.removeAttribute('data-bid');
    n.removeAttribute('data-sid');
  });
  return ul;
}

/** The arrow is drawn already turned, so it is turned from where it was. */
function turnChevron(el, sid, opening, reduce) {
  if (reduce) return;
  const chevron = el.querySelector(`.o-item[data-sid="${CSS.escape(sid)}"] > .o-row .o-row__toggle .icon`);
  chevron?.animate(
    [{ rotate: opening ? '0deg' : '90deg' }, { rotate: opening ? '90deg' : '0deg' }],
    { duration: opening ? 220 : 160, easing: opening ? EASE_OUT : EASE_EXIT },
  );
}

function fadeHidden(row, was, now, reduce) {
  const label = row.querySelector('.o-row__label');
  const iconEl = row.querySelector('.o-row__icon');
  if (!label) return;
  // Read both looks from the stylesheet, so the motion ends exactly where it rests.
  row.classList.toggle('is-hidden', was);
  const from = { color: getComputedStyle(label).color, icon: iconEl && getComputedStyle(iconEl).color, line: getComputedStyle(label).textDecorationColor };
  row.classList.toggle('is-hidden', now);
  const to = { color: getComputedStyle(label).color, icon: iconEl && getComputedStyle(iconEl).color, line: getComputedStyle(label).textDecorationColor };
  const timing = { duration: now ? 240 : 180, easing: EASE_OUT };
  label.animate([
    { color: from.color, textDecorationLine: 'line-through', textDecorationColor: was ? from.line : 'transparent' },
    { color: to.color, textDecorationLine: 'line-through', textDecorationColor: now ? to.line : 'transparent' },
  ], timing);
  if (iconEl) iconEl.animate([{ color: from.icon }, { color: to.icon }], timing);
  const eye = row.querySelector('.o-row__actions [aria-pressed] .icon');
  eye?.animate(
    reduce ? [{ opacity: 0 }, { opacity: 1 }] : [{ opacity: 0, scale: 0.6 }, { opacity: 1, scale: 1 }],
    { duration: 160, easing: EASE_OUT },
  );
}

function moreButton(label, onclick) {
  return h('button', {
    type: 'button', class: 'icon-btn icon-btn--sm o-more', 'aria-label': `More actions for ${label}`,
    'aria-haspopup': 'true', onclick,
  }, dotsIcon());
}

function eyeButton(hidden, label, onclick) {
  return h('button', {
    type: 'button', class: 'icon-btn icon-btn--sm',
    'aria-label': hidden ? `Show ${label}` : `Hide ${label}`, 'aria-pressed': String(hidden),
    'data-tip': hidden ? 'Show' : 'Hide', onclick,
  }, icon(hidden ? 'eye-off' : 'eye'));
}

function sectionItem(s, index, count, errors, group) {
  const def = sectionDef(s.type);
  const label = labelOf(s.id);
  const blocks = blockList(s.id);
  const canHaveBlocks = Boolean(def?.blocks?.types?.length);
  const isOpen = S.open.has(s.id);
  const selected = S.sel?.kind === 'section' && S.sel.sid === s.id && !S.sel.bid;
  const hidden = Boolean(s.disabled);
  const main = Boolean(def?.main);
  const movable = count > 1;
  const busy = isBusy(s.id);

  const row = h('div', {
    class: `o-row${selected ? ' is-selected' : ''}${hidden ? ' is-hidden' : ''}${busy ? ' is-busy' : ''}${errors.has(s.id) ? ' has-error' : ''}${def ? '' : ' is-missing'}`,
    dataset: { row: s.id },
    onmouseenter: () => highlight(s.id),
    onmouseleave: () => highlight(null),
  });

  if (movable) {
    row.append(h('button', {
      type: 'button', class: 'o-grip', 'aria-label': `Move ${label}. Use the up and down arrow keys.`,
      onkeydown: (e) => {
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
          e.preventDefault();
          moveBy(s.id, e.key === 'ArrowUp' ? -1 : 1);
        }
      },
      onpointerdown: () => { li.draggable = true; },
    }, icon('grip')));
  } else {
    row.append(h('span', { class: 'o-grip o-grip--none', 'aria-hidden': 'true' }));
  }

  if (canHaveBlocks || blocks.length) {
    row.append(h('button', {
      type: 'button', class: 'o-row__toggle', 'aria-expanded': String(isOpen),
      'aria-label': `${isOpen ? 'Hide' : 'Show'} blocks in ${label}`,
      onclick: () => {
        if (S.open.has(s.id)) S.open.delete(s.id);
        else openOnly(s.id);
        renderOutline();
      },
    }, icon('chevron-right')));
  } else {
    row.append(h('span', { class: 'o-row__spacer', 'aria-hidden': 'true' }));
  }

  if (S.renaming && S.renaming.sid === s.id && !S.renaming.bid) {
    row.append(renameInput(s.id, null, label));
  } else {
    row.append(h('button', {
      type: 'button', class: 'o-row__main', 'aria-current': selected ? 'true' : null,
      title: def ? `${label} (${def.label})` : absentNote(s.type, ctx.boot?.extensions) || 'This section is no longer available',
      'aria-keyshortcuts': group === 'template' && !main ? `F2 Delete ${MOD}+D Alt+ArrowUp Alt+ArrowDown` : 'F2 Alt+ArrowUp Alt+ArrowDown',
      onclick: () => select(s.id),
      onkeydown: (e) => rowKeys(e, { sid: s.id, removable: group === 'template' && !main, copyable: group === 'template' && canDuplicate(s.id) }),
    }, icon(def ? sectionIcon(s.type) : 'alert', 'o-row__icon'),
    def ? rowText(label, needTag(s.id)) : h('span', { class: 'o-row__label', text: absentNote(s.type, ctx.boot?.extensions) || 'No longer available' }),
    busy ? h('span', { class: 'o-row__busy', text: 'Updating' }) : null,
    errors.has(s.id) ? h('span', { class: 'o-row__err', 'aria-label': 'Has a value that was not accepted' }, icon('alert')) : null));
  }

  row.append(h('span', { class: 'o-row__actions' },
    main ? null : eyeButton(hidden, label, () => toggleVisible(s.id)),
    moreButton(label, (e) => sectionMenu(e.currentTarget, s.id, index, count, group))));

  const li = h('li', { class: `o-item${isOpen ? ' is-open' : ''}`, dataset: { sid: s.id, group } }, row);
  if (movable) wireDrag(li, { kind: 'section', sid: s.id, group });

  if (isOpen && (blocks.length || canHaveBlocks)) {
    const ul = h('ul', { class: 'o-blocks', role: 'list', dataset: { sid: s.id } });
    blocks.forEach((b, i) => ul.append(blockItem(s, b, i, blocks.length)));
    const { types, full, max } = addableBlocks(s.id);
    if (types.length) {
      ul.append(h('li', { class: 'o-item' }, h('button', {
        type: 'button', class: 'o-add o-add--block', 'aria-haspopup': types.length > 1 ? 'true' : null,
        onclick: (e) => (types.length === 1 ? addBlock(s.id, types[0].type) : openMenu(e.currentTarget, blockMenu(s, types))),
      }, icon('plus'), types.length === 1 ? `Add ${String(types[0].label || humanize(types[0].type)).toLowerCase()}` : 'Add block')));
    } else if (full) {
      ul.append(h('li', { class: 'o-note', text: `Holds up to ${max}. Remove one to add another.` }));
    }
    li.append(ul);
  }
  return li;
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

/* A block of an extension that is away: kept, shown with its note, and only movable or removable. */
function absentBlockItem(s, b, index, count) {
  const label = absentNote(b.type, ctx.boot?.extensions) || 'No longer available';
  const li = h('li', { class: 'o-item', dataset: { bid: b.id } });
  const row = h('div', { class: 'o-row o-row--block is-missing', dataset: { row: b.id } },
    h('span', { class: 'o-row__main', title: label }, icon('alert', 'o-row__icon'), h('span', { class: 'o-row__label', text: label })),
    h('span', { class: 'o-row__actions' }, moreButton(labelOf(s.id, b.id) || label, (e) => openMenu(e.currentTarget, [
      { label: 'Move up', iconName: 'chevron-up', disabled: index === 0, run: () => moveBlockBy(s.id, b.id, -1) },
      { label: 'Move down', iconName: 'chevron-down', disabled: index >= count - 1, run: () => moveBlockBy(s.id, b.id, 1) },
      { sep: true },
      { label: 'Remove block', iconName: 'trash', danger: true, run: () => removeBlock(s.id, b.id) },
    ]))));
  li.append(row);
  if (count > 1) wireDrag(li, { kind: 'block', sid: s.id, bid: b.id });
  return li;
}

function blockItem(s, b, index, count) {
  if (sectionDef(s.type) && !blockDef(s.type, b.type)) return absentBlockItem(s, b, index, count);
  const selected = S.sel?.kind === 'section' && S.sel.sid === s.id && S.sel.bid === b.id;
  const hidden = Boolean(b.disabled);
  const label = labelOf(s.id, b.id);
  const def = blockDef(s.type, b.type);
  const row = h('div', {
    class: `o-row o-row--block${selected ? ' is-selected' : ''}${hidden ? ' is-hidden' : ''}`,
    dataset: { row: b.id },
    onmouseenter: () => highlight(s.id, b.id),
    onmouseleave: () => highlight(null),
  });
  const li = h('li', { class: 'o-item', dataset: { bid: b.id } });

  if (count > 1) {
    row.append(h('button', {
      type: 'button', class: 'o-grip o-grip--block', 'aria-label': `Move ${label}. Use the up and down arrow keys.`,
      onkeydown: (e) => {
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
          e.preventDefault();
          moveBlockBy(s.id, b.id, e.key === 'ArrowUp' ? -1 : 1);
        }
      },
      onpointerdown: () => { li.draggable = true; },
    }, icon('grip')));
  }

  if (S.renaming && S.renaming.bid === b.id) {
    row.append(renameInput(s.id, b.id, label));
  } else {
    row.append(h('button', {
      type: 'button', class: 'o-row__main', 'aria-current': selected ? 'true' : null,
      'aria-keyshortcuts': `F2 Delete ${MOD}+D Alt+ArrowUp Alt+ArrowDown`,
      onclick: () => select(s.id, b.id),
      onkeydown: (e) => rowKeys(e, { sid: s.id, bid: b.id, removable: canRemoveBlock(s.id), copyable: canDuplicateBlock(s.id, b.id) }),
    }, icon(def ? blockIcon(s.type, b.type) : 'alert', 'o-row__icon'), rowText(label || humanize(b.type), needTag(s.id, b.id))));
  }
  row.append(h('span', { class: 'o-row__actions' },
    eyeButton(hidden, label, () => toggleVisible(s.id, b.id)),
    moreButton(label, (e) => openMenu(e.currentTarget, [
      { label: 'Rename', iconName: 'type', tag: 'F2', run: () => startRename(s.id, b.id) },
      { label: hidden ? 'Show block' : 'Hide block', iconName: hidden ? 'eye' : 'eye-off', run: () => toggleVisible(s.id, b.id) },
      {
        label: 'Duplicate', iconName: 'copy', tag: canDuplicateBlock(s.id, b.id) ? `${MOD}+D` : null, disabled: !canDuplicateBlock(s.id, b.id),
        hint: canDuplicateBlock(s.id, b.id) ? null : 'This section holds as many as it can',
        run: () => duplicateBlock(s.id, b.id),
      },
      { sep: true },
      { label: 'Move up', iconName: 'chevron-up', disabled: index === 0, run: () => moveBlockBy(s.id, b.id, -1) },
      { label: 'Move down', iconName: 'chevron-down', disabled: index >= count - 1, run: () => moveBlockBy(s.id, b.id, 1) },
      { sep: true },
      { label: 'Remove block', iconName: 'trash', danger: true, tag: canRemoveBlock(s.id) ? 'Del' : null, disabled: !canRemoveBlock(s.id), hint: canRemoveBlock(s.id) ? null : 'This section needs at least this many', run: () => removeBlock(s.id, b.id) },
    ]))));
  li.append(row);
  if (count > 1) wireDrag(li, { kind: 'block', sid: s.id, bid: b.id });
  return li;
}

function renameInput(sid, bid, current) {
  let done = false;
  const finish = (save) => {
    if (done) return;
    done = true;
    const value = input.value.trim();
    S.renaming = null;
    if (save && value && value !== current) rename(sid, bid, value);
    else renderOutline();
  };
  const input = h('input', {
    class: 'o-rename', type: 'text', value: current, 'aria-label': 'Name', maxlength: 60,
    onkeydown: (e) => {
      if (e.key === 'Enter') finish(true);
      if (e.key === 'Escape') {
        e.stopPropagation();
        finish(false);
      }
    },
    onblur: () => finish(true),
  });
  return input;
}

function sectionMenu(anchor, sid, index, count, group) {
  const def = sectionDef(sec(sid)?.type);
  const hidden = Boolean(sec(sid)?.disabled);
  const isTemplate = group === 'template';
  const main = Boolean(def?.main);
  const items = [];
  if (def) items.push({ label: 'Rename', iconName: 'type', tag: 'F2', run: () => startRename(sid) });
  if (def && !main) items.push({ label: hidden ? 'Show section' : 'Hide section', iconName: hidden ? 'eye' : 'eye-off', run: () => toggleVisible(sid) });
  items.push(
    { sep: true },
    { label: 'Move up', iconName: 'chevron-up', disabled: index === 0, run: () => moveBy(sid, -1) },
    { label: 'Move down', iconName: 'chevron-down', disabled: index >= count - 1, run: () => moveBy(sid, 1) },
  );
  if (isTemplate) {
    items.push({ sep: true });
    if (def) {
      items.push({
        label: 'Duplicate', iconName: 'copy', disabled: !canDuplicate(sid), tag: canDuplicate(sid) ? `${MOD}+D` : null,
        hint: canDuplicate(sid) ? null : main ? 'The page\'s main section cannot be copied' : 'This page has as many as it can hold',
        run: () => duplicateSection(sid),
      });
    }
    items.push({ label: 'Add section below', iconName: 'plus', run: () => openAdd(index + 1) });
    if (!main) items.push({ sep: true }, { label: 'Remove section', iconName: 'trash', danger: true, tag: 'Del', run: () => removeSection(sid) });
  }
  openMenu(anchor, items);
}

/* ------------------------------------------------------------------------
   Drag to reorder: sections within their group, blocks within their section
   ------------------------------------------------------------------------ */

let drag = null;

function wireDrag(li, info) {
  li.addEventListener('dragstart', (e) => {
    e.stopPropagation();
    drag = { ...info };
    li.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', info.bid || info.sid); } catch { /* some browsers refuse custom data */ }
  });
  li.addEventListener('dragend', () => {
    li.draggable = false;
    li.classList.remove('is-dragging');
    drag = null;
    $$('.o-drop', nav()).forEach((d) => d.remove());
  });
  li.addEventListener('pointerup', () => { li.draggable = false; });
}

function dropList() {
  if (!drag) return null;
  return drag.kind === 'block'
    ? nav().querySelector(`.o-blocks[data-sid="${CSS.escape(drag.sid)}"]`)
    : nav().querySelector(`.o-list[data-group="${drag.group}"]`);
}

export function initOutline() {
  const el = nav();
  el.addEventListener('dragover', (e) => {
    const list = dropList();
    if (!list || !list.contains(e.target)) return;
    e.preventDefault();
    const items = $$(drag.kind === 'block' ? ':scope > .o-item[data-bid]' : ':scope > .o-item', list);
    let index = items.length;
    for (let i = 0; i < items.length; i += 1) {
      const r = items[i].firstElementChild.getBoundingClientRect();
      if (e.clientY < r.top + r.height / 2) {
        index = i;
        break;
      }
    }
    drag.index = index;
    $$('.o-drop', el).forEach((d) => d.remove());
    const ref = items[index] || items[items.length - 1];
    if (!ref) return;
    const marker = h('div', { class: 'o-drop' });
    const listRect = list.getBoundingClientRect();
    const r = ref.getBoundingClientRect();
    marker.style.top = `${(items[index] ? r.top : r.bottom) - listRect.top - 1}px`;
    list.append(marker);
  });

  el.addEventListener('drop', (e) => {
    if (!drag || drag.index === undefined) return;
    e.preventDefault();
    const d = drag;
    drag = null;
    $$('.o-drop', el).forEach((m) => m.remove());
    if (d.kind === 'block') {
      const order = sec(d.sid)?.block_order || [];
      const from = order.indexOf(d.bid);
      let to = d.index;
      if (to > from) to -= 1;
      if (from >= 0 && to !== from) moveBlockTo(d.sid, d.bid, to);
    } else {
      const from = groupList(d.group).findIndex((x) => x.id === d.sid);
      let to = d.index;
      if (to > from) to -= 1;
      if (from >= 0 && to !== from) moveTo(d.sid, to);
    }
  });

  ui.renderOutline = renderOutline;
}
