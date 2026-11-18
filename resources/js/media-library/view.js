/**
 * The media library's screen: a folder tree with counts and a filter, a grid
 * of images with search, sort and two thumbnail sizes, uploads into the
 * folder in view, and a footer that shows what is picked.
 *
 * The same view runs in a near full-screen <dialog> (choosing images for a
 * field) and inside a page (the Media library page). The page adds its own
 * tools (rename, move, delete) through extend(), so this file only chooses;
 * it offers the page a menu, the focused image and a hook on every change.
 */

import { Selection } from './selection.js';
import { buildTree, filterTree, ancestors, folderLabel } from './tree.js';
import { lastFolder, rememberFolder, thumbSize, rememberThumbSize } from './memory.js';
import { usedByText, checkFiles } from './files.js';
import { icon } from './icons.js';
import { folderNameError } from './names.js';

const ROOT = 'media';
const PHONE = '(max-width: 719px)';
const MIME = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp' };

let views = 0;

/* --------------------------------------------------------------- helpers */

export function h(tag, attrs = {}, ...kids) {
    const el = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([key, value]) => {
        if (value === null || value === undefined || value === false) return;
        if (key === 'class') el.className = value;
        else if (key === 'text') el.textContent = value;
        else if (key.startsWith('on')) el.addEventListener(key.slice(2), value);
        else el.setAttribute(key, value === true ? '' : value);
    });
    kids.flat().forEach((kid) => {
        if (kid === null || kid === undefined || kid === false) return;
        el.append(typeof kid === 'object' ? kid : String(kid));
    });
    return el;
}

