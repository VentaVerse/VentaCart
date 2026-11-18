/*
 * Pages and special layouts (the page picker is page-picker.js): the page's own
 * settings in the panel when nothing is selected (which layout it uses,
 * what shares it, making a layout of its own, and a special layout's name,
 * what uses it and delete), making a new layout, and the pages with a
 * fixed layout that are only styled by theme settings.
 *
 * A special layout is a custom template of one page type (handle
 * `product.gift-guide`). Its sections are edited like any page's; its name
 * and the products (or categories, brands, pages, posts) that use it are
 * saved at once, not as part of the draft. Each of those uses one layout:
 * choosing it for one layout takes it from another, and the editor says so.
 */

import { $, h, icon, toast, announce, helpTip } from './dom.js';
import {
  S, ctx, ui, doc, pageDef, templateOf, layoutsOf, currentLayout, isFixed, noteDraft, addDocument,
} from './state.js';
import { themeGroups } from './catalog.js';
import { normalizeTemplate } from './boot.js';
import { flush, setFatal } from './drafts.js';
import { openPopover, closePopover, confirmBox } from './popover.js';
import { openThemeGroup } from './selection.js';
import { openList, row as pickRow, describe, fetchNames, remember, isGone } from './pickers/entity.js';
import { plural } from './util.js';

/** "Its product goes" or "Its 3 products go". */
const theirSubjects = (n, words, verbOne, verbMany) => (n === 1 ? `Its ${words.one} ${verbOne}` : `Its ${n} ${words.many} ${verbMany}`);

/* What a layout is used for, in words, and the picker that lists them. */
const SUBJECTS = {
  product: { one: 'product', many: 'products', kind: 'products' },
  category: { one: 'category', many: 'categories', kind: 'categories' },
  brand: { one: 'brand', many: 'brands', kind: 'brands' },
  page: { one: 'page', many: 'pages', kind: 'pages' },
  blog_post: { one: 'blog post', many: 'blog posts', kind: 'blog-posts' },
};

export const subjectWords = (type) => SUBJECTS[type] || null;

/* ------------------------------------------------------------------------
   The list of layouts, kept in step with the server
   ------------------------------------------------------------------------ */

/** Takes the layouts list and draft versions every layout answer carries. */
export function applyLayouts(answer) {
  if (Array.isArray(answer?.templates)) S.templates = answer.templates.map(normalizeTemplate).filter(Boolean);
  if (answer?.drafts) {
    // Loaded documents know their own versions (state.draftVersions); the
    // answer is the truth for every other page.
    S.draftVersions = Object.fromEntries(Object.entries(answer.drafts).map(([handle, v]) => [handle, Number(v)]));
  }
}

export async function refreshLayouts() {
  try {
    applyLayouts(await ctx.api.templates());
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') setFatal(error);
  }
  redraw();
}

/** Layouts the server deleted (thrown away with a draft): forget them, and leave one that is open. */
export function forgetLayouts(handles) {
  if (!handles?.length) return;
  handles.forEach((handle) => {
    const d = doc(handle);
    if (d) window.clearTimeout(d.retryTimer);
    delete S.docs[handle];
    noteDraft(handle, null);
  });
  S.templates = S.templates.filter((t) => !handles.includes(t.handle));
  if (S.page?.layout && handles.includes(S.page.handle)) {
    // The draft is being thrown away, so there is nothing to save first.
    S.page = { ...pageDef(S.page.type) };
    if (S.sel?.kind !== 'theme') S.sel = null;
    ui.updatePageButton?.();
  }
}

function redraw() {
  ui.updatePageButton?.();
  ui.renderBoard();
  if (!S.sel) ui.renderInspector();
  ui.updateToolbar();
}

/** How many records use a page type's default layout: all of them but those on a special layout. */
export function defaultUsers(page) {
  const special = layoutsOf(page.type).reduce((n, l) => n + l.count, 0);
  return Math.max(0, (page.subjectCount || 0) - special);
}

