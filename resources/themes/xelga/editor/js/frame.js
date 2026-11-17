/*
 * The preview half of the editor bridge, protocol version 1.
 *
 * Loaded only in preview mode, inside the editor's frame. It outlines and
 * selects sections, applies setting changes live, and tells the editor what
 * the page holds. Messages are {source, v: 1, type, ...} objects; both sides
 * use the store's own origin as the target and drop anything that does not
 * come from it, from the expected window, or with an unknown type.
 */
import { $, $$, root, reduceMotion, EDITOR_SELECT } from '../../js/util.js';

const PROTOCOL = 1;
const ORIGIN = window.location.origin;
const SCHEME = /^scheme-[1-9][0-9]?$/;

let pageData = { sections: [], page: null, theme_settings: {}, font_pairs: [] };
try {
  const el = document.getElementById('xelga-page-data');
  if (el) pageData = { ...pageData, ...JSON.parse(el.textContent) };
} catch (error) {
  console.error('The preview page data could not be read.', error);
}

const post = (message) => window.parent.postMessage({ source: 'theme', v: PROTOCOL, ...message }, ORIGIN);

// A section's script reads the editor's selection from the page as it starts.
const initSection = (el) => {
  markSelection();
  window.XelgaTheme?.initSection(el);
};
const cleanupSection = (el) => window.XelgaTheme?.cleanupSection(el);

const findSection = (id) => (id ? document.querySelector(`[data-section-id="${CSS.escape(id)}"]`) : null);
const findBlock = (section, id) => (section && id ? section.querySelector(`[data-block-id="${CSS.escape(id)}"]`) : null);
const sectionData = (id) => pageData.sections.find((s) => s.id === id);
const blockData = (sectionId, blockId) => sectionData(sectionId)?.blocks?.find((b) => b.id === blockId);

/** Elements of a section or block that belong to it, not to a block nested in it. */
const ownElements = (scope, selector) => {
  const owner = scope.matches('[data-block-id]') ? scope : null;
  return $$(selector, scope).filter((el) => {
    const block = el.closest('[data-block-id]');
    return owner ? block === owner : !block || !scope.contains(block);
  });
};

/* ------------------------------------------------------------------------
   Values
   ------------------------------------------------------------------------ */

