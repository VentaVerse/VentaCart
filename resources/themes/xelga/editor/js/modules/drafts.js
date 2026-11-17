/*
 * Saving, publishing and the shared draft (ARCHITECTURE 5.6 and 7.6).
 *
 * Every operation marks its document changed. Changed documents are saved
 * 1.5 seconds after the last change, at once before a server re-render, when
 * the page is switched and when the tab is hidden. A save sends the
 * draft_version it was based on; a 409 means someone else saved first and
 * the merchant chooses whose version to keep. Nothing is ever overwritten
 * silently.
 */

import { S, ctx, ui, doc, isDirty, addDocument, docLabel, pageHandles, ensureShape, schemes, anyDirty, anySaving, noteDraft, draftVersions, templateOf } from './state.js';
import { detachDocument, reapply, resetHistory, rulesFor } from './operations.js';
import { normalize } from './board/model.js';
import { normalizeDocument } from './api.js';
import { h, toast, announce } from './dom.js';
import { choose } from './popover.js';
import { formatTime, plural, readability, clone } from './util.js';

const autosaveMs = () => Number(ctx.boot?.limits?.autosave_ms) || 1500;
const RETRY_MAX_MS = 30000;
let timer = 0;

/* ------------------------------------------------------------------------
   Saving
   ------------------------------------------------------------------------ */

export function scheduleSave() {
  window.clearTimeout(timer);
  timer = window.setTimeout(() => { flush(); }, autosaveMs());
  ui.updateToolbar();
}

/**
 * Saves changed documents now. Answers true when every one of them is saved
 * (or had nothing to save).
 */
export async function flush({ handles = null, keepalive = false } = {}) {
  window.clearTimeout(timer);
  const targets = (handles || Object.keys(S.docs)).map(doc).filter(Boolean);
  const results = await Promise.all(targets.map((d) => saveDoc(d, { keepalive })));
  return results.every(Boolean);
}

async function saveDoc(d, { keepalive = false } = {}) {
  if (S.fatal) return false;
  if (d.conflict) return false;
  if (d.saving) {
    await d.saving;
    // The live record may have been replaced while this save was in flight.
    const current = doc(d.handle);
    return current && current !== d ? saveDoc(current, { keepalive }) : saveDoc(d, { keepalive });
  }
  if (!isDirty(d)) return !d.error || d.error.kind !== 'validation';

  window.clearTimeout(d.retryTimer);
  const json = JSON.stringify(d.content);
  const seq = S.seq;
  d.saving = (async () => {
    try {
      const res = await ctx.api.saveDraft(d.handle, JSON.parse(json), d.draftVersion, { keepalive });
      d.draftVersion = res.draftVersion;
      d.hasDraft = true;
      noteDraft(d.handle, d.draftVersion);
      d.savedJson = json;
      d.savedSeq = seq;
      d.lastSavedAt = res.updatedAt;
      d.lastSavedBy = S.user;
      d.failures = 0;
      d.error = null;
      if (Object.keys(d.fieldErrors).length) {
        d.fieldErrors = {};
        ui.refreshFieldErrors();
      }
      if (res.adjusted.length || res.content) adopt(d, res, json);
      return true;
    } catch (error) {
      return failed(d, error);
    }
  })();
  ui.updateToolbar();
  const ok = await d.saving;
  d.saving = null;
  ui.updateToolbar();
  return ok;
}

/* Paths from the server look like `content.sections.hero-k2p9.settings.overlay`. */
const pathParts = (path) => String(path || '').replace(/^content\./, '').split('.').filter(Boolean);

function setPath(target, parts, value) {
  let node = target;
  for (let i = 0; i < parts.length - 1; i += 1) {
    const key = Array.isArray(node) ? Number(parts[i]) : parts[i];
    if (node[key] === undefined || node[key] === null || typeof node[key] !== 'object') return false;
    node = node[key];
  }
  const last = parts[parts.length - 1];
  if (value === undefined) delete node[last];
  else node[last] = clone(value);
  return true;
}

