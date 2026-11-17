/*
 * The one client for the editor's server routes (ARCHITECTURE 7.3) and the
 * media routes of core it borrows. Every call answers with plain data or
 * throws an ApiError whose `kind` says what went wrong, so the rest of the
 * editor never reads an HTTP status:
 *
 *   unauthenticated  401, or 419 when the session or CSRF token expired
 *   forbidden        403
 *   not-found        404
 *   conflict         409, `data` holds the server's current draft
 *   too-large        413
 *   validation       422, `fields` maps a path to its messages
 *   too-many         429, `retryAfter` in seconds
 *   server           5xx or an answer that is not JSON
 *   network          no answer at all (offline, DNS, connection reset)
 *   refused          an answer the editor will not use: a section redraw that
 *                    left the store's origin, was redirected, or did not come
 *                    from the section-only preview response
 *
 * A test harness may pass its own adapter with the same methods instead
 * of this one.
 */

import { MOVE_REASONS } from './board/model.js';

export class ApiError extends Error {
  constructor({ kind, status = 0, message = '', data = null, fields = {}, retryAfter = 0, code = null }) {
    super(message || defaultMessage(kind));
    this.name = 'ApiError';
    this.kind = kind;
    this.code = code;
    this.status = status;
    this.data = data;
    this.fields = fields;
    this.retryAfter = retryAfter;
  }

  /** Worth trying again by itself, without the merchant doing anything. */
  get transient() {
    return this.kind === 'network' || this.kind === 'server' || this.kind === 'too-many';
  }
}

function defaultMessage(kind) {
  switch (kind) {
    case 'unauthenticated': return 'Your admin session has ended. Sign in again to keep editing.';
    case 'forbidden': return 'Your account is not allowed to do this. Ask an admin for theme access.';
    case 'not-found': return 'That could not be found. It may have been deleted.';
    case 'conflict': return 'Someone else saved a newer version.';
    case 'too-large': return 'This page has grown too large to save. Remove a few sections or blocks.';
    case 'validation': return 'Some values were not accepted.';
    case 'too-many': return 'Too many changes in a short time. Waiting a moment before trying again.';
    case 'network': return 'The store could not be reached. Check your connection.';
    case 'refused': return 'The preview answered from somewhere else, so the editor did not use it.';
    default: return 'The server had a problem. Try again in a moment.';
  }
}

function kindFor(status) {
  if (status === 401 || status === 419) return 'unauthenticated';
  if (status === 403) return 'forbidden';
  if (status === 404) return 'not-found';
  if (status === 409) return 'conflict';
  if (status === 413) return 'too-large';
  if (status === 422) return 'validation';
  if (status === 429) return 'too-many';
  return 'server';
}

const sleep = (ms) => new Promise((resolve) => { window.setTimeout(resolve, ms); });

/** Whether an address belongs to the page's own origin. Relative addresses do. */
export function isSameOrigin(url, base = window.location.href) {
  try {
    return new URL(String(url), base).origin === new URL(base).origin;
  } catch {
    return false;
  }
}

/**
 * Fills a route template from the boot payload. The server writes a token
 * where each parameter goes (`route_tokens`, for example __HANDLE__); a
 * revision id stands in as 0 inside "/revisions/0/".
 */
export function fillRoute(template, params = {}, tokens = {}) {
  let url = String(template || '');
  for (const [key, value] of Object.entries(params)) {
    const enc = encodeURIComponent(String(value));
    const token = tokens[key];
    if (key === 'revision' && (token === undefined || /^\d+$/.test(String(token)))) {
      url = url.replace(`/revisions/${token ?? 0}/`, `/revisions/${enc}/`);
    } else if (token) {
      url = url.split(String(token)).join(enc);
    }
    url = url.split(`{${key}}`).join(enc).split(`%7B${key}%7D`).join(enc).split(`__${key.toUpperCase()}__`).join(enc);
  }
  return url;
}

/* ------------------------------------------------------------------------
   Normalizing answers, so the editor reads one shape whatever the wrapping.
   ------------------------------------------------------------------------ */

/* Every editor answer is {success, message, code?, data, errors?}; the data is what callers read. */
const unwrap = (json) => (json && typeof json === 'object' && 'success' in json && 'data' in json ? json.data : json);