/* ------------------------------------------------------------------------
   The page's own settings: the panel with nothing selected
   ------------------------------------------------------------------------ */

/**
 * What the page's own settings say, from plain facts; no DOM, no state.
 * Answers `{ uses, rows, actions }`: the line beside the page's name in
 * its heading (which layout it uses), the facts the panel lists as [term,
 * value] pairs, and the actions offered ('only' makes a layout for the
 * record open, 'special' a special layout). Which layout a record uses is
 * said in the heading only, so the panel does not repeat it.
 *
 *   fixed      the page keeps a fixed layout
 *   label      the page type's name ("Category")
 *   words      { one, many } for the records it shows, or null
 *   subject    { name } of the record shown, or null
 *   record     the page was opened on that record, not on its layout
 *   layout     { label, count } of the special layout open, or null
 *   users      records on the page type's shared layout
 *   specials   names of the page type's special layouts
 *   elsewhere  the record shown uses a special layout
 */
export function pageFacts({ fixed = false, label = '', words = null, subject = null, record = false, layout = null, users = 0, specials = [], elsewhere = false } = {}) {
  const count = (n) => (n === 1 ? `1 ${words.one}` : `${n} ${words.many}`);
  if (fixed) return { uses: 'A fixed layout', rows: [['Layout', 'Fixed. Theme settings change how it looks.']], actions: [] };
  if (!words) return { uses: 'Its own layout', rows: [['Layout', 'Its own, on this page only']], actions: [] };
  const shows = subject?.name && !record ? [['Shows', subject.name]] : [];
  if (layout) {
    const used = layout.count ? count(layout.count) : `No ${words.one} yet`;
    return {
      uses: record ? `Uses the ${layout.label} layout` : `Special ${label.toLowerCase()} layout, used by ${layout.count ? count(layout.count) : `no ${words.one} yet`}`,
      rows: [['Used by', used], ...shows],
      actions: ['special'],
    };
  }
  const usedBy = users === 1 ? `1 ${words.one}` : users ? `All ${count(users)}` : `No ${words.one} yet`;
  const actions = record && subject?.name && !elsewhere && users ? ['only', 'special'] : ['special'];
  return {
    uses: record ? `Uses the ${label.toLowerCase()} layout` : users ? `Shared by ${count(users)}` : `Not used by any ${words.one} yet`,
    rows: [
      ['Used by', usedBy],
      ['Special layouts', specials.length ? specials.join(', ') : 'None'],
      ...shows,
    ],
    actions,
  };
}

/**
 * Who a layout reaches, in a few words, for the heading of the page on
 * the board: "All 24 products" when every record of its type uses it, a
 * count when only some do, "This page only" for a page that shows no
 * records. No DOM, no state.
 *   words    { one, many } for the records the page shows, or null
 *   users    the records that use this layout
 *   total    every record of the type
 *   special  the layout is a special one, used by the records chosen for it
 */
export function pageReach({ words = null, users = 0, total = 0, special = false } = {}) {
  if (!words) return 'This page only';
  if (!users) return `No ${words.many} yet`;
  if (users === 1) return `1 ${words.one}`;
  return !special && users === total ? `All ${users} ${words.many}` : `${users} ${words.many}`;
}

/* The facts of the page open now. */
function factsNow() {
  const page = S.page;
  const words = subjectWords(page?.subjectType);
  const base = pageDef(page?.type) || page || {};
  const subject = page?.subject || null;
  return pageFacts({
    fixed: isFixed(),
    label: base.label || '',
    words,
    subject,
    record: Boolean(page?.record),
    layout: currentLayout(),
    users: words ? defaultUsers(base) : 0,
    specials: words ? layoutsOf(base.type).map((l) => l.label) : [],
    elsewhere: Boolean(subject) && S.templates.some((t) => t.pageType === page.type && t.assigned.includes(Number(subject.id))),
  });
}

