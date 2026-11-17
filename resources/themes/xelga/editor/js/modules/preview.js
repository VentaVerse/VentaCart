/*
 * The store preview: loading the frame, the device sizes, applying the
 * effects of each change, and server re-renders (ARCHITECTURE 7.5) for
 * changes the browser cannot draw by itself: a new section, a duplicate, a
 * new block, or a setting that needs data from the store.
 */

import { $, $$, h, icon, toast } from './dom.js';
import { S, ctx, ui, locate, sec, labelOf, isDirty, doc, blockList, isFixed } from './state.js';
import { flush, setFatal } from './drafts.js';
import { select, openSheet, isPhone, radioItems } from './selection.js';
import { onChange } from './operations.js';

const DEVICES = {
  desktop: { label: 'Desktop', width: 1280 },
  tablet: { label: 'Tablet', width: 834, height: 1194 },
  phone: { label: 'Phone', width: 390, height: 844 },
};

const LOAD_TIMEOUT_MS = 15000;

const frame = () => $('#frame');
const app = () => $('#app');

let loadTimer = 0;
let queued = []; // messages held while the preview loads
let readyWaiters = [];
let reloadedForNonce = false;
let retriedSilentFrame = false;
let silentTimer = 0;
const SILENT_FRAME_MS = 3500;

export function setAppState(state) {
  app().dataset.state = state;
}

/* ------------------------------------------------------------------------
   Loading
   ------------------------------------------------------------------------ */

/** The address the frame loads: the page, marked as an editor preview. */
function frameUrl(page) {
  const url = new URL(page?.url || ctx.boot.preview?.url || '/', window.location.href);
  const { nonce, param } = ctx.boot.preview || {};
  if (nonce) url.searchParams.set(param || 'xelga_preview', nonce);
  return url.href;
}

/* What a page about one record needs before it can be shown. */
const NO_SUBJECT = {
  product: ['package', 'There is no product to show yet', 'This page shows one product. Add a product in the admin and switch it on, then open this page again.'],
  category: ['layers', 'There is no category to show yet', 'This page shows one category. Add a category in the admin and switch it on, then open this page again.'],
  brand: ['tag', 'There is no brand to show yet', 'This page shows one brand. Add a brand in the admin and switch it on, then open this page again.'],
  page: ['align-left', 'There is no page to show yet', 'This page shows one information page. Add one in the admin and switch it on, then open this page again.'],
  blog_post: ['align-left', 'There is no blog post to show yet', 'This page shows one post. Publish a post in the blog, or install the blog in Extensions, then open this page again.'],
};

/** A page the store cannot show (no product yet, no blog): say why instead of loading nothing. */
function showNoPreview() {
  S.frameReady = false;
  frame().src = 'about:blank';
  const [iconName, title, text] = NO_SUBJECT[S.page?.subjectType] || ['alert', 'This page cannot be shown on this store', 'The store has nothing to show on this page yet, or the part of the store it belongs to is not installed.'];
  setAppState(S.loaded ? 'empty' : 'loading');
  canvasState({ iconName, title, text: `${text} ${isFixed() ? 'Theme settings still apply to it.' : 'Its sections can still be edited on the left.'}` });
}

export function loadFrame() {
  frameLoads += 1;
  S.frameReady = false;
  queued = [];
  window.clearTimeout(loadTimer);
  if (!S.page?.url) {
    showNoPreview();
    return;
  }
  setAppState('loading');
  hideCanvasState();
  frame().src = frameUrl(S.page);
  loadTimer = window.setTimeout(() => {
    if (!S.frameReady) showLoadError();
  }, LOAD_TIMEOUT_MS);
}

/** Saves, then reloads the preview so it draws what the server has. */
export async function reloadPreview() {
  if (S.fatal) return;
  await flush();
  loadFrame();
}

export function whenPreviewReady() {
  if (S.frameReady) return Promise.resolve();
  return new Promise((resolve) => readyWaiters.push(resolve));
}

