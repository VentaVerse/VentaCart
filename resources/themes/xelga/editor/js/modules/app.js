/*
 * Starts the editor: draws the shell, connects the preview bridge, loads the
 * documents the current page is drawn from, and wires every change to the
 * panels, the layout board, the preview and autosave.
 */

import { $, setSprite, toast } from './dom.js';
import {
  S, ctx, ui, pageHandles, doc, addDocument, pageDef, templateOf, pageTitle, isFixed, anyDirty, isShared, scopeOfHandle, SHARED_PARAM,
} from './state.js';
import { normalizeDocument, isSameOrigin } from './api.js';
import { setCatalog } from './catalog.js';
import { useLayout } from './board/model.js';
import { installIcons } from './icons.js';
import { onChange, resetHistory, LAYOUT_TYPES } from './operations.js';
import { createBridge } from './bridge.js';
import { buildShell, panelsLoading } from './shell.js';
import { initPreview, loadFrame, setDevice, setAppState, canvasState, hideCanvasState } from './preview.js';
import { initOutline } from './outline.js';
import { initAdd } from './add-section.js';
import { initInspector } from './inspector.js';
import { initRevisions } from './revisions.js';
import { initTopbar } from './topbar.js';
import { scheduleSave, flush, reloadDocuments, startPresence, watchLeaving, setFatal } from './drafts.js';
import { setNav, isPhone, closeSheet } from './selection.js';
import { closePopover } from './popover.js';
import { initLayouts } from './layouts.js';
import { initStores } from './stores.js';
import { initNeeds } from './needs.js';
import { initBoard } from './board/board.js';
import { h } from './dom.js';

const STRUCTURAL = new Set(['remove', 'insert', 'move', 'rename', 'block-add', 'block-remove', 'block-move', 'visible', ...LAYOUT_TYPES]);

/*
 * Undo and redo show the list the change belongs to, so it is never made out
 * of sight. Theme settings have no list, so they leave it as it is.
 */
function followChange(op) {
  if (op.t === 'theme' || op.t === 'color' || !op.doc) return;
  if (ui.setScope(scopeOfHandle(op.doc))) ui.flashPicker();
}

function afterChange(op, { source }) {
  // The undo of a starting store applies to the draft as it was applied.
  ui.endApplyNote?.();
  if (source !== 'commit') followChange(op);
  scheduleSave();
  ui.renderOutline();
  // Typing into a field must keep its focus, so a plain value change only
  // refreshes the fields that depend on it. Undo and redo redraw the form.
  if (source !== 'commit' || STRUCTURAL.has(op.t)) {
    ui.renderInspector();
    if (S.nav === 'theme') ui.renderThemeNav();
  } else {
    ui.refreshVisibility();
  }
  ui.updateToolbar();
}

let preloaded = null;

async function loadPageDocuments() {
  const handles = [...new Set(['settings', ...pageHandles()])];
  // The shell prints the first page's documents; use them once, then ask the server.
  if (preloaded && handles.every((h) => preloaded[h])) {
    handles.forEach((h) => addDocument(normalizeDocument(preloaded[h], h)));
    preloaded = null;
    return;
  }
  preloaded = null;
  await reloadDocuments(handles);
}

/*
 * The preview window is opened here, by a POST, rather than by loading the
 * editor page; the frame loads once the nonce is known.
 */
async function openPreview() {
  if (!ctx.api.openPreview) {
    loadFrame();
    return;
  }
  setAppState('loading');
  try {
    const opened = await ctx.api.openPreview();
    Object.assign(ctx.boot.preview, { nonce: opened.nonce, until: opened.until });
    if (opened.url && isSameOrigin(opened.url)) ctx.boot.preview.url = opened.url;
    // The board's pictures and Open store preview both need the window.
    ui.updatePreviewLink?.();
    ui.renderBoard?.();
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    setAppState('error');
    canvasState({
      iconName: 'alert',
      title: 'The preview could not be opened',
      text: `${error.message} Nothing was changed.`,
      actions: [h('button', { type: 'button', class: 'btn btn--primary', onclick: () => openPreview() }, 'Try again')],
    });
    return;
  }
  loadFrame();
}