/** The line beside the page's name in its heading: which layout it uses. */
export const pageUses = () => (S.page ? factsNow().uses : '');

/**
 * The page's own settings, for the panel: the facts, the ways to give the
 * page a layout of its own and, on a special layout, its name, what uses
 * it and delete.
 */
export function pageSettings() {
  const page = S.page;
  if (!page) return [];
  const facts = factsNow();
  const words = subjectWords(page.subjectType);
  const base = pageDef(page.type) || page;
  const out = [h('dl', { class: 'facts' }, ...facts.rows.map(([term, value]) => h('div', { class: 'facts__row' }, h('dt', { text: term }), h('dd', { text: value }))))];
  const actions = h('div', { class: 'insp-actions' });
  if (facts.actions.includes('only')) {
    const name = page.subject.name;
    const n = defaultUsers(base);
    out.push(h('div', { class: 'pagecard' },
      icon('layers', 'pagecard__icon'),
      h('p', { class: 'pagecard__meta', text: n === 1 ? `Changes here show on this ${words.one} only.` : `Changes here show on all ${plural(n, words.one, words.many)}.` }),
      h('button', {
        type: 'button', class: 'btn btn--secondary btn--sm pagecard__action', 'aria-haspopup': 'dialog',
        onclick: (e) => newLayoutForm(e.currentTarget, base, { name, subject: page.subject }),
      }, icon('plus', 'icon--sm'), `Make a layout for ${name} only`)));
  }
  if (facts.actions.includes('special') && !currentLayout()) {
    actions.append(h('button', {
      type: 'button', class: 'btn btn--secondary btn--sm', 'aria-haspopup': 'dialog',
      onclick: (e) => newLayoutForm(e.currentTarget, base),
    }, icon('plus', 'icon--sm'), 'Make a special layout'));
    out.push(actions);
  }
  const layout = currentLayout();
  if (layout && words) out.push(...specialLayoutSettings(layout, words, page));
  return out;
}

/**
 * Opens the page's own settings; with `choose`, also the list of records
 * that use the special layout open now (a new layout starts there).
 */
export function selectLayout({ choose = false } = {}) {
  ui.showPage?.({ focus: 'panel' });
  if (choose) window.setTimeout(() => $('#layout-choose')?.click(), 80);
}

/* ------------------------------------------------------------------------
   Pages with a fixed layout
   ------------------------------------------------------------------------ */

/** What the board shows in place of rows on a page with a fixed layout: why, and the theme settings that style it. */
export function fixedPanel() {
  const page = S.page;
  const groups = themeGroups().filter((g) => (page.groups || []).includes(g.group));
  return h('div', { class: 'fixed-page' },
    h('div', { class: 'fixed-page__head' }, icon('lock'), h('p', { text: page.note || 'This page keeps a fixed layout. Theme settings change how it looks.' })),
    groups.length ? h('h2', { class: 'fixed-page__sub', text: 'What changes how it looks' }) : null,
    groups.length ? h('ul', { class: 'tlist', role: 'list' }, ...groups.map((g) => h('li', null, h('button', {
      type: 'button', class: 'tlist__item', 'aria-pressed': String(S.sel?.kind === 'theme' && S.sel.group === g.group),
      onclick: () => openThemeGroup(g.group),
    }, h('span', { class: 'tlist__icon' }, icon(g.icon || 'settings', 'icon--sm')),
    h('span', { class: 'tlist__text' }, h('span', { class: 'tlist__title', text: g.label })),
    icon('chevron-right', 'icon--sm'))))) : null);
}

/* ------------------------------------------------------------------------
   Layout settings: name, what uses it, delete
   ------------------------------------------------------------------------ */

