/*
 * Pictures of the sections on the layout board: each section as the store
 * draws it from the draft, scaled down and cropped.
 *
 * One request draws the whole page: the draft page is fetched once, parsed
 * inertly and cleaned with the same allowlists as the preview frame. Each
 * section is then shown in its own shadow root, inside an inert box with
 * no storefront scripts, so nothing in it runs, takes focus or is read out.
 * The theme's stylesheets are fetched once and shared by every shadow root.
 *
 * A section is drawn at the width it has on a 1280 pixel store page (its
 * column's share of it inside a row) and scaled to its place on the board.
 * A tall section shows about a third of its height, from where its content
 * starts, so the picture is not mostly padding; a short one shows whole.
 *
 * The theme's CSS keys page-wide values on :root, html and body, none of
 * which exists in a shadow root, so the rules are rewritten for the shadow
 * host and the picture's own body box. Font faces do not apply inside a
 * shadow root and are added to the editor page once.
 */

import { cleanTree, cleanFragment, MAX_HTML } from '../clean-html.js';

const PAGE_WIDTH = 1280; // the store page width a section is drawn at before it is scaled
const SHORT = 120; // a picture this short (on the board) shows whole
const FLOOR = 90; // the least a tall section's picture shows
const CONTENT_START = '.s__inner, .hero__slides, .s__bg ~ *';

