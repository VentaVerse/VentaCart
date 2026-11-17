/*
 * Pages and special layouts: the page picker in the top bar (with Header and
 * footer as its first entry, see setScope), the Layout row
 * at the top of the section list, the layout settings (name, what uses it,
 * delete), making a new layout, and the pages with a fixed layout that are
 * only styled by theme settings.
 *
 * A special layout is a custom template of one page type (handle
 * `product.gift-guide`). Its sections are edited like any page's; its name
 * and the products (or categories, brands, pages, posts) that use it are
 * saved at once, not as part of the draft. Each of those uses one layout:
 * choosing it for one layout takes it from another, and the editor says so.
 */

import { $, h, icon, toast, announce, helpTip } from './dom.js';
import {
  S, ctx, ui, doc, pageDef, templateOf, layoutsOf, currentLayout, pageTitle, isFixed, noteDraft, addDocument,
  locate, isShared, scopeOfHandle, SHARED_LABEL,
} from './state.js';
import { themeGroups } from './catalog.js';
import { normalizeTemplate } from './boot.js';
import { flush, setFatal } from './drafts.js';
import { openMenu, openPopover, closePopover, confirmBox } from './popover.js';
import { showPanel, openSheet, isPhone, setNav, openThemeGroup, post } from './selection.js';
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
    updatePageButton();
  }
}

function redraw() {
  updatePageButton();
  ui.renderOutline();
  if (S.sel?.kind === 'layout') ui.renderInspector();
  ui.updateToolbar();
}

export function updatePageButton() {
  const text = isShared() ? SHARED_LABEL : pageTitle();
  const el = $('#page-name');
  if (el) el.textContent = text;
  document.title = `${text}: theme editor${S.store?.name ? ` | ${S.store.name}` : ''}`;
}

/* ------------------------------------------------------------------------
   The page picker
   ------------------------------------------------------------------------ */

export function pageMenu(anchor) {
  // The theme's own pages, then each extension's pages under its name.
  const editable = S.pages.filter((p) => p.kind !== 'fixed' && !p.extension);
  const extensionPages = S.pages.filter((p) => p.kind !== 'fixed' && p.extension);
  const fixed = S.pages.filter((p) => p.kind === 'fixed');
  const shared = isShared();
  const items = [
    { label: SHARED_LABEL, iconName: 'layout', checked: shared, run: openShared },
    { sep: true },
    { heading: 'Pages' },
  ];
  editable.forEach((p) => {
    const words = subjectWords(p.subjectType);
    items.push({
      label: p.subjectType ? `${p.label}: Default` : p.label,
      checked: !shared && S.page?.type === p.type && !S.page?.layout,
      disabled: p.ready === false,
      hint: p.ready === false ? 'Coming in a later update' : null,
      run: () => ui.openPage({ type: p.type }),
    });
    if (!words) return;
    layoutsOf(p.type).forEach((l) => items.push({
      label: `${p.label}: ${l.label}`,
      iconName: 'sparkle',
      checked: !shared && Boolean(S.page?.layout) && S.page.handle === l.handle,
      hint: l.count ? plural(l.count, words.one, words.many) : `Not used by any ${words.one} yet`,
      run: () => ui.openPage({ type: p.type, layout: l.handle }),
    }));
    items.push({
      label: `New ${p.label.toLowerCase()} layout`,
      iconName: 'plus',
      // The menu closes first; the form opens on the same button.
      run: () => window.setTimeout(() => newLayoutForm(anchor, p), 0),
    });
  });
  let group = null;
  extensionPages.forEach((p) => {
    if (p.group !== group) items.push({ sep: true }, { heading: p.group || 'Extensions' });
    group = p.group;
    items.push({
      label: p.label,
      checked: !shared && S.page?.type === p.type,
      hint: p.url ? null : 'Nothing to preview yet',
      run: () => ui.openPage({ type: p.type }),
    });
  });
  if (fixed.length) {
    items.push({ sep: true }, { heading: 'Styled by theme settings' });
    fixed.forEach((p) => items.push({
      label: p.label,
      iconName: 'lock',
      checked: S.page?.type === p.type,
      run: () => ui.openPage({ type: p.type }),
    }));
  }
  openMenu(anchor, items, { label: 'Pages' });
}