/* A special layout's own settings: its name, what uses it, and delete. */
function specialLayoutSettings(layout, words, page) {
  const out = [];
  const form = h('div', { class: 'form' });

  // Name: saved when the field is left or Enter is pressed.
  const nameError = h('p', { class: 'field__error', id: 'layout-name-error', hidden: true }, icon('alert', 'icon--sm'), h('span'));
  const nameInput = h('input', {
    class: 'input', id: 'layout-name', type: 'text', value: layout.label, maxlength: 60, autocomplete: 'off',
    'aria-describedby': 'layout-name-error',
    onkeydown: (e) => { if (e.key === 'Enter') e.target.blur(); },
    oninput: () => { nameError.hidden = true; nameInput.removeAttribute('aria-invalid'); },
    onchange: () => renameLayout(layout, nameInput, nameError),
  });
  form.append(h('div', { class: 'field', dataset: { field: 'layout-name' } },
    h('div', { class: 'field__label' }, h('label', { for: 'layout-name', text: 'Name' })), nameInput, nameError));

  // What uses it.
  const chosen = h('div', { class: 'picked picked--list' });
  const status = h('p', { class: 'notice notice--warn', role: 'status' });
  const chooseLabel = h('span');
  const chooseBtn = h('button', {
    type: 'button', class: 'btn btn--secondary btn--sm', id: 'layout-choose', 'aria-haspopup': 'dialog',
    onclick: (e) => subjectPicker(e.currentTarget, layout.handle, drawChosen),
  }, icon('plus', 'icon--sm'), chooseLabel);

  function drawChosen() {
    const live = templateOf(layout.handle);
    if (!live) return;
    const kind = words.kind;
    const ids = live.assigned;
    const rows = ids.map((id) => {
      const item = describe(kind, id);
      return pickRow(kind, item, {
        trailing: h('button', {
          type: 'button', class: 'icon-btn icon-btn--sm', 'aria-label': `Stop using this layout for ${item.name}`,
          onclick: () => toggleSubject(live.handle, id, item.name, drawChosen),
        }, icon('close')),
      });
    });
    const more = live.count > ids.length ? h('p', { class: 'pick-empty', text: `And ${plural(live.count - ids.length, `more ${words.one}`, `more ${words.many}`)}.` }) : null;
    chooseLabel.textContent = live.count ? `Choose more ${words.many}` : `Choose ${words.many}`;
    status.replaceChildren(icon('alert'), `No ${words.one} uses this layout yet, so shoppers do not see it.`);
    status.hidden = live.count > 0;
    chosen.replaceChildren(...(rows.length ? [h('div', { class: 'pick-chosen', role: 'list', 'aria-label': `${words.many} using this layout` }, ...rows)] : []), more || '', chooseBtn);
    const unknown = ids.filter((id) => describe(kind, id).unknown && !isGone(kind, id));
    if (unknown.length) fetchNames(kind, unknown).then(() => chosen.isConnected && drawChosen());
  }
  drawChosen();
  form.append(h('div', { class: 'field', role: 'group', 'aria-labelledby': 'layout-used-label' },
    h('div', { class: 'field__label', id: 'layout-used-label' }, h('span', { text: `Used for these ${words.many}` })),
    status, chosen));
  out.push(form);

  out.push(h('div', { class: 'insp-foot' },
    h('button', {
      type: 'button', class: 'btn btn--danger-text btn--sm', 'aria-haspopup': 'dialog',
      onclick: (e) => openPopover(e.currentTarget, confirmBox({
        title: `Delete ${layout.label}?`,
        text: layout.count
          ? `${theirSubjects(layout.count, words, 'goes', 'go')} back to the default ${page.label.toLowerCase()} layout at once. Its sections and settings are deleted with it.`
          : `Its sections and settings are deleted with it. No ${words.one} uses it.`,
        confirmLabel: 'Delete layout',
        danger: true,
        onConfirm: () => deleteLayout(layout.handle),
      }), { align: 'start', label: `Delete ${layout.label}` }),
    }, icon('trash', 'icon--sm'), 'Delete layout')));
  return out;
}

