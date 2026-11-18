/*
 * The layout board drawn under Node, for its tests: the mini DOM, a window
 * whose width and pointer the test chooses, a small section catalog, the
 * boot payload's layout and a Demo Store with its header, footer, Home,
 * Product and Category pages. `drawBoard()` draws the board into #board
 * and answers it; `useWindow()` makes the window a computer, a phone or a
 * touch screen; `openPage()` opens a page with its document.
 */

import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { installDom, Element } from './mini-dom.mjs';

installDom({ href: 'https://shop.example.com/admin/theme' });

const media = { phone: false, coarse: false, hover: true, width: 1440 };
globalThis.window = {
  addEventListener: () => {},
  removeEventListener: () => {},
  setTimeout,
  clearTimeout,
  requestAnimationFrame: (fn) => setTimeout(fn, 0),
  cancelAnimationFrame: clearTimeout,
  location: { href: 'https://shop.example.com/admin/theme', origin: 'https://shop.example.com' },
  get innerWidth() { return media.width; },
  matchMedia: (query) => ({
    get matches() {
      if (query.includes('max-width: 719px')) return media.phone;
      if (query.includes('pointer: coarse')) return media.coarse;
      if (query.includes('hover: hover')) return media.hover;
      return false;
    },
    addEventListener: () => {},
    removeEventListener: () => {},
  }),
};
globalThis.CSS = globalThis.CSS || { escape: (value) => String(value) };

const here = dirname(fileURLToPath(import.meta.url));
export const LAYOUT = JSON.parse(readFileSync(join(here, '../../fixtures/layout/_layout.json'), 'utf8'));

const { S, ctx } = await import('../../../editor/js/modules/state.js');
const { setCatalog } = await import('../../../editor/js/modules/catalog.js');
const { useLayout } = await import('../../../editor/js/modules/board/model.js');
const { renderBoard } = await import('../../../editor/js/modules/board/board.js');

const plain = (min, extra = {}) => ({ group: 'template', templates: ['*'], span: { min, variants: {} }, settings: [], ...extra });

/* Section types as the boot payload carries them. */
export const CATALOG = {
  header: { label: 'Header', group: 'header', fixed: true, span: { min: 12, variants: {} }, settings: [] },
  footer: { label: 'Footer', group: 'footer', fixed: true, span: { min: 12, variants: {} }, settings: [] },
  'announcement-bar': plain(6, { label: 'Announcement bar', shared_groups: ['header', 'footer'], limit: 1, limit_per_area: true, own_row: true }),
  'product-main': plain(12, { label: 'Product information', main: true, templates: ['product'] }),
  'category-main': plain(12, { label: 'Product listing', main: true, templates: ['category'] }),
  breadcrumbs: plain(3, { label: 'Breadcrumbs' }),
  filters: plain(3, { label: 'Filters', templates: ['category'], limit: 1, content_only: true }),
  'rich-text': plain(3, { label: 'Text' }),
  banner: plain(12, { label: 'Main banner' }),
};

const section = (type, label = CATALOG[type].label) => ({ type, label, disabled: false, settings: {}, block_order: [], blocks: {} });
const row = (id, columns, area, split = '12') => ({ id, split, columns, settings: { ...LAYOUT.row_defaults }, ...(area ? { area } : {}) });

/** The Demo Store's pages: Home, Product (24 products) and Category (6 categories). */
export const PAGES = {
  home: { type: 'home', kind: 'template', handle: 'home', label: 'Home page', pageType: 'home', url: 'https://shop.example.com/', subjectType: null, subjectCount: 0, groups: [] },
  product: { type: 'product', kind: 'template', handle: 'product', label: 'Product', pageType: 'product', url: 'https://shop.example.com/products/demo-001', subjectType: 'product', subjectCount: 24, groups: [] },
  category: { type: 'category', kind: 'template', handle: 'category', label: 'Category', pageType: 'category', url: 'https://shop.example.com/laptops', subjectType: 'category', subjectCount: 6, groups: [] },
};

/** The documents of each page, fresh for every test. */
export function documents() {
  return {
    header: { schema: 1, order: ['bar', 'head'], sections: { bar: section('announcement-bar'), head: section('header') } },
    footer: { schema: 1, order: ['foot'], sections: { foot: section('footer') } },
    home: { schema: 1, order: ['hero', 'words'], sections: { hero: section('banner'), words: section('rich-text') } },
    // Top: breadcrumbs; Content: the product with a left side; Bottom: a text.
    product: {
      schema: 1,
      order: ['crumbs', 'note', 'main', 'words'],
      sections: { crumbs: section('breadcrumbs'), note: section('rich-text', 'Side note'), main: section('product-main'), words: section('rich-text') },
      rows: [row('r-crumbs', [['crumbs']], 'top'), row('r-main', [['main']], 'content'), row('r-words', [['words']], 'bottom')],
      sides: { left: ['note'] },
    },
    // Filters in the left side, beside the listing; Bottom empty.
    category: {
      schema: 1,
      order: ['crumbs', 'filters', 'main'],
      sections: { crumbs: section('breadcrumbs'), filters: section('filters'), main: section('category-main') },
      rows: [row('r-crumbs', [['crumbs']], 'top'), row('r-main', [['main']], 'content')],
      sides: { left: ['filters'] },
    },
  };
}

ctx.boot = { layout: LAYOUT, preview: { url: 'https://shop.example.com/', nonce: '', param: 'xelga_preview' } };
useLayout(LAYOUT);
setCatalog({ sections: CATALOG });

const app = new Element('div');
app.setAttribute('id', 'app');
const board = new Element('div');
board.setAttribute('id', 'board');
app.append(board);
document.body.append(app);

/** Makes the window a computer ('computer'), a phone ('phone') or a touch tablet ('touch'). */
export function useWindow(kind) {
  Object.assign(media, {
    computer: { phone: false, coarse: false, hover: true, width: 1440 },
    phone: { phone: true, coarse: true, hover: false, width: 390 },
    touch: { phone: false, coarse: true, hover: false, width: 1024 },
  }[kind]);
  app.dataset.addline = kind === 'computer' ? 'hover' : 'always';
}

/** Opens a page of the Demo Store with its documents (`content` replaces the page's own). */
export function openPage(type, { content = null, docs = documents() } = {}) {
  // Another test of the same file may have set its own catalog or layout.
  ctx.boot = { layout: LAYOUT, preview: { url: 'https://shop.example.com/', nonce: '', param: 'xelga_preview' } };
  useLayout(LAYOUT);
  setCatalog({ sections: CATALOG });
  S.store = { name: 'Demo Store', url: 'https://shop.example.com/' };
  S.pages = Object.values(PAGES);
  S.page = { ...PAGES[type] };
  S.templates = [];
  S.sel = null;
  S.view = { type: 'board' };
  S.docs = {};
  ['header', 'footer', type].forEach((handle) => {
    const c = handle === type && content ? content : docs[handle];
    S.docs[handle] = { handle, content: c, savedJson: JSON.stringify(c), fieldErrors: {} };
  });
  S.loaded = true;
}

/** Draws the board and answers its element. */
export function drawBoard() {
  renderBoard();
  return board;
}

export { S, ctx };
