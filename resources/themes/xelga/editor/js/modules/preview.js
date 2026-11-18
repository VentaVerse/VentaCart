/*
 * Preview: the store page itself, as a shopper sees it, drawn by the store
 * from the draft in a frame beside the always-open settings panel. Board
 * and Preview are two views of the same page, switched in the middle of
 * the top bar; switching keeps the reader's place (the section at the top
 * of one view is at the top of the other, and an open section is the one
 * shown) and never animates.
 *
 * The frame shows the draft because its address carries this admin's
 * preview window nonce (PreviewMode on the server). It is on the store's
 * own origin, so the editor reaches into it directly. Clicks in it do what
 * they do on the store, links and swipe rows included; nothing in it opens
 * settings.
 *
 * Changes made in the panel show as they are made, by each setting's live
 * mode from the catalog: a plain text is written into the elements that
 * show it (data-setting) at once; anything else is drawn again by the
 * store once the draft is saved: the one section it belongs to (the
 * section-only answer), the theme's tokens stylesheet for a token, or the
 * whole page for a change of the page's structure.
 */

import { $, $$ } from './dom.js';
import { S, ctx, ui, valueOf, locate, isFixed } from './state.js';
import { sectionDef, blockDef, themeSetting } from './catalog.js';
import { onChange } from './operations.js';
import { flush, setFatal } from './drafts.js';
import { isPhone, radioItems, radioKeys } from './selection.js';
import { isSameOrigin } from './api.js';
import { placeFor, placeIn, scrollBy, OPEN_OFFSET } from './board/place.js';

/* The widths Preview offers. A computer is at least this wide, scaled down to fit when the panel leaves less. */
const DEVICES = {
  computer: { label: 'Computer', width: 1280 },
  tablet: { label: 'Tablet', width: 834 },
  phone: { label: 'Phone', width: 390 },
};

/* How long after the last change the store is asked to draw it again, in milliseconds. */
const WAIT_MS = 450;
const RETRY_MS = 2500;

/* Pages about one record that have none to show yet. */
const NO_RECORD = {
  product: 'This page shows one product. Add a product in the admin and switch it on to see it here.',
  category: 'This page shows one category. Add a category in the admin and switch it on to see it here.',
  brand: 'This page shows one brand. Add a brand in the admin and switch it on to see it here.',
  page: 'This page shows one information page. Add one in the admin and switch it on to see it here.',
  blog_post: 'This page shows one blog post. Publish a post in the blog to see it here.',
};

const frame = () => $('#preview-frame');

let shown = ''; // the address last loaded for the page open in the editor
let ready = false;
let waiters = [];
let restoreY = null;
let generation = 0; // counts loads, so a redraw that lands after a reload is dropped
let scale = 1;
let timer = 0;
let running = null;
let pending = fresh();

function fresh() {
  return { reload: false, tokens: false, sections: new Set() };
}

/* ------------------------------------------------------------------------
   The frame
   ------------------------------------------------------------------------ */

/** The address the frame shows for the page open now, with the preview nonce; '' when it cannot be shown. */
function address() {
  const { nonce, param } = ctx.boot?.preview || {};
  const base = S.page?.url;
  // The nonce only ever goes to the store's own address.
  if (!nonce || !base || !isSameOrigin(base)) return '';
  const url = new URL(base, window.location.href);
  url.searchParams.set(param || 'xelga_preview', nonce);
  return url.href;
}

/** The frame's window while it shows a page of this store, else null. */
function frameWin() {
  try {
    const win = frame()?.contentWindow;
    if (!win || win.location.origin !== window.location.origin || win.location.href === 'about:blank') return null;
    return win.document ? win : null;
  } catch {
    return null;
  }
}

function whenReady() {
  if (ready) return Promise.resolve();
  return new Promise((resolve) => { waiters.push(resolve); });
}

/*
 * Loads an address in the frame. Once the frame shows a store page it is
 * moved by replacing its address, so the browser's Back button still
 * leaves the editor rather than stepping through the frame's pages.
 */