export function formatSize(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${Math.round(n / 1024)} KB`;
    return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

const fmt = (n) => Number(n).toLocaleString('en-US');
const plural = (n, one, many = `${one}s`) => `${fmt(n)} ${n === 1 ? one : many}`;
const basename = (path) => String(path).split('/').pop();
const dirname = (path) => String(path).split('/').slice(0, -1).join('/');
const extension = (path) => String(path).split('.').pop().toLowerCase();
const isPhone = () => Boolean(globalThis.matchMedia?.(PHONE).matches);

function highlight(text, term) {
    if (!term) return [text];
    const at = text.toLowerCase().indexOf(term.toLowerCase());
    if (at < 0) return [text];
    return [text.slice(0, at), h('mark', { text: text.slice(at, at + term.length) }), text.slice(at + term.length)];
}

/** What onChoose receives for each image, whatever the server sent. */
function describe(file) {
    return {
        path: file.path,
        url: file.url ?? null,
        thumb: file.thumb ?? file.url ?? null,
        name: file.name ?? basename(file.path),
        folder: file.folder ?? dirname(file.path),
        width: file.width ?? null,
        height: file.height ?? null,
        size: file.size ?? 0,
        usedBy: file.usedBy ?? { total: 0, label: null },
    };
}

function firstMessage(error) {
    const fields = Object.values(error?.fields || {});
    const first = fields.find((list) => Array.isArray(list) && list.length);
    return first ? first[0] : error?.message || 'Something went wrong. Try again.';
}

/* ------------------------------------------------------- page extensions */

let manageFactory = null;

/**
 * The Media library page registers its tools here; a view mounted with
 * `manage: true` then receives them.
 */
export function provideManage(factory) {
    manageFactory = factory;
}

/**
 * Fills the view's hook points:
 * - toolbar(view): nodes added to the tools row, before Upload
 * - selectionBar(view): what the footer shows, drawn again on every change
 * - sidePanel(view): a panel beside the images
 * - change(view): called when the selection or the focused image changes
 * - uploadMenu(view): more items for Upload, which then opens a menu whose
 *   first item is "Upload images"
 * - treeMenu({ path, anchor, view }): opened from a folder's actions button,
 *   its context menu or Shift+F10
 */
export function extend(view, hooks = {}) {
    Object.assign(view.hooks, hooks);
    view.applyHooks();
    return view;
}

export function manageHooksFor(view) {
    return manageFactory ? manageFactory(view) : null;
}

/* ------------------------------------------------------- no permission */

/** Shown instead of the library to someone who may not look at it. */
export function createRefusal({ modal, onClose }) {
    const id = `ml-${(views += 1)}`;
    const title = h('h2', { class: 'ml-title', id: `${id}-title`, tabindex: '-1', text: 'Media library' });
    const done = h('button', { type: 'button', class: 'ml-btn ml-btn--primary', text: 'Close' });
    const root = h(modal ? 'dialog' : 'div', {
        class: `ml-root ${modal ? 'ml-modal ml-modal--small' : 'ml-page'}`,
        'aria-labelledby': `${id}-title`,
    },
    h('div', { class: 'ml-frame' },
        h('header', { class: 'ml-head' }, h('div', { class: 'ml-heading' }, title)),
        h('div', { class: 'ml-state' },
            h('span', { class: 'ml-state-icon' }, icon('lock')),
            h('p', { class: 'ml-state-title', text: 'Ask an admin for the Media view permission.' })),
        modal ? h('footer', { class: 'ml-foot ml-foot--end' }, done) : null));

    done.addEventListener('click', () => onClose?.());
    if (modal) {
        root.addEventListener('close', () => onClose?.());
        root.addEventListener('cancel', (e) => {
            e.preventDefault();
            onClose?.();
        });
    }

    return { root, focus: () => done.focus() };
}

/* ------------------------------------------------------------- the view */

/**
 * @param {object} args
 * @param {object} args.api      requests, from createApi()
 * @param {object} args.config   the page's <x-media-library /> settings
 * @param {object} args.options  open() or mount() options
 * @param {boolean} args.modal   true inside the dialog
 * @param {Function} args.onDone called with the chosen files, or null on cancel
 */
export function createView({ api, config, options, modal, onDone }) {
    const id = `ml-${(views += 1)}`;
    const can = { upload: false, ...(config.can || {}) };
    const mode = options.mode === 'multi' ? 'multi' : 'single';
    const values = Array.isArray(options.value) ? options.value.filter(Boolean) : options.value ? [options.value] : [];
    const kind = options.kind || 'default';
    const accept = (options.accept || config.accept || []).map((e) => String(e).toLowerCase());
    const uploadMimes = [...new Set((config.uploadAccept || []).map((e) => MIME[String(e).toLowerCase()]).filter(Boolean))];
    const maxFiles = config.maxFiles || 20;
    const inUse = new Set(options.inUse || []);

    const S = {
        path: ROOT, q: '', sort: 'newest', size: thumbSize(), filter: '', expanded: new Set([ROOT]),
        tree: new Map(), files: [], folders: [], page: 0, lastPage: 1, total: 0,
        error: null, moreError: null, loading: false, loadingMore: false, token: 0,
        uploads: [], running: false, creating: null, focus: 0, focused: null, anchor: null,
        known: new Map(), closed: false, menu: null,
        sel: new Selection({ mode, limit: mode === 'single' ? 1 : (options.limit ?? null), value: values, inUse: [...inUse] }),
    };

    let searchTimer = 0;
    let observer = null;
    let dragDepth = 0;

    /* ----------------------------------------------------------- skeleton */

    const els = {};
    const btn = (cls, label, ...kids) => h('button', { type: 'button', class: cls }, ...kids, label);

    els.title = h('h2', { class: 'ml-title', id: `${id}-title`, tabindex: '-1', text: options.title || (mode === 'multi' ? 'Add images' : 'Choose an image') });
    els.where = h('p', { class: 'ml-where' });
    els.close = modal ? h('button', { type: 'button', class: 'ml-icon-btn ml-close', 'aria-label': 'Close' }, icon('close')) : null;

    els.newFolder = can.upload ? btn('ml-btn ml-btn--ghost ml-btn--sm', 'New folder', icon('folder-plus', 'ml-icon--sm')) : null;
    els.filter = h('input', { class: 'ml-input', type: 'search', placeholder: 'Filter folders', 'aria-label': 'Filter folders', autocomplete: 'off' });
    els.tree = h('ul', { class: 'ml-tree', role: 'tree', 'aria-label': 'Folders' });
    els.side = h('nav', { class: 'ml-side', 'aria-labelledby': `${id}-folders` },
        h('div', { class: 'ml-side-head' }, h('h3', { class: 'ml-side-title', id: `${id}-folders`, text: 'Folders' }), els.newFolder),
        h('div', { class: 'ml-filter' }, icon('filter', 'ml-icon--sm'), els.filter),
        els.tree);

    els.search = h('input', { class: 'ml-input', type: 'search', placeholder: 'Search all images', 'aria-label': 'Search all images', autocomplete: 'off' });
    els.sort = h('select', { class: 'ml-select ml-sort', id: `${id}-sort` },
        h('option', { value: 'newest', text: 'Newest first' }),
        h('option', { value: 'name', text: 'Name A to Z' }));
    els.sizes = ['small', 'large'].map((size) => h('button', {
        type: 'button', class: 'ml-icon-btn', 'data-size': size, 'aria-pressed': 'false',
        'aria-label': size === 'small' ? 'Small thumbnails' : 'Large thumbnails',
    }, icon(`grid-${size}`)));
    els.upload = can.upload ? h('button', { type: 'button', class: 'ml-btn ml-btn--secondary ml-upload' },
        icon('upload', 'ml-icon--sm'), h('span', { class: 'ml-upload-label', text: 'Upload' }),
        icon('chevron-down', 'ml-icon--xs ml-upload-caret')) : null;
    els.file = can.upload ? h('input', {
        type: 'file', multiple: true, hidden: true,
        accept: [...(config.uploadAccept || []).map((e) => `.${e}`), ...uploadMimes].join(','),
    }) : null;
    els.tools = h('div', { class: 'ml-tools' },
        h('div', { class: 'ml-search' }, icon('search', 'ml-icon--sm'), els.search),
        h('label', { class: 'ml-sr', for: `${id}-sort`, text: 'Sort' }), els.sort,
        h('div', { class: 'ml-size', role: 'group', 'aria-label': 'Thumbnail size' }, els.sizes),
        els.upload, els.file);

    els.up = h('button', { type: 'button', class: 'ml-icon-btn ml-up', 'aria-label': 'Up one folder' }, icon('arrow-left'));
    els.crumbs = h('nav', { class: 'ml-crumbs', 'aria-label': 'Folder path' });
    els.count = h('p', { class: 'ml-count' });
    els.bar = h('div', { class: 'ml-bar' }, els.up, els.crumbs, els.count);

    els.folders = h('div', { class: 'ml-folders' });
    els.grid = h('div', { class: 'ml-grid', role: 'listbox', 'aria-label': 'Images', tabindex: '-1' });
    els.state = h('div', { class: 'ml-state', hidden: true });
    els.sentinel = h('div', { class: 'ml-sentinel', 'aria-hidden': 'true' });
    els.scroll = h('div', { class: 'ml-scroll' }, els.folders, els.grid, els.state, els.sentinel);
    els.dropText = h('p', { class: 'ml-drop-text' });
    els.drop = h('div', { class: 'ml-drop', hidden: true }, icon('upload'), els.dropText);
    els.main = h('section', { class: 'ml-main', 'aria-label': 'Images' }, els.tools, els.bar, els.scroll, els.drop);
    els.body = h('div', { class: 'ml-body' }, els.side, els.main);

    els.picked = h('div', { class: 'ml-picked' });
    els.cancel = modal ? h('button', { type: 'button', class: 'ml-btn ml-btn--ghost ml-cancel', text: 'Cancel' }) : null;
    els.choose = options.onChoose || modal ? h('button', { type: 'button', class: 'ml-btn ml-btn--primary ml-choose', disabled: true }) : null;
    els.actions = h('div', { class: 'ml-actions' }, els.cancel, els.choose);
    els.foot = h('footer', { class: 'ml-foot' }, els.picked, els.actions);
    els.live = h('p', { class: 'ml-sr', 'aria-live': 'polite' });

    els.frame = h('div', { class: 'ml-frame' },
        h('header', { class: 'ml-head' }, h('div', { class: 'ml-heading' }, els.title, els.where), els.close),
        els.body, els.foot, els.live);
    const root = h(modal ? 'dialog' : 'div', {
        class: `ml-root ${modal ? 'ml-modal' : 'ml-page'}`, 'aria-labelledby': `${id}-title`,
        'data-mode': mode, 'data-size': S.size,
    }, els.frame);

    const where = (options.where || []).filter(Boolean).map(String);
    if (where.length) {
        els.where.append(icon('image', 'ml-icon--sm'), h('span', null, h('strong', { text: where[0] }), where.length > 1 ? `, ${where.slice(1).join(', ')}` : ''));
    } else {
        els.where.hidden = true;
    }

    /* --------------------------------------------------------------- tree */

    const nameOf = (path) => S.tree.get(path)?.name ?? (path === ROOT ? 'Media' : basename(path));

    async function loadTree() {
        const data = await api.tree();
        S.tree = buildTree(data.folders || []);
    }

    function treeRows() {
        const term = S.filter.trim();
        const keep = term ? filterTree(S.tree, term) : null;
        const rows = [];
        const walk = (path, level) => {
            const node = S.tree.get(path);
            if (!node || (keep && !keep.has(path))) return;
            const open = keep ? true : S.expanded.has(path);
            rows.push({ node, level, open });
            if (open) node.children.forEach((child) => walk(child, level + 1));
        };
        walk(ROOT, 0);
        const matched = term ? [...S.tree.values()].filter((n) => n.path !== ROOT && n.name.toLowerCase().includes(term.toLowerCase())).length : null;
        return { rows, matched };
    }

    function drawTree() {
        const { rows, matched } = treeRows();
        const term = S.filter.trim();
        const items = new Map();
        let top = null;

        rows.forEach(({ node, level, open }) => {
            const hasKids = node.children.length > 0;
            const li = h('li', {
                class: 'ml-tree__item', role: 'treeitem', 'aria-level': level + 1, 'data-path': node.path,
                'aria-selected': String(node.path === S.path && !S.q), 'aria-expanded': hasKids ? String(open) : null, tabindex: '-1',
            },
            h('div', { class: `ml-tree__row ml-tree__row--l${Math.min(level, 5)}` },
                hasKids
                    ? h('button', { type: 'button', class: 'ml-tree__toggle', tabindex: '-1', 'aria-hidden': 'true' }, icon('chevron-right', 'ml-icon--xs'))
                    : h('span'),
                icon(node.path === ROOT ? 'image' : 'folder', 'ml-icon--sm ml-tree__icon'),
                h('span', { class: 'ml-tree__name' }, ...highlight(node.name, term)),
                h('span', { class: 'ml-tree__count', text: fmt(node.count) }),
                view.hooks.treeMenu ? h('button', {
                    type: 'button', class: 'ml-icon-btn ml-icon-btn--sm ml-tree__more', tabindex: '-1',
                    'aria-label': `Actions for ${node.name}`, 'aria-haspopup': 'menu',
                }, icon('more')) : null));
            items.set(node.path, li);

            const parent = items.get(node.parent);
            if (!parent) {
                top = li;
            } else {
                let group = parent.querySelector('.ml-tree__group');
                if (!group || group.parentNode !== parent) {
                    group = h('ul', { class: 'ml-tree__group', role: 'group' });
                    parent.append(group);
                }
                group.append(li);
            }
        });

        els.tree.classList.toggle('has-menu', Boolean(view.hooks.treeMenu));
        els.tree.replaceChildren(...(top ? [top] : []));
        if (matched === 0) els.tree.append(h('li', { class: 'ml-tree__none', role: 'none', text: 'No folder matches.' }));
        if (S.creating !== null) drawNewFolderRow();
        syncTreeTabStop();
    }

    const treeItems = () => [...els.tree.querySelectorAll('.ml-tree__item')];
    const treeItem = (path) => treeItems().find((li) => li.dataset.path === path) || null;

    function syncTreeTabStop() {
        const items = treeItems();
        const current = items.find((li) => li.getAttribute('aria-selected') === 'true') || items[0];
        items.forEach((li) => { li.tabIndex = li === current ? 0 : -1; });
    }

    function scrollTreeToCurrent() {
        const row = els.tree.querySelector('.ml-tree__item[aria-selected="true"] > .ml-tree__row');
        if (!row) return;
        const box = els.tree.getBoundingClientRect();
        const r = row.getBoundingClientRect();
        if (r.top < box.top || r.bottom > box.bottom) els.tree.scrollTop += r.top - box.top - box.height / 3;
    }

    function toggle(path, force) {
        const open = force ?? !S.expanded.has(path);
        if (open) S.expanded.add(path);
        else S.expanded.delete(path);
        drawTree();
        treeItem(path)?.focus();
    }

    els.tree.addEventListener('click', (e) => {
        const li = e.target.closest('.ml-tree__item');
        if (!li || !e.target.closest('.ml-tree__row')) return;
        const more = e.target.closest('.ml-tree__more');
        if (more && view.hooks.treeMenu) {
            view.hooks.treeMenu({ path: li.dataset.path, anchor: more, view });
            return;
        }
        if (e.target.closest('.ml-tree__toggle')) {
            toggle(li.dataset.path);
            return;
        }
        goFolder(li.dataset.path);
    });

    els.tree.addEventListener('contextmenu', (e) => {
        const li = e.target.closest('.ml-tree__item');
        if (!li || !view.hooks.treeMenu) return;
        e.preventDefault();
        view.hooks.treeMenu({ path: li.dataset.path, anchor: li, view });
    });

    els.tree.addEventListener('keydown', (e) => {
        const li = e.target.closest('.ml-tree__item');
        if (!li || e.target.tagName === 'INPUT') return;
        const items = treeItems();
        const i = items.indexOf(li);
        const path = li.dataset.path;
        const moveTo = (el) => {
            if (!el) return;
            items.forEach((x) => { x.tabIndex = -1; });
            el.tabIndex = 0;
            el.focus();
        };
        switch (e.key) {
            case 'ArrowDown': moveTo(items[i + 1]); break;
            case 'ArrowUp': moveTo(items[i - 1]); break;
            case 'Home': moveTo(items[0]); break;
            case 'End': moveTo(items[items.length - 1]); break;
            case 'ArrowRight':
                if (li.getAttribute('aria-expanded') === 'false') toggle(path, true);
                else if (li.getAttribute('aria-expanded') === 'true') moveTo(items[i + 1]);
                break;
            case 'ArrowLeft':
                if (li.getAttribute('aria-expanded') === 'true' && !S.filter) toggle(path, false);
                else moveTo(items.find((x) => x.dataset.path === S.tree.get(path)?.parent));
                break;
            case 'Enter':
            case ' ':
                goFolder(path, { keepFocus: true });
                break;
            case 'F10':
                if (!e.shiftKey || !view.hooks.treeMenu) return;
                view.hooks.treeMenu({ path, anchor: li, view });
                break;
            default: return;
        }
        e.preventDefault();
    });

    els.filter.addEventListener('input', () => {
        S.filter = els.filter.value;
        drawTree();
    });

    els.filter.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowDown') return;
        e.preventDefault();
        const items = treeItems();
        const first = S.filter ? items.find((li) => li.firstElementChild?.querySelector('mark')) : items[0];
        first?.focus();
    });

    /* --------------------------------------------------------- new folder */

    els.newFolder?.addEventListener('click', () => startNewFolder(S.path));

    function startNewFolder(parent) {
        S.creating = parent;
        S.filter = '';
        els.filter.value = '';
        S.expanded.add(parent);
        ancestors(parent).forEach((a) => S.expanded.add(a));
        drawTree();
        const input = els.tree.querySelector('.ml-tree__new .ml-input');
        input?.focus();
        input?.scrollIntoView({ block: 'nearest' });
    }

    function drawNewFolderRow(error = '', value = '', busy = false) {
        const parentLi = treeItem(S.creating);
        if (!parentLi) return;
        let group = [...parentLi.children].find((c) => c.classList.contains('ml-tree__group'));
        if (!group) {
            group = h('ul', { class: 'ml-tree__group', role: 'group' });
            parentLi.append(group);
        }
        group.querySelector('.ml-tree__new')?.remove();
        const level = Number(parentLi.getAttribute('aria-level'));
        const input = h('input', {
            class: 'ml-input', type: 'text', maxlength: '120', autocomplete: 'off',
            'aria-label': `New folder in ${nameOf(S.creating)}`,
            'aria-invalid': error ? 'true' : null, 'aria-describedby': error ? `${id}-folder-error` : null,
            disabled: busy,
        });
        input.value = value;
        input.addEventListener('keydown', (e) => {
            e.stopPropagation();
            if (e.key === 'Enter') {
                e.preventDefault();
                createFolder(input.value);
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                cancelNewFolder();
            }
        });
        input.addEventListener('blur', () => {
            setTimeout(() => {
                if (S.creating !== null && !input.disabled && !input.value.trim() && document.activeElement !== input) cancelNewFolder(false);
            }, 0);
        });
        group.prepend(h('li', { class: `ml-tree__new ml-tree__row--l${Math.min(level, 5)}`, role: 'none' },
            h('span', { 'aria-hidden': 'true' }), icon('folder-plus', 'ml-icon--sm ml-tree__icon'), input,
            error ? h('p', { class: 'ml-tree__new-error', id: `${id}-folder-error`, role: 'alert', text: error }) : null));
        return input;
    }

    function cancelNewFolder(refocus = true) {
        const parent = S.creating;
        S.creating = null;
        drawTree();
        if (refocus) treeItem(parent)?.focus();
    }

    async function createFolder(raw) {
        const name = raw.trim();
        const parent = S.tree.get(S.creating);
        let error = folderNameError(raw) || '';
        if (!error && parent?.children.some((c) => S.tree.get(c)?.name === name)) error = `A folder called ${name} is already here.`;
        if (error) {
            drawNewFolderRow(error, raw).focus();
            return;
        }

        drawNewFolderRow('', raw, true);
        try {
            const data = await api.makeFolder(S.creating, name);
            S.creating = null;
            await loadTree().catch(() => {});
            announce(`Folder ${name} made.`);
            goFolder(data.path || `${parent.path}/${name}`, { keepFocus: true });
        } catch (e) {
            if (S.closed) return;
            drawNewFolderRow(firstMessage(e), raw).focus();
        }
    }

    /* --------------------------------------------------------- navigation */

    function goFolder(path, { keepFocus = false } = {}) {
        S.path = path;
        S.q = '';
        els.search.value = '';
        S.expanded.add(path);
        ancestors(path).forEach((a) => S.expanded.add(a));
        drawTree();
        if (keepFocus) treeItem(path)?.focus();
        return load();
    }

    els.up.addEventListener('click', () => {
        if (S.q) {
            clearSearch();
            return;
        }
        const parent = S.tree.get(S.path)?.parent;
        if (parent) goFolder(parent);
    });

    function clearSearch() {
        clearTimeout(searchTimer);
        S.q = '';
        els.search.value = '';
        drawTree();
        return load();
    }

    els.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            S.q = els.search.value.trim();
            drawTree();
            load();
        }, 250);
    });

    els.search.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && els.search.value) {
            e.preventDefault();
            e.stopPropagation();
            clearSearch();
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            tileAt(S.focus)?.focus();
        }
    });

    els.sort.addEventListener('change', () => {
        S.sort = els.sort.value;
        load();
    });

    els.sizes.forEach((b) => b.addEventListener('click', () => {
        S.size = b.dataset.size;
        rememberThumbSize(S.size);
        root.dataset.size = S.size;
        syncSize();
    }));

    function syncSize() {
        els.sizes.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.size === S.size)));
    }

    /* ------------------------------------------------------------ loading */

    function take(data) {
        S.page = data.page || 1;
        S.lastPage = data.lastPage || 1;
        S.total = data.total ?? (data.files || []).length;
        S.folders = data.folders || [];
        const fresh = (data.files || []).filter((f) => !S.files.some((x) => x.path === f.path));
        fresh.forEach((f) => S.known.set(f.path, f));
        S.files.push(...fresh);
        return fresh;
    }

    async function load() {
        const token = ++S.token;
        S.error = null;
        S.moreError = null;
        S.files = [];
        S.folders = [];
        S.page = 0;
        S.lastPage = 1;
        S.total = 0;
        S.focus = 0;
        S.focused = null;
        els.scroll.scrollTop = 0;
        drawBar();
        showLoading(false);
        try {
            const data = await api.browse({ path: S.path, q: S.q, sort: S.sort, page: 1 });
            if (token !== S.token || S.closed) return false;
            take(data);
        } catch (e) {
            if (token !== S.token || S.closed) return false;
            S.error = e;
        }
        drawFolders();
        drawGrid();
        drawCount();
        drawFoot();
        if (!S.error) announce(S.q ? `${plural(S.total, 'image')} found` : `${nameOf(S.path)}, ${plural(S.total, 'image')}`);
        return true;
    }

    /** The next page of the list in view. Resolves true when tiles were added. */
    async function more() {
        if (S.loadingMore || S.error || S.page >= S.lastPage) return false;
        S.loadingMore = true;
        S.moreError = null;
        els.grid.append(...Array.from({ length: 6 }, skeletonTile));
        const token = S.token;
        try {
            const data = await api.browse({ path: S.path, q: S.q, sort: S.sort, page: S.page + 1 });
            if (token !== S.token || S.closed) return false;
            appendTiles(take(data));
            return true;
        } catch (e) {
            if (token !== S.token) return false;
            S.moreError = e;
            return false;
        } finally {
            S.loadingMore = false;
            if (token === S.token && !S.closed) {
                els.grid.querySelectorAll('.ml-tile--sk').forEach((t) => t.remove());
                drawState();
            }
        }
    }

    function showLoading(tree) {
        els.state.hidden = true;
        els.folders.className = 'ml-folders';
        els.folders.replaceChildren();
        els.grid.setAttribute('aria-busy', 'true');
        els.grid.replaceChildren(...Array.from({ length: 24 }, skeletonTile));
        els.count.textContent = '';
        if (tree) {
            els.tree.replaceChildren(...Array.from({ length: 14 }, () => h('li', { class: 'ml-sk ml-tree__sk', role: 'none', 'aria-hidden': 'true' })));
            els.crumbs.replaceChildren(h('span', { class: 'ml-sk', 'aria-hidden': 'true' }));
            drawFoot();
        }
    }

    function skeletonTile() {
        return h('div', { class: 'ml-tile ml-tile--sk', 'aria-hidden': 'true' },
            h('span', { class: 'ml-tile__img ml-sk' }), h('span', { class: 'ml-sk ml-sk-name' }));
    }

    function drawBar() {
        const atRoot = !S.q && S.path === ROOT;
        els.up.hidden = atRoot;
        els.up.setAttribute('aria-label', S.q ? 'Back to folders' : 'Up one folder');
        els.bar.classList.toggle('is-root', atRoot);
        if (S.q) {
            els.crumbs.replaceChildren(
                h('span', { class: 'ml-crumbs__here' }, `Results for “${S.q}”`),
                h('button', { type: 'button', class: 'ml-link-btn ml-crumbs__clear', onclick: () => clearSearch() }, 'Clear search'));
            return;
        }
        const chain = [...ancestors(S.path), S.path];
        const parts = [];
        chain.forEach((p, i) => {
            if (i) parts.push(icon('chevron-right', 'ml-icon--xs'));
            parts.push(i === chain.length - 1
                ? h('span', { class: 'ml-crumbs__here', 'aria-current': 'location', text: nameOf(p) })
                : h('button', { type: 'button', class: 'ml-crumbs__link', onclick: () => goFolder(p), text: nameOf(p) }));
        });
        els.crumbs.replaceChildren(...parts);
    }

    function drawCount() {
        const live = S.uploads.filter((u) => u.state !== 'failed');
        if (live.length) {
            const done = S.uploadsDone || 0;
            const all = live.length + done;
            els.count.className = 'ml-count ml-count--busy';
            els.count.textContent = `Uploading ${Math.min(done + 1, all)} of ${all} to ${nameOf(live[0].to)}`;
            return;
        }
        S.uploadsDone = 0;
        els.count.className = 'ml-count';
        if (S.error) {
            els.count.textContent = '';
            return;
        }
        const kids = S.tree.get(S.path)?.children.length || 0;
        const bits = [plural(S.total, 'image')];
        if (S.q && S.folders.length) bits.unshift(plural(S.folders.length, 'folder'));
        if (!S.q && kids && !S.total) bits.splice(0, 1);
        if (!S.q && kids && (!isPhone() || !S.total)) bits.push(plural(kids, 'folder'));
        els.count.textContent = bits.join(', ');
    }

    /* Matching folders in a search; on phones also the folder's own folders. */
    function drawFolders() {
        const rowsFor = (list, showWhere) => list.map((f) => {
            const parent = f.parent ?? dirname(f.path);
            const count = S.tree.get(f.path)?.count ?? f.count ?? 0;
            return [
                h('button', { type: 'button', class: 'ml-fchip', onclick: () => goFolder(f.path) },
                    icon('folder', 'ml-icon--sm'), h('span', null, ...highlight(f.name, S.q)),
                    showWhere ? h('span', { class: 'ml-fchip__where', text: `in ${folderLabel(parent)}` }) : null,
                    h('span', { class: 'ml-fchip__count', text: fmt(count) })),
                h('button', { type: 'button', class: 'ml-frow', onclick: () => goFolder(f.path) },
                    icon('folder'),
                    h('span', { class: 'ml-frow__name' }, h('span', null, ...highlight(f.name, S.q)),
                        showWhere ? h('span', { class: 'ml-frow__where', text: folderLabel(parent) }) : null),
                    h('span', { class: 'ml-frow__count', text: fmt(count) }),
                    icon('chevron-right', 'ml-icon--sm')),
            ];
        }).flat();

        if (S.error) {
            els.folders.className = 'ml-folders';
            els.folders.replaceChildren();
            return;
        }
        if (S.q) {
            els.folders.className = S.folders.length ? 'ml-folders is-results' : 'ml-folders';
            els.folders.replaceChildren(...rowsFor(S.folders, true));
            return;
        }
        els.folders.className = S.folders.length ? 'ml-folders is-rows' : 'ml-folders';
        els.folders.replaceChildren(...rowsFor(S.folders, false));
    }

    function drawGrid() {
        els.grid.setAttribute('aria-busy', 'false');
        els.grid.setAttribute('aria-multiselectable', String(mode === 'multi'));
        els.grid.replaceChildren(...S.uploads.filter((u) => u.to === S.path && !S.q).map(uploadTile));
        if (!S.error) els.grid.append(...S.files.map((f, i) => fileTile(f, i)));
        syncTabStops();
        drawState();
        watchSentinel();
    }

    function appendTiles(fresh) {
        const start = S.files.length - fresh.length;
        els.grid.append(...fresh.map((f, i) => fileTile(f, start + i)));
        syncTabStops();
    }

    function stateBox(kindClass, iconName, title, text, action) {
        els.state.hidden = false;
        els.state.className = `ml-state${kindClass ? ` ${kindClass}` : ''}`;
        els.state.replaceChildren(...[h('span', { class: 'ml-state-icon' }, icon(iconName)), title,
            text ? h('p', { class: 'ml-state-text', text }) : null, action].filter(Boolean));
    }

    function drawState() {
        if (S.error) {
            const title = S.q ? 'The search did not finish.' : `${nameOf(S.path)} did not load.`;
            stateBox('ml-state--error', 'alert', h('p', { class: 'ml-state-title', text: title }), S.error.message,
                h('button', { type: 'button', class: 'ml-btn ml-btn--secondary', onclick: () => load() }, 'Try again'));
            return;
        }
        if (S.moreError) {
            stateBox('ml-state--error ml-state--more', 'alert', h('p', { class: 'ml-state-title', text: 'More images did not load.' }), S.moreError.message,
                h('button', { type: 'button', class: 'ml-btn ml-btn--secondary', onclick: () => more() }, 'Try again'));
            return;
        }
        const shownUploads = S.uploads.some((u) => u.to === S.path && !S.q);
        const empty = !S.files.length && !shownUploads;
        // On a phone a folder's own folders are rows above the grid, so a
        // folder with folders and no loose images is not empty there.
        const hasRows = !S.q && S.folders.length && isPhone();
        if (!empty || hasRows || (S.q && S.folders.length)) {
            els.state.hidden = true;
            return;
        }
        if (S.q) {
            stateBox('', 'search', h('p', { class: 'ml-state-title' }, 'Nothing named ', h('q', { text: S.q })), '',
                h('button', { type: 'button', class: 'ml-btn ml-btn--secondary', onclick: () => clearSearch() }, 'Clear search'));
            return;
        }
        stateBox('', 'image', h('p', { class: 'ml-state-title', text: `No images in ${nameOf(S.path)}` }), '',
            can.upload ? h('button', { type: 'button', class: 'ml-btn ml-btn--secondary', onclick: () => els.file.click() }, icon('upload', 'ml-icon--sm'), 'Upload images') : null);
    }

    function watchSentinel() {
        observer?.disconnect();
        if (typeof IntersectionObserver === 'undefined') return;
        observer = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) more();
        }, { root: els.scroll, rootMargin: '0px 0px 600px 0px' });
        observer.observe(els.sentinel);
    }

    const choosable = (path) => accept.length === 0 || accept.includes(extension(path));

    function fileTile(f, index) {
        const n = S.sel.number(f.path);
        const picked = S.sel.has(f.path);
        const dims = f.width && f.height ? `${f.width} × ${f.height}` : '';
        const off = !choosable(f.path) || S.sel.disabled(f.path);
        return h('div', {
            class: `ml-tile${off ? ' is-off' : ''}`, role: 'option', id: `${id}-tile-${index}`, 'data-index': index, 'data-path': f.path,
            'aria-selected': String(picked), 'aria-disabled': off ? 'true' : null, tabindex: '-1',
            'aria-label': [f.name, dims ? `${f.width} by ${f.height}` : null, formatSize(f.size), S.q ? `in ${folderLabel(f.folder)}` : null].filter(Boolean).join(', '),
        },
        h('span', { class: 'ml-tile__img' },
            h('img', { src: f.thumb || f.url, alt: '', loading: 'lazy', decoding: 'async', width: '240', height: '240', draggable: 'false' }),
            inUse.has(f.path) ? h('span', { class: 'ml-tile__pill', text: 'Already added' }) : null,
            h('span', { class: 'ml-tile__mark', 'aria-hidden': 'true' }, markContent(picked, n)),
            h('span', { class: 'ml-tile__meta', 'aria-hidden': 'true', text: [dims, formatSize(f.size)].filter(Boolean).join(' · ') })),
        h('span', { class: 'ml-tile__text', 'aria-hidden': 'true' },
            h('span', { class: 'ml-tile__name' }, ...highlight(f.name, S.q)),
            S.q ? h('span', { class: 'ml-tile__where', text: folderLabel(f.folder) }) : null));
    }

    function markContent(picked, n) {
        if (!picked) return null;
        return mode === 'multi' ? String(n) : icon('check');
    }

    function uploadTile(u) {
        if (u.state === 'failed') {
            return h('div', { class: 'ml-tile ml-tile--up ml-tile--failed', 'aria-label': `${u.name}: ${u.error}` },
                h('span', { class: 'ml-tile__img' }, icon('alert'),
                    h('button', { type: 'button', class: 'ml-icon-btn ml-icon-btn--sm ml-tile__dismiss', 'aria-label': `Dismiss ${u.name}`, onclick: () => dismissUpload(u.id) }, icon('close'))),
                h('span', { class: 'ml-tile__text' }, h('span', { class: 'ml-tile__name', text: u.name }), h('span', { class: 'ml-tile__upnote', text: u.error })));
        }
        const queued = u.state === 'queued';
        const bar = h('progress', { class: 'ml-tile__progress', max: '100', 'aria-label': `Uploading ${u.name}` });
        bar.value = u.progress || 0;
        return h('div', { class: `ml-tile ml-tile--up${queued ? ' ml-tile--queued' : ''}`, 'data-up': u.id },
            h('span', { class: 'ml-tile__img' }, u.src ? h('img', { src: u.src, alt: '', draggable: 'false' }) : null, bar),
            h('span', { class: 'ml-tile__text' }, h('span', { class: 'ml-tile__name', text: u.name }),
                h('span', { class: 'ml-tile__upnote', text: queued ? 'Waiting' : `${u.progress || 0}%` })));
    }

    /** Moves one tile's bar and percentage without drawing the grid again. */
    function showProgress(u, percent) {
        u.progress = percent;
        const tile = els.grid.querySelector(`[data-up="${u.id}"]`);
        if (!tile) return;
        tile.querySelector('.ml-tile__progress').value = percent;
        tile.querySelector('.ml-tile__upnote').textContent = `${percent}%`;
    }

    /* ----------------------------------------------------------- choosing */

    const tiles = () => [...els.grid.querySelectorAll('.ml-tile[role="option"]')];
    const tileAt = (i) => tiles().find((t) => Number(t.dataset.index) === i) || null;

    function syncTabStops() {
        const list = tiles();
        list.forEach((t) => { t.tabIndex = Number(t.dataset.index) === S.focus ? 0 : -1; });
        if (!tileAt(S.focus) && list[0]) list[0].tabIndex = 0;
    }

    function roomMessage() {
        const limit = S.sel.limit;
        announce(`${limit === 1 ? 'Only 1 image fits' : `Only ${limit} images fit`}. Remove one to pick another.`);
        els.picked.querySelector('.ml-pick-sum__room')?.classList.add('ml-pick-sum__room--full');
    }

    function select(path, { range = false } = {}) {
        if (!choosable(path)) return;
        if (mode === 'single') {
            S.sel.set(path);
        } else {
            if (!S.sel.has(path) && S.sel.full()) {
                roomMessage();
                return;
            }
            S.sel.toggle(path, { range, list: S.files.map((f) => f.path) });
            if (range && S.sel.full()) roomMessage();
        }
        S.anchor = path;
        syncMarks(path);
        drawFoot();
    }

    function syncMarks(justPicked) {
        tiles().forEach((t) => {
            const picked = S.sel.has(t.dataset.path);
            t.setAttribute('aria-selected', String(picked));
            t.classList.toggle('is-picked-now', t.dataset.path === justPicked && picked);
            t.querySelector('.ml-tile__mark').replaceChildren(...[markContent(picked, S.sel.number(t.dataset.path))].filter(Boolean));
        });
    }

    els.grid.addEventListener('click', (e) => {
        const tile = e.target.closest('.ml-tile[role="option"]');
        if (!tile) return;
        S.focus = Number(tile.dataset.index);
        S.focused = tile.dataset.path;
        syncTabStops();
        tile.focus({ preventScroll: true });
        select(tile.dataset.path, { range: e.shiftKey });
    });

    els.grid.addEventListener('dblclick', (e) => {
        const tile = e.target.closest('.ml-tile[role="option"]');
        if (tile && mode === 'single' && els.choose) choose();
    });

    function columns() {
        const list = tiles();
        if (list.length < 2) return 1;
        const top = list[0].offsetTop;
        const i = list.findIndex((t) => t.offsetTop !== top);
        return i < 0 ? list.length : i;
    }

    els.grid.addEventListener('keydown', async (e) => {
        const tile = e.target.closest('.ml-tile[role="option"]');
        if (!tile) return;
        const i = Number(tile.dataset.index);
        const cols = columns();
        const known = S.files.length - 1;
        const last = S.total - 1;
        let next = null;
        switch (e.key) {
            case 'ArrowRight': next = Math.min(last, i + 1); break;
            case 'ArrowLeft': next = Math.max(0, i - 1); break;
            case 'ArrowDown': next = i + cols <= last ? i + cols : i; break;
            case 'ArrowUp':
                if (i - cols < 0) {
                    e.preventDefault();
                    els.search.focus();
                    return;
                }
                next = i - cols;
                break;
            case 'Home': next = 0; break;
            case 'End': next = known; break;
            case 'PageDown': next = Math.min(last, i + cols * 4); break;
            case 'PageUp': next = Math.max(0, i - cols * 4); break;
            case ' ':
                e.preventDefault();
                S.focused = tile.dataset.path;
                select(tile.dataset.path);
                return;
            case 'Enter':
                e.preventDefault();
                if (!els.choose) return;
                if (!S.sel.has(tile.dataset.path) && (mode === 'single' || !S.sel.paths().length)) select(tile.dataset.path);
                choose();
                return;
            default: return;
        }
        e.preventDefault();
        // Arrow keys past the loaded images load the next batch.
        while (next > S.files.length - 1 && (await more()));
        next = Math.min(next, S.files.length - 1);
        S.focus = next;
        syncTabStops();
        const t = tileAt(next);
        if (!t) return;
        t.focus({ preventScroll: true });
        t.scrollIntoView({ block: 'nearest' });
        S.focused = t.dataset.path;
        if (mode === 'single' && !e.shiftKey) select(t.dataset.path);
        else if (mode === 'multi' && e.shiftKey) select(t.dataset.path, { range: true });
        else changed();
    });

    function thumbOf(file, cls) {
        const src = file?.thumb || file?.url;
        return src ? h('img', { class: cls, src, alt: '' }) : h('span', { class: `${cls} ml-thumb-blank` }, icon('image', 'ml-icon--sm'));
    }

    function drawFoot() {
        if (view.hooks.selectionBar) {
            els.picked.replaceChildren(...[view.hooks.selectionBar(view)].flat().filter(Boolean));
        } else if (mode === 'single') {
            drawFootSingle();
        } else {
            drawFootMulti();
        }
        if (els.choose) {
            els.choose.textContent = S.sel.buttonLabel();
            els.choose.disabled = S.sel.paths().length === 0;
        }
        changed();
    }

    /** Tells the page the selection or the focused image may have changed. */
    function changed() {
        view.hooks.change?.(view);
    }

    function drawFootSingle() {
        const path = S.sel.paths()[0];
        if (!path) {
            els.picked.replaceChildren(...(isPhone() ? [] : [h('p', { class: 'ml-pick-none', text: 'No image selected' })]));
            return;
        }
        const f = describe(S.known.get(path) || { path });
        const meta = [
            f.width && f.height ? `${f.width} × ${f.height}` : null,
            S.known.has(path) ? formatSize(f.size) : null,
            folderLabel(f.folder),
            S.known.has(path) ? usedByText(f.usedBy) || 'Not used' : null,
        ].filter(Boolean);
        els.picked.replaceChildren(h('div', { class: 'ml-pick-one' },
            thumbOf(S.known.get(path), 'ml-pick-one__thumb'),
            h('div', { class: 'ml-pick-one__text' },
                h('span', { class: 'ml-pick-one__name', text: f.name }),
                h('span', { class: 'ml-pick-one__meta' }, ...meta.map((text) => h('span', { text }))))));
    }

    function drawFootMulti() {
        const picked = S.sel.paths();
        const n = picked.length;
        const room = S.sel.remaining();
        els.picked.replaceChildren(...[
            n ? h('ol', { class: 'ml-pick-many', 'aria-label': 'Picked, in order' }, ...picked.map((p, i) => {
                const file = S.known.get(p);
                const name = file?.name ?? basename(p);
                return h('li', null, h('button', {
                    type: 'button', class: 'ml-pick-many__thumb', 'aria-label': `Remove ${name}`,
                    onclick: () => {
                        S.sel.remove(p);
                        syncMarks();
                        drawFoot();
                    },
                }, thumbOf(file, 'ml-pick-many__img'), h('span', { class: 'ml-pick-many__n', 'aria-hidden': 'true', text: String(i + 1) }),
                h('span', { class: 'ml-pick-many__x', 'aria-hidden': 'true' }, icon('close', 'ml-icon--sm'))));
            })) : null,
            h('div', { class: 'ml-pick-sum' },
                h('span', { class: 'ml-pick-sum__count', text: n ? `${fmt(n)} selected` : 'None selected' }),
                room === null ? null : h('span', {
                    class: `ml-pick-sum__room${room === 0 ? ' ml-pick-sum__room--full' : ''}`,
                    text: room === 0 ? 'No more fit' : `${room} more ${room === 1 ? 'fits' : 'fit'}`,
                })),
            n ? h('button', {
                type: 'button', class: 'ml-link-btn',
                onclick: () => {
                    S.sel.paths().forEach((p) => S.sel.remove(p));
                    syncMarks();
                    drawFoot();
                },
            }, 'Clear') : null,
        ].filter(Boolean));
    }

    function choose() {
        const picked = S.sel.paths();
        if (!picked.length) return;
        rememberFolder(kind, S.path);
        finish(picked.map((p) => describe(S.known.get(p) || { path: p })));
    }

    els.choose?.addEventListener('click', choose);
    els.cancel?.addEventListener('click', () => finish(null));
    els.close?.addEventListener('click', () => finish(null));

    function finish(chosen) {
        if (S.closed) return;
        onDone?.(chosen);
    }

    if (modal) {
        // However the dialog closes (a browser may refuse to cancel a second
        // Esc), the same cleanup runs: finish() is the one way out.
        root.addEventListener('close', () => finish(null));
        root.addEventListener('cancel', (e) => {
            e.preventDefault();
            if (S.menu) {
                closeMenu();
                return;
            }
            if (S.creating !== null) {
                cancelNewFolder();
                return;
            }
            if (!els.drop.hidden) {
                showDrop(false);
                return;
            }
            finish(null);
        });
    }

    root.addEventListener('keydown', (e) => {
        if (e.key === '/' && !['INPUT', 'SELECT', 'TEXTAREA'].includes(e.target.tagName)) {
            e.preventDefault();
            els.search.focus();
            els.search.select();
            return;
        }
        if (!modal || e.key !== 'Tab') return;
        // Tab stays inside the library: header, folders, tools, images, footer.
        const focusable = [...root.querySelectorAll('button, input, select, [tabindex="0"]')]
            .filter((el) => !el.disabled && el.offsetParent !== null && !el.closest('[hidden]') && el.tabIndex >= 0);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    /* ------------------------------------------------------------ uploads */

    if (can.upload) {
        els.upload.addEventListener('click', () => {
            if (!view.hooks.uploadMenu) {
                els.file.click();
                return;
            }
            openMenu(els.upload, [
                { label: 'Upload images', icon: 'upload', run: () => els.file.click() },
                ...[view.hooks.uploadMenu(view)].flat(),
            ]);
        });
        els.file.addEventListener('change', () => {
            const files = [...els.file.files];
            els.file.value = '';
            if (files.length) upload(files);
        });

        const hasFiles = (e) => [...(e.dataTransfer?.types || [])].includes('Files');
        els.main.addEventListener('dragenter', (e) => {
            if (!hasFiles(e)) return;
            dragDepth += 1;
            showDrop(true);
        });
        els.main.addEventListener('dragover', (e) => {
            if (hasFiles(e)) e.preventDefault();
        });
        els.main.addEventListener('dragleave', () => {
            dragDepth = Math.max(0, dragDepth - 1);
            if (!dragDepth) showDrop(false);
        });
        els.main.addEventListener('drop', (e) => {
            if (!hasFiles(e)) return;
            e.preventDefault();
            dragDepth = 0;
            showDrop(false);
            const files = [...(e.dataTransfer?.files || [])];
            if (files.length) upload(files);
        });
    }

    function showDrop(on) {
        els.drop.hidden = !on;
        els.dropText.textContent = `Drop to upload to ${nameOf(S.path)}`;
    }

    let uploadIds = 0;

    function upload(files) {
        if (S.q) clearSearch();
        const to = S.path;
        const { refused } = checkFiles(files, { maxBytes: config.maxBytes, accept: uploadMimes });
        const reasons = new Map(refused.map((r) => [r.file, r.reason]));
        let room = maxFiles;
        const added = files.map((file) => {
            let error = reasons.get(file) || '';
            if (!error && room <= 0) error = `Upload at most ${maxFiles} images at a time`;
            if (!error) room -= 1;
            return {
                id: `${id}-up-${(uploadIds += 1)}`, name: file.name, file, to, error,
                state: error ? 'failed' : 'queued',
                src: !error && globalThis.URL?.createObjectURL ? URL.createObjectURL(file) : null,
            };
        });
        S.uploads = [...added, ...S.uploads];
        drawGrid();
        drawCount();
        const going = added.filter((u) => !u.error).length;
        const refusedText = added.filter((u) => u.error).map((u) => `${u.name} was not uploaded: ${u.error}.`);
        announce([going ? `Uploading ${plural(going, 'image')} to ${nameOf(to)}.` : 'Nothing was uploaded.', ...refusedText].join(' '));
        runUploads();
    }

    function redrawUpload(u) {
        const tile = els.grid.querySelector(`[data-up="${u.id}"]`);
        if (tile) tile.replaceWith(uploadTile(u));
    }

    async function runUploads() {
        if (S.running) return;
        S.running = true;
        let next;
        let last = null;
        const refusedNow = [];
        while (!S.closed && (next = S.uploads.find((u) => u.state === 'queued'))) {
            const sending = next;
            sending.state = 'sending';
            sending.progress = 0;
            redrawUpload(sending);
            drawCount();
            try {
                const data = await api.upload([sending.file], sending.to, { onProgress: (p) => showProgress(sending, p) });
                if (S.closed) break;
                last = finishUpload(next, (data.files || [])[0]);
            } catch (e) {
                if (S.closed) break;
                next.state = 'failed';
                next.error = firstMessage(e);
                if (next.src) URL.revokeObjectURL(next.src);
                next.src = null;
                redrawUpload(next);
                drawCount();
                refusedNow.push(`${next.name} was not uploaded: ${next.error}`);
                announce(refusedNow[refusedNow.length - 1]);
            }
        }
        S.running = false;
        if (S.closed) return;
        // Counts in the tree include the new images.
        loadTree().then(() => !S.closed && drawTree()).catch(() => {});
        // The last word in the live region names every refusal, so one said
        // while another file was still sending is not lost.
        const done = last ? [`${last.name} uploaded and selected.`] : [];
        if (done.length || refusedNow.length) announce([...done, ...refusedNow].join(' '));
    }

    function finishUpload(u, file) {
        S.uploads = S.uploads.filter((x) => x !== u);
        S.uploadsDone = (S.uploadsDone || 0) + 1;
        if (u.src) URL.revokeObjectURL(u.src);
        if (!file) {
            drawGrid();
            drawCount();
            return null;
        }
        S.known.set(file.path, file);
        if (S.path === u.to && !S.q) {
            S.files = [file, ...S.files.filter((x) => x.path !== file.path)];
            S.total += 1;
        }
        if (mode === 'single') S.sel.set(file.path);
        else S.sel.add([file.path]);
        const keep = els.scroll.scrollTop;
        drawGrid();
        els.scroll.scrollTop = keep;
        drawCount();
        drawFoot();
        return file;
    }

    function dismissUpload(uploadId) {
        S.uploads = S.uploads.filter((u) => u.id !== uploadId);
        drawGrid();
        drawCount();
    }

    /* --------------------------------------------------------------- menus */

    /**
     * A small menu under `anchor`. Items are { label, icon, hint, disabled,
     * run }; a disabled item stays in the list with its hint, so the reason
     * an action is missing is said rather than hidden. Arrow keys move,
     * Esc and Tab close, and focus goes back to the anchor.
     */
    function openMenu(anchor, items) {
        closeMenu(false);
        const list = items.filter(Boolean);
        if (!list.length) return null;

        const buttons = list.map((item) => {
            const b = h('button', {
                type: 'button', class: 'ml-menu__item', role: 'menuitem', tabindex: '-1',
                'aria-disabled': item.disabled ? 'true' : null,
            },
            item.icon ? icon(item.icon, 'ml-icon--sm') : h('span', { class: 'ml-menu__blank' }),
            h('span', { class: 'ml-menu__text' }, h('span', { text: item.label }),
                item.hint ? h('span', { class: 'ml-menu__hint', text: item.hint }) : null));
            b.addEventListener('click', () => {
                if (item.disabled) return;
                closeMenu(false);
                item.run();
            });
            return b;
        });
        const menu = h('div', { class: 'ml-menu', role: 'menu' }, buttons);
        const enabled = () => buttons.filter((b) => b.getAttribute('aria-disabled') !== 'true');

        menu.addEventListener('keydown', (e) => {
            const all = enabled();
            const i = all.indexOf(document.activeElement);
            const to = { ArrowDown: all[(i + 1) % all.length], ArrowUp: all[(i - 1 + all.length) % all.length], Home: all[0], End: all[all.length - 1] }[e.key];
            if (to) {
                e.preventDefault();
                to.focus();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                closeMenu();
            } else if (e.key === 'Tab') {
                closeMenu(false);
            }
        });

        const outside = (e) => {
            if (!menu.contains(e.target) && !anchor.contains(e.target)) closeMenu(false);
        };
        document.addEventListener('pointerdown', outside, true);

        els.frame.append(menu);
        place(menu, anchor);
        anchor.setAttribute('aria-expanded', 'true');
        S.menu = { menu, anchor, outside };
        (enabled()[0] || buttons[0]).focus();
        return menu;
    }

    function place(menu, anchor) {
        const frame = els.frame.getBoundingClientRect();
        const a = anchor.getBoundingClientRect();
        const width = menu.offsetWidth || 220;
        const height = menu.offsetHeight || 0;
        let top = a.bottom - frame.top + 4;
        if (height && top + height > frame.height - 8) top = Math.max(8, a.top - frame.top - height - 4);
        const left = Math.min(Math.max(8, a.left - frame.left), Math.max(8, frame.width - width - 8));
        menu.style.top = `${top}px`;
        menu.style.left = `${left}px`;
    }

    function closeMenu(refocus = true) {
        if (!S.menu) return;
        const { menu, anchor, outside } = S.menu;
        S.menu = null;
        document.removeEventListener('pointerdown', outside, true);
        anchor.setAttribute('aria-expanded', 'false');
        menu.remove();
        if (refocus && anchor.isConnected) anchor.focus();
    }

    function announce(text) {
        els.live.textContent = '';
        setTimeout(() => { els.live.textContent = text; }, 30);
    }

    const media = globalThis.matchMedia?.(PHONE);
    const onMedia = () => {
        drawCount();
        drawFoot();
        drawState();
    };
    media?.addEventListener?.('change', onMedia);

    /* -------------------------------------------------------------- start */

    function startFolder() {
        const exists = (p) => S.tree.has(p);
        const candidates = [values[0] ? dirname(values[0]) : null, lastFolder(kind, exists), options.folder, ROOT];
        return candidates.find((p) => p && exists(p)) || ROOT;
    }

    async function start() {
        root.dataset.size = S.size;
        syncSize();
        showLoading(true);
        try {
            await loadTree();
        } catch (e) {
            if (S.closed) return;
            S.error = e;
            els.tree.replaceChildren();
            els.crumbs.replaceChildren();
            els.grid.replaceChildren();
            els.grid.setAttribute('aria-busy', 'false');
            stateBox('ml-state--error', 'alert', h('p', { class: 'ml-state-title', text: 'The media library did not load.' }), e.message,
                h('button', { type: 'button', class: 'ml-btn ml-btn--secondary', onclick: () => start() }, 'Try again'));
            return;
        }
        if (S.closed) return;
        S.path = startFolder();
        S.expanded = new Set([ROOT, ...ancestors(S.path), S.path]);
        drawTree();
        if (!(await load())) return;

        // The current image may sit further down its folder than one page.
        const current = S.sel.paths()[S.sel.paths().length - 1];
        if (current) {
            while (!S.files.some((f) => f.path === current) && (await more()));
        }
        const at = S.files.findIndex((f) => f.path === current);
        if (at >= 0) S.focus = at;
        syncTabStops();
        drawFoot();

        if (!modal) return;
        if (isPhone()) {
            els.title.focus();
        } else if (at >= 0) {
            const tile = tileAt(at);
            tile?.focus({ preventScroll: true });
            tile?.scrollIntoView({ block: 'center' });
        } else {
            els.search.focus();
        }
        scrollTreeToCurrent();
    }

    function destroy() {
        S.closed = true;
        S.token += 1;
        clearTimeout(searchTimer);
        closeMenu(false);
        observer?.disconnect();
        media?.removeEventListener?.('change', onMedia);
        S.uploads.forEach((u) => { if (u.src) URL.revokeObjectURL(u.src); });
        root.remove();
    }

    const view = {
        root,
        els,
        can,
        api,
        hooks: {},
        start,
        destroy,
        go: (path) => goFolder(path),
        /** Reloads the tree and the images; with a path, opens that folder. */
        refresh: async (path = null) => {
            await loadTree().catch(() => {});
            if (path && S.tree.has(path)) return goFolder(path);
            if (!S.tree.has(S.path)) return goFolder(ROOT);
            drawTree();
            return load();
        },
        /** True while the folder tree is on screen (not on a phone). */
        treeShown: () => els.side.offsetParent !== null,
        announce,
        state: () => ({ path: S.path, q: S.q, files: [...S.files], picked: S.sel.paths(), tree: S.tree }),
        selection: () => S.sel,
        /** The image last clicked or moved to with the keyboard, or null. */
        focused: () => (S.focused ? S.known.get(S.focused) || null : null),
        /** What browse said about an image this view has shown. */
        file: (path) => S.known.get(path) || null,
        menu: (anchor, items) => openMenu(anchor, items),
        closeMenu: () => closeMenu(),
        newFolder: (parent) => {
            if (!can.upload) return;
            startNewFolder(parent);
        },
        pickFiles: () => els.file?.click(),
        redraw: () => {
            syncMarks();
            drawFoot();
        },
        applyHooks() {
            els.tools.querySelector('.ml-hook-tools')?.remove();
            if (view.hooks.toolbar) {
                const extra = h('div', { class: 'ml-hook-tools' }, ...[view.hooks.toolbar(view)].flat().filter(Boolean));
                if (els.upload) els.upload.before(extra);
                else els.tools.append(extra);
            }
            if (els.upload) {
                els.upload.classList.toggle('has-menu', Boolean(view.hooks.uploadMenu));
                if (view.hooks.uploadMenu) {
                    els.upload.setAttribute('aria-haspopup', 'menu');
                    els.upload.setAttribute('aria-expanded', 'false');
                } else {
                    els.upload.removeAttribute('aria-haspopup');
                    els.upload.removeAttribute('aria-expanded');
                }
            }
            els.body.querySelector('.ml-panel')?.remove();
            els.body.classList.toggle('has-panel', Boolean(view.hooks.sidePanel));
            if (view.hooks.sidePanel) {
                els.body.append(h('aside', { class: 'ml-panel' }, ...[view.hooks.sidePanel(view)].flat().filter(Boolean)));
            }
            if (S.tree.size) drawTree();
            drawFoot();
        },
    };

    drawFoot();
    return view;
}