/**
 * The server corrected some values (clamped, defaulted, stripped). Take its
 * values into the document, the saved copy and the preview.
 */
function adopt(d, res, savedJson) {
  const untouched = JSON.stringify(d.content) === savedJson;
  if (res.content && untouched) {
    d.content = ensureShape(res.content, d.kind);
    d.savedJson = JSON.stringify(d.content);
  } else {
    const saved = JSON.parse(d.savedJson);
    res.adjusted.forEach((item) => {
      const parts = pathParts(item.path);
      // A moved section names its new row, not a value to write.
      if (!parts.length || item.moved) return;
      setPath(d.content, parts, item.value);
      setPath(saved, parts, item.value);
    });
    // The server moved sections out of places they do not fit; the row
    // model makes the same moves here, since both normalize alike.
    if (res.adjusted.some((item) => item.moved)) {
      const rules = rulesFor(d.handle);
      d.content = ensureShape(normalize(d.content, rules).content, d.kind);
      d.savedJson = JSON.stringify(res.content ? ensureShape(res.content, d.kind) : normalize(saved, rules).content);
    } else {
      d.savedJson = JSON.stringify(saved);
    }
  }
  const sections = new Set();
  res.adjusted.forEach((item) => {
    const parts = pathParts(item.path);
    if (parts[0] === 'sections' && parts[1]) sections.add(parts[1]);
  });
  ui.renderOutline();
  ui.renderInspector();
  ui.runEffects?.([...sections].map((sid) => ({ render: 'replace', handle: d.handle, sectionId: sid })), { saved: true });
  // Columns and sides are drawn by the server, so a moved section shows after the preview loads again.
  if (d.handle === 'settings' || res.adjusted.some((item) => item.moved)) ui.reloadPreview?.();
  const first = res.adjusted.find((a) => a.message)?.message;
  if (res.adjusted.length) {
    toast(first
      ? `Saved with a correction: ${first}`
      : `Saved. ${plural(res.adjusted.length, 'value was', 'values were')} corrected to fit what the store allows.`, { timeout: 7000 });
  }
}

function failed(d, error) {
  const kind = error?.kind || 'server';
  if (kind === 'conflict') {
    d.conflict = normalizeDocument(error.data?.current || error.data?.document || error.data || {}, d.handle);
    d.error = { kind, message: error.message };
    resolveConflict(d);
    return false;
  }
  if (kind === 'unauthenticated' || kind === 'forbidden') {
    setFatal(error);
    return false;
  }
  if (kind === 'validation') {
    d.fieldErrors = error.fields || {};
    d.error = { kind, message: error.message };
    ui.refreshFieldErrors();
    ui.renderOutline();
    toast(`${docLabel(d.handle)} was not saved: ${firstFieldMessage(d.fieldErrors) || error.message}`, { tone: 'error', timeout: 8000 });
    return false;
  }
  if (kind === 'not-found' && templateOf(d.handle)) {
    // Someone deleted this special layout while it was being edited.
    d.error = { kind, message: error.message };
    ui.layoutGone?.(d.handle);
    return false;
  }
  if (kind === 'too-large' || kind === 'not-found') {
    d.error = { kind, message: error.message };
    toast(`${docLabel(d.handle)} was not saved. ${error.message}`, { tone: 'error', timeout: 9000 });
    return false;
  }
  // Network, server trouble and rate limits: keep the changes and try again.
  d.failures += 1;
  d.error = { kind, message: error.message };
  const wait = error.retryAfter ? error.retryAfter * 1000 : Math.min(RETRY_MAX_MS, 2000 * 2 ** (d.failures - 1));
  window.clearTimeout(d.retryTimer);
  d.retryTimer = window.setTimeout(() => saveDoc(doc(d.handle) || d), wait);
  if (d.failures === 1 && kind !== 'network') toast(error.message, { tone: 'error' });
  return false;
}

const firstFieldMessage = (fields) => {
  const first = Object.values(fields || {})[0];
  return Array.isArray(first) ? first[0] : first || '';
};