function navigate(url, { keepScroll = false } = {}) {
  const win = frameWin();
  restoreY = keepScroll && win ? win.scrollY : null;
  ready = false;
  generation += 1;
  if (win) win.location.replace(url);
  else frame().src = url;
  return whenReady();
}

/** Loads the frame's page again, where it was scrolled, to show what the server now draws. */
function reloadFrame() {
  const win = frameWin();
  if (!win) return shown ? navigate(shown) : Promise.resolve();
  restoreY = win.scrollY;
  ready = false;
  generation += 1;
  win.location.reload();
  return whenReady();
}

function onFrameLoad() {
  const win = frameWin();
  if (!win) return;
  ready = true;
  if (restoreY !== null) {
    win.scrollTo({ top: restoreY, behavior: 'instant' });
    restoreY = null;
  }
  waiters.splice(0).forEach((fn) => fn());
  if (S.mode === 'preview') schedule(0);
}

function setState(text) {
  const el = $('#preview-state');
  if (!el) return;
  el.textContent = text || '';
  el.hidden = !text;
}

/*
 * Makes the frame show the page open in the editor. Called when the page
 * changes and when the preview window opens: the frame loads in the
 * background, so the first switch to Preview shows the page at once.
 */
export function previewPage() {
  const url = address();
  if (!S.page) return;
  if (!url) {
    shown = '';
    ready = false;
    if (frame() && frameWin()) frame().src = 'about:blank';
    const nonce = ctx.boot?.preview?.nonce;
    setState(!nonce ? 'Opening the store preview...'
      : isFixed() || S.page.url ? 'This page cannot be shown here.'
        : NO_RECORD[S.page.subjectType] || 'The store has nothing to show on this page yet.');
    return;
  }
  setState('');
  if (url === shown) return;
  shown = url;
  pending = fresh();
  navigate(url);
}

/* ------------------------------------------------------------------------
   Sections in the frame
   ------------------------------------------------------------------------ */

/** The page's sections in the frame, in page order, without sections drawn inside others. */
function frameSections(doc) {
  return Array.from(doc.querySelectorAll('[data-section-id]')).filter((el) => !el.parentElement?.closest('[data-section-id]'));
}

const frameSection = (doc, sid) => frameSections(doc).find((el) => el.getAttribute('data-section-id') === sid) || null;

/** The elements of a section or block that show one setting, leaving out those of blocks inside it. */
function settingNodes(scope, key, isBlock) {
  return Array.from(scope.querySelectorAll(`[data-setting="${CSS.escape(key)}"]`)).filter((el) => {
    const block = el.closest('[data-block-id]');
    return isBlock ? block === scope : !block || !scope.contains(block);
  });
}

/**
 * Writes a plain text value where the page shows it. Answers false when it
 * cannot (the section is not on the page, the text is rich text, nothing
 * shows it), and the section is drawn again instead.
 */
function writeText(sid, bid, key) {
  const doc = frameWin()?.document;
  const section = doc && frameSection(doc, sid);
  if (!section) return false;
  const scope = bid ? section.querySelector(`[data-block-id="${CSS.escape(bid)}"]`) : section;
  if (!scope) return false;
  const nodes = settingNodes(scope, key, Boolean(bid));
  if (!nodes.length || nodes.some((el) => el.hasAttribute('data-richtext'))) return false;
  const value = valueOf(sid, bid, key);
  const text = value === null || value === undefined ? '' : String(value);
  nodes.forEach((el) => {
    el.textContent = text;
    if (el.hasAttribute('data-optional')) el.hidden = text === '';
  });
  return true;
}

/** Draws one section again from the saved draft. Answers false when the page has to load again instead. */
async function redrawSection(sid) {
  const win = frameWin();
  const old = win && frameSection(win.document, sid);
  if (!old) return false;
  const at = generation;
  const html = await ctx.api.renderSection(win.location.href, sid);
  const now = frameWin();
  if (at !== generation || !now) return true;
  // The store's own section-only answer (api.renderSection checks where it came from), parsed inertly.
  const tpl = now.document.createElement('template');
  tpl.innerHTML = html;
  const next = Array.from(tpl.content.querySelectorAll('[data-section-id]')).find((el) => el.getAttribute('data-section-id') === sid);
  const current = frameSection(now.document, sid);
  if (!next || !current) return false;
  now.XelgaTheme?.cleanupSection?.(current);
  current.replaceWith(next);
  now.XelgaTheme?.initSection?.(next);
  return true;
}