/* ------------------------------------------------------------------------
   Header and footer

   The header and footer are one design on every page, so they have one
   place in the section list, the first entry of the page picker, and a
   page's list holds only that page's own sections. Switching between the
   two lists never reloads the preview and keeps undo history; only opening
   another page does.
   ------------------------------------------------------------------------ */

/**
 * Shows the other list, 'page' or 'shared', without reloading the preview.
 * Answers true when the list changed.
 */
export function setScope(scope) {
  const next = isFixed() ? 'page' : scope;
  if (S.scope === next) return false;
  S.scope = next;
  ui.rememberPage?.();
  if (S.sel?.kind === 'section' && scopeOfHandle(locate(S.sel.sid)?.handle) !== next) {
    S.sel = null;
    post('select', { sectionId: null, blockId: null });
  }
  if (S.sel?.kind === 'layout' && next === 'shared') S.sel = null;
  S.addAt = null;
  S.renaming = null;
  if (S.nav === 'add') setNav('sections');
  updatePageButton();
  ui.renderOutline();
  ui.renderInspector();
  ui.updateDock();
  announce(next === 'shared' ? 'Editing the header and footer, shown on every page.' : `Editing the sections of ${pageTitle()}.`);
  return true;
}

/** The scope a section is edited in: its own page's, or the header and footer. */
export const scopeOf = (sid) => scopeOfHandle(locate(sid)?.handle);

/* A short tint on the page picker, so a switch made from the preview is seen. */
export function flashPicker() {
  const btn = $('#page-btn');
  if (!btn) return;
  btn.classList.remove('is-changed');
  void btn.offsetWidth; // restarts the animation
  btn.classList.add('is-changed');
}

/** Header and footer, from the page picker: shown on the home page. */
function openShared() {
  if (S.page?.type === 'home' && !S.page.layout) setScope('shared');
  else ui.openPage({ type: 'home' }, { scope: 'shared' });
}

/* ------------------------------------------------------------------------
   The Layout row at the top of the section list
   ------------------------------------------------------------------------ */

export function layoutRow() {
  const page = S.page;
  const words = subjectWords(page?.subjectType);
  if (!words || isFixed()) return null;
  const layout = currentLayout();
  const selected = S.sel?.kind === 'layout';
  const n = layout?.count || 0;
  const title = layout ? layout.label : `Default ${page.label.toLowerCase()} layout`;
  const meta = layout
    ? (n ? `Used by ${plural(n, words.one, words.many)}` : `Not used by any ${words.one} yet`)
    : `Every ${words.one} without a special layout`;
  return h('div', { class: 'o-group o-group--layout' },
    h('h2', { class: 'o-group__title' }, h('span', { text: 'Layout' })),
    h('button', {
      type: 'button',
      class: `layout-row${selected ? ' is-selected' : ''}${layout && !n ? ' is-unused' : ''}`,
      'aria-current': selected ? 'true' : null,
      onclick: () => selectLayout(),
    }, h('span', { class: 'layout-row__icon' }, icon(layout ? 'sparkle' : 'file')),
    h('span', { class: 'layout-row__text' }, h('span', { class: 'layout-row__name', text: title }), h('span', { class: 'layout-row__meta', text: meta })),
    icon('chevron-right')));
}

export function selectLayout({ choose = false } = {}) {
  if (!subjectWords(S.page?.subjectType)) return;
  S.sel = { kind: 'layout' };
  post('select', { sectionId: null, blockId: null });
  if (S.nav !== 'sections') setNav('sections', { keepSelection: true });
  ui.renderOutline();
  ui.renderInspector();
  showPanel('inspector');
  if (isPhone()) openSheet('inspector');
  ui.updateDock();
  if (choose) window.setTimeout(() => $('#layout-choose')?.click(), 80);
}

/* ------------------------------------------------------------------------
   Pages with a fixed layout
   ------------------------------------------------------------------------ */

