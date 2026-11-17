/*
 * Cleans markup from the server before the editor shows it: the layout
 * board draws each section's picture from a draft page.
 *
 * The markup is the store's own preview answer, fetched from this origin,
 * and is parsed inertly (a template element or DOMParser). It is still
 * cleaned with allowlists before it is used, so that markup which slipped
 * past every other check cannot run: elements that can run or load code
 * are removed, every inline handler is dropped, and an address is kept only
 * when its scheme is one a storefront link or image needs.
 */

export const MAX_HTML = 2000000;

// Removed with everything inside them. SVG names are matched in lower case.
const DROP_TAGS = new Set([
  'script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'portal', 'link', 'style', 'base', 'meta',
  'noscript', 'animate', 'animatemotion', 'animatetransform', 'set', 'foreignobject', 'handler', 'listener',
]);

// Attributes that hold one address, or a list of them (srcset).
const URL_ATTRS = new Set([
  'href', 'src', 'action', 'formaction', 'xlink:href', 'poster', 'data', 'background', 'ping', 'cite', 'longdesc',
  'codebase', 'manifest', 'lowsrc', 'dynsrc', 'icon',
]);
const LIST_ATTRS = new Set(['srcset', 'imagesrcset']);
const SAFE_SCHEMES = new Set(['http:', 'https:', 'mailto:', 'tel:']);
const IMAGE_DATA = /^data:image\/(png|jpeg|jpg|gif|webp|avif);base64,[a-z0-9+/=]*$/;

// Control characters, space and DEL are ignored by URL parsers, so
// "java&#9;script:" must be tested as "javascript:".
const squeeze = (value) => String(value).replace(/[\u0000- \u007f]/g, '').toLowerCase();

const baseUri = () => globalThis.document?.baseURI || globalThis.location?.href || 'http://localhost/';
const ownOrigin = () => globalThis.location?.origin || new URL(baseUri()).origin;

/** Whether an address may stay on an attribute. */
function safeUrl(raw, { name, tag }) {
  const value = squeeze(raw);
  if (value === '' || value.startsWith('#')) return true;
  // An SVG <use> only points into this document; elsewhere it loads markup.
  if (tag === 'use' && (name === 'href' || name === 'xlink:href')) return false;
  if ((name === 'src' || name === 'srcset' || name === 'imagesrcset') && value.startsWith('data:')) return IMAGE_DATA.test(value);
  let url;
  try {
    url = new URL(String(raw).trim(), baseUri());
  } catch {
    return false;
  }
  // A scheme written in the value has to be on the list too; anything without
  // one is relative and resolves on this origin.
  const scheme = /^([a-z][a-z0-9+.-]*):/.exec(value)?.[1];
  if (scheme && !SAFE_SCHEMES.has(`${scheme}:`)) return false;
  if (!SAFE_SCHEMES.has(url.protocol)) return false;
  // A form only submits to the store itself.
  if (name === 'action' || name === 'formaction') return url.origin === ownOrigin();
  return true;
}

/** Each address in a srcset list, without its width or density. */
const srcsetUrls = (value) => String(value).split(',').map((part) => part.trim().split(/\s+/)[0]).filter(Boolean);

/** Cleans a tree in place: a document's body, a fragment or an element. */
export function cleanTree(rootNode) {
  Array.from(rootNode.querySelectorAll('*')).forEach((n) => {
    if (DROP_TAGS.has(n.localName.toLowerCase())) n.remove();
  });
  Array.from(rootNode.querySelectorAll('*')).forEach((n) => {
    const tag = n.localName.toLowerCase();
    Array.from(n.attributes).forEach((attr) => {
      const name = attr.name.toLowerCase();
      let keep = !name.startsWith('on') && name !== 'srcdoc';
      if (keep && URL_ATTRS.has(name)) keep = safeUrl(attr.value, { name, tag });
      if (keep && LIST_ATTRS.has(name)) keep = srcsetUrls(attr.value).every((u) => safeUrl(u, { name, tag }));
      // Any other attribute holding a script address (a data-href read by a script, say).
      if (keep && /^(javascript|vbscript):/.test(squeeze(attr.value))) keep = false;
      if (!keep) n.removeAttribute(attr.name);
    });
    // A template's content is a separate fragment that the page's own
    // scripts may clone later: clean it the same way.
    if (tag === 'template' && n.content) cleanTree(n.content);
  });
  return rootNode;
}

/** Markup parsed inertly and cleaned, as a fragment. Too large or not text: an empty fragment. */
export function cleanFragment(html) {
  const tpl = globalThis.document.createElement('template');
  if (typeof html === 'string' && html !== '' && html.length <= MAX_HTML) tpl.innerHTML = html;
  return cleanTree(tpl.content);
}