/* The draft tokens stylesheet loaded again; the old one goes once the new one has loaded, so nothing flashes. */
function refreshTokens() {
  const doc = frameWin()?.document;
  const link = doc && Array.from(doc.querySelectorAll('link[rel="stylesheet"]')).find((l) => /\/xelga\/tokens\/draft\.css/.test(l.getAttribute('href') || ''));
  if (!link) return false;
  const url = new URL(link.href);
  url.searchParams.set('v', String(Date.now()));
  const next = link.cloneNode();
  next.href = url.href;
  next.addEventListener('load', () => link.remove(), { once: true });
  next.addEventListener('error', () => next.remove(), { once: true });
  link.after(next);
  return true;
}

/* ------------------------------------------------------------------------
   Changes, as they are made
   ------------------------------------------------------------------------ */

function settingOf(sid, bid, key) {
  const at = locate(sid);
  if (!at) return null;
  const list = bid ? blockDef(at.data.type, at.data.blocks?.[bid]?.type)?.settings : sectionDef(at.data.type)?.settings;
  return (list || []).find((s) => s.key === key) || null;
}

function queue(job) {
  if (job.reload) pending.reload = true;
  if (job.tokens) pending.tokens = true;
  if (job.section) pending.sections.add(job.section);
  schedule();
}

function schedule(ms = WAIT_MS) {
  window.clearTimeout(timer);
  timer = window.setTimeout(() => { run(); }, ms);
}

/** What one change needs in the frame. Undo and redo are changes like any other. */
function follow(op) {
  if (S.fatal || !op) return;
  switch (op.t) {
    case 'rename':
      // Names are the editor's own; the store does not show them.
      return;
    case 'set': {
      const def = settingOf(op.sid, op.bid, op.key);
      if (def?.live === 'text' && def.type !== 'richtext' && writeText(op.sid, op.bid || null, op.key)) return;
      queue({ section: op.sid });
      return;
    }
    case 'visible':
      // A hidden section is not drawn at all, so showing it again needs the page.
      queue(op.bid ? { section: op.sid } : { reload: true });
      return;
    case 'block-add':
    case 'block-remove':
    case 'block-move':
      queue({ section: op.sid });
      return;
    case 'theme':
      queue(themeSetting(op.key)?.target?.type === 'token' ? { tokens: true } : { reload: true });
      return;
    case 'color':
      queue({ tokens: true });
      return;
    default:
      // Sections added, moved, replaced or removed, and rows and sides: the page is drawn again.
      queue({ reload: true });
  }
}

/*
 * Runs what is waiting, once the draft is saved. Only while Preview is
 * shown: on the board the work waits, and runs when Preview comes back.
 */
async function run() {
  if (S.mode !== 'preview' || !ready || S.fatal) return;
  if (running) {
    schedule();
    return;
  }
  const job = pending;
  if (!job.reload && !job.tokens && !job.sections.size) return;
  pending = fresh();
  running = work(job);
  try {
    await running;
  } finally {
    running = null;
  }
}

async function work(job) {
  try {
    if (!await flush()) {
      // Not saved yet, so the store would draw the old draft: try again shortly.
      if (job.reload) pending.reload = true;
      if (job.tokens) pending.tokens = true;
      job.sections.forEach((sid) => pending.sections.add(sid));
      schedule(RETRY_MS);
      return;
    }
    if (job.reload) {
      await reloadFrame();
      return;
    }
    if (job.tokens && !refreshTokens()) {
      await reloadFrame();
      return;
    }
    for (const sid of job.sections) {
      if (!await redrawSection(sid)) {
        await reloadFrame();
        return;
      }
    }
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    if (error.kind === 'too-many') {
      job.sections.forEach((sid) => pending.sections.add(sid));
      schedule(Math.min(30, error.retryAfter || 5) * 1000);
      return;
    }
    // Anything else (the section moved off the page, a redirect): the page loads again from the saved draft.
    await reloadFrame();
  }
}