function onReady(msg) {
  window.clearTimeout(loadTimer);
  // A preview that answers without draft content lost its preview session
  // (an old browser without Sec-Fetch-Dest after a redirect): load it again
  // with the nonce, once.
  if (msg.preview === false && !reloadedForNonce) {
    reloadedForNonce = true;
    loadFrame();
    return;
  }
  S.frameReady = true;
  retriedSilentFrame = false;
  window.clearTimeout(silentTimer);
  S.frameEpoch += 1;
  S.pageData = msg.pageData || null;
  S.frameUrl = msg.url || null;
  // The preview names the messages it can apply; anything else reloads it.
  S.supports = Array.isArray(msg.supports) ? msg.supports.filter((t) => typeof t === 'string') : null;
  hookFrameKeys();
  if (S.fatal) return;
  if (S.loaded) setAppState('ready');
  hideCanvasState();
  // Bring the preview up to date with anything changed while it loaded.
  const pending = queued;
  queued = [];
  pending.forEach((m) => ctx.bridge.post(m.type, m));
  if (S.sel?.kind === 'section' && sec(S.sel.sid)) {
    ctx.bridge.post('select', { sectionId: S.sel.sid, blockId: S.sel.bid || null });
  }
  checkDrift();
  readyWaiters.forEach((fn) => fn());
  readyWaiters = [];
}

/** Warns in the console when the preview's sections and the documents disagree. */
function checkDrift() {
  const list = S.pageData?.sections;
  if (!Array.isArray(list) || !S.loaded) return;
  // A page followed by a link shows another template until the editor switches to it.
  if (S.pageData?.page?.handle && S.pageData.page.handle !== S.page?.handle) return;
  const shown = new Set(list.map((s) => s.id));
  const missing = ['header', S.page?.handle, 'footer']
    .flatMap((handle) => doc(handle)?.content?.order || [])
    .filter((id) => !doc(locate(id)?.handle)?.content?.sections?.[id]?.disabled && !shown.has(id));
  if (missing.length && !Object.values(S.docs).some(isDirty)) {
    console.warn('The preview does not show these sections from the draft:', missing);
  }
}

/* Keyboard shortcuts also work while focus is inside the preview. */
function hookFrameKeys() {
  try {
    const win = frame().contentWindow;
    if (!win || win.__editorKeys) return;
    win.__editorKeys = true;
    win.document.addEventListener('keydown', (e) => ui.onKeydown?.(e));
  } catch {
    /* A preview from another origin keeps its own keys. */
  }
}

/* ------------------------------------------------------------------------
   Canvas states over the frame
   ------------------------------------------------------------------------ */

export function canvasState({ iconName, title, text, actions = [] }) {
  const box = $('#canvas-state');
  box.replaceChildren(h('div', { class: 'canvas-state__inner' },
    h('div', { class: 'canvas-state__icon' }, icon(iconName)),
    h('h2', { text: title }),
    h('p', { text }),
    actions.length ? h('div', { class: 'canvas-state__actions' }, ...actions) : null));
  box.hidden = false;
}

export function hideCanvasState() {
  $('#canvas-state').hidden = true;
}

function showLoadError() {
  setAppState('error');
  canvasState({
    iconName: 'alert',
    title: 'The preview did not load',
    text: 'The store page took too long to answer. Check your connection, then reload the preview. Your saved draft is not affected.',
    actions: [h('button', { type: 'button', class: 'btn btn--primary', onclick: () => loadFrame() }, 'Reload preview')],
  });
}

export function showFatal() {
  const f = S.fatal;
  if (!f) return;
  setAppState('fatal');
  const signedOut = f.kind === 'unauthenticated';
  canvasState({
    iconName: signedOut ? 'user' : 'shield-check',
    title: signedOut ? 'Your session has ended' : 'You do not have access to the theme editor',
    text: signedOut
      ? 'Sign in again to keep editing. Changes that were not saved yet are kept in this tab until you leave it.'
      : 'Your account cannot change themes. Ask an admin to give your user group the Themes edit permission.',
    actions: signedOut
      ? [h('a', { class: 'btn btn--primary', href: ctx.boot.routes?.login || window.location.href, target: '_blank', rel: 'noopener' }, 'Sign in again'),
        h('button', { type: 'button', class: 'btn btn--secondary', onclick: () => window.location.reload() }, 'Reload the editor')]
      : [h('a', { class: 'btn btn--secondary', href: ctx.boot.routes?.exit || '/' }, 'Back to admin')],
  });
}

/* ------------------------------------------------------------------------
   Effects of a change
   ------------------------------------------------------------------------ */

/*
 * What the preview can apply by itself, as it said in `ready`. A preview
 * that cannot take a message (an older theme build) gets the draft saved and
 * is reloaded instead, so the preview always matches.
 */