function documentsFailed(error) {
  if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
    setFatal(error);
    return;
  }
  setAppState('error');
  canvasState({
    iconName: 'alert',
    title: 'The theme could not be loaded',
    text: `${error.message} Nothing was changed.`,
    actions: [h('button', { type: 'button', class: 'btn btn--primary', onclick: () => start() }, 'Try again')],
  });
}

async function start() {
  // A page with nothing to preview keeps its explanation over the frame.
  if (S.page?.url) hideCanvasState();
  panelsLoading();
  try {
    await loadPageDocuments();
  } catch (error) {
    if (error.kind === 'not-found' && S.page?.layout) {
      // The layout was deleted by someone else since the editor opened.
      ui.layoutGone?.(S.page.handle);
      return;
    }
    documentsFailed(error);
    return;
  }
  S.loaded = true;
  if (S.frameReady) setAppState('ready');
  else if (!S.page?.url) setAppState('empty');
  ui.renderOutline();
  ui.renderThemeNav();
  ui.renderInspector();
  ui.updateToolbar();
  ui.updateDock();
}

/** The page and list open in this tab, kept in the address so a reload comes back to them. */
function rememberPage() {
  try {
    const q = new URLSearchParams(window.location.search);
    q.delete('page');
    q.delete('layout');
    q.delete('edit');
    if (S.page?.type && S.page.type !== 'home') q.set('page', S.page.type);
    if (S.page?.layout) q.set('layout', S.page.handle);
    if (isShared()) q.set('edit', SHARED_PARAM);
    const query = q.toString();
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`);
  } catch {
    /* The address is a convenience only. */
  }
}

/** The page to open for a target: { type, layout? } with layout a special layout's handle. */
function resolvePage({ type, layout = null }) {
  const base = pageDef(type);
  if (!base) return null;
  const custom = layout ? templateOf(layout) : null;
  if (custom && custom.pageType === base.type) {
    return { ...base, handle: custom.handle, layout: true, custom: true, url: custom.url, subject: custom.subject };
  }
  return { ...base, layout: false };
}

const samePage = (a, b) => Boolean(a && b) && a.type === b.type && (a.handle || null) === (b.handle || null);

/**
 * Opens another page or special layout. Changes are saved first; when that
 * fails, the editor stays where it is. Undo history belongs to the page it
 * was made on, so it starts again.
 *   url             the address the preview already shows (a followed link)
 *   frameNavigated  the preview is already on that page
 *   openLayout      select the Layout row and open its chooser (a new layout)
 *   saved           the caller has just saved
 *   scope           the list to show: 'page', or 'shared' for the header and footer
 */
async function openPage(target, { url = null, frameNavigated = false, openLayout = false, saved = false, scope = 'page' } = {}) {
  const next = resolvePage(target);
  if (!next) return false;
  if (url) next.url = url;
  if (samePage(next, S.page) && !openLayout) {
    if (frameNavigated) S.page = { ...S.page, url: url || S.page.url };
    // The page already in the preview: only the list changes.
    ui.setScope(scope);
    return true;
  }
  closePopover({ restoreFocus: false });
  const wasDirty = anyDirty();
  if (!saved) {
    const ok = await flush();
    if (!ok) {
      toast('Your changes are not saved yet, so the page was not switched. Check the status at the top.', { tone: 'error' });
      // The preview went ahead on its own: bring it back to the page being edited.
      if (frameNavigated) loadFrame();
      return false;
    }
    if (wasDirty) toast('Draft saved before switching pages.');
  }
  S.page = next;
  S.scope = next.kind === 'fixed' ? 'page' : scope;
  S.sel = null;
  S.view = { type: 'board' };
  S.open.clear();
  S.addAt = null;
  S.renaming = null;
  resetHistory();
  $('#app').dataset.fixed = String(isFixed());
  ui.updatePageButton();
  rememberPage();
  setNav('sections');
  if (isPhone()) closeSheet();
  S.loaded = false;
  // A preview that followed a link drew the page before the save finished:
  // load it again so it shows the saved draft.
  if (!frameNavigated || wasDirty) loadFrame();
  await start();
  if (openLayout && S.loaded) ui.selectLayout?.({ choose: true });
  return true;
}

/*
 * The merchant followed a link inside the preview (Alt and click). The new
 * page has already said `ready`; open its template (or the special layout
 * it uses), or say it has none.
 */
function onNavigated(msg) {
  S.navigatedAt = Date.now();
  const page = msg.page || {};
  const layout = page.handle ? templateOf(page.handle) : null;
  const target = layout ? { type: layout.pageType, layout: layout.handle } : pageDef(page.type) ? { type: page.type } : null;
  if (!target) {
    toast('This page has no layout to edit. The preview shows it as shoppers see it.', {
      timeout: 8000,
      action: { label: `Back to ${pageTitle()}`, run: () => loadFrame() },
    });
    return;
  }
  const next = resolvePage(target);
  if (samePage(next, S.page)) {
    S.page = { ...S.page, url: msg.url || S.page.url };
    // A link to the page shown while editing the header and footer goes back to the page's list.
    if (ui.setScope('page')) return;
    toast(`Already editing ${pageTitle()}.`);
    return;
  }
  openPage(target, { url: msg.url || null, frameNavigated: true });
}

/*
 * The preview may open somewhere other than the page asked for: the store
 * sends an empty checkout to the cart, or a signed-in page to sign in.
 * Say so, so a change in the panel is not looked for on the wrong page.
 */
function onFrameReady(msg) {
  window.setTimeout(() => {
    if (Date.now() - (S.navigatedAt || 0) < 2000) return;
    const shown = msg.page?.type;
    if (!shown || !S.page || shown === S.page.type) return;
    const label = pageDef(shown)?.label || 'another page';
    toast(`The store showed ${label === 'another page' ? label : `the ${label.toLowerCase()} page`} instead of ${pageTitle()}. Shoppers are sent there too in this case.`, { timeout: 8000 });
  }, 400);
}

/**
 * Starts the editor.
 *   boot   the normalized boot payload (see boot.js)
 *   api    createApi(boot), or an adapter with the same methods
 *   mount  the element to draw into (default: #xelga-editor or body)
 */
export function startEditor({ boot, api, mount = null }) {
  ctx.boot = boot;
  ctx.api = api;
  preloaded = boot.documents && typeof boot.documents === 'object' ? boot.documents : null;
  setSprite(boot.icons || '');
  if (!boot.icons) installIcons();
  setCatalog(boot.catalog, boot.option_sources || {});
  useLayout(boot.layout);

  S.store = boot.store;
  S.user = boot.user || null;
  S.pages = boot.pages;
  S.templates = boot.templates || [];
  S.draftVersions = { ...(boot.drafts || {}) };
  // The page in the address (a reload), else the one the server names.
  const query = new URLSearchParams(window.location.search);
  S.page = resolvePage({ type: query.get('page') || 'home', layout: query.get('layout') })
    || resolvePage({ type: (boot.pages.find((p) => p.handle === boot.page) || boot.pages[0]).type });
  S.scope = query.get('edit') === SHARED_PARAM && S.page.kind !== 'fixed' ? 'shared' : 'page';
  // When the address named a page the picker no longer lists (its
  // extension is away), the fallback page is written back to it, so a
  // reload or a shared link opens what is shown.
  rememberPage();

  const target = mount || document.getElementById('xelga-editor') || document.body;
  buildShell(target, {
    storeName: boot.store.name,
    exitUrl: boot.routes.exit || '/',
    storeUrl: boot.store.url || '/',
  });
  $('#app').dataset.fixed = String(isFixed());

  ctx.bridge = createBridge({ frame: $('#frame'), origin: boot.preview.origin });

  initTopbar();
  initOutline();
  initAdd();
  initInspector();
  initRevisions();
  initLayouts();
  initStores();
  initPreview({ onNavigated });
  initNeeds();
  initBoard();
  ctx.bridge.on('ready', onFrameReady);
  ui.openPage = openPage;
  ui.rememberPage = rememberPage;
  ui.updatePageButton();
  onChange(afterChange);

  // Leaving through Exit closes this admin's preview window on the server.
  $('#exit-link').addEventListener('click', () => { api.closePreview?.(); });
  if (boot.user?.can?.publish === false) {
    const pub = $('#publish-btn');
    pub.hidden = true;
    pub.dataset.denied = 'true';
  }

  setDevice('desktop');
  ui.updateDock();
  ui.updateToolbar();
  watchLeaving();

  openPreview();
  start().then(() => {
    if (S.loaded) startPresence();
  });

  // For console checks and the browser tests.
  return { state: S, doc, flush };
}