/** Tries every document that failed for a passing reason again, now. */
export function retryNow() {
  Object.values(S.docs).forEach((d) => {
    if (d.error && ['network', 'server', 'too-many'].includes(d.error.kind)) {
      window.clearTimeout(d.retryTimer);
      saveDoc(d);
    }
  });
}

/* ------------------------------------------------------------------------
   Conflict: someone else saved first
   ------------------------------------------------------------------------ */

export async function resolveConflict(d) {
  if (d.conflictOpen) return;
  d.conflictOpen = true;
  const theirs = d.conflict;
  const who = theirs.draftUpdatedBy?.name || 'Another admin';
  const when = theirs.draftUpdatedAt ? formatTime(theirs.draftUpdatedAt) : 'a moment ago';
  const label = docLabel(d.handle);
  ui.updateToolbar();
  announce(`${who} saved ${label} while you were editing.`);
  const choice = await choose({
    title: `${who} also changed ${label}`,
    iconName: 'user',
    required: true,
    body: [
      h('p', { text: `They saved their version at ${when}, while you were editing. Your latest changes to ${label} are not saved yet.` }),
      h('ul', { class: 'dialog__list', role: 'list' },
        h('li', null, h('strong', { text: 'Load their changes' }), ' to see what they did. Your own changes are kept in this visit, and you can apply them again on top.'),
        h('li', null, h('strong', { text: 'Replace with mine' }), ' to save your version over theirs. Their changes to this part are lost.')),
    ],
    actions: [
      { label: 'Replace with mine', value: 'mine' },
      { label: 'Load their changes', value: 'theirs', primary: true },
    ],
  });
  d.conflictOpen = false;

  if (choice === 'mine') {
    d.draftVersion = theirs.draftVersion;
    d.conflict = null;
    d.error = null;
    const ok = await saveDoc(d);
    if (ok) toast(`Your version of ${label} is saved. It replaced theirs.`, { tone: 'success' });
    return;
  }

  const unsaved = detachDocument(d.handle, d.savedSeq);
  addDocument(theirs);
  ui.renderOutline();
  ui.renderThemeNav();
  ui.renderInspector();
  ui.updateToolbar();
  ui.reloadPreview?.();
  if (!unsaved.length) {
    toast(`Their changes to ${label} are loaded.`, { tone: 'success' });
    return;
  }
  toast(`Their changes to ${label} are loaded. You had ${plural(unsaved.length, 'change')} of your own.`, {
    timeout: 20000,
    action: {
      label: 'Apply mine again',
      run: async () => {
        await ui.whenPreviewReady?.();
        const count = reapply(unsaved);
        toast(count ? `${plural(count, 'change')} applied again. Undo works for each one.` : 'Your changes no longer fit their version, so none were applied.');
      },
    },
  });
}

/* ------------------------------------------------------------------------
   Publish, discard and earlier versions
   ------------------------------------------------------------------------ */

/** Loads the given documents from the server, replacing what is in memory. */
export async function reloadDocuments(handles = ['settings', ...pageHandles()]) {
  const list = await Promise.all(handles.map((handle) => ctx.api.getDocument(handle)));
  list.forEach(addDocument);
  return list;
}

/** Schemes that fail the readability rule, worded for the merchant. */
export function unreadableSchemes() {
  return schemes().map((scheme) => {
    const low = readability(scheme.colors).filter((p) => !p.ok);
    return low.length ? { id: scheme.id, label: scheme.label || scheme.id, pairs: low } : null;
  }).filter(Boolean);
}

const capital = (t) => (t ? t[0].toUpperCase() + t.slice(1) : t);

function refusalFrom(error) {
  const data = error.data || {};
  const failures = data.failures || data.readability;
  let list = [];
  if (Array.isArray(failures)) {
    const byScheme = new Map();
    failures.forEach((r) => {
      const id = r.scheme || r.id;
      if (!byScheme.has(id)) byScheme.set(id, { id, label: r.label || id, pairs: [] });
      byScheme.get(id).pairs.push({ label: capital(r.pair_label || r.pair || 'text'), ratio: Number(r.ratio) || 0, min: Number(r.min) || 4.5 });
    });
    list = [...byScheme.values()];
  }
  // Whatever the server's wording, name the schemes the same rule finds here.
  if (!list.length) list = unreadableSchemes();
  const other = Object.entries(error.fields || {})
    .filter(([path]) => !/scheme/i.test(path))
    .map(([, messages]) => (Array.isArray(messages) ? messages[0] : messages));
  return { message: error.message, schemes: list, other };
}