async function renameLayout(layout, input, error) {
  const label = input.value.trim().replace(/\s+/g, ' ');
  const live = templateOf(layout.handle);
  if (!live || label === live.label) {
    input.value = live?.label || label;
    return;
  }
  const fail = (message) => {
    error.lastChild.textContent = message;
    error.hidden = false;
    input.setAttribute('aria-invalid', 'true');
  };
  if (!label) {
    fail('Give the layout a name.');
    return;
  }
  if (layoutsOf(live.pageType).some((l) => l.handle !== live.handle && l.label.toLowerCase() === label.toLowerCase())) {
    fail('A layout with that name already exists.');
    return;
  }
  try {
    applyLayouts(await ctx.api.renameTemplate(live.handle, label));
    ui.updatePageButton?.();
    ui.renderBoard();
    // The heading and the delete question name it too.
    if (!S.sel) ui.renderInspector();
    announce(`Renamed to ${label}`);
  } catch (e) {
    if (e.kind === 'unauthenticated' || e.kind === 'forbidden') setFatal(e);
    else if (e.kind === 'not-found') layoutGone(live);
    else fail(e.fields?.label?.[0] || e.message);
  }
}

/* ------------------------------------------------------------------------
   Choosing what uses a layout
   ------------------------------------------------------------------------ */

/** Where a subject is now: the layout of the same page type that lists it. */
const elsewhere = (layout, id) => S.templates.find((t) => t.handle !== layout.handle && t.pageType === layout.pageType && t.assigned.includes(Number(id)));

function subjectPicker(anchor, handle, onChange) {
  const layout = templateOf(handle);
  const words = subjectWords(layout?.subjectType);
  if (!layout || !words) return;
  openList(anchor, words.kind, {
    multiple: true,
    label: `Choose ${words.many} for ${layout.label}`,
    isChosen: (id) => (templateOf(handle)?.assigned || []).includes(Number(id)),
    metaFor: (item) => {
      const other = elsewhere(templateOf(handle) || layout, item.id);
      const base = [item.price || null, item.path ? `In ${item.path}` : null, item.active === false ? 'Switched off' : null].filter(Boolean);
      if (other) base.push(`Uses ${other.label}`);
      return base.join(' · ');
    },
    onPick: (item) => {
      remember(words.kind, item);
      const on = (templateOf(handle)?.assigned || []).includes(Number(item.id));
      toggleSubject(handle, Number(item.id), item.name, onChange);
      return !on;
    },
  });
}

/**
 * Adds a subject to the layout, or takes it off. The list changes at once;
 * the server's answer then replaces it, and a refusal puts it back.
 */
function toggleSubject(handle, id, name, onChange) {
  const layout = templateOf(handle);
  if (!layout) return;
  const words = subjectWords(layout.subjectType);
  const on = layout.assigned.includes(id);
  const other = on ? null : elsewhere(layout, id);

  // Shown straight away.
  if (on) {
    layout.assigned = layout.assigned.filter((x) => x !== id);
    layout.count = Math.max(0, layout.count - 1);
  } else {
    layout.assigned = [...layout.assigned, id];
    layout.count += 1;
    if (other) {
      other.assigned = other.assigned.filter((x) => x !== id);
      other.count = Math.max(0, other.count - 1);
    }
  }
  onChange?.();
  ui.renderBoard();

  ctx.api.assign(layout.subjectType, [id], on ? null : handle).then((res) => {
    applyLayouts(res);
    const moved = res.moved?.[0];
    if (moved) {
      toast(`${name} used ${moved.from_label || 'another layout'}. It uses ${templateOf(handle)?.label || layout.label} now: each ${words.one} has one layout.`, { timeout: 7000 });
    } else {
      announce(on ? `${name} uses the default layout again` : `${name} uses ${layout.label}`);
    }
    onChange?.();
    ui.renderBoard();
  }).catch((error) => {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    if (error.kind === 'not-found') {
      layoutGone(layout);
      return;
    }
    toast(`${name} was not ${on ? 'taken off' : 'added'}. ${error.fields?.subject_ids?.[0] || error.message}`, { tone: 'error', timeout: 8000 });
    refreshLayouts().then(() => onChange?.());
  });
}

