/*
 * The page picker is the page's heading: its name with a chevron, and
 * beside it a quiet line saying which layout it uses. It sits above the
 * board and, the same, above the store in Preview, and scrolls away with
 * them. It opens a menu of page types. Category, Product and Information
 * page open in place to their shared layout, a search of their records
 * (with the count) and the records, each tagged with the special layout it
 * uses; the special layouts are listed at the foot. On a phone the heading
 * is a full-width button and the menu comes up as a sheet.
 *
 * It sits behind a small interface, so it can be mounted wherever the
 * page's name is shown:
 *
 *   pageHeading(where)   the heading, for 'board' or 'preview'
 *   updatePagePicker()   names the page open now in every heading and the window's title
 *   openPageMenu()       opens the menu from the heading of the view shown
 *
 * The rules of its rows and keys are in board/page-menu.js.
 */

import { $, $$, h, icon, toast } from './dom.js';
import { S, ctx, ui, layoutsOf, pageDef, pageTitle } from './state.js';
import { setFatal } from './drafts.js';
import { plural } from './util.js';
import { subjectWords } from './layouts.js';
import { RECORD_TYPES, searchWords, recordRows, menuKey } from './board/page-menu.js';

/* Records shown under an open type before anything is typed; a search shows more. */
const FIRST_SHOWN = 8;
const SEARCH_WAIT_MS = 200;

const PM = { open: false, expanded: null, query: '', found: {}, seq: 0 };

const menuEl = () => $('#pagemenu');
const headOf = () => $(S.mode === 'preview' ? '#preview .pagehead' : '#board .pagehead');

/** The heading of a view: the page's name, which opens the menu, and the layout it uses. */
export function pageHeading(where) {
  const id = `pagehead-${where}`;
  return h('div', { class: 'pagehead', dataset: { where } },
    h(where === 'board' ? 'h1' : 'h2', { class: 'pagehead__title' },
      h('button', {
        type: 'button', class: 'pagehead__btn', id,
        'aria-haspopup': 'dialog', 'aria-expanded': String(PM.open && headOf()?.dataset.where === where), 'aria-describedby': `${id}-uses`,
        onclick: () => (PM.open ? closePageMenu() : openPageMenu()),
        onkeydown: (e) => {
          if (e.key !== 'ArrowDown' || PM.open) return;
          e.preventDefault();
          openPageMenu();
        },
      }, h('span', { class: 'pagehead__name', text: pageTitle() }), icon('chevron-down', 'pagehead__chev'))),
    h('p', { class: 'pagehead__uses', id: `${id}-uses`, text: ui.pageUses?.() || '' }));
}

export function updatePagePicker() {
  const text = pageTitle();
  const uses = ui.pageUses?.() || '';
  $$('.pagehead').forEach((head) => {
    $('.pagehead__name', head).textContent = text;
    $('.pagehead__uses', head).textContent = uses;
  });
  document.title = `${text}: theme editor${S.store?.name ? ` | ${S.store.name}` : ''}`;
}

/* ------------------------------------------------------------------------
   The menu
   ------------------------------------------------------------------------ */

/* Focus inside the menu without scrolling the view behind it; the menu scrolls itself. */
function focusIn(el) {
  if (!el) return;
  el.focus({ preventScroll: true });
  const menu = menuEl();
  const box = [$('.pagemenu__body', menu), menu].find((x) => x && x.scrollHeight > x.clientHeight);
  if (!box) return;
  const r = el.getBoundingClientRect();
  const b = box.getBoundingClientRect();
  if (r.top < b.top + 8) box.scrollTop -= b.top + 8 - r.top;
  else if (r.bottom > b.bottom - 8) box.scrollTop += r.bottom - b.bottom + 8;
}

/** Opens the menu on the page open now: a record's type is open, with the record checked. */
export function openPageMenu({ expand = null } = {}) {
  if (!S.pages?.length) return;
  PM.open = true;
  PM.expanded = expand || (S.page?.record && RECORD_TYPES[S.page.type] ? S.page.type : null);
  PM.query = '';
  drawMenu();
  if (PM.expanded) loadRecords(PM.expanded);
  const menu = menuEl();
  focusIn($('.pagemenu__item[aria-current="page"]', menu) || $('.pagemenu__item:not(:disabled)', menu));
}