/** A media library path as an address, or '' when it is not one. */
function storageUrl(path) {
  if (typeof path !== 'string' || path === '' || path.startsWith('/') || path.includes('..') || /[:\\<>"'`]/.test(path)) return '';
  return (pageData.storage_url || '/storage/') + path.split('/').map(encodeURIComponent).join('/');
}

/** A link value the preview can follow without the server: only a typed web address. */
function linkHref(value) {
  if (!value || typeof value !== 'object' || value.type !== 'url' || typeof value.href !== 'string') return null;
  const href = value.href.trim();
  if (href.startsWith('//')) return null;
  return /^(https?:\/\/|mailto:|tel:|\/|#|\?)/i.test(href) ? href : null;
}

/** Only these tags survive in rich text shown live; the server cleans it again on save. */
function sanitizeRichText(html) {
  const allowed = new Set(['P', 'BR', 'STRONG', 'EM', 'A', 'UL', 'OL', 'LI']);
  const doc = new DOMParser().parseFromString(`<div>${String(html)}</div>`, 'text/html');
  const walk = (node) => {
    Array.from(node.children).forEach((child) => {
      if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'TEMPLATE', 'NOSCRIPT', 'SVG', 'MATH'].includes(child.tagName.toUpperCase())) {
        child.remove();
        return;
      }
      if (!allowed.has(child.tagName)) {
        const kids = Array.from(child.childNodes);
        child.replaceWith(...kids);
        walk(node);
        return;
      }
      Array.from(child.attributes).forEach((attr) => {
        const keep = child.tagName === 'A' && attr.name === 'href' && !attr.value.trim().startsWith('//') && /^(https?:|\/|#|mailto:|tel:)/i.test(attr.value.trim());
        if (!keep) child.removeAttribute(attr.name);
      });
      if (child.tagName === 'A') child.setAttribute('rel', 'noopener');
      walk(child);
    });
  };
  const wrapper = doc.body.firstElementChild;
  walk(wrapper);
  return wrapper.innerHTML;
}

/** Writes one value into a bound element: text, rich text or a photo. */
function writeNode(node, value) {
  const empty = value === '' || value === null || value === undefined;
  if (node instanceof HTMLImageElement) {
    const src = storageUrl(value);
    node.hidden = src === '';
    if (src !== '') {
      node.removeAttribute('srcset');
      node.removeAttribute('width');
      node.removeAttribute('height');
      node.src = src;
    }
    return;
  }
  if (node.hasAttribute('data-richtext')) {
    node.innerHTML = sanitizeRichText(value ?? '');
  } else {
    node.textContent = String(value ?? '');
  }
  if (node.hasAttribute('data-optional')) node.hidden = empty;
}

/* ------------------------------------------------------------------------
   Live changes
   ------------------------------------------------------------------------ */

function applySetting(sectionId, blockId, key, value) {
  const section = findSection(sectionId);
  if (!section || typeof key !== 'string' || !/^[a-z][a-z0-9_]{0,40}$/.test(key)) return false;
  const target = blockId ? findBlock(section, blockId) : section;
  if (!target) return false;

  if (key === 'disabled') {
    target.hidden = value === true;
    const data = blockId ? blockData(sectionId, blockId) : sectionData(sectionId);
    if (data) data.disabled = value === true;
    // A hidden block leaves its section's slideshow or row, so the section's script starts again.
    if (blockId) initSection(section);
    return true;
  }
  if (key === 'scheme') {
    if (typeof value !== 'string' || !SCHEME.test(value)) return false;
    Array.from(target.classList).filter((c) => SCHEME.test(c)).forEach((c) => target.classList.remove(c));
    target.classList.add(value);
    return true;
  }

  let bound = false;
  ownElements(target, `[data-setting="${CSS.escape(key)}"]`).forEach((node) => {
    writeNode(node, value);
    bound = true;
  });
  ownElements(target, `[data-setting-href="${CSS.escape(key)}"]`).forEach((node) => {
    const href = linkHref(value);
    if (href !== null) node.setAttribute('href', href);
    bound = true;
  });
  ownElements(target, '[data-setting-attr]').forEach((node) => {
    node.dataset.settingAttr.split(',').map((pair) => pair.trim().split(':')).forEach(([k, attr]) => {
      if (k !== key || !['alt', 'placeholder', 'title', 'aria-label'].includes(attr)) return;
      node.setAttribute(attr, String(value ?? ''));
      bound = true;
    });
  });

  if (!bound) {
    // Layout settings are data attributes; numeric ones are matched by the
    // generated range rules, so no style is written.
    const name = `data-${key.replace(/_/g, '-')}`;
    const text = typeof value === 'boolean' ? String(value) : String(value ?? '');
    if (/^[a-z0-9.\- ]{0,40}$/i.test(text)) target.setAttribute(name, text);
  }

  initSection(section);
  return true;
}

let liveSheet = null;
const schemeOverrides = {};

function writeSchemeColors(entries) {
  entries.forEach(({ scheme, role, color }) => {
    if (!SCHEME.test(String(scheme)) || !/^[a-z-]{1,20}$/.test(String(role)) || !/^#[0-9a-f]{6}$/i.test(String(color))) return;
    (schemeOverrides[scheme] ||= {})[`--c-${role}`] = color;
  });
  if (!liveSheet) {
    liveSheet = new CSSStyleSheet();
    document.adoptedStyleSheets = [...document.adoptedStyleSheets, liveSheet];
  }
  liveSheet.replaceSync(Object.entries(schemeOverrides)
    .map(([name, props]) => `${name === 'scheme-1' ? ':root,.scheme-1' : `.${name}`}{${Object.entries(props).map(([p, v]) => `${p}:${v}`).join(';')}}`)
    .join('\n'));
}

const SAFE_TOKEN = /^[a-zA-Z0-9 #.,%()/"'+-]{1,200}$/;

function setToken(name, css) {
  if (/^--[a-z0-9-]{1,40}$/.test(name) && SAFE_TOKEN.test(String(css)) && !String(css).includes('/*')) root.style.setProperty(name, String(css));
}

function applyThemeSetting(key, value) {
  const target = pageData.theme_settings?.[key];
  if (!target) return false;

  switch (target.type) {
    case 'token': {
      const css = target.map ? target.map[String(value)] : (typeof value === 'number' || /^[a-z0-9.-]+$/i.test(String(value)) ? `${value}${target.unit || ''}` : undefined);
      if (css !== undefined) setToken(target.name, css);
      return true;
    }
    case 'attr':
      if (/^data-[a-z0-9-]+$/.test(target.name)) root.setAttribute(target.name, String(value));
      return true;
    case 'font-pair': {
      const pair = (pageData.font_pairs || []).find((p) => p.value === value);
      if (!pair) return false;
      setToken('--font-heading', pair.heading);
      setToken('--font-body', pair.body);
      if (pair.heading_weight) setToken('--font-heading-weight', String(pair.heading_weight));
      Object.entries(pair.tokens || {}).forEach(([name, css]) => setToken(name, css));
      return true;
    }
    case 'scheme-colors': {
      // One color ({scheme, role, color}) or every scheme ([{id, colors}]).
      if (Array.isArray(value)) {
        writeSchemeColors(value.flatMap((s) => Object.entries(s?.colors || {}).map(([role, color]) => ({ scheme: s.id, role, color }))));
      } else if (value && typeof value === 'object') {
        writeSchemeColors([value]);
      }
      return true;
    }
    default:
      return false;
  }
}

/* ------------------------------------------------------------------------
   Outlines
   ------------------------------------------------------------------------ */

const makeBox = (kind) => {
  const box = document.createElement('div');
  box.className = `ve-box ve-box--${kind}`;
  box.setAttribute('aria-hidden', 'true');
  const label = document.createElement('span');
  label.className = 've-label';
  box.append(label);
  document.body.append(box);
  return box;
};

const boxes = { hover: null, select: null };
const fieldBoxes = [];
const state = { hover: null, select: null, field: null };

const resolveRef = (ref) => {
  if (!ref) return null;
  const section = findSection(ref.sectionId);
  if (!section) return null;
  const block = ref.blockId ? findBlock(section, ref.blockId) : null;
  return { section, el: block || section, isBlock: Boolean(block) };
};

function place(box, el, labelText, small) {
  const rect = el.getBoundingClientRect();
  if (el.hidden || (rect.width === 0 && rect.height === 0)) {
    box.classList.remove('is-on');
    return;
  }
  const top = Math.max(rect.top, 0);
  const bottom = Math.min(rect.bottom, window.innerHeight);
  box.style.transform = `translate(${rect.left}px, ${top}px)`;
  box.style.width = `${rect.width}px`;
  box.style.height = `${Math.max(bottom - top, 0)}px`;
  box.classList.toggle('is-flipped', top < 26);
  const label = box.querySelector('.ve-label');
  label.textContent = labelText || '';
  if (small) {
    const s = document.createElement('small');
    s.textContent = ` ${small}`;
    label.append(s);
  }
  label.hidden = !labelText;
  box.classList.add('is-on');
}

function paint(kind) {
  const box = boxes[kind];
  const ref = state[kind];
  const hit = resolveRef(ref);
  const sameAsSelected = kind === 'hover' && state.select && ref
    && ref.sectionId === state.select.sectionId && (ref.blockId || null) === (state.select.blockId || null);
  if (!hit || sameAsSelected) {
    box.classList.remove('is-on');
    return;
  }
  box.classList.toggle('ve-box--block', hit.isBlock);
  const s = sectionData(ref.sectionId);
  const b = ref.blockId ? blockData(ref.sectionId, ref.blockId) : null;
  if (ref.blockId) place(box, hit.el, b?.label || 'Block', `in ${s?.label || ref.sectionId}`);
  else place(box, hit.el, s?.label || ref.sectionId, null);
}

function fieldElements(ref) {
  const hit = resolveRef(ref);
  if (!hit || !ref.key) return [];
  const key = CSS.escape(ref.key);
  const els = [
    ...ownElements(hit.el, `[data-setting="${key}"], [data-setting-href="${key}"]`),
    ...ownElements(hit.el, '[data-setting-attr]').filter((n) => n.dataset.settingAttr.split(',').some((p) => p.trim().split(':')[0] === ref.key)),
  ];
  return els.length ? els : [hit.el];
}

function paintFields() {
  const els = state.field ? fieldElements(state.field) : [];
  while (fieldBoxes.length < els.length) fieldBoxes.push(makeBox('field'));
  fieldBoxes.forEach((box, i) => {
    if (els[i]) place(box, els[i], '', null);
    else box.classList.remove('is-on');
  });
}

let frame = 0;
const repaint = () => {
  cancelAnimationFrame(frame);
  frame = requestAnimationFrame(() => { paint('select'); paint('hover'); paintFields(); });
};

const refFromEvent = (event) => {
  const section = event.target.closest?.('[data-section-id]');
  if (!section) return null;
  const block = event.target.closest('[data-block-id]');
  const ownBlock = block && section.contains(block) ? block : null;
  const setting = event.target.closest('[data-setting], [data-setting-href]');
  const key = setting && (ownBlock ? ownBlock.contains(setting) : !setting.closest('[data-block-id]'))
    ? (setting.dataset.setting || setting.dataset.settingHref || null)
    : null;
  return { sectionId: section.dataset.sectionId, blockId: ownBlock ? ownBlock.dataset.blockId : null, key };
};

/*
 * Where an element sits in the page, ignoring transforms. A slide that is
 * selected starts sliding in from beside or below its banner, so its drawn
 * box is briefly outside the banner; scrolling to that box moved the whole
 * page. Layout offsets give the place it is going to.
 */
const layoutBox = (el) => {
  let top = 0;
  for (let node = el; node; node = node.offsetParent) top += node.offsetTop;
  return { top: top - window.scrollY, height: el.offsetHeight };
};

const scrollIntoViewIfNeeded = (el) => {
  const box = layoutBox(el);
  if (box.top >= 0 && box.top < window.innerHeight * 0.6) return;
  const offset = box.height > window.innerHeight ? 0 : (window.innerHeight - box.height) / 2;
  window.scrollTo({ top: Math.max(0, window.scrollY + box.top - offset), behavior: reduceMotion.matches ? 'auto' : 'smooth' });
};

/* ------------------------------------------------------------------------
   Structure: sections within their own group, blocks within their section
   ------------------------------------------------------------------------ */

const removed = new Map();
const removedBlocks = new Map();

const groupSiblings = (group) => pageData.sections
  .filter((s) => (s.group || 'template') === group)
  .map((s) => findSection(s.id))
  .filter(Boolean);

/** Top-level sections in page order (a section never sits inside another). */
const syncOrderFromDom = () => {
  const order = $$('[data-section-id]').filter((el) => !el.parentElement.closest('[data-section-id]')).map((el) => el.dataset.sectionId);
  pageData.sections.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
};

const placeInGroup = (el, group, toIndex) => {
  const siblings = groupSiblings(group).filter((s) => s !== el);
  if (siblings.length === 0) {
    // An empty group: the header sits above the page content, the footer below it.
    const main = $('#main-content');
    if (group === 'header' && main) main.before(el);
    else if (group === 'footer' && main) main.after(el);
    else main?.append(el);
    return;
  }
  const index = Math.max(0, Math.min(Number(toIndex) || 0, siblings.length));
  if (index >= siblings.length) siblings[siblings.length - 1].after(el);
  else siblings[index].before(el);
};

/** A section's own blocks, in page order: not blocks of a section or block nested inside it. */
const ownBlocks = (section) => $$('[data-block-id]', section)
  .filter((b) => b.parentElement.closest('[data-section-id]') === section && !b.parentElement.closest('[data-block-id]'));

const syncBlocksFromDom = (sectionId) => {
  const data = sectionData(sectionId);
  const section = findSection(sectionId);
  if (!data || !section || !Array.isArray(data.blocks)) return;
  const order = ownBlocks(section).map((b) => b.dataset.blockId);
  data.blocks.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
};

const placeBlock = (section, el, toIndex, fallbackParent = null) => {
  const siblings = ownBlocks(section).filter((b) => b !== el);
  if (siblings.length === 0) {
    if (!fallbackParent || !section.contains(fallbackParent)) return false;
    fallbackParent.append(el);
    return true;
  }
  const index = Math.max(0, Math.min(Number(toIndex) || 0, siblings.length));
  if (index >= siblings.length) siblings[siblings.length - 1].after(el);
  else siblings[index].before(el);
  return true;
};

/** Runs a section's storefront script again, after its old one let go. */
const rerun = (section) => {
  cleanupSection(section);
  initSection(section);
};

const forget = (sectionId, blockId = null) => {
  ['select', 'hover', 'field'].forEach((kind) => {
    const ref = state[kind];
    if (ref?.sectionId === sectionId && (!blockId || ref.blockId === blockId)) state[kind] = null;
  });
  markSelection();
};

/*
 * The selection, written into the page: the selected section and block carry
 * data-editor-selected, and each section whose part of the selection changed
 * hears EDITOR_SELECT. A slideshow uses it to hold on the selected slide.
 */
function markSelection() {
  const ref = state.select;
  const section = ref ? findSection(ref.sectionId) : null;
  const block = section && ref.blockId ? findBlock(section, ref.blockId) : null;
  const changed = new Set();
  $$('[data-editor-selected]').forEach((el) => {
    if (el === section || el === block) return;
    el.removeAttribute('data-editor-selected');
    changed.add(el.closest('[data-section-id]'));
  });
  [section, block].forEach((el) => {
    if (!el || el.hasAttribute('data-editor-selected')) return;
    el.setAttribute('data-editor-selected', '');
    changed.add(section);
  });
  changed.forEach((el) => el?.dispatchEvent(new CustomEvent(EDITOR_SELECT)));
}

/*
 * Markup from the server. It is the store's own preview answer, fetched by
 * the editor from this origin, and is parsed inertly in a template element.
 * It is still cleaned with allowlists before it joins the page, so that
 * markup which slipped past every other check cannot run: elements that can
 * run or load code are removed, every inline handler is dropped, and an
 * address is kept only when its scheme is one a storefront link or image
 * needs.
 */
const MAX_HTML = 2000000;

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
const normalize = (value) => String(value).replace(/[\u0000- \u007f]/g, '').toLowerCase();

/** Whether an address may stay on an attribute. */
function safeUrl(raw, { name, tag }) {
  const value = normalize(raw);
  if (value === '' || value.startsWith('#')) return true;
  // An SVG <use> only points into this document; elsewhere it loads markup.
  if (tag === 'use' && (name === 'href' || name === 'xlink:href')) return false;
  if ((name === 'src' || name === 'srcset' || name === 'imagesrcset') && value.startsWith('data:')) return IMAGE_DATA.test(value);
  let url;
  try {
    url = new URL(String(raw).trim(), document.baseURI);
  } catch {
    return false;
  }
  // A scheme written in the value has to be on the list too; anything without
  // one is relative and resolves on this origin.
  const scheme = /^([a-z][a-z0-9+.-]*):/.exec(value)?.[1];
  if (scheme && !SAFE_SCHEMES.has(`${scheme}:`)) return false;
  if (!SAFE_SCHEMES.has(url.protocol)) return false;
  // A form only submits to the store itself.
  if (name === 'action' || name === 'formaction') return url.origin === window.location.origin;
  return true;
}

/** Each address in a srcset list, without its width or density. */
const srcsetUrls = (value) => String(value).split(',').map((part) => part.trim().split(/\s+/)[0]).filter(Boolean);

function scrub(rootNode) {
  rootNode.querySelectorAll('*').forEach((n) => {
    if (DROP_TAGS.has(n.localName.toLowerCase())) n.remove();
  });
  rootNode.querySelectorAll('*').forEach((n) => {
    const tag = n.localName.toLowerCase();
    Array.from(n.attributes).forEach((attr) => {
      const name = attr.name.toLowerCase();
      let keep = !name.startsWith('on') && name !== 'srcdoc';
      if (keep && URL_ATTRS.has(name)) keep = safeUrl(attr.value, { name, tag });
      if (keep && LIST_ATTRS.has(name)) keep = srcsetUrls(attr.value).every((u) => safeUrl(u, { name, tag }));
      // Any other attribute holding a script address (a data-href read by a script, say).
      if (keep && /^(javascript|vbscript):/.test(normalize(attr.value))) keep = false;
      if (!keep) n.removeAttribute(attr.name);
    });
    // A template's content is a separate fragment that the page's own
    // scripts may clone later: clean it the same way.
    if (tag === 'template' && n.content) scrub(n.content);
  });
}

function parseOne(html, selector, id, attr) {
  if (typeof html !== 'string' || html === '' || html.length > MAX_HTML) return null;
  const tpl = document.createElement('template');
  tpl.innerHTML = html;
  scrub(tpl.content);
  const el = Array.from(tpl.content.children).find((node) => node.matches(selector) && node.getAttribute(attr) === id);
  return el ? document.importNode(el, true) : null;
}

const sectionFrom = (html, id) => parseOne(html, '[data-section-id]', id, 'data-section-id');
const blockFrom = (html, id) => parseOne(html, '[data-block-id]', id, 'data-block-id');

const ID = /^[a-z0-9-]{1,40}$/;
const clip = (text, max = 80) => String(text ?? '').slice(0, max);

/** The preview's record of one section, from what the editor sent or, failing that, from the markup. */
function entryFor(id, el, group, data) {
  const blocks = Array.isArray(data?.blocks) ? data.blocks : ownBlocks(el).map((b) => ({ id: b.dataset.blockId, type: b.dataset.blockType || 'block', label: '', disabled: b.hidden }));
  return {
    id,
    type: clip(data?.type || el.dataset.sectionType || 'section', 40),
    group: ['header', 'template', 'footer'].includes(group) ? group : 'template',
    label: clip(data?.label || el.getAttribute('aria-label') || el.dataset.sectionType || id),
    disabled: Boolean(data?.disabled ?? el.hidden),
    blocks: blocks.filter((b) => b && ID.test(String(b.id))).map((b) => ({ id: String(b.id), type: clip(b.type, 40), label: clip(b.label), disabled: Boolean(b.disabled) })),
  };
}

function addSection(id, el, group, index, data) {
  pageData.sections = pageData.sections.filter((s) => s.id !== id);
  pageData.sections.push(entryFor(id, el, group, data));
  document.body.append(el);
  placeInGroup(el, group, index);
  syncOrderFromDom();
  initSection(el);
  if (state.select?.sectionId === id) scrollIntoViewIfNeeded(el);
}

const pageDataMessage = () => ({ type: 'page-data', pageData: { page: pageData.page, sections: pageData.sections } });

const done = () => {
  repaint();
  post(pageDataMessage());
};

const fail = (sectionId, message) => post({ type: 'error', sectionId, message });

/* ------------------------------------------------------------------------
   Messages from the editor
   ------------------------------------------------------------------------ */

const handlers = {
  highlight({ sectionId, blockId }) {
    state.hover = sectionId ? { sectionId, blockId: blockId || null } : null;
    repaint();
  },
  select({ sectionId, blockId }) {
    state.select = sectionId ? { sectionId, blockId: blockId || null } : null;
    markSelection();
    const hit = resolveRef(state.select);
    if (hit) scrollIntoViewIfNeeded(hit.el);
    repaint();
  },
  field({ sectionId, blockId, key }) {
    state.field = sectionId && key ? { sectionId, blockId: blockId || null, key } : null;
    repaint();
  },
  setting({ sectionId, blockId, key, value }) {
    applySetting(sectionId, blockId || null, key, value);
    repaint();
  },
  'theme-setting'({ key, value }) {
    applyThemeSetting(key, value);
    repaint();
  },
  move({ sectionId, toIndex }) {
    const el = findSection(sectionId);
    const data = sectionData(sectionId);
    if (!el || !data) return;
    placeInGroup(el, data.group || 'template', toIndex);
    syncOrderFromDom();
    done();
  },
  remove({ sectionId }) {
    const el = findSection(sectionId);
    const index = pageData.sections.findIndex((s) => s.id === sectionId);
    if (!el || index < 0) return;
    cleanupSection(el);
    const data = pageData.sections[index];
    const groupIndex = groupSiblings(data.group || 'template').indexOf(el);
    pageData.sections.splice(index, 1);
    removed.set(sectionId, { el, data, groupIndex });
    el.remove();
    forget(sectionId);
    done();
  },
  restore({ sectionId, toIndex }) {
    const entry = removed.get(sectionId);
    if (!entry || findSection(sectionId)) return;
    removed.delete(sectionId);
    addSection(sectionId, entry.el, entry.data.group || 'template', toIndex ?? entry.groupIndex, entry.data);
    done();
  },
  /* A new section, drawn by the server from the saved draft. */
  insert({ sectionId, group, index, html, data }) {
    if (!ID.test(String(sectionId))) return;
    if (findSection(sectionId)) {
      handlers.replace({ sectionId, html, data });
      return;
    }
    const el = sectionFrom(html, sectionId);
    if (!el) {
      fail(sectionId, 'The new section could not be drawn.');
      return;
    }
    removed.delete(sectionId);
    addSection(sectionId, el, group, index, data);
    done();
  },
  /* A copy of a section, placed just after it unless the editor says where. */
  duplicate({ sectionId, newId, html, index, data }) {
    if (!ID.test(String(newId)) || findSection(newId)) return;
    const source = findSection(sectionId);
    const group = data?.group || sectionData(sectionId)?.group || 'template';
    const el = sectionFrom(html, newId);
    if (!el) {
      fail(sectionId, 'The copy could not be drawn.');
      return;
    }
    const at = Number.isFinite(index) ? index : (source ? groupSiblings(group).indexOf(source) + 1 : groupSiblings(group).length);
    addSection(newId, el, group, at, data);
    done();
    post({ type: 'duplicated', sectionId, newId });
  },
  /* A section drawn again after a change only the server can draw. */
  replace({ sectionId, html, data }) {
    const old = findSection(sectionId);
    const el = sectionFrom(html, sectionId);
    if (!old || !el) {
      if (!old) return;
      fail(sectionId, 'The section could not be drawn again.');
      return;
    }
    cleanupSection(old);
    old.replaceWith(el);
    const entry = sectionData(sectionId);
    if (entry) Object.assign(entry, entryFor(sectionId, el, entry.group, data || entry));
    [...removedBlocks.keys()].filter((k) => k.startsWith(`${sectionId}/`)).forEach((k) => removedBlocks.delete(k));
    initSection(el);
    done();
  },
  'block-insert'({ sectionId, blockId, index, html, sectionHtml, data }) {
    const section = findSection(sectionId);
    if (!section || !ID.test(String(blockId))) return;
    const el = findBlock(section, blockId) ? null : blockFrom(html, blockId);
    if (!el || !placeBlock(section, el, index)) {
      // No sibling to place it beside (the section's first block): draw the whole section.
      if (sectionHtml) handlers.replace({ sectionId, html: sectionHtml, data });
      return;
    }
    removedBlocks.delete(`${sectionId}/${blockId}`);
    const entry = sectionData(sectionId);
    if (entry) {
      const info = data?.blocks?.find((b) => b.id === blockId);
      entry.blocks = (entry.blocks || []).filter((b) => b.id !== blockId);
      entry.blocks.push({ id: blockId, type: clip(info?.type || el.dataset.blockType || 'block', 40), label: clip(info?.label || ''), disabled: Boolean(info?.disabled ?? el.hidden) });
      syncBlocksFromDom(sectionId);
    }
    rerun(section);
    done();
  },
  'block-remove'({ sectionId, blockId }) {
    const section = findSection(sectionId);
    const el = findBlock(section, blockId);
    if (!section || !el) return;
    const entry = sectionData(sectionId);
    const data = entry?.blocks?.find((b) => b.id === blockId) || null;
    removedBlocks.set(`${sectionId}/${blockId}`, { el, data, index: ownBlocks(section).indexOf(el), parent: el.parentElement });
    cleanupSection(section);
    el.remove();
    if (entry) entry.blocks = (entry.blocks || []).filter((b) => b.id !== blockId);
    forget(sectionId, blockId);
    initSection(section);
    done();
  },
  'block-restore'({ sectionId, blockId, index }) {
    const key = `${sectionId}/${blockId}`;
    const saved = removedBlocks.get(key);
    const section = findSection(sectionId);
    if (!saved || !section || findBlock(section, blockId)) return;
    if (!placeBlock(section, saved.el, index ?? saved.index, saved.parent)) return;
    removedBlocks.delete(key);
    const entry = sectionData(sectionId);
    if (entry && saved.data) {
      entry.blocks = [...(entry.blocks || []).filter((b) => b.id !== blockId), saved.data];
      syncBlocksFromDom(sectionId);
    }
    rerun(section);
    done();
  },
  'block-move'({ sectionId, blockId, toIndex }) {
    const section = findSection(sectionId);
    const el = findBlock(section, blockId);
    if (!section || !el) return;
    placeBlock(section, el, toIndex);
    syncBlocksFromDom(sectionId);
    rerun(section);
    done();
  },
  /*
   * Sections that wait for a category or products the store does not have:
   * the section keeps its place and shows the editor's empty state, in the
   * store's own colors, until something is picked. The editor sends the
   * whole list each time; an empty list clears them all.
   */
  empty({ sections }) {
    document.querySelectorAll('[data-editor-empty]').forEach((el) => {
      el.querySelectorAll(':scope > .xe-empty').forEach((box) => box.remove());
      delete el.dataset.editorEmpty;
    });
    (Array.isArray(sections) ? sections : []).slice(0, 40).forEach((item) => {
      const el = ID.test(String(item?.sectionId)) ? findSection(item.sectionId) : null;
      if (!el) return;
      const box = document.createElement('div');
      box.className = 'xe-empty';
      const title = document.createElement('p');
      title.className = 'xe-empty__title';
      title.textContent = clip(item.title, 160);
      const text = document.createElement('p');
      text.className = 'xe-empty__text';
      text.textContent = clip(item.text, 240);
      box.append(title, text);
      if (item.pick) {
        const pick = document.createElement('span');
        pick.className = 'xe-empty__pick';
        pick.textContent = clip(item.pick, 40);
        box.append(pick);
      }
      el.dataset.editorEmpty = 'true';
      el.append(box);
    });
    repaint();
  },
  rename({ sectionId, blockId, label }) {
    const entry = blockId ? blockData(sectionId, blockId) : sectionData(sectionId);
    if (!entry || typeof label !== 'string') return;
    entry.label = clip(label);
    const el = blockId ? null : findSection(sectionId);
    if (el?.hasAttribute('aria-label')) el.setAttribute('aria-label', entry.label);
    done();
  },
};

/* What this preview can do without a reload; the editor reloads for anything else. */
const SUPPORTS = Object.keys(handlers);

/*
 * Following a link inside the preview. A plain click selects what it lands
 * on; holding Alt follows the link instead. The next page says so to the
 * editor, which then opens that page's template.
 */
const NAV_KEY = 'xelga-editor-navigation';

/* The preview window's query parameter (PreviewMode::QUERY on the server). */
const PREVIEW_PARAM = 'xelga_preview';

function followLink(link) {
  let url;
  try {
    url = new URL(link.href, window.location.href);
  } catch {
    return;
  }
  if (url.origin !== ORIGIN) return;
  // The next page must open in the preview too. Browsers only send
  // Sec-Fetch-Dest on secure origins, so a store on plain http (a dev box,
  // a LAN address) would draw the published page without the editor's
  // bridge; the preview window's nonce from this page's address keeps it
  // in the preview. A special layout asked for here is not carried over.
  const nonce = new URLSearchParams(window.location.search).get(PREVIEW_PARAM);
  if (nonce && !url.searchParams.has(PREVIEW_PARAM)) url.searchParams.set(PREVIEW_PARAM, nonce);
  try {
    window.sessionStorage.setItem(NAV_KEY, JSON.stringify({ path: url.pathname, at: Date.now() }));
  } catch {
    /* Without storage the editor still finds the page from `ready`. */
  }
  window.location.assign(url.href);
}

function arrivedByLink() {
  try {
    const raw = window.sessionStorage.getItem(NAV_KEY);
    if (!raw) return false;
    window.sessionStorage.removeItem(NAV_KEY);
    const { path, at } = JSON.parse(raw);
    return Date.now() - Number(at) < 30000 && typeof path === 'string';
  } catch {
    return false;
  }
}

function start() {
  if (window.parent === window) return;
  root.classList.add('in-editor');
  boxes.hover = makeBox('hover');
  boxes.select = makeBox('select');

  window.addEventListener('scroll', repaint, { passive: true });
  window.addEventListener('resize', repaint);
  new ResizeObserver(repaint).observe(document.body);
  document.addEventListener('transitionend', repaint, true);

  document.addEventListener('pointerover', (event) => {
    state.hover = refFromEvent(event);
    repaint();
  });
  root.addEventListener('mouseleave', () => {
    state.hover = null;
    repaint();
  });

  // Where the last press started: a press that moved (a drag through a
  // slideshow or a row) is not a click that selects.
  let pressedAt = null;
  document.addEventListener('pointerdown', (event) => {
    pressedAt = { x: event.clientX, y: event.clientY };
  }, true);
  const moved = (event) => event.detail > 0 && pressedAt !== null
    && Math.hypot(event.clientX - pressedAt.x, event.clientY - pressedAt.y) > 6;

  // In the preview a click selects; links and forms never navigate away,
  // except a link followed with Alt held.
  document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');
    if (link) {
      event.preventDefault();
      if (event.altKey) {
        event.stopPropagation();
        followLink(link);
        return;
      }
    }
    if (moved(event)) return;
    const ref = refFromEvent(event);
    if (!ref) return;
    state.select = { sectionId: ref.sectionId, blockId: ref.blockId };
    markSelection();
    repaint();
    post({ type: 'select', sectionId: ref.sectionId, blockId: ref.blockId, key: ref.key });
  }, true);
  document.addEventListener('submit', (event) => event.preventDefault(), true);

  window.addEventListener('message', (event) => {
    if (event.source !== window.parent || event.origin !== ORIGIN) return;
    const message = event.data;
    if (!message || typeof message !== 'object' || message.source !== 'editor' || message.v !== PROTOCOL) return;
    if (!Object.prototype.hasOwnProperty.call(handlers, message.type)) return;
    handlers[message.type](message);
  });

  post({ type: 'ready', pageData, page: pageData.page, url: pageData.url || window.location.href, preview: pageData.preview !== false, supports: SUPPORTS });
  if (arrivedByLink()) post({ type: 'navigated', page: pageData.page, url: pageData.url || window.location.href });

  $$('[data-section-error]').forEach((el) => {
    post({ type: 'error', sectionId: el.dataset.sectionId, message: el.textContent.trim() });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
  start();
}
