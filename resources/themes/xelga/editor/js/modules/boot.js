/*
 * The boot payload: what the server's shell hands the editor as JSON in
 * <script type="application/json" id="xelga-boot">. Read once, checked, and
 * filled with safe defaults where a key is optional.
 *
 * Shape (keys marked * are optional):
 *   store      { name, url, origin }           url: the published store
 *   user       { id, name, can: { publish, menus_edit } }
 *   csrf       the CSRF token
 *   catalog    { sections: {type: schema}, theme_settings: [groups], font_pairs: [pairs] }
 *   options    { menus: [{value, label}], font_pairs: [...] }   lists for options_source
 *   documents* { handle: document state }      the documents of the first page, so it opens without a request
 *   pages      [{ type, handle, label, kind, url, subject_type*, subject*, subject_count*, groups*, note*, ready* }]
 *              kind 'template' pages have sections; kind 'fixed' pages (checkout, sign in,
 *              account) have no handle and are only styled by theme settings. A page with
 *              subject_type can have special layouts; url is null when the store has no
 *              record to show it on (no product yet, for the product page); subject_count
 *              is how many records of that kind the store has
 *   templates* [{ handle, page_type, label, subject_type, count, assigned, url, subject, ... }]
 *              the special layouts (custom templates)
 *   drafts*    { handle: draft_version } for every document with a draft, loaded or not
 *   store_templates* [{ slug, name, line }]   the starting stores, in the picker's order
 *   products_exist*  whether the store has products yet, for the empty
 *              state of product sections
 *   page*      the handle to open first (default: the first page)
 *   preview    { url, param, section_param, nonce, until }   nonce is empty until the
 *              editor opens the window (POST preview_open), which fills nonce, until and url
 *   routes     { documents_show, documents_draft, documents_discard, publish, revisions_index,
 *                revisions_restore, presence, pick, preview_open, preview_close,
 *                menus_edit, exit }
 *   route_tokens { handle: "__HANDLE__", revision: "0", kind: "__KIND__", slug: "__SLUG__" }
 *   limits     { sections, document_bytes, heartbeat_seconds, autosave_ms }
 *   icons*     the icon sprite's URL, or empty when the shell inlines it
 *   extensions* { name: { title, state } }    every installed extension, state 'on' or 'off'
 *              (one not listed is not installed), so a section or block whose extension is away names it
 *
 * A section schema of an extension carries source and source_label; a page
 * of an extension's page type carries extension: true and group (its name).
 */

export class BootError extends Error {}

export function readBoot(id = 'xelga-boot') {
  const el = document.getElementById(id);
  if (!el) throw new BootError('The editor page has no boot data.');
  let boot;
  try {
    boot = JSON.parse(el.textContent || '{}');
  } catch {
    throw new BootError('The editor boot data is not valid JSON.');
  }
  return normalizeBoot(boot);
}

export function normalizePage(p) {
  const kind = p.kind === 'fixed' ? 'fixed' : 'template';
  const type = String(p.type || p.page_type || p.pageType || String(p.handle || '').split('.')[0] || 'home');
  return {
    type,
    kind,
    handle: kind === 'fixed' ? null : String(p.handle || type),
    label: p.label || type,
    pageType: type,
    url: p.url || null,
    ready: p.ready !== false,
    subjectType: p.subject_type || p.subjectType || null,
    subject: p.subject || null,
    subjectCount: Number(p.subject_count ?? p.subjectCount ?? 0),
    groups: Array.isArray(p.groups) ? p.groups : [],
    note: p.note || '',
    custom: false,
    extension: Boolean(p.extension),
    group: p.group || null,
  };
}

/** One special layout as the server lists it. */
export function normalizeTemplate(t) {
  if (!t || typeof t !== 'object' || !t.handle) return null;
  return {
    handle: String(t.handle),
    pageType: String(t.page_type || String(t.handle).split('.')[0]),
    label: String(t.label || t.handle),
    subjectType: t.subject_type || null,
    count: Number(t.count || 0),
    assigned: Array.isArray(t.assigned) ? t.assigned.map(Number) : [],
    url: t.url || null,
    subject: t.subject || null,
    isPublished: Boolean(t.is_published),
  };
}

export function normalizeBoot(raw) {
  const boot = { ...raw };
  if (!boot.catalog || typeof boot.catalog !== 'object') throw new BootError('The editor was started without the section catalog.');
  if (!boot.routes || typeof boot.routes !== 'object') throw new BootError('The editor was started without its routes.');
  boot.store = { name: '', url: '/', ...(boot.store || {}) };
  boot.preview = { url: boot.store.url || '/', nonce: '', param: 'xelga_preview', ...(boot.preview || {}) };
  boot.option_sources = boot.option_sources || boot.options || {};
  boot.limits = { autosave_ms: 1500, heartbeat_seconds: 30, ...(boot.limits || {}) };
  const pages = Array.isArray(boot.pages) && boot.pages.length ? boot.pages : [{ handle: 'home', label: 'Home page', type: 'home', url: boot.preview.url }];
  boot.pages = pages.map(normalizePage);
  boot.templates = (Array.isArray(boot.templates) ? boot.templates : []).map(normalizeTemplate).filter(Boolean);
  boot.drafts = boot.drafts && typeof boot.drafts === 'object' ? { ...boot.drafts } : {};
  boot.store_templates = (Array.isArray(boot.store_templates) ? boot.store_templates : [])
    .filter((s) => s && typeof s.slug === 'string' && typeof s.name === 'string')
    .map((s) => ({ slug: s.slug, name: s.name, line: String(s.line || '') }));
  boot.products_exist = Boolean(boot.products_exist ?? true);
  boot.extensions = boot.extensions && typeof boot.extensions === 'object' && !Array.isArray(boot.extensions) ? boot.extensions : {};
  boot.page = boot.page || boot.pages.find((p) => p.ready && p.kind === 'template')?.handle || 'home';
  return boot;
}