/** Shows the saved draft again from scratch: after a store is applied, a draft thrown away or a version restored. */
export function reloadPreview() {
  queue({ reload: true });
}

/* ------------------------------------------------------------------------
   Width
   ------------------------------------------------------------------------ */

/*
 * The frame is a real viewport of the chosen width, so the store draws as
 * on that screen, scaled down (never up) to fit beside the panel. Its
 * height fills the view. On a phone the editor itself is the phone, and
 * the frame simply fills the view.
 */
export function fitFrame() {
  const stage = $('#preview-stage');
  const wrap = $('#preview-wrap');
  const el = frame();
  const caption = $('#preview-size');
  if (!stage || !wrap || !el) return;
  if (isPhone()) {
    scale = 1;
    Object.assign(wrap.style, { width: '', height: '' });
    Object.assign(el.style, { width: '', height: '', transform: '' });
    if (caption) caption.textContent = '';
    return;
  }
  const cs = getComputedStyle(stage);
  const availW = Math.max(200, stage.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight));
  const availH = Math.max(200, stage.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom));
  const device = DEVICES[S.device] || DEVICES.computer;
  const width = S.device === 'computer' ? Math.max(Math.floor(availW), device.width) : device.width;
  scale = Math.min(1, availW / width);
  Object.assign(wrap.style, { width: `${Math.floor(width * scale)}px`, height: `${Math.floor(availH)}px` });
  Object.assign(el.style, {
    width: `${width}px`,
    height: `${Math.floor(availH / scale)}px`,
    transform: scale < 1 ? `scale(${scale})` : '',
    transformOrigin: '0 0',
  });
  const pct = Math.round(scale * 100);
  if (caption) caption.textContent = `${width} pixels wide${pct < 100 ? `, shown at ${pct}%` : ''}`;
}

function setDevice(device) {
  if (!DEVICES[device] || device === S.device) return;
  const place = framePlace();
  S.device = device;
  radioItems($('#preview-devices'), device);
  fitFrame();
  window.requestAnimationFrame(() => applyFramePlace(place));
}

/* ------------------------------------------------------------------------
   Board and Preview, keeping the place
   ------------------------------------------------------------------------ */

const openSection = () => (S.sel?.kind === 'section' ? S.sel.sid : null);

/* The board's sections from the top of its view down, and the place to keep. */
function boardPlace() {
  const board = $('#board');
  if (!board) return null;
  const top = board.getBoundingClientRect().top;
  const boxes = $$('.piece[data-sid]', board).map((el) => {
    const r = el.getBoundingClientRect();
    return { sid: el.dataset.sid, top: r.top, bottom: r.bottom };
  });
  const place = placeFor({ selected: openSection(), scrolled: board.scrollTop, boxes, viewTop: top });
  return place && { ...place, next: following(boxes, top) };
}

/*
 * Where the frame's page starts to show: under the store's header when it
 * stays at the top as the page scrolls, else at the frame's top. In the
 * frame's own pixels.
 */
function frameTop(win) {
  let el = win.document.elementFromPoint(Math.floor(win.innerWidth / 2), 1);
  while (el && el !== win.document.body) {
    const position = win.getComputedStyle(el).position;
    if (position === 'sticky' || position === 'fixed') return Math.max(0, el.getBoundingClientRect().bottom);
    el = el.parentElement;
  }
  return 0;
}

/* The frame's sections, measured inside the frame, and the place to keep, in the editor's pixels. */
function framePlace() {
  const win = frameWin();
  if (!win || !ready) return null;
  const top = frameTop(win) * scale;
  const boxes = frameSections(win.document).map((el) => {
    const r = el.getBoundingClientRect();
    return { sid: el.getAttribute('data-section-id'), top: r.top * scale, bottom: r.bottom * scale };
  });
  const place = placeFor({ selected: openSection(), scrolled: win.scrollY, boxes, viewTop: top });
  return place && { ...place, next: following(boxes, top) };
}