export function closePageMenu({ focus = true } = {}) {
  if (!PM.open) return;
  PM.open = false;
  menuEl()?.remove();
  $('.pagemenu-scrim')?.remove();
  $$('.pagehead__btn').forEach((b) => b.setAttribute('aria-expanded', 'false'));
  if (focus) $('.pagehead__btn', headOf())?.focus();
}

/* The board's heading is drawn again with the board: an open menu goes back under it, keeping the focus. */
function remount() {
  if (!PM.open) return;
  const head = headOf();
  const menu = menuEl();
  if (!head || !menu || head.contains(menu)) return;
  const focused = menu.contains(document.activeElement) ? document.activeElement : null;
  head.append($('.pagemenu-scrim') || '', menu);
  $('.pagehead__btn', head).setAttribute('aria-expanded', 'true');
  focused?.focus({ preventScroll: true });
}

const isPageOpen = (p) => S.page?.type === p.type && !S.page?.layout && !S.page?.record;
const check = (on) => (on ? icon('check', 'icon--sm pagemenu__check') : h('span', { class: 'pagemenu__check', 'aria-hidden': 'true' }));

function typeRow(p) {
  const rec = RECORD_TYPES[p.type] && p.subjectType && p.ready !== false && p.subjectCount > 0 ? RECORD_TYPES[p.type] : null;
  const open = rec && PM.expanded === p.type;
  const words = subjectWords(p.subjectType);
  const meta = p.ready === false ? 'Coming in a later update'
    : words && p.subjectCount ? plural(p.subjectCount, words.one, words.many)
      : p.kind === 'fixed' ? 'Styled by theme settings'
        : p.extension ? p.group || '' : '';
  const here = isPageOpen(p) || (rec && S.page?.type === p.type && !open);
  return h('li', { class: 'pagemenu__entry' },
    h('button', {
      type: 'button', class: `pagemenu__item${rec ? ' pagemenu__item--type' : ''}`, dataset: { type: p.type },
      disabled: p.ready === false || null,
      'aria-current': isPageOpen(p) && !rec ? 'page' : null,
      'aria-expanded': rec ? String(Boolean(open)) : null,
      onclick: () => (rec ? toggleType(p.type) : goTo({ type: p.type })),
    },
    rec ? icon(open ? 'chevron-down' : 'chevron-right', 'icon--sm pagemenu__twist') : h('span', { class: 'pagemenu__twist', 'aria-hidden': 'true' }),
    h('span', { class: 'pagemenu__name', text: p.label }),
    h('span', { class: 'pagemenu__meta', text: meta }),
    check(here)),
    open ? groupEl(p, rec) : null);
}

/* An open type: its shared layout, a search with the count, and its records. */
function groupEl(p, rec) {
  const id = `pm-search-${p.type}`;
  const records = h('ul', { class: 'pagemenu__records', role: 'list' });
  const input = h('input', {
    class: 'input', id, type: 'search', autocomplete: 'off', spellcheck: 'false',
    placeholder: searchWords(p.subjectCount, rec), value: PM.query,
    oninput: (e) => {
      PM.query = e.target.value;
      searchSoon(p.type);
    },
  });
  const group = h('div', { class: 'pagemenu__group', dataset: { type: p.type } },
    h('button', {
      type: 'button', class: 'pagemenu__item pagemenu__item--layout',
      'aria-current': isPageOpen(p) ? 'page' : null,
      onclick: () => goTo({ type: p.type }),
    }, icon('layers', 'icon--sm pagemenu__twist'),
    h('span', { class: 'pagemenu__name', text: `${p.label} layout` }),
    h('span', { class: 'pagemenu__meta', text: `All ${plural(p.subjectCount, rec.one, rec.many)}` }),
    check(isPageOpen(p))),
    h('div', { class: 'pagemenu__search' },
      h('label', { class: 'visually-hidden', for: id, text: `Search ${rec.many}` }),
      icon('search', 'icon--sm'), input),
    records);
  fillRecords(records, p, rec);
  return group;
}