const person = (value) => {
  if (!value) return null;
  if (typeof value === 'string') return { id: null, name: value };
  return { id: value.id ?? null, name: value.name || 'Another admin' };
};

/** One document as GET documents/{handle} answers it. */
export function normalizeDocument(raw, handle) {
  const json = unwrap(raw) || {};
  const draft = json.draft ?? null;
  const published = json.published ?? null;
  return {
    handle: json.handle || handle,
    kind: json.kind || (handle === 'settings' ? 'settings' : ['header', 'footer'].includes(handle) ? 'group' : 'template'),
    label: json.label || null,
    pageType: json.page_type || null,
    // The server fills content with the draft, else what is live, else the shipped default.
    content: json.content ?? draft ?? published ?? null,
    published,
    hasDraft: json.has_draft ?? draft !== null,
    isDefault: Boolean(json.is_default),
    draftVersion: Number(json.draft_version ?? 0),
    publishedVersion: Number(json.published_version ?? 0),
    publishedAt: json.published_at || null,
    publishedBy: person(json.published_by),
    draftUpdatedAt: json.draft_updated_at || null,
    draftUpdatedBy: person(json.draft_updated_by),
  };
}

function normalizeRevision(raw) {
  return {
    id: raw.id,
    version: Number(raw.version ?? 0),
    source: raw.source || 'publish',
    createdAt: raw.created_at || null,
    createdBy: person(raw.created_by),
    live: Boolean(raw.is_live),
  };
}

/** The layouts list and draft versions that every layout answer carries. */
function templatesAnswer(json) {
  return {
    templates: Array.isArray(json?.templates) ? json.templates : null,
    drafts: json?.drafts && typeof json.drafts === 'object' ? json.drafts : null,
  };
}

/** A save or restore answer: { document, adjusted, warnings }. */
function savedAnswer(json, handle) {
  const document = normalizeDocument(json.document || {}, handle);
  // A section the server moved to a row of its own is reported with a reason code and the new row's id.
  const adjusted = (Array.isArray(json.adjusted) ? json.adjusted : []).map((a) => ({
    path: a.path,
    value: a.value,
    reason: a.reason || null,
    moved: Object.hasOwn(MOVE_REASONS, a.reason || ''),
    message: a.message || MOVE_REASONS[a.reason] || a.reason || '',
  }));
  return {
    document,
    draftVersion: document.draftVersion,
    updatedAt: document.draftUpdatedAt || new Date().toISOString(),
    adjusted,
    warnings: Array.isArray(json.warnings) ? json.warnings : [],
    // The cleaned content is only taken over when something was corrected.
    content: adjusted.length ? document.content : null,
  };
}

/** An apply or undo answer, with its documents normalized. */
function storeAnswer(json) {
  const documents = {};
  Object.entries(json?.documents || {}).forEach(([handle, raw]) => { documents[handle] = normalizeDocument(raw, handle); });
  return {
    documents,
    undo: typeof json?.undo === 'string' ? json.undo : null,
    basedOn: json?.based_on || null,
    missing: json?.missing || {},
  };
}

/* ------------------------------------------------------------------------
   The HTTP client
   ------------------------------------------------------------------------ */