const BASIC = ['highlight', 'select', 'field', 'setting', 'theme-setting', 'move', 'remove', 'restore'];
const supports = (type) => (S.supports || ctx.boot.preview?.supports || BASIC).includes(type);
/* Messages the preview may skip without drawing anything wrong. */
const COSMETIC = new Set(['rename']);

let frameLoads = 0;
const removedIn = new Map(); // section id -> the frame load it was removed in
let reloadTimer = 0;

function reloadSoon() {
  window.clearTimeout(reloadTimer);
  reloadTimer = window.setTimeout(() => reloadPreview(), 120);
}

function postOrQueue(message) {
  if (!supports(message.type)) {
    if (!COSMETIC.has(message.type)) reloadSoon();
    return;
  }
  // A section removed before the last reload is no longer held by the preview.
  if (message.type === 'restore' && removedIn.has(message.sectionId) && removedIn.get(message.sectionId) !== frameLoads) {
    removedIn.delete(message.sectionId);
    reloadSoon();
    return;
  }
  if (message.type === 'remove') removedIn.set(message.sectionId, frameLoads);
  if (!S.frameReady) {
    queued.push(message);
    return;
  }
  ctx.bridge.post(message.type, message);
}

/*
 * Server re-renders run one at a time, in order. Each queued redraw of a
 * section gets a number; an answer that arrives after a newer redraw of the
 * same section was asked for, or after the preview loaded again, is stale
 * and dropped, so the preview never steps back to an older draft.
 */
let renderQueue = [];
let rendering = false;
let renderSeq = 0;
const latestRender = new Map(); // section id -> number of the newest redraw asked for

export function runEffects(effects) {
  effects.forEach((effect) => {
    // A render effect is named after the message it ends in (insert, duplicate, replace, block-insert).
    if (effect.render && !supports(effect.render)) {
      reloadSoon();
    } else if (effect.render) {
      // One pending redraw per section is enough; it draws the latest draft.
      if (effect.render === 'replace' && renderQueue.some((e) => e.render === 'replace' && e.sectionId === effect.sectionId)) return;
      effect.seq = ++renderSeq;
      latestRender.set(effect.sectionId, effect.seq);
      renderQueue.push(effect);
      S.rendering.add(effect.sectionId);
    } else {
      postOrQueue(effect);
    }
  });
  if (renderQueue.length) {
    ui.renderOutline();
    ui.updateBusy?.();
    drainRenders();
  }
}

async function drainRenders() {
  if (rendering) return;
  rendering = true;
  while (renderQueue.length) {
    const effect = renderQueue.shift();
    await renderOne(effect);
    if (!renderQueue.some((e) => e.sectionId === effect.sectionId)) S.rendering.delete(effect.sectionId);
    ui.renderOutline();
    ui.updateBusy?.();
  }
  rendering = false;
}

/**
 * The markup of one block, cut from a section's HTML. The HTML comes from the
 * store's own preview render; a template element parses it inertly (no
 * scripts run, nothing loads) and only the block's markup is passed on.
 */
function blockHtml(html, blockId) {
  const tpl = document.createElement('template');
  tpl.innerHTML = html;
  return tpl.content.querySelector(`[data-block-id="${CSS.escape(blockId)}"]`)?.outerHTML || null;
}

/** What the preview records about a section: the editor's names, so the outline and the outlines in the preview agree. */
export function sectionPageData(sid) {
  const at = locate(sid);
  if (!at) return null;
  return {
    id: sid,
    type: at.data.type,
    group: at.group,
    label: labelOf(sid),
    disabled: Boolean(at.data.disabled),
    blocks: blockList(sid).map((b) => ({ id: b.id, type: b.type, label: labelOf(sid, b.id), disabled: Boolean(b.disabled) })),
  };
}

const sleep = (ms) => new Promise((resolve) => { window.setTimeout(resolve, ms); });

async function fetchSection(sectionId) {
  try {
    return await ctx.api.renderSection(S.frameUrl || frameUrl(S.page), sectionId);
  } catch (error) {
    // A burst of redraws: wait as long as the server asks, once.
    if (error.kind !== 'too-many') throw error;
    await sleep(Math.min(30, error.retryAfter || 5) * 1000);
    return ctx.api.renderSection(S.frameUrl || frameUrl(S.page), sectionId);
  }
}