function fillRecords(list, p, rec) {
  const found = PM.found[p.type];
  if (!found) {
    list.replaceChildren(h('li', { class: 'pagemenu__none', role: 'status', text: `Loading ${rec.many}...` }));
    return;
  }
  if (found.error) {
    list.replaceChildren(h('li', { class: 'pagemenu__none', role: 'status', text: found.error }));
    return;
  }
  const q = PM.query.trim();
  const rows = recordRows(q ? found.items : found.items.slice(0, FIRST_SHOWN), {
    query: q, templates: S.templates, pageType: p.type, current: S.page?.type === p.type && S.page?.record ? S.page.subject?.id : null,
  });
  if (!rows.length) {
    list.replaceChildren(h('li', { class: 'pagemenu__none', role: 'status', text: q ? `No ${rec.many} match "${q}".` : `No ${rec.many} yet.` }));
    return;
  }
  list.replaceChildren(...rows.map((r) => h('li', null, h('button', {
    type: 'button', class: 'pagemenu__item pagemenu__item--record', dataset: { record: String(r.id) },
    'aria-current': r.current ? 'page' : null,
    onclick: () => openRecord(p, r),
  },
  h('span', { class: 'pagemenu__name' }, ...r.parts.map((part) => (part.match ? h('mark', { class: 'pagemenu__match', text: part.text }) : part.text))),
  r.tag ? h('span', { class: 'tag pagemenu__special', text: r.tag }) : h('span', { 'aria-hidden': 'true' }),
  check(r.current)))));
}

/* The records of a type, from the store's lookup: the first ones, or those matching the search. */
async function loadRecords(type) {
  const rec = RECORD_TYPES[type];
  if (!rec) return;
  PM.seq += 1;
  const seq = PM.seq;
  const q = PM.query.trim();
  try {
    const res = await ctx.api.pick(rec.kind, { q });
    if (seq !== PM.seq) return;
    PM.found[type] = { items: res.items || [], more: Boolean(res.more) };
  } catch (error) {
    if (seq !== PM.seq) return;
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    PM.found[type] = { error: `The ${rec.many} could not be loaded. ${error.message || ''}`.trim() };
  }
  redrawRecords(type);
}

function redrawRecords(type) {
  const list = $(`#pagemenu .pagemenu__group[data-type="${CSS.escape(type)}"] .pagemenu__records`);
  const p = pageDef(type);
  if (list && p) fillRecords(list, p, RECORD_TYPES[type]);
}

let searchTimer = 0;
function searchSoon(type) {
  redrawRecords(type);
  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => loadRecords(type), SEARCH_WAIT_MS);
}

function specialRows() {
  return S.templates.map((t) => {
    const page = pageDef(t.pageType);
    const words = subjectWords(t.subjectType);
    const here = Boolean(S.page?.layout) && S.page.handle === t.handle && !S.page.record;
    const used = words ? (t.count ? plural(t.count, words.one, words.many) : `no ${words.one} yet`) : '';
    return h('li', { class: 'pagemenu__entry' }, h('button', {
      type: 'button', class: 'pagemenu__item', dataset: { layout: t.handle },
      'aria-current': here ? 'page' : null,
      onclick: () => goTo({ type: t.pageType, layout: t.handle }),
    }, h('span', { class: 'pagemenu__twist', 'aria-hidden': 'true' }),
    h('span', { class: 'pagemenu__name', text: t.label }),
    h('span', { class: 'pagemenu__meta', text: [page?.label, used ? `used by ${used}` : ''].filter(Boolean).join(', ') }),
    check(here)));
  });
}