/* The sections from the one at the top on, so a section the other view lacks (a hidden one) passes to the next. */
function following(boxes, viewTop) {
  const first = placeIn(boxes, viewTop);
  const i = first ? boxes.findIndex((b) => b.sid === first.sid) : -1;
  return i < 0 ? [] : boxes.slice(i).map((b) => b.sid);
}

const candidates = (place) => [place.sid, ...(place.next || [])];

function applyBoardPlace(place) {
  const board = $('#board');
  if (!board) return;
  if (!place) {
    board.scrollTop = 0;
    return;
  }
  const el = candidates(place).map((sid) => $(`.piece[data-sid="${CSS.escape(sid)}"]`, board)).find(Boolean);
  if (!el) return;
  const offset = el.dataset.sid === place.sid ? place.offset : OPEN_OFFSET;
  board.scrollTop += scrollBy(el.getBoundingClientRect().top, board.getBoundingClientRect().top, offset);
}

function applyFramePlace(place) {
  const win = frameWin();
  if (!win) return;
  if (!place) {
    win.scrollTo({ top: 0, behavior: 'instant' });
    return;
  }
  const el = candidates(place).map((sid) => frameSection(win.document, sid)).find(Boolean);
  if (!el) return;
  const offset = (el.getAttribute('data-section-id') === place.sid ? place.offset : OPEN_OFFSET) / scale;
  // The store's header that stays at the top is measured after a first scroll, since it may only stick once scrolled.
  win.scrollTo({ top: win.scrollY + scrollBy(el.getBoundingClientRect().top, 0, offset), behavior: 'instant' });
  const top = frameTop(win);
  if (top) win.scrollTo({ top: win.scrollY + scrollBy(el.getBoundingClientRect().top, top, offset), behavior: 'instant' });
}

function drawMode() {
  const app = $('#app');
  if (app) app.dataset.mode = S.mode;
  $$('.viewswitch__tab').forEach((tab) => {
    const on = tab.dataset.mode === S.mode;
    tab.setAttribute('aria-selected', String(on));
    tab.tabIndex = on ? 0 : -1;
  });
  const board = $('#board');
  const preview = $('#preview');
  if (board) board.hidden = S.mode !== 'board';
  if (preview) preview.hidden = S.mode !== 'preview';
  ui.syncDrawer?.();
}

let switching = 0;

/**
 * Shows Board or Preview. The panel stays as it is; the section at the top
 * of the view left (or the open one) is put at the top of the view shown.
 */
export async function setMode(mode, { focus = false } = {}) {
  if (mode === S.mode || !['board', 'preview'].includes(mode)) return;
  const place = S.mode === 'board' ? boardPlace() : framePlace();
  ui.closePageMenu?.({ focus: false });
  S.mode = mode;
  switching += 1;
  const turn = switching;
  drawMode();
  if (focus) $(`.viewswitch__tab[data-mode="${mode}"]`)?.focus();
  if (mode === 'board') {
    applyBoardPlace(place);
    return;
  }
  fitFrame();
  previewPage();
  if (!shown) return;
  await whenReady();
  if (turn !== switching || S.mode !== 'preview') return;
  // What changed while the board was shown is drawn before the place is taken.
  await run();
  await whenReady();
  if (turn === switching && S.mode === 'preview') applyFramePlace(place);
}

function bindSwitch() {
  const tabs = $$('.viewswitch__tab');
  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => setMode(tab.dataset.mode));
    tab.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      e.preventDefault();
      const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      setMode(next.dataset.mode, { focus: true });
    });
  });
}

export function initPreview() {
  ui.previewPage = previewPage;
  ui.previewOpened = previewPage;
  ui.reloadPreview = reloadPreview;
  ui.setMode = setMode;
  bindSwitch();
  const devices = $('#preview-devices');
  if (devices) {
    radioItems(devices, S.device);
    radioKeys(devices);
    $$('[role="radio"]', devices).forEach((b) => b.addEventListener('click', () => setDevice(b.dataset.value)));
  }
  frame()?.addEventListener('load', onFrameLoad);
  const stage = $('#preview-stage');
  if (stage && typeof ResizeObserver === 'function') new ResizeObserver(() => fitFrame()).observe(stage);
  onChange((op) => follow(op));
  drawMode();
}