async function renderOne(effect) {
  const at = locate(effect.sectionId);
  if (!at || (effect.blockId && !at.data.blocks[effect.blockId])) return;
  const handle = S.page?.handle;
  try {
    const saved = await flush({ handles: [effect.handle] });
    if (!saved) throw new Error('The draft could not be saved, so the preview cannot draw this yet.');
    await whenPreviewReady();
    const epoch = S.frameEpoch;
    const html = await fetchSection(effect.sectionId);
    const now = locate(effect.sectionId);
    if (!now || S.page?.handle !== handle) return;
    // The preview loaded again meanwhile, from the saved draft: it already shows this.
    if (S.frameEpoch !== epoch) return;
    // A newer redraw of this section is on its way; this answer is out of date.
    if (effect.render === 'replace' && latestRender.get(effect.sectionId) !== effect.seq) return;
    const data = sectionPageData(effect.sectionId);
    const index = now.doc.content.order.indexOf(effect.sectionId);
    if (effect.render === 'insert') {
      postOrQueue({ type: 'insert', sectionId: effect.sectionId, group: now.group, index, html, data });
      if (effect.op) effect.op.shown = true;
    } else if (effect.render === 'duplicate') {
      postOrQueue({ type: 'duplicate', sectionId: effect.source, newId: effect.sectionId, index, html, data });
      if (effect.op) effect.op.shown = true;
    } else if (effect.render === 'block-insert') {
      const part = blockHtml(html, effect.blockId);
      const blockIndex = now.data.block_order.indexOf(effect.blockId);
      if (part) postOrQueue({ type: 'block-insert', sectionId: effect.sectionId, blockId: effect.blockId, index: blockIndex, html: part, sectionHtml: html, data });
      else postOrQueue({ type: 'replace', sectionId: effect.sectionId, html, data });
      if (effect.op) effect.op.shown = true;
    } else {
      postOrQueue({ type: 'replace', sectionId: effect.sectionId, html, data });
    }
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    // The page in the preview does not draw this section (it moved on, or
    // lost its preview window): loading the page again shows the draft.
    if (error.kind === 'not-found' || error.kind === 'conflict') {
      reloadSoon();
      return;
    }
    // The redraw did not come from the store's section-only answer (it was
    // redirected, or answered from elsewhere). Its HTML is never used; the
    // whole preview loads again instead, which shows the saved draft.
    if (error.kind === 'refused') {
      toast('The preview was loaded again to show this change.', { timeout: 5000 });
      reloadSoon();
      return;
    }
    const name = labelOf(effect.sectionId) || 'This section';
    toast(`${name} could not be drawn in the preview. ${error.kind === 'network' ? 'The store could not be reached.' : error.message || ''}`.trim(), {
      tone: 'error',
      timeout: 9000,
      action: { label: 'Reload preview', run: () => reloadPreview() },
    });
  }
}

/* ------------------------------------------------------------------------
   Pointing at fields and sections
   ------------------------------------------------------------------------ */

export function highlight(sid, bid = null) {
  if (!S.frameReady) return;
  ctx.bridge.post('highlight', { sectionId: sid || null, blockId: bid || null });
}

/** Outlines in the preview the elements one field changes. */
export function pointField(sid, bid, key) {
  if (!S.frameReady) return;
  ctx.bridge.post('field', { sectionId: sid, blockId: bid || null, key });
}

export function unpointField() {
  if (!S.frameReady) return;
  ctx.bridge.post('field', { sectionId: null, blockId: null, key: null });
  ctx.bridge.post('highlight', { sectionId: null, blockId: null });
}

/* ------------------------------------------------------------------------
   Device size. The preview is a real viewport, width and height: anything
   sized to the window (a banner of 44svh, a full-screen slideshow, sticky
   parts) draws exactly as on that screen. Desktop is the merchant's own
   browser window, so the preview matches opening the store in this same
   window; tablet and phone are common device sizes. The viewport is then
   scaled down, never up, to fit the canvas in both directions and centred,
   and the page scrolls inside it.
   ------------------------------------------------------------------------ */

/** The viewport a device stands for, in CSS pixels. */
export function viewportFor(device) {
  const d = DEVICES[device] || DEVICES.desktop;
  if (d.height) return { width: d.width, height: d.height };
  const w = window.innerWidth || d.width;
  const h = window.innerHeight || Math.round(d.width * 0.625);
  // A window narrower than the desktop layout still previews the desktop
  // layout, at that window's proportions.
  if (w < d.width) return { width: d.width, height: Math.round((d.width * h) / w) };
  return { width: w, height: h };
}