/* Draws the menu under the heading of the view shown. */
function drawMenu() {
  menuEl()?.remove();
  $('.pagemenu-scrim')?.remove();
  const head = headOf();
  if (!head) return;
  $('.pagehead__btn', head).setAttribute('aria-expanded', 'true');
  const specials = specialRows();
  const menu = h('div', { class: 'pagemenu popover', id: 'pagemenu', role: 'dialog', 'aria-label': 'Choose a page', onkeydown: menuKeys },
    h('div', { class: 'pagemenu__head' },
      h('h2', { class: 'pagemenu__title', text: 'Choose a page' }),
      h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Close', onclick: () => closePageMenu() }, icon('close'))),
    h('div', { class: 'pagemenu__body' },
      h('p', { class: 'pagemenu__heading', text: 'Pages' }),
      h('ul', { class: 'pagemenu__list', role: 'list' }, ...S.pages.map(typeRow)),
      specials.length ? h('p', { class: 'pagemenu__heading', text: 'Special layouts' }) : null,
      specials.length ? h('ul', { class: 'pagemenu__list', role: 'list' }, ...specials) : null));
  menu.addEventListener('focusout', (e) => {
    // Tabbing out of the menu closes it, as leaving any popup does.
    if (PM.open && e.relatedTarget && !menu.contains(e.relatedTarget) && !e.relatedTarget.closest('.pagehead')) closePageMenu({ focus: false });
  });
  // The scrim only shows on a phone, under the sheet.
  head.append(h('div', { class: 'pagemenu-scrim', 'aria-hidden': 'true', onclick: () => closePageMenu() }), menu);
}

function toggleType(type) {
  PM.expanded = PM.expanded === type ? null : type;
  PM.query = '';
  drawMenu();
  if (PM.expanded) loadRecords(type);
  const menu = menuEl();
  focusIn(PM.expanded ? $('.pagemenu__search .input', menu) : $(`.pagemenu__item--type[data-type="${CSS.escape(type)}"]`, menu));
}

function goTo(target) {
  closePageMenu();
  ui.openPage?.(target);
}

async function openRecord(p, row) {
  closePageMenu();
  let found;
  try {
    found = await ctx.api.subjectPage(p.subjectType, row.id);
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    toast(error.kind === 'not-found'
      ? `${row.name} cannot be shown: shoppers cannot open it yet. Switch it on in the admin first.`
      : `${row.name} could not be opened. ${error.message}`, { tone: 'error', timeout: 8000 });
    return;
  }
  const layout = S.templates.find((t) => t.pageType === p.type && t.assigned.includes(Number(found.subject.id)));
  await ui.openPage?.({ type: p.type, layout: layout?.handle || null, subject: found.subject, url: found.url });
}

/* Arrows move, Right and Left open and close a type, typing searches, Enter in the search opens the first match, Escape closes. */
function menuKeys(e) {
  const menu = menuEl();
  const stops = $$('.pagemenu__item:not(:disabled), .pagemenu__search .input', menu);
  const index = stops.indexOf(document.activeElement);
  const search = $('.pagemenu__search .input', menu);
  const action = menuKey({
    key: e.key,
    index,
    count: stops.length,
    inSearch: e.target === search,
    type: e.target.matches?.('.pagemenu__item--type') ? e.target.dataset.type : e.target.closest?.('.pagemenu__group')?.dataset.type || null,
    expanded: PM.expanded,
    hasSearch: Boolean(search),
    names: stops.map((el) => (el.matches('[data-type]') ? $('.pagemenu__name', el)?.textContent || '' : '')),
    modifier: e.ctrlKey || e.metaKey || e.altKey,
  });
  if (!action) return;
  e.preventDefault();
  e.stopPropagation();
  if (action.do === 'close') closePageMenu();
  else if (action.do === 'move') focusIn(stops[action.to]);
  else if (action.do === 'toggle') toggleType(action.type);
  else if (action.do === 'first') $('.pagemenu__item--record', menu)?.click();
  else if (action.do === 'search') {
    search.value += action.letter;
    search.dispatchEvent(new Event('input'));
    focusIn(search);
  }
}

export function initPagePicker() {
  // Escape closes the menu even when the focus has left it (a click on its padding, say).
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || !PM.open) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    closePageMenu();
  }, true);
  ui.updatePageButton = updatePagePicker;
  ui.remountPageMenu = remount;
  ui.closePageMenu = closePageMenu;
  // A click outside the menu closes it. The path is read at dispatch, since a click inside may redraw the menu.
  document.addEventListener('click', (e) => {
    if (!PM.open) return;
    const path = e.composedPath();
    if (path.some((n) => n.id === 'pagemenu' || n.classList?.contains('pagehead__btn'))) return;
    closePageMenu({ focus: false });
  });
}
