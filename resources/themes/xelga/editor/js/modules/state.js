/*
 * Editor state. Documents are held in the shape the server stores
 * (ARCHITECTURE 5.2): a settings document with values and schemes, and group
 * and template documents with an order list plus a map of sections, each
 * with its own block order and block map.
 *
 * The page being edited is drawn from three documents: the header group,
 * the page's template and the footer group. Section ids are unique across
 * those three, because a section is found by its id alone (the board, the
 * drawer, the store's section-only answer).
 */

import { clone } from './util.js';
import { sectionDef, blockDef, themeSetting } from './catalog.js';

/** Shared services, set once at start: api, boot payload. */
export const ctx = { api: null, boot: null };

/**
 * Render hooks. Each UI module registers its own, so modules can ask for a
 * redraw without importing each other.
 */
export const ui = {
  renderBoard() {},
  renderInspector() {},
  updateToolbar() {},
  refreshVisibility() {},
  refreshFieldErrors() {},
};

export const S = {
  store: { name: '' },
  user: null,
  pages: [], // page types the picker lists (boot.js normalizePage)
  page: null, // the page open now: a page from `pages`, or a special layout of one ({ ..., handle, layout })
  templates: [], // special layouts (boot.js normalizeTemplate)
  draftVersions: {}, // handle -> draft_version of every document with a draft, loaded or not
  docs: {}, // handle -> document record (see addDocument)
  loaded: false, // documents for the current page are in memory
  history: [], // operations made in this visit
  pointer: 0, // how many of history are applied
  seq: 0, // increasing number given to each operation
  // What is selected: { kind: 'section', sid, bid } | { kind: 'theme', group, scheme? }
  // | { kind: 'stores' }; its settings show in the panel.
  sel: null,
  // What the board marks and the panel shows: {type: 'board'} (nothing
  // selected: the page's own settings), {type: 'section', sid} (follows the
  // selection), {type: 'row', rowId}, {type: 'theme'} or {type: 'revisions'}.
  view: { type: 'board' },
  // On a phone the panel rests as one line; true while the page's own settings are pulled up.
  sheet: false,
  // 'board' or 'preview': which of the two views of the page is shown.
  mode: 'board',
  // The width Preview shows the store at: 'computer', 'tablet' or 'phone'.
  device: 'computer',
  renaming: null, // { sid, bid } while a name is being edited in the drawer
  presence: [], // other admins in the editor
  online: true,
  fatal: null, // { kind, message } when the editor cannot continue
  publishedFlash: 0, // time of the last publish, for the top bar status
};

export const GROUPS = [
  { key: 'header', label: 'Header' },
  { key: 'template', label: 'Page sections' },
  { key: 'footer', label: 'Footer' },
];

/* ------------------------------------------------------------------------
   Documents
   ------------------------------------------------------------------------ */

export function emptyContent(kind) {
  return kind === 'settings'
    ? { schema: 1, values: {}, schemes: [] }
    : { schema: 1, order: [], sections: {} };
}

/** Fills missing parts so the editor can rely on the shape. */
export function ensureShape(content, kind) {
  const c = content && typeof content === 'object' ? clone(content) : emptyContent(kind);
  c.schema = c.schema || 1;
  if (kind === 'settings') {
    c.values = c.values && typeof c.values === 'object' ? c.values : {};
    c.schemes = Array.isArray(c.schemes) ? c.schemes : [];
    c.schemes.forEach((s) => { s.colors = s.colors || {}; });
    return c;
  }
  c.sections = c.sections && typeof c.sections === 'object' ? c.sections : {};
  c.order = Array.isArray(c.order) ? c.order.filter((id) => c.sections[id]) : Object.keys(c.sections);
  // Rows and sides are kept as the server sent them: the row model reads
  // them, and saving without them would flatten the page. Anything that is
  // not a list of rows or a map of sides is dropped.
  if ('rows' in c && !Array.isArray(c.rows)) delete c.rows;
  if ('sides' in c && (!c.sides || typeof c.sides !== 'object' || Array.isArray(c.sides))) delete c.sides;
  Object.values(c.sections).forEach((s) => {
    s.settings = s.settings && typeof s.settings === 'object' && !Array.isArray(s.settings) ? s.settings : {};
    s.blocks = s.blocks && typeof s.blocks === 'object' && !Array.isArray(s.blocks) ? s.blocks : {};
    s.block_order = Array.isArray(s.block_order) ? s.block_order.filter((id) => s.blocks[id]) : Object.keys(s.blocks);
    s.disabled = Boolean(s.disabled);
    Object.values(s.blocks).forEach((b) => {
      b.settings = b.settings && typeof b.settings === 'object' && !Array.isArray(b.settings) ? b.settings : {};
      b.disabled = Boolean(b.disabled);
    });
  });
  return c;
}

