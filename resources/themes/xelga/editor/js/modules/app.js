/*
 * Starts the editor: draws the shell, opens this admin's preview window
 * (for the Preview view), loads the documents the current page is drawn
 * from, and wires every change to the layout board, the settings panel,
 * Preview and autosave.
 */

import { $, h, setSprite, toast } from './dom.js';
import {
  S, ctx, ui, pageHandles, doc, addDocument, pageDef, templateOf, isFixed, anyDirty, groupHandle,
} from './state.js';
import { normalizeDocument, isSameOrigin } from './api.js';
import { setCatalog } from './catalog.js';
import { useLayout } from './board/model.js';
import { installIcons } from './icons.js';
import { onChange, resetHistory, LAYOUT_TYPES } from './operations.js';
import { buildShell, setAppState, canvasState, hideCanvasState, showFatal } from './shell.js';
import { initInspector } from './inspector.js';
import { initRevisions } from './revisions.js';
import { initTopbar } from './topbar.js';
import { scheduleSave, flush, reloadDocuments, startPresence, watchLeaving, setFatal, noteOpenedMoves } from './drafts.js';
import { closePopover } from './popover.js';
import { initLayouts } from './layouts.js';
import { initStores } from './stores.js';
import { initBoard } from './board/board.js';
import { initDrawer } from './board/drawer.js';
import { initPicker } from './board/picker.js';
import { initPreview } from './preview.js';
import { initPagePicker } from './page-picker.js';
import { addressQuery, recordAsked, recordTarget } from './page-address.js';

const STRUCTURAL = new Set(['remove', 'insert', 'replace', 'move', 'rename', 'block-add', 'block-remove', 'block-move', 'visible', ...LAYOUT_TYPES]);