export function createApi(boot, { fetchImpl } = {}) {
  const doFetch = fetchImpl || window.fetch.bind(window);
  const routes = boot.routes || {};
  const csrf = boot.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';

  const tokens = boot.route_tokens || {};
  // Route names are written either way: documents.show or documents_show.
  const route = (name, params) => {
    const template = routes[name] ?? routes[name.replace(/[.-]/g, '_')];
    if (!template) throw new ApiError({ kind: 'not-found', message: `The editor was started without the "${name}" route.` });
    return fillRoute(template, params, tokens);
  };

  /*
   * `redirect` is passed to fetch. With 'manual' a redirect answers as an
   * opaque response, which is refused here. `verify(response)` may refuse an
   * otherwise good answer before its body is read.
   */
  async function send(method, url, { body, query, keepalive = false, retries = 0, form = null, accept = 'json', redirect = 'follow', verify = null } = {}) {
    const target = new URL(url, window.location.href);
    if (query) {
      Object.entries(query).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') target.searchParams.set(k, String(v));
      });
    }
    const headers = {
      Accept: accept === 'json' ? 'application/json' : 'text/html',
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (method !== 'GET') headers['X-CSRF-TOKEN'] = csrf;
    let payload;
    if (form) payload = form;
    else if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    }

    for (let attempt = 0; ; attempt += 1) {
      let response;
      try {
        response = await doFetch(target.href, {
          method,
          headers,
          body: payload,
          credentials: 'same-origin',
          cache: 'no-store',
          // A keepalive body is capped near 64 KB by browsers; larger saves go normally.
          keepalive: keepalive && (!payload || payload.length < 60000),
          redirect,
        });
      } catch {
        if (attempt < retries) {
          await sleep(600 * (attempt + 1) ** 2);
          continue;
        }
        throw new ApiError({ kind: 'network' });
      }

      if (response.type === 'opaqueredirect' || (verify && response.ok && !verify(response))) {
        throw new ApiError({ kind: 'refused', status: response.status });
      }

      // A redirect to the sign-in page answers 200 with HTML: treat as signed out.
      const type = response.headers.get('Content-Type') || '';
      if (response.redirected && /login|sign-?in/i.test(response.url) && !type.includes('json')) {
        throw new ApiError({ kind: 'unauthenticated', status: 401 });
      }

      if (response.ok) {
        if (accept === 'html') return response.text();
        if (response.status === 204) return {};
        if (!type.includes('json')) throw new ApiError({ kind: 'server', status: response.status, message: 'The server answered with something other than data.' });
        return response.json();
      }

      let data = null;
      try {
        data = type.includes('json') ? await response.json() : null;
      } catch {
        data = null;
      }
      const kind = kindFor(response.status);
      if (kind === 'server' && attempt < retries) {
        await sleep(600 * (attempt + 1) ** 2);
        continue;
      }
      throw new ApiError({
        kind,
        code: data?.code || null,
        status: response.status,
        message: typeof data?.message === 'string' && kind !== 'server' ? data.message : '',
        data: unwrap(data),
        fields: data?.errors && typeof data.errors === 'object' ? data.errors : {},
        retryAfter: Number(response.headers.get('Retry-After')) || 0,
      });
    }
  }

  const storageUrl = String(boot.media?.storage_url || '/storage/').replace(/\/?$/, '/');

  return {
    /** The address of a file on the public disk, from its storage path. */
    mediaUrl(path) {
      const p = String(path || '');
      if (!p) return '';
      if (/^(https?:)?\/\//.test(p) || p.startsWith('/')) return p;
      return `${storageUrl}${p.replace(/^\/+/, '')}`;
    },

    /** Draft and published content, versions, and who saved. */
    async getDocument(handle) {
      const json = await send('GET', route('documents.show', { handle }), { retries: 2 });
      return normalizeDocument(json, handle);
    },

    /** Saves the whole draft. Throws `conflict` when draftVersion is stale. */
    async saveDraft(handle, content, draftVersion, { keepalive = false } = {}) {
      const json = unwrap(await send('PUT', route('documents.draft', { handle }), {
        // This editor keeps a page's rows and sides, so the server lets it save pages that have them.
        body: { content, draft_version: draftVersion, with_rows: true },
        keepalive,
      }));
      return savedAnswer(json, handle);
    },

    /**
     * Deletes the drafts. With versions ({handle: draft_version}) the server
     * refuses (conflict) when any of them moved on since.
     */
    async discard(versions = null) {
      const json = unwrap(await send('DELETE', route('documents.discard'), { body: versions ? { versions } : {} }));
      return { ...json, deletedTemplates: Array.isArray(json?.deleted_templates) ? json.deleted_templates : [] };
    },

    /** Publishes; `versions` must name every document that has a draft. */
    async publish(versions) {
      const json = unwrap(await send('POST', route('publish'), { body: { versions } }));
      return { documents: json.documents || {} };
    },

    async revisions(handle) {
      const json = unwrap(await send('GET', route('revisions.index', { handle }), { retries: 1 }));
      const list = Array.isArray(json) ? json : json.revisions || [];
      return list.map(normalizeRevision);
    },

    /** Copies a revision into the draft; answers like a draft save. */
    async restoreRevision(handle, revision, draftVersion) {
      const json = unwrap(await send('POST', route('revisions.restore', { handle, revision }), { body: { draft_version: draftVersion } }));
      return savedAnswer(json, handle);
    },

    /**
     * Copies a section, or one of its blocks, on the server with new ids and
     * saves the draft. Throws `conflict` when draftVersion is stale.
     * Answers { newId, document, adjusted, warnings }.
     */
    async duplicate(handle, { section, block = null, draftVersion }) {
      const json = unwrap(await send('POST', route('documents.duplicate', { handle }), {
        body: { section, block, draft_version: draftVersion },
      }));
      return { newId: String(json.new_id || ''), ...savedAnswer(json, handle) };
    },

    /* ----------------------------------------------------------------------
       Special layouts. Each answer carries the fresh list of layouts and the
       version of every draft: { templates, drafts, ... }.
       ---------------------------------------------------------------------- */

    async templates() {
      return templatesAnswer(unwrap(await send('GET', route('templates.index'), { retries: 1 })));
    },

    /** A new layout for a page type, as a copy of `from` (a layout of that page). */
    async createTemplate({ pageType, label, from = null }) {
      const json = unwrap(await send('POST', route('templates.store'), { body: { page_type: pageType, label, from } }));
      return {
        ...templatesAnswer(json),
        template: json.template || null,
        document: json.document ? normalizeDocument(json.document, json.document.handle) : null,
        leftOut: Array.isArray(json.left_out) ? json.left_out : [],
      };
    },

    async renameTemplate(handle, label) {
      return templatesAnswer(unwrap(await send('PATCH', route('templates.update', { handle }), { body: { label } })));
    },

    /** Deletes a layout; its products (or categories...) go back to the default. Answers { cleared, ... }. */
    async deleteTemplate(handle) {
      const json = unwrap(await send('DELETE', route('templates.destroy', { handle })));
      return { ...templatesAnswer(json), cleared: Number(json.cleared || 0) };
    },

    /**
     * Gives subjects a layout, or with a null handle takes it away.
     * Answers { assigned, cleared, moved: [{ subject_id, from, from_label }], ... }.
     */
    async assign(subjectType, ids, handle) {
      const json = unwrap(await send('PUT', route('assignments.update'), { body: { subject_type: subjectType, subject_ids: ids, handle } }));
      return {
        ...templatesAnswer(json),
        assigned: Array.isArray(json.assigned) ? json.assigned.map(Number) : [],
        cleared: Array.isArray(json.cleared) ? json.cleared.map(Number) : [],
        moved: Array.isArray(json.moved) ? json.moved : [],
      };
    },

    /* ----------------------------------------------------------------------
       Starting stores
       ---------------------------------------------------------------------- */

    /**
     * Applies a starting store to the draft. `bring` is 'all' (look and home
     * page) or 'look'; `versions` names the draft_version of every document
     * it replaces. Throws `conflict` when one of them moved on. Answers
     * { documents: {handle: document}, undo, basedOn, missing }.
     */
    async applyStore(slug, { bring, versions }) {
      const json = unwrap(await send('POST', route('store_templates_apply', { slug }), { body: { bring, versions } }));
      return storeAnswer(json);
    },

    /** Puts back the drafts from before the last apply. Answers { documents, message }. */
    async undoStore(token) {
      const raw = await send('POST', route('store_templates_undo'), { body: { token } });
      return { ...storeAnswer(unwrap(raw)), message: typeof raw?.message === 'string' ? raw.message : null };
    },

    /**
     * Where each color scheme is used across the store, read from the saved
     * drafts: { schemeId: [{ handle, section, block, row, label }] }.
     */
    async schemeUsage() {
      const json = unwrap(await send('GET', route('schemes.usage'), { retries: 1 }));
      const usage = json?.usage && typeof json.usage === 'object' && !Array.isArray(json.usage) ? json.usage : {};
      const out = {};
      Object.entries(usage).forEach(([id, list]) => {
        out[id] = (Array.isArray(list) ? list : []).filter((u) => u && typeof u.handle === 'string').map((u) => ({
          handle: u.handle,
          section: typeof u.section === 'string' ? u.section : null,
          block: typeof u.block === 'string' ? u.block : null,
          row: typeof u.row === 'string' ? u.row : null,
          label: String(u.label || ''),
        }));
      });
      return out;
    },

    /** Heartbeat. Answers the other admins in the editor now. */
    async presence(handle) {
      const json = unwrap(await send('POST', route('presence'), { body: handle ? { handle } : {} }));
      const list = json.others || json.editors || [];
      return list.map(person).filter(Boolean);
    },

    /**
     * Opens this admin's preview window (a fresh nonce). Loading the editor
     * page does not open one, so a link from elsewhere cannot.
     * Answers { nonce, until, url }.
     */
    async openPreview() {
      const json = unwrap(await send('POST', route('preview_open'), { retries: 1 }));
      const nonce = typeof json?.nonce === 'string' ? json.nonce : '';
      if (!nonce) throw new ApiError({ kind: 'server', message: 'The preview could not be opened.' });
      return { nonce, until: Number(json.until || 0), url: typeof json.url === 'string' ? json.url : '' };
    },

    /** Closes this admin's preview window, on the way out of the editor. */
    closePreview() {
      if (!routes.preview_close) return Promise.resolve();
      return send('DELETE', routes.preview_close, { keepalive: true }).catch(() => {});
    },

    /** Picker lookups by kind. Answers { items, more, editUrl }. */
    async pick(kind, { q = '', page = 1, ids = null } = {}) {
      const json = unwrap(await send('GET', route('pick', { kind }), {
        query: { q, page: page > 1 ? page : null, ids: Array.isArray(ids) ? ids.join(',') : ids },
        retries: 1,
      }));
      const items = Array.isArray(json) ? json : json.results || json.items || [];
      return { items, more: Boolean(json.more || json.has_more), editUrl: json.edit_url || null };
    },

    async mediaBrowse({ path = '', q = '', page = 1, root = '' } = {}) {
      return send('GET', route('media.browse'), { query: { path, q, page: page > 1 ? page : null, root }, retries: 1 });
    },

    async mediaUpload(files, { path = '', root = '' } = {}) {
      const form = new FormData();
      Array.from(files).forEach((file) => form.append('files[]', file));
      if (path) form.append('path', path);
      if (root) form.append('root', root);
      return send('POST', route('media.upload'), { form });
    },

    /**
     * One section's HTML from a full preview render of the current page
     * (ARCHITECTURE 7.5). The draft must be saved first.
     *
     * The HTML is drawn on the board as a section's picture, so it is only
     * taken from the store itself: the address must be on this
     * origin, a redirect (a store redirect rule, a sign-in page) is refused
     * rather than followed, and the answer must be the section-only response
     * for this section. Anything else throws `refused`.
     */
    async renderSection(pageUrl, sectionId) {
      const preview = boot.preview || {};
      if (!isSameOrigin(pageUrl)) throw new ApiError({ kind: 'refused' });
      return send('GET', pageUrl, {
        query: { [preview.section_param || 'xelga_section']: sectionId, [preview.param || 'xelga_preview']: preview.nonce },
        accept: 'html',
        retries: 1,
        redirect: 'manual',
        verify: (response) => !response.redirected
          && isSameOrigin(response.url)
          && response.headers.get('X-Xelga-Section') === String(sectionId),
      });
    },

    /**
     * A whole page of the store as the preview window draws it, from the
     * saved draft, for the layout board's pictures. Taken only from the
     * store itself, as renderSection is: an address on this origin, never a
     * redirect, and an answer from this origin. Anything else throws
     * `refused`. The HTML is cleaned before any of it is shown.
     */
    async renderPage(pageUrl) {
      const preview = boot.preview || {};
      if (!isSameOrigin(pageUrl)) throw new ApiError({ kind: 'refused' });
      return send('GET', pageUrl, {
        query: { [preview.param || 'xelga_preview']: preview.nonce },
        accept: 'html',
        retries: 1,
        redirect: 'manual',
        verify: (response) => !response.redirected && isSameOrigin(response.url),
      });
    },
  };
}