/** Stores a document as the server answered it (see normalizeDocument in api.js). */
export function addDocument(remote) {
  const content = ensureShape(remote.content, remote.kind);
  const existing = S.docs[remote.handle];
  const doc = {
    handle: remote.handle,
    kind: remote.kind,
    label: remote.label,
    pageType: remote.pageType,
    content,
    savedJson: JSON.stringify(content),
    savedSeq: S.seq,
    hasDraft: remote.hasDraft,
    draftVersion: remote.draftVersion,
    publishedVersion: remote.publishedVersion,
    publishedAt: remote.publishedAt,
    publishedBy: remote.publishedBy,
    lastSavedAt: remote.hasDraft ? remote.draftUpdatedAt : null,
    lastSavedBy: remote.draftUpdatedBy,
    saving: null, // the promise of a save in flight
    failures: 0,
    retryTimer: 0,
    error: null, // { kind, message } of the last failed save
    fieldErrors: {}, // path -> messages, from a 422 answer
    // What the server moved when it read the page, said once when the page opens.
    adjusted: Array.isArray(remote.adjusted) ? remote.adjusted : [],
  };
  if (existing?.retryTimer) window.clearTimeout(existing.retryTimer);
  S.docs[remote.handle] = doc;
  noteDraft(remote.handle, remote.hasDraft ? remote.draftVersion : null);
  return doc;
}

/** Remembers a document's draft version (null: it has no draft), for Publish and Discard. */
export function noteDraft(handle, version) {
  if (version === null || version === undefined) delete S.draftVersions[handle];
  else S.draftVersions[handle] = Number(version);
}

/**
 * What Publish and Discard must name: every document with a draft and the
 * version the editor knows, whether or not its page was opened this visit.
 */
export function draftVersions() {
  const out = { ...S.draftVersions };
  Object.values(S.docs).forEach((d) => {
    if (d.hasDraft) out[d.handle] = d.draftVersion;
    else delete out[d.handle];
  });
  return out;
}

export const doc = (handle) => (handle ? S.docs[handle] || null : null);
export const settingsDoc = () => doc('settings');
export const isDirty = (d) => Boolean(d) && JSON.stringify(d.content) !== d.savedJson;
export const dirtyDocs = () => Object.values(S.docs).filter(isDirty);
export const anyDirty = () => dirtyDocs().length > 0;
export const anySaving = () => Object.values(S.docs).some((d) => d.saving);

/** Documents that would change on Publish: saved drafts and unsaved edits. */
export const publishable = () => [
  ...Object.values(S.docs).filter((d) => d.hasDraft || isDirty(d)),
  // Drafts of pages not opened in this visit.
  ...Object.keys(S.draftVersions).filter((handle) => !S.docs[handle]).map((handle) => ({ handle })),
];

export function groupHandle(group) {
  if (group === 'header') return 'header';
  if (group === 'footer') return 'footer';
  if (S.page?.kind === 'fixed') return null;
  return S.page?.handle || 'home';
}

/** A page with a fixed layout (checkout, sign in, account): only theme settings change it. */
export const isFixed = () => S.page?.kind === 'fixed';

/** The documents that draw the current page, in page order. */
export const pageHandles = () => (isFixed() ? ['header', 'footer'] : ['header', groupHandle('template'), 'footer']);

/* ------------------------------------------------------------------------
   Pages and special layouts
   ------------------------------------------------------------------------ */

export const pageDef = (type) => S.pages.find((p) => p.type === type) || null;
export const templateOf = (handle) => S.templates.find((t) => t.handle === handle) || null;
export const layoutsOf = (type) => S.templates.filter((t) => t.pageType === type);
/** The special layout open now, or null on a page type's own template. */
export const currentLayout = () => (S.page?.layout ? templateOf(S.page.handle) : null);