export function fixedPanel() {
  const page = S.page;
  const groups = themeGroups().filter((g) => (page.groups || []).includes(g.group));
  return h('div', { class: 'fixed-note' },
    h('div', { class: 'fixed-note__head' }, h('span', { class: 'fixed-note__icon' }, icon('lock')),
      h('h2', { text: `${page.label}: fixed layout` })),
    h('p', { text: page.note || 'This page keeps a fixed layout. Theme settings change how it looks.' }),
    h('h3', { class: 'fixed-note__sub', text: 'What changes how it looks' }),
    h('ul', { class: 't-list', role: 'list' }, ...groups.map((g) => h('li', null, h('button', {
      type: 'button', class: `t-item${S.sel?.kind === 'theme' && S.sel.group === g.group ? ' is-selected' : ''}`,
      onclick: () => openThemeGroup(g.group),
    }, h('span', { class: 't-item__icon' }, icon(g.icon || 'settings')),
    h('span', { class: 't-item__text' }, h('span', { class: 't-item__title', text: g.label })),
    icon('chevron-right'))))),
    h('p', { class: 'fixed-note__foot', text: 'Other pages: hold Alt and click a link in the preview, or use the page menu at the top.' }));
}

/* ------------------------------------------------------------------------
   Layout settings: name, what uses it, delete
   ------------------------------------------------------------------------ */

export function layoutInspector(inspectorHead) {
  const page = S.page;
  const words = subjectWords(page?.subjectType);
  const layout = currentLayout();
  if (!words) return null;

  if (!layout) {
    const others = layoutsOf(page.type);
    const head = inspectorHead({ title: `Default ${page.label.toLowerCase()} layout`, kind: 'Page layout' });
    const body = h('div', { class: 'insp-body' },
      h('p', { class: 'layout-status' }, icon('check'), `Used by every ${words.one} that has no special layout.`),
      others.length ? h('p', { class: 'layout-status layout-status--muted' }, icon('sparkle'),
        `${plural(others.length, 'special layout')}: ${others.map((l) => l.label).join(', ')}. Open them from the page menu at the top.`) : null,
      h('div', { class: 'insp-actions' },
        h('button', {
          type: 'button', class: 'btn btn--secondary btn--sm', 'aria-haspopup': 'dialog',
          onclick: (e) => newLayoutForm(e.currentTarget, page),
        }, icon('plus', 'icon--sm'), 'Make a special layout')));
    return h('div', { class: 'inspector' }, head, body);
  }

  const head = inspectorHead({
    title: layout.label,
    help: `Shoppers see this layout on the ${words.many} chosen below, once it is published. Every other ${words.one} keeps the default layout. Sections and their settings are edited in the section list, as on any page.`,
    kind: `Special ${page.label.toLowerCase()} layout`,
  });
  const body = h('div', { class: 'insp-body' });
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
  body.append(form);

  body.append(h('div', { class: 'insp-foot' },
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
  return h('div', { class: 'inspector' }, head, body);
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
    updatePageButton();
    ui.renderOutline();
    // The heading and the delete question name it too.
    if (S.sel?.kind === 'layout') ui.renderInspector();
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
  ui.renderOutline();

  ctx.api.assign(layout.subjectType, [id], on ? null : handle).then((res) => {
    applyLayouts(res);
    const moved = res.moved?.[0];
    if (moved) {
      toast(`${name} used ${moved.from_label || 'another layout'}. It uses ${templateOf(handle)?.label || layout.label} now: each ${words.one} has one layout.`, { timeout: 7000 });
    } else {
      announce(on ? `${name} uses the default layout again` : `${name} uses ${layout.label}`);
    }
    onChange?.();
    ui.renderOutline();
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

/** Making a special layout: a name, and the layout it starts as a copy of. */
export function newLayoutForm(anchor, page) {
  const words = subjectWords(page.subjectType);
  if (!words) return;
  const existing = layoutsOf(page.type);
  const nameInput = h('input', {
    class: 'input', id: 'new-layout-name', type: 'text', maxlength: 60, placeholder: 'For example: Gift guide', autocomplete: 'off', 'aria-describedby': 'new-layout-error',
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
      toast(`${label} made from ${source}. Choose which ${words.many} use it.${leftOut}`, { tone: 'success', timeout: leftOut ? 10000 : 6000 });
      await ui.openPage({ type: page.type, layout: handle }, { openLayout: true, saved: true });
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
  ui.layoutRow = layoutRow;
  ui.fixedPanel = fixedPanel;
  ui.layoutInspector = layoutInspector;
  ui.updatePageButton = updatePageButton;
  ui.setScope = setScope;
  ui.scopeOf = scopeOf;
  ui.flashPicker = flashPicker;
  ui.layoutGone = (handle) => {
    const layout = templateOf(handle);
    if (layout) layoutGone(layout);
  };
}