/**
 * Publishes every document that has a draft. Answers { ok } or
 * { ok: false, refusal } when the server refused (readability, structure).
 */
export async function publish() {
  const saved = await flush();
  if (!saved) {
    return { ok: false, error: { message: 'Publishing waits until every change is saved. Check the status at the top and try again.' } };
  }
  // Every draft, also of pages not opened in this visit: the server refuses
  // a publish that leaves one out, so nothing goes live unseen by accident.
  const versions = draftVersions();
  if (!Object.keys(versions).length) return { ok: false, error: { message: 'There is nothing new to publish.' } };

  try {
    const res = await ctx.api.publish(versions);
    const now = new Date().toISOString();
    Object.values(S.docs).forEach((d) => {
      if (versions[d.handle] === undefined) return;
      const info = res.documents?.[d.handle] || {};
      d.hasDraft = false;
      // Publishing clears the draft, which moves its version on.
      if (info.draft_version !== undefined) d.draftVersion = Number(info.draft_version);
      d.publishedVersion = Number(info.published_version ?? d.publishedVersion + 1);
      d.publishedAt = info.published_at || now;
      d.publishedBy = S.user;
    });
    Object.keys(versions).forEach((handle) => noteDraft(handle, null));
    S.templates.forEach((t) => { if (versions[t.handle] !== undefined) t.isPublished = true; });
    S.publishedFlash = Date.now();
    ui.updateToolbar();
    ui.reloadPreview?.();
    return { ok: true };
  } catch (error) {
    if (error.kind === 'conflict') {
      // Drafts of pages not open here: take their current versions, so the
      // next Publish names them as they are now.
      await ui.refreshLayouts?.();
      const handles = Array.isArray(error.data?.handles) ? error.data.handles : Object.keys(versions).filter((h) => doc(h));
      const fresh = await Promise.all(handles.map((handle) => ctx.api.getDocument(handle).catch(() => null)));
      fresh.filter(Boolean).forEach((theirs) => {
        const d = doc(theirs.handle);
        if (d && theirs.draftVersion !== d.draftVersion) {
          d.conflict = theirs;
          resolveConflict(d);
        }
      });
      return { ok: false, error: { message: 'Someone else changed the draft just now, so nothing was published. Choose whose version to keep, then publish again.' } };
    }
    if (error.kind === 'validation') return { ok: false, refusal: refusalFrom(error) };
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') setFatal(error);
    return { ok: false, error };
  }
}

/** Throws away every draft and goes back to the published store. */
export async function discard() {
  window.clearTimeout(timer);
  Object.values(S.docs).forEach((d) => window.clearTimeout(d.retryTimer));
  await Promise.all(Object.values(S.docs).map((d) => d.saving).filter(Boolean));
  // Only what this editor has seen is thrown away: a newer save by someone else stops it.
  const versions = draftVersions();
  Object.values(S.docs).forEach((d) => { versions[d.handle] = d.draftVersion; });
  let deleted = [];
  try {
    deleted = (await ctx.api.discard(versions)).deletedTemplates || [];
    // A layout never published goes with the draft (it has nothing live).
    ui.forgetLayouts?.(deleted);
    S.draftVersions = {};
    await reloadDocuments([...new Set(['settings', ...pageHandles(), ...Object.keys(S.docs)])]);
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') setFatal(error);
    else if (error.kind === 'conflict') {
      await ui.refreshLayouts?.();
      await reloadDocuments(Object.keys(S.docs)).catch(() => {});
      resetHistory();
      ui.renderOutline();
      ui.renderInspector();
      ui.updateToolbar();
      ui.reloadPreview?.();
      toast('Someone else saved changes just now, so nothing was thrown away. Their changes are loaded; look, then throw away again if you still want to.', { tone: 'error', timeout: 10000 });
    } else toast(`The draft was not thrown away. ${error.message}`, { tone: 'error', timeout: 8000 });
    return false;
  }
  resetHistory();
  S.sel = null;
  ui.renderOutline();
  ui.renderThemeNav();
  ui.renderInspector();
  ui.updateToolbar();
  ui.reloadPreview?.();
  toast(deleted.length
    ? `Draft thrown away. ${plural(deleted.length, 'special layout')} that ${deleted.length === 1 ? 'was' : 'were'} never published ${deleted.length === 1 ? 'was' : 'were'} deleted with it.`
    : 'Draft thrown away. The editor shows the published store again.', { tone: 'success', timeout: deleted.length ? 8000 : 4200 });
  return true;
}