/**
 * What the editor calls the page open now, in its heading, the panel and
 * the window's title: a special layout by its name ("Gift guide"), a record
 * opened from the page menu by its own name ("Laptops"), the shared layout
 * of a type of record as "Category layout", any other page by its name.
 */
export function pageTitle(page = S.page) {
  if (!page) return '';
  if (page.record && page.subject?.name) return page.subject.name;
  if (page.layout) return templateOf(page.handle)?.label || `${page.label} special layout`;
  return page.subjectType ? `${page.label} layout` : page.label;
}

export function docLabel(handle) {
  if (handle === 'settings') return 'Theme settings';
  if (handle === 'header') return 'Header';
  if (handle === 'footer') return 'Footer';
  const layout = templateOf(handle);
  if (layout) return `${pageDef(layout.pageType)?.label || layout.pageType}: ${layout.label}`;
  const page = S.pages.find((p) => p.handle === handle);
  return page?.label || doc(handle)?.label || handle;
}

/* ------------------------------------------------------------------------
   Sections and blocks
   ------------------------------------------------------------------------ */

/** Where a section lives: { handle, group, data } or null. */
export function locate(sid) {
  for (const group of GROUPS) {
    const handle = groupHandle(group.key);
    const d = doc(handle);
    const data = d?.content?.sections?.[sid];
    if (data) return { handle, group: group.key, data, doc: d };
  }
  return null;
}

export const sec = (sid) => locate(sid)?.data || null;

export const blk = (sid, bid) => sec(sid)?.blocks?.[bid] || null;

/** Sections of one group in order, each as { id, ...data }. */
export function groupList(group) {
  const d = doc(groupHandle(group));
  if (!d?.content?.order) return [];
  return d.content.order.map((id) => ({ id, ...d.content.sections[id] })).filter((s) => s.type);
}

export function blockList(sid) {
  const s = sec(sid);
  if (!s) return [];
  return s.block_order.map((id) => ({ id, ...s.blocks[id] })).filter((b) => b.type);
}

/** Every id used on this page: sections and blocks of all three documents. */
export function usedIds() {
  const used = new Set();
  pageHandles().forEach((handle) => {
    const c = doc(handle)?.content;
    if (!c?.sections) return;
    Object.entries(c.sections).forEach(([id, s]) => {
      used.add(id);
      Object.keys(s.blocks || {}).forEach((b) => used.add(b));
    });
  });
  return used;
}

export function settingsFor(sid, bid) {
  const s = sec(sid);
  if (!s) return [];
  if (bid) {
    const b = s.blocks[bid];
    return b ? blockDef(s.type, b.type)?.settings || [] : [];
  }
  return sectionDef(s.type)?.settings || [];
}

/**
 * A setting's value: what the merchant set, else the schema default. For a
 * block, a key only its section has (such as the layout a block field's
 * visible_if looks at) reads the section's value, and a setting that moved
 * from the section to its blocks reads the value the section saved before,
 * as the server does when it draws the page.
 */
export function valueOf(sid, bid, key) {
  const target = bid ? blk(sid, bid) : sec(sid);
  if (!target) return undefined;
  if (key === 'visible') return !target.disabled;
  const stored = target.settings?.[key];
  if (stored !== undefined) return stored;
  if (bid) {
    const own = settingsFor(sid, bid).some((d) => d.key === key);
    const sectionHas = settingsFor(sid, null).some((d) => d.key === key);
    if (!own && sectionHas) return valueOf(sid, null, key);
    const carried = sec(sid)?.settings?.[key];
    if (own && !sectionHas && carried !== undefined) return clone(carried);
  }
  return clone(settingsFor(sid, bid).find((d) => d.key === key)?.default);
}

export const labelOf = (sid, bid) => {
  const target = bid ? blk(sid, bid) : sec(sid);
  if (!target) return '';
  if (target.label) return target.label;
  const def = bid ? blockDef(sec(sid)?.type, target.type) : sectionDef(target.type);
  return def?.label || target.type;
};

/* ------------------------------------------------------------------------
   Theme settings
   ------------------------------------------------------------------------ */

export function themeValue(key) {
  const stored = settingsDoc()?.content?.values?.[key];
  if (stored !== undefined) return stored;
  return clone(themeSetting(key)?.default);
}

/** Schemes as stored: [{ id, label, colors }]. */
export const schemes = () => settingsDoc()?.content?.schemes || [];