/* ------------------------------------------------------------------------
   Making and deleting layouts
   ------------------------------------------------------------------------ */

/**
 * Making a special layout: a name, and the layout it starts as a copy of.
 * With `subject` (a record shown on the board), the record is moved onto
 * the new layout as soon as it exists, so "for {Name} only" holds.
 */
export function newLayoutForm(anchor, page, { name = '', subject = null } = {}) {
  const words = subjectWords(page.subjectType);
  if (!words) return;
  const existing = layoutsOf(page.type);
  const nameInput = h('input', {
    class: 'input', id: 'new-layout-name', type: 'text', maxlength: 60, placeholder: 'For example: Gift guide', autocomplete: 'off', 'aria-describedby': 'new-layout-error',
    value: name ? String(name).slice(0, 60) : null,
  });
  const error = h('p', { class: 'field__error', id: 'new-layout-error', hidden: true }, icon('alert', 'icon--sm'), h('span', { text: 'Give the layout a name.' }));
  const from = h('select', { class: 'select', id: 'new-layout-from' },
    h('option', { value: page.type }, `Default ${page.label.toLowerCase()} layout`),
    ...existing.map((l) => h('option', { value: l.handle }, l.label)));
  if (S.page?.type === page.type && S.page.layout) from.value = S.page.handle;
  const createBtn = h('button', { type: 'button', class: 'btn btn--primary btn--sm' }, 'Create layout');

  const fail = (message) => {
    nameInput.setAttribute('aria-invalid', 'true');
    error.lastChild.textContent = message;
    error.hidden = false;
    nameInput.focus();
  };
  const create = async () => {
    const label = nameInput.value.trim().replace(/\s+/g, ' ');
    if (!label) {
      fail('Give the layout a name.');
      return;
    }
    if (layoutsOf(page.type).some((l) => l.label.toLowerCase() === label.toLowerCase())) {
      fail('A layout with that name already exists.');
      return;
    }
    createBtn.disabled = true;
    createBtn.textContent = 'Creating...';
    // The copy starts from the saved draft, so the changes made here go first.
    const saved = await flush();
    if (!saved) {
      createBtn.disabled = false;
      createBtn.textContent = 'Create layout';
      fail('Your changes are not saved yet, so the copy would miss them. Check the status at the top, then try again.');
      return;
    }
    try {
      const res = await ctx.api.createTemplate({ pageType: page.type, label, from: from.value === page.type ? null : from.value });
      applyLayouts(res);
      if (res.document) addDocument(res.document);
      closePopover({ restoreFocus: false });
      const handle = res.template?.handle || res.document?.handle;
      const source = from.value === page.type ? `the default ${page.label.toLowerCase()} layout` : templateOf(from.value)?.label || 'the layout you chose';
      const leftOut = res.leftOut?.length ? ` ${res.leftOut.join(', ')} ${res.leftOut.length === 1 ? 'was' : 'were'} left out: special layouts cannot hold ${res.leftOut.length === 1 ? 'it' : 'them'}.` : '';
      let assigned = false;
      if (subject?.id && handle) {
        try {
          applyLayouts(await ctx.api.assign(page.subjectType, [Number(subject.id)], handle));
          assigned = true;
        } catch (error) {
          if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
            setFatal(error);
            return;
          }
          // The layout exists; choosing what uses it stays open below.
        }
      }
      const next = assigned
        ? `${subject.name} uses it now.`
        : `Choose which ${words.many} use it.`;
      toast(`${label} made from ${source}. ${next}${leftOut}`, { tone: 'success', timeout: leftOut ? 10000 : 6000 });
      await ui.openPage({ type: page.type, layout: handle }, { openLayout: !assigned, saved: true });
    } catch (e) {
      createBtn.disabled = false;
      createBtn.textContent = 'Create layout';
      if (e.kind === 'unauthenticated' || e.kind === 'forbidden') {
        closePopover();
        setFatal(e);
      } else {
        fail(e.fields?.label?.[0] || e.fields?.from?.[0] || e.message);
      }
    }
  };
  createBtn.addEventListener('click', create);
  nameInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') create(); });
  nameInput.addEventListener('input', () => {
    nameInput.removeAttribute('aria-invalid');
    error.hidden = true;
  });
  openPopover(anchor, h('div', { class: 'new-layout', role: 'group', 'aria-labelledby': 'new-layout-title' },
    h('h3', { id: 'new-layout-title' }, h('span', { text: `New ${page.label.toLowerCase()} layout` }),
      helpTip(`A special layout gives chosen ${words.many} their own sections. Every other ${words.one} keeps the default layout.`)),
    h('div', { class: 'field' }, h('div', { class: 'field__label' }, h('label', { for: 'new-layout-name', text: 'Name' })), nameInput, error),
    h('div', { class: 'field' }, h('div', { class: 'field__label' }, h('label', { for: 'new-layout-from', text: 'Start as a copy of' })), from),
    h('div', { class: 'confirm__actions' },
      h('button', { type: 'button', class: 'btn btn--secondary btn--sm', onclick: () => closePopover() }, 'Cancel'),
      createBtn)), { label: `New ${page.label.toLowerCase()} layout` });
  window.setTimeout(() => nameInput.focus(), 20);
}