/** Copies an earlier version into the draft. Nothing goes live until Publish. */
export async function restoreRevision(handle, revision) {
  window.clearTimeout(doc(handle)?.retryTimer);
  await doc(handle)?.saving;
  let fresh;
  try {
    fresh = await ctx.api.restoreRevision(handle, revision.id, doc(handle)?.draftVersion ?? 0);
  } catch (error) {
    if (error.kind === 'unauthenticated' || error.kind === 'forbidden') setFatal(error);
    else if (error.kind === 'conflict' && doc(handle)) {
      const d = doc(handle);
      d.conflict = normalizeDocument(error.data || {}, handle);
      resolveConflict(d);
    } else toast(`That version could not be restored. ${error.message}`, { tone: 'error', timeout: 8000 });
    return false;
  }
  detachDocument(handle, Infinity);
  addDocument(fresh.document);
  if (fresh.adjusted.length) toast(`Restored with ${plural(fresh.adjusted.length, 'correction')} to fit the theme as it is now.`);
  if (S.sel?.kind === 'section') S.sel = null;
  ui.renderOutline();
  ui.renderThemeNav();
  ui.renderInspector();
  ui.updateToolbar();
  ui.reloadPreview?.();
  toast(`The version from ${formatTime(revision.createdAt)} is now the draft of ${docLabel(handle)}. Publish to make it live.`, { tone: 'success', timeout: 7000 });
  return true;
}

/* ------------------------------------------------------------------------
   Session, presence and leaving
   ------------------------------------------------------------------------ */

export function setFatal(error) {
  if (S.fatal) return;
  S.fatal = { kind: error.kind, message: error.message };
  window.clearTimeout(timer);
  Object.values(S.docs).forEach((d) => window.clearTimeout(d.retryTimer));
  ui.showFatal?.();
  ui.updateToolbar();
}

let presenceTimer = 0;

async function heartbeat() {
  if (S.fatal) return;
  try {
    const editors = await ctx.api.presence(S.page?.handle || null);
    const me = S.user?.id;
    const seen = new Set();
    S.presence = editors.filter((e) => {
      if ((me !== undefined && me !== null && e.id === me) || seen.has(e.id ?? e.name)) return false;
      seen.add(e.id ?? e.name);
      return true;
    });
  } catch (error) {
    if (error.kind === 'unauthenticated') setFatal(error);
    // Presence is advice only; any other failure just leaves the line as it was.
  }
  ui.updateToolbar();
}

export function startPresence() {
  window.clearInterval(presenceTimer);
  heartbeat();
  presenceTimer = window.setInterval(heartbeat, (Number(ctx.boot?.limits?.heartbeat_seconds) || 30) * 1000);
}

export function watchLeaving() {
  window.addEventListener('beforeunload', (e) => {
    if (S.fatal) return;
    if (anyDirty() || anySaving()) {
      flush({ keepalive: true });
      e.preventDefault();
      e.returnValue = '';
    }
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && anyDirty()) flush({ keepalive: true });
  });
  window.addEventListener('online', () => {
    S.online = true;
    ui.updateToolbar();
    retryNow();
  });
  window.addEventListener('offline', () => {
    S.online = false;
    ui.updateToolbar();
  });
}