export function setDevice(device) {
  S.device = DEVICES[device] ? device : 'desktop';
  app().dataset.device = S.device;
  const group = $('#device-toggle');
  radioItems(group, S.device);
  fitFrame();
}

export function fitFrame() {
  const canvas = $('#canvas');
  const wrap = $('#frame-wrap');
  const el = frame();
  const caption = $('#frame-size');
  if (!canvas || !wrap || !el) return;
  if (isPhone()) {
    // The editor itself on a phone: the preview fills the space, unscaled.
    Object.assign(wrap.style, { width: '', height: '' });
    Object.assign(el.style, { width: '', height: '', transform: '', transformOrigin: '' });
    caption.textContent = '';
    return;
  }
  const { width, height } = viewportFor(S.device);
  const cs = getComputedStyle(canvas);
  const availW = canvas.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
  // The caption under the frame takes its line out of the height.
  if (!caption.textContent) caption.textContent = ' ';
  const capCs = getComputedStyle(caption);
  const captionH = caption.offsetHeight + parseFloat(capCs.marginTop) + parseFloat(capCs.marginBottom);
  const availH = canvas.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom) - Math.max(0, captionH);
  const scale = Math.max(0.05, Math.min(1, availW / width, availH / height));
  Object.assign(wrap.style, {
    width: `${Math.floor(width * scale)}px`,
    height: `${Math.floor(height * scale)}px`,
  });
  Object.assign(el.style, {
    width: `${width}px`,
    height: `${height}px`,
    transform: scale < 1 ? `scale(${scale})` : '',
    transformOrigin: '0 0',
  });
  const pct = Math.round(scale * 100);
  caption.textContent = `${DEVICES[S.device].label} preview, ${width} x ${height}${pct < 100 ? `, shown at ${pct}%` : ''}`;
}

/* ------------------------------------------------------------------------
   Messages from the preview
   ------------------------------------------------------------------------ */

export function initPreview({ onNavigated }) {
  const bridge = ctx.bridge;
  bridge.on('ready', onReady);
  bridge.on('page-data', (msg) => {
    S.pageData = msg.pageData || S.pageData;
    checkDrift();
  });
  bridge.on('select', (msg) => {
    if (!S.loaded || S.fatal) return;
    if (!msg.sectionId) return;
    if (isFixed()) {
      toast(`The ${S.page.label.toLowerCase()} page keeps a fixed layout. Its colors, fonts and buttons follow Theme settings.`);
      return;
    }
    if (!sec(msg.sectionId)) {
      toast('That part of the page is not part of the theme layout, so it has no settings here.');
      return;
    }
    select(msg.sectionId, msg.blockId || null, { fromPreview: true, field: msg.key || null });
    if (isPhone()) openSheet('inspector');
  });
  bridge.on('navigated', (msg) => onNavigated?.(msg));
  bridge.on('duplicated', () => { /* the fresh page data that follows is enough */ });
  bridge.on('error', (msg) => {
    const name = msg.sectionId ? labelOf(msg.sectionId) : '';
    toast(`${name || 'A section'} could not be shown in the preview${msg.message ? `: ${msg.message}` : '.'}`, { tone: 'error', timeout: 8000 });
  });

  ui.reloadPreview = reloadPreview;
  ui.runEffects = runEffects;
  ui.whenPreviewReady = whenPreviewReady;
  ui.showFatal = showFatal;

  onChange((op, { effects }) => runEffects(effects));

  new ResizeObserver(() => fitFrame()).observe($('#canvas'));
  // Desktop follows the editor window's own size.
  window.addEventListener('resize', () => fitFrame());
  $$('#device-toggle [role="radio"]').forEach((b) => b.addEventListener('click', () => setDevice(b.dataset.device)));
  frame().addEventListener('load', () => {
    // A frame that loads without a bridge (an error page, a page outside the
    // store, or the published page because the preview request lost a race)
    // never says ready. Try once more by itself; if that also stays silent,
    // the load timeout shows the error with its Reload button.
    hookFrameKeys();
    window.clearTimeout(silentTimer);
    silentTimer = window.setTimeout(() => {
      if (S.frameReady || S.fatal || retriedSilentFrame) return;
      retriedSilentFrame = true;
      loadFrame();
    }, SILENT_FRAME_MS);
  });
}