/** Stops saving a layout's draft: it is about to go, or has gone. */
function dropDocument(handle) {
  const d = doc(handle);
  if (d) {
    window.clearTimeout(d.retryTimer);
    d.savedJson = JSON.stringify(d.content);
  }
  delete S.docs[handle];
  noteDraft(handle, null);
}

async function deleteLayout(handle) {
  const layout = templateOf(handle);
  if (!layout) return;
  const page = pageDef(layout.pageType);
  const words = subjectWords(layout.subjectType);
  try {
    // A save of this layout still on its way must not land after the delete.
    await doc(handle)?.saving;
    const res = await ctx.api.deleteTemplate(handle);
    dropDocument(handle);
    applyLayouts(res);
    toast(res.cleared
      ? `${layout.label} deleted. ${theirSubjects(res.cleared, words, 'uses', 'use')} the default layout again.`
      : `${layout.label} deleted.`, { tone: 'success' });
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    if (error.kind !== 'not-found') {
      toast(`${layout.label} was not deleted. ${error.message}`, { tone: 'error', timeout: 8000 });
      return;
    }
    dropDocument(handle);
    await refreshLayouts();
  }
  if (S.page?.layout && S.page.handle === handle) await ui.openPage({ type: page?.type || 'home' }, { saved: true });
  else redraw();
}

/** Someone else deleted the layout open here. */
async function layoutGone(layout) {
  dropDocument(layout.handle);
  await refreshLayouts();
  toast(`${layout.label} was deleted by someone else. Its ${subjectWords(layout.subjectType)?.many || 'pages'} use the default layout.`, { tone: 'error', timeout: 8000 });
  if (S.page?.layout && S.page.handle === layout.handle) await ui.openPage({ type: layout.pageType }, { saved: true });
}

export function initLayouts() {
  ui.refreshLayouts = refreshLayouts;
  ui.forgetLayouts = forgetLayouts;
  ui.selectLayout = selectLayout;
  ui.pageSettings = pageSettings;
  ui.pageUses = pageUses;
  ui.layoutGone = (handle) => {
    const layout = templateOf(handle);
    if (layout) layoutGone(layout);
  };
}