function afterChange(op, { source }) {
  // The undo of a starting store applies to the draft as it was applied.
  ui.endApplyNote?.();
  scheduleSave();
  ui.renderBoard();
  // Typing into a field must keep its focus, so a plain value change only
  // refreshes the fields that depend on it. Undo and redo redraw the form.
  if (source !== 'commit' || STRUCTURAL.has(op.t)) {
    ui.renderInspector();
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
 * editor page. Its nonce is what lets Preview show the draft.
 */
async function openPreview() {
  if (!ctx.api.openPreview) return;
  try {
    const opened = await ctx.api.openPreview();
    Object.assign(ctx.boot.preview, { nonce: opened.nonce, until: opened.until });
    if (opened.url && isSameOrigin(opened.url)) ctx.boot.preview.url = opened.url;
    ui.previewOpened?.();
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
      setFatal(error);
      return;
    }
    // The board works without it; only Preview waits.
    toast(`The store preview could not be opened. ${error.message}`, {
      tone: 'error',
      timeout: 10000,
      action: { label: 'Try again', run: () => openPreview() },
    });
  }
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
  hideCanvasState();
  setAppState('loading');
  ui.renderBoard();
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
  if (!S.fatal) setAppState('ready');
  ui.renderBoard();
  ui.renderInspector();
  noteOpenedMoves(groupHandle('template'));
  ui.updateToolbar();
  // Preview follows the page open in the editor.
  ui.previewPage?.();
}

/** The page open in this tab, kept in the address so a reload comes back to it. */
function rememberPage() {
  try {
    const query = addressQuery(window.location.search, S.page);
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`);
  } catch {
    /* The address is a convenience only. */
  }
}

/** The template_param a special layout's preview address carries, so it draws that layout. */
function withLayoutParam(url, handle) {
  const param = ctx.boot?.preview?.template_param;
  if (!url || !param) return url;
  try {
    const next = new URL(url, window.location.href);
    next.searchParams.set(param, handle);
    return next.href;
  } catch {
    return url;
  }
}

/**
 * The page to open for a target: { type, layout?, subject?, url? } with
 * layout a special layout's handle, and subject and url one record to
 * draw the page on (from the page menu's search) instead of the sample.
 */
function resolvePage({ type, layout = null, subject = null, url = null }) {
  const base = pageDef(type);
  if (!base) return null;
  const custom = layout ? templateOf(layout) : null;
  const record = subject && url ? { subject, url } : null;
  if (custom && custom.pageType === base.type) {
    return {
      ...base, handle: custom.handle, layout: true, custom: true,
      url: record ? withLayoutParam(record.url, custom.handle) : custom.url,
      subject: record ? record.subject : custom.subject,
      record: Boolean(record),
    };
  }
  return { ...base, layout: false, ...(record ? { ...record, record: true } : {}) };
}

const samePage = (a, b) => Boolean(a && b) && a.type === b.type && (a.handle || null) === (b.handle || null)
  && Number(a.subject?.id || 0) === Number(b.subject?.id || 0);

/**
 * Opens another page or special layout. Changes are saved first; when that
 * fails, the editor stays where it is. Undo history belongs to the page it
 * was made on, so it starts again.
 *   openLayout  open the layout settings and their chooser (a new layout)
 *   saved       the caller has just saved
 */
async function openPage(target, { openLayout = false, saved = false } = {}) {
  const next = resolvePage(target);
  if (!next) return false;
  if (samePage(next, S.page) && !openLayout) return true;
  closePopover({ restoreFocus: false });
  const wasDirty = anyDirty();
  if (!saved) {
    const ok = await flush();
    if (!ok) {
      toast('Your changes are not saved yet, so the page was not switched. Check the status at the top.', { tone: 'error' });
      return false;
    }
    if (wasDirty) toast('Draft saved before switching pages.');
  }
  S.page = next;
  S.sel = null;
  S.view = { type: 'board' };
  S.sheet = false;
  resetHistory();
  $('#app').dataset.fixed = String(isFixed());
  ui.updatePageButton();
  rememberPage();
  S.loaded = false;
  ui.syncDrawer?.();
  await start();
  if (openLayout && S.loaded) ui.selectLayout?.({ choose: true });
  return true;
}

/*
 * The record the address named (a reload of a record's page) is opened on
 * it again; one the store can no longer show leaves the page on its shared
 * layout, and the address follows.
 */
async function restoreRecord(id) {
  if (!id || !S.page?.subjectType) return;
  try {
    const target = await recordTarget({ page: S.page, id, lookup: (type, rid) => ctx.api.subjectPage(type, rid), templates: S.templates });
    const next = target && resolvePage(target);
    if (next) S.page = next;
  } catch (error) {
    setFatal(error);
  }
  $('#app').dataset.fixed = String(isFixed());
  rememberPage();
  ui.updatePageButton();
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
  // A record opened from the page menu comes back once the store says where it is.
  const asked = recordAsked(window.location.search);
  S.page = resolvePage({ type: query.get('page') || 'home', layout: query.get('layout') })
    || resolvePage({ type: (boot.pages.find((p) => p.handle === boot.page) || boot.pages[0]).type });
  // When the address named a page the picker no longer lists (its
  // extension is away), the fallback page is written back to it, so a
  // reload or a shared link opens what is shown.
  rememberPage();

  const target = mount || document.getElementById('xelga-editor') || document.body;
  buildShell(target, {
    storeName: boot.store.name,
    exitUrl: boot.routes.exit || '/',
  });
  $('#app').dataset.fixed = String(isFixed());

  initPagePicker();
  initTopbar();
  initInspector();
  initRevisions();
  initLayouts();
  initStores();
  initBoard();
  initDrawer();
  initPicker();
  initPreview();
  ui.openPage = openPage;
  ui.rememberPage = rememberPage;
  ui.showFatal = showFatal;
  ui.updatePageButton();
  onChange(afterChange);

  // Leaving through Exit closes this admin's preview window on the server.
  $('#exit-link').addEventListener('click', () => { api.closePreview?.(); });
  if (boot.user?.can?.publish === false) {
    const pub = $('#publish-btn');
    pub.hidden = true;
    pub.dataset.denied = 'true';
  }

  ui.updateToolbar();
  watchLeaving();

  openPreview();
  restoreRecord(asked).then(() => start()).then(() => {
    if (S.loaded) startPresence();
  });

  // For console checks and the browser tests.
  return { state: S, doc, flush };
}