/** Rewrites the theme's page-wide selectors for a shadow root. */
export function forShadow(css) {
  return css
    .replace(/:root/g, ':host')
    .replace(/(^|[\s,}>(])html((?:\[[^\]]*\])+)/g, '$1:host($2)')
    .replace(/(^|[\s,}>(])html(?=[\s,{.:>])/g, '$1:host')
    .replace(/(^|[\s,}>(])body(?=[\s,{.:>[])/g, '$1.pic-body');
}

/** Addresses inside a stylesheet resolved against where the stylesheet lives. */
function absoluteUrls(css, base) {
  return css.replace(/url\(\s*(['"]?)([^'")]+)\1\s*\)/g, (whole, quote, raw) => {
    const value = raw.trim();
    if (/^(data:|https?:|\/|#)/i.test(value)) return whole;
    try {
      return `url("${new URL(value, base).href}")`;
    } catch {
      return whole;
    }
  });
}

const fontFaces = (css) => (css.match(/@font-face\s*{[^}]*}/g) || []).join('\n');
// A constructed stylesheet refuses @import; the page's own links are fetched instead.
const withoutImports = (css) => css.replace(/@import[^;]+;/g, '');

const FRAME_CSS = `
  :host { display: block; position: relative; overflow: hidden; height: var(--pic-h, 0px); contain: layout paint style; }
  .pic-frame { width: var(--pic-w, ${PAGE_WIDTH}px); transform: translateY(calc(var(--pic-y, 0px) * -1)) scale(var(--pic-k, .25)); transform-origin: 0 0; pointer-events: none; }
  .pic-body { margin: 0; min-height: 0; }
`;

/** Sections drawn at the top level of a parsed page or fragment, by id. */
function sectionsIn(root) {
  const out = new Map();
  root.querySelectorAll('[data-section-id]').forEach((el) => {
    if (el.parentElement?.closest('[data-section-id]')) return;
    out.set(el.getAttribute('data-section-id'), el);
  });
  return out;
}

/**
 * createPictures({ api, stylesheets }) draws pictures of the sections of
 * one page. `stylesheets` are the stylesheet addresses to draw with; left
 * empty, the page's own stylesheets are used.
 */
export function createPictures({ api, stylesheets = [] }) {
  let pageUrl = null;
  let generation = 0;
  let page = null; // { attrs: [[name, value]], bodyClass, sprite, sections: Map<id, Element> }
  let sheets = null; // { key, list: CSSStyleSheet[] }
  const faces = new Set();
  const hosts = new Map(); // `${id}:${span}` -> { host, id, span, observer }

  async function loadSheets(urls, { fresh = false } = {}) {
    const key = urls.join('\n');
    if (sheets && sheets.key === key && !fresh) return;
    const texts = await Promise.all(urls.map(async (url) => {
      const response = await fetch(url, { credentials: 'same-origin', cache: fresh ? 'no-store' : 'default' });
      if (!response.ok) return '';
      return absoluteUrls(withoutImports(await response.text()), url);
    }));
    const unseenFaces = texts.map(fontFaces).filter((f) => f && !faces.has(f));
    if (unseenFaces.length) {
      unseenFaces.forEach((f) => faces.add(f));
      const sheet = new CSSStyleSheet();
      sheet.replaceSync(unseenFaces.join('\n'));
      document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
    }
    const list = texts.filter(Boolean).map((text) => {
      const sheet = new CSSStyleSheet();
      sheet.replaceSync(forShadow(text));
      return sheet;
    });
    const frame = new CSSStyleSheet();
    frame.replaceSync(FRAME_CSS);
    sheets = { key, list: [...list, frame] };
    hosts.forEach(({ host }) => { host.shadowRoot.adoptedStyleSheets = sheets.list; });
  }

  /** The page as the store draws it from the draft, read without running any of it. */
  async function readPage(url) {
    const html = await api.renderPage(url);
    if (typeof html !== 'string' || html.length > MAX_HTML) throw new Error('The page is too large to draw.');
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const links = Array.from(doc.querySelectorAll('link[rel~="stylesheet"][href]'))
      .map((l) => new URL(l.getAttribute('href'), url))
      .filter((u) => u.origin === window.location.origin)
      .map((u) => u.href);
    const attrs = Array.from(doc.documentElement.attributes)
      .filter((a) => a.name.startsWith('data-') || a.name === 'lang' || a.name === 'dir')
      .map((a) => [a.name, a.value]);
    const bodyClass = doc.body.className;
    cleanTree(doc.body);
    const sprite = Array.from(doc.body.children).find((el) => el.localName === 'svg') || null;
    return { links, page: { attrs, bodyClass, sprite, sections: sectionsIn(doc.body) } };
  }

  /** Draws one section into its host again: after a load or a refresh. */
  function fill(entry) {
    const { host, id, span } = entry;
    const source = page?.sections.get(id);
    const root = host.shadowRoot;
    if (!source || !sheets) {
      host.dataset.empty = 'true';
      root.replaceChildren();
      return;
    }
    // The theme's html[data-*] rules became :host([data-*]): the host carries the page's attributes.
    (entry.copied || []).forEach((name) => host.removeAttribute(name));
    entry.copied = page.attrs.filter(([name]) => name.startsWith('data-')).map(([name, value]) => {
      host.setAttribute(name, value);
      return name;
    });
    delete host.dataset.empty;
    const width = Math.round((PAGE_WIDTH * span) / 12);
    host.style.setProperty('--pic-w', `${width}px`);
    const frame = document.createElement('div');
    frame.className = 'pic-frame';
    frame.inert = true;
    const body = document.createElement('div');
    body.className = `pic-body ${page.bodyClass}`.trim();
    page.attrs.filter(([name]) => name === 'lang' || name === 'dir').forEach(([name, value]) => body.setAttribute(name, value));
    if (page.sprite) body.append(document.importNode(page.sprite, true));
    const section = document.importNode(source, true);
    section.querySelectorAll('img').forEach((img) => { img.loading = 'eager'; });
    if (span < 12) {
      const column = document.createElement('div');
      column.className = 'xr__col';
      column.dataset.span = String(span);
      column.append(section);
      body.append(column);
    } else {
      body.append(section);
    }
    frame.append(body);
    root.replaceChildren(frame);
    root.adoptedStyleSheets = sheets.list;
    entry.section = section;
    entry.width = width;
    entry.observer.disconnect();
    entry.observer.observe(host);
    entry.observer.observe(section);
    measure(entry);
  }

  /** Scales a picture to its place and crops it. */
  function measure(entry) {
    const { host, section, width } = entry;
    if (!section || !host.isConnected) return;
    const k = host.clientWidth / width;
    if (!k) return;
    const full = section.offsetHeight * k;
    if (full < 2) {
      // Drawn, but nothing to see (an empty list, say): the board shows its drawing.
      host.dataset.empty = 'true';
      return;
    }
    delete host.dataset.empty;
    let skip = 0;
    if (full > SHORT) {
      const inner = section.querySelector(CONTENT_START);
      if (inner) skip = Math.max(0, inner.getBoundingClientRect().top - section.getBoundingClientRect().top - 8 * k);
    }
    const shown = full <= SHORT ? full : Math.min(full - skip, Math.max(full / 3, FLOOR));
    host.style.setProperty('--pic-k', String(k));
    host.style.setProperty('--pic-y', `${skip}px`);
    host.style.setProperty('--pic-h', `${Math.round(shown)}px`);
  }

  function makeEntry(id, span) {
    const host = document.createElement('div');
    host.className = 'pic';
    host.setAttribute('aria-hidden', 'true');
    host.attachShadow({ mode: 'open' });
    const entry = { host, id, span, section: null, width: PAGE_WIDTH };
    entry.observer = new ResizeObserver(() => measure(entry));
    return entry;
  }

  return {
    /** Draws every picture of a page from one render of its draft. */
    async load(url) {
      const mine = ++generation;
      pageUrl = url;
      const read = await readPage(url);
      if (mine !== generation) return;
      await loadSheets(stylesheets.length ? stylesheets : read.links);
      if (mine !== generation) return;
      page = read.page;
      hosts.forEach((entry) => entry.observer.disconnect());
      hosts.clear();
    },

    /**
     * The picture of a section at a span (12 for full width), or null when
     * the page draws nothing for it (hidden, not placed on this store, or
     * not loaded yet). The same element comes back for the same section and
     * span until the page loads again, so the board can redraw freely.
     */
    pictureFor(id, span = 12) {
      if (!page || !page.sections.has(id)) return null;
      const key = `${id}:${span}`;
      let entry = hosts.get(key);
      if (!entry) {
        entry = makeEntry(id, span);
        hosts.set(key, entry);
        fill(entry);
      }
      return entry.host;
    },

    /**
     * Draws again what a change touched: each listed row of columns from
     * the server's answer for that row, and anything else (the sections
     * given, or new stylesheets) from one render of the page. The pictures
     * already on the board change in place.
     */
    async refresh({ rows = [], sections = [], styles = false } = {}) {
      if (!pageUrl || !page) return;
      const mine = generation;
      if (sections.length || styles) {
        const read = await readPage(pageUrl);
        if (mine !== generation) return;
        await loadSheets(stylesheets.length ? stylesheets : read.links, { fresh: styles });
        page = read.page;
      } else {
        for (const rowId of rows) {
          let html;
          try {
            html = await api.renderSection(pageUrl, rowId);
          } catch (error) {
            // Not drawn on its own (an empty row, a redraw budget spent): draw the page instead.
            if (error.kind === 'unauthenticated' || error.kind === 'forbidden') throw error;
            await this.refresh({ sections: ['*'] });
            return;
          }
          if (mine !== generation) return;
          sectionsIn(cleanFragment(html)).forEach((el, id) => page.sections.set(id, el));
        }
      }
      hosts.forEach((entry) => fill(entry));
    },
  };
}
