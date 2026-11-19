/**
 * The Media library page's tools. The page mounts the shared library with
 * `manage: true`; this file fills its hook points:
 *
 * - the selection bar: Rename (one image, in place), Move to... and Delete;
 * - the side panel: the focused image's details and what uses it;
 * - Upload becomes a menu with Import from a web address;
 * - a menu on every folder: New folder, Rename, Move, Delete.
 *
 * A tool is drawn only for someone who may use it; the server refuses the
 * request anyway. The helpers at the top hold the rules the page shares
 * with the server and need no browser, so they are tested on their own.
 */

import { h, provideManage, formatSize } from './view.js';
import { folderLabel } from './tree.js';
import { kindCount, usesText } from './files.js';
import { icon } from './icons.js';
import { folderNameError, phpTrim } from './names.js';

const ROOT = 'media';

const fmt = (n) => Number(n).toLocaleString('en-US');
const plural = (n, one, many = `${one}s`) => `${fmt(n)} ${n === 1 ? one : many}`;
const basename = (path) => String(path).split('/').pop();
const dirname = (path) => String(path).split('/').slice(0, -1).join('/');

/* ------------------------------------------------------------ the rules */

/**
 * Where these paths may be moved to, in tree order. A folder never goes
 * into itself or a folder inside it, and the folder every path already sits
 * in is left out, since nothing would move. Paths are compared exactly:
 * "Demo" and "demo" are two folders, and "Demo-2" is not inside "Demo".
 *
 * @param {Map} tree   from buildTree()
 * @param {string[]} paths  images and folders
 */
export function moveTargets(tree, paths) {
    const list = [...new Set(paths.map(String))];
    const folders = list.filter((p) => tree.has(p));
    const homes = new Set(list.map((p) => tree.get(p)?.parent ?? dirname(p)));

    return [...tree.keys()].filter((dest) => {
        if (folders.some((f) => dest === f || dest.startsWith(`${f}/`))) return false;
        return !(homes.size === 1 && homes.has(dest));
    });
}

/**
 * Which images a delete would remove and which it keeps because something
 * uses them. `inUse` maps a path to its users, [{ label, count }], as the
 * usage request and a refused delete (409) both give them.
 *
 * @return {{ deletable: string[], blocked: { path: string, uses: object[] }[] }}
 */
export function deleteSummary(files, inUse = {}) {
    const usesOf = (path) => (inUse instanceof Map ? inUse.get(path) : inUse[path]) || [];
    const deletable = [];
    const blocked = [];

    [...new Set(files.map(String))].forEach((path) => {
        const uses = usesOf(path).filter((u) => u && Number(u.count) > 0);
        if (uses.length) blocked.push({ path, uses });
        else deletable.push(path);
    });

    return { deletable, blocked };
}

/**
 * The message the server would give for this new name, or null. A folder
 * keeps the name as typed, so the folder name rule applies and a sibling
 * with exactly that name (case and all) is taken. An image's name is made
 * web-safe by the server, which also says when the result is taken, so only
 * an empty name is refused here.
 *
 * @param {string} name
 * @param {string[]} siblings  names of the other folders beside it
 */
export function renameError(name, siblings = [], { folder = true } = {}) {
    if (String(name ?? '').trim() === '') return 'Give it a name.';
    if (!folder) return null;

    const problem = folderNameError(name);
    if (problem) return problem;

    const typed = phpTrim(name);
    return siblings.includes(typed) ? `A folder called ${typed} is already here.` : null;
}

function firstMessage(error) {
    const fields = Object.values(error?.fields || {});
    const first = fields.find((list) => Array.isArray(list) && list.length);
    return first ? first[0] : error?.message || 'Something went wrong. Try again.';
}

/* ------------------------------------------------------------- dialogs */

/**
 * A small dialog in the library's own look. Returns its parts; `close()`
 * removes it and puts focus back where it was.
 */
function dialog({ title, labelText = null, refocus = null }) {
    const opener = document.activeElement;
    const titleEl = h('h2', { class: 'ml-title', id: `ml-mng-${Date.now()}`, tabindex: '-1', text: title });
    const close = h('button', { type: 'button', class: 'ml-icon-btn ml-close', 'aria-label': 'Close' }, icon('close'));
    const body = h('div', { class: 'ml-mng-dialog__body' });
    const error = h('p', { class: 'ml-mng-error', role: 'alert', hidden: true });
    const actions = h('footer', { class: 'ml-foot ml-foot--end' });
    const root = h('dialog', { class: 'ml-root ml-modal ml-modal--small ml-mng-dialog', 'aria-labelledby': titleEl.id },
        h('div', { class: 'ml-frame' },
            h('header', { class: 'ml-head' }, h('div', { class: 'ml-heading' }, titleEl,
                labelText ? h('p', { class: 'ml-where', text: labelText }) : null), close),
            h('div', { class: 'ml-mng-dialog__main' }, body, error),
            actions));

    let done = false;
    const api = {
        root,
        body,
        actions,
        title: titleEl,
        setError(text) {
            error.textContent = text || '';
            error.hidden = !text;
        },
        close() {
            if (done) return;
            done = true;
            if (root.open) root.close();
            root.remove();
            if (opener?.isConnected && typeof opener.focus === 'function') opener.focus();
            else refocus?.();
        },
    };

    close.addEventListener('click', () => api.close());
    root.addEventListener('cancel', (e) => {
        e.preventDefault();
        api.close();
    });
    root.addEventListener('close', () => api.close());
    document.body.append(root);
    root.showModal();
    return api;
}

const button = (cls, label, onclick, ...kids) => h('button', { type: 'button', class: cls, onclick }, ...kids, label);

/* ------------------------------------------------------------ the tools */

export function createManageHooks(view) {
    const { can, api } = view;
    const M = {
        renaming: null,
        renameRow: null,
        panel: h('div', { class: 'ml-mng-panel' }),
        shown: undefined,
        usage: new Map(),
    };

    const folderTools = can.upload || can.edit || can.delete;
    const tree = () => view.state().tree;

    /** Reloads the tree and the images, forgetting what was said about use. */
    function reload(path = null) {
        M.usage.clear();
        M.shown = undefined;
        return view.refresh(path);
    }

    function forget(paths) {
        const sel = view.selection();
        paths.forEach((p) => sel.remove(p));
    }

    /* ---------------------------------------------------- selection bar */

    function selectionBar() {
        if (M.renaming) return renameRow();

        const picked = view.selection().paths();
        const n = picked.length;

        // Shown on a phone only, where the side panel does not fit.
        const details = n === 1
            ? button('ml-btn ml-btn--secondary ml-btn--sm ml-mng-details', 'Details', () => detailsDialog(picked[0]), icon('image', 'ml-icon--sm'))
            : null;

        // Someone who may only look has nothing else to do with a selection.
        // With one image selected there is nothing to ask for: the side panel
        // (or Details, on a phone) already shows it.
        if (n === 1 && !(can.edit || can.delete)) {
            return [
                h('div', { class: 'ml-pick-sum' }, h('span', { class: 'ml-pick-sum__count', text: '1 selected' })),
                h('div', { class: 'ml-mng-acts' }, details),
            ];
        }

        if (!n || !(can.edit || can.delete)) {
            const hint = can.edit || can.delete
                ? 'Select images to rename, move or delete them.'
                : n > 1 ? 'Select one image to see where it is used.' : 'Select an image to see where it is used.';
            return [h('p', { class: 'ml-pick-none', text: hint })];
        }

        const tools = [
            details,
            can.edit && n === 1 ? button('ml-btn ml-btn--secondary ml-btn--sm', 'Rename', () => startRename(picked[0]), icon('pencil', 'ml-icon--sm')) : null,
            can.edit ? button('ml-btn ml-btn--secondary ml-btn--sm', 'Move to...', () => moveDialog(picked), icon('move', 'ml-icon--sm')) : null,
            can.delete ? button('ml-btn ml-btn--danger ml-btn--sm', 'Delete', () => deleteDialog(picked), icon('trash', 'ml-icon--sm')) : null,
        ].filter(Boolean);

        return [
            h('div', { class: 'ml-pick-sum' }, h('span', { class: 'ml-pick-sum__count', text: `${fmt(n)} selected` })),
            button('ml-link-btn', 'Clear', () => {
                forget(picked);
                view.redraw();
            }),
            h('div', { class: 'ml-mng-acts' }, tools),
        ];
    }

    /* ----------------------------------------------------------- rename */

    function startRename(path) {
        const file = view.file(path);
        const name = file?.name ?? basename(path);
        const dot = name.lastIndexOf('.');
        const stem = dot > 0 ? name.slice(0, dot) : name;
        const ext = dot > 0 ? name.slice(dot) : '';

        const input = h('input', { class: 'ml-input ml-mng-rename__input', type: 'text', maxlength: '200', autocomplete: 'off', 'aria-label': `New name for ${name}` });
        input.value = stem;
        const error = h('p', { class: 'ml-mng-error', role: 'alert', hidden: true });
        const save = button('ml-btn ml-btn--primary ml-btn--sm', 'Save', () => submit());
        const cancel = button('ml-btn ml-btn--ghost ml-btn--sm', 'Cancel', () => stop());

        const fail = (text) => {
            error.textContent = text;
            error.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            input.disabled = false;
            save.disabled = false;
            input.focus();
        };

        async function submit() {
            const problem = renameError(input.value, [], { folder: false });
            if (problem) {
                fail(problem);
                return;
            }
            input.disabled = true;
            save.disabled = true;
            try {
                const data = await api.rename(path, input.value);
                const sel = view.selection();
                sel.remove(path);
                sel.add([data.path]);
                stop(false);
                view.announce(`Renamed to ${basename(data.path)}.`);
                await reload();
            } catch (e) {
                fail(firstMessage(e));
            }
        }

        function stop(refocus = true) {
            M.renaming = null;
            M.renameRow = null;
            view.redraw();
            if (refocus) view.els.grid.querySelector(`[data-path="${CSS.escape(path)}"]`)?.focus();
        }

        input.addEventListener('keydown', (e) => {
            e.stopPropagation();
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                stop();
            }
        });

        M.renaming = path;
        M.renameRow = h('div', { class: 'ml-mng-rename' },
            h('label', { class: 'ml-mng-rename__label', text: 'Rename' }),
            h('div', { class: 'ml-mng-rename__field' }, input, ext ? h('span', { class: 'ml-mng-rename__ext', text: ext }) : null),
            save, cancel, error);
        view.redraw();
        input.focus();
        input.select?.();
    }

    function renameRow() {
        return M.renameRow;
    }

    /* ------------------------------------------------------------- move */

    function moveDialog(paths, { folder = false } = {}) {
        const targets = moveTargets(tree(), paths);
        const offered = new Set(targets);
        const what = folder ? `folder ${tree().get(paths[0])?.name ?? basename(paths[0])}` : plural(paths.length, 'image');
        const d = dialog({ title: `Move ${what} to...` });
        let chosen = null;

        const filter = h('input', { class: 'ml-input', type: 'search', placeholder: 'Filter folders', 'aria-label': 'Filter folders', autocomplete: 'off' });
        const list = h('div', { class: 'ml-mng-targets', role: 'radiogroup', 'aria-label': 'Folders' });
        const go = button('ml-btn ml-btn--primary', 'Move here', () => submit());
        go.disabled = true;

        function draw() {
            const term = filter.value.trim().toLowerCase();
            const rows = targets.filter((p) => !term || folderLabel(p).toLowerCase().includes(term));
            list.replaceChildren(...rows.map((p) => {
                const node = tree().get(p);
                const depth = p.split('/').length - 1;
                const radio = h('input', { type: 'radio', name: 'ml-move-to', value: p, checked: p === chosen });
                radio.addEventListener('change', () => {
                    chosen = p;
                    go.disabled = false;
                });
                return h('label', { class: `ml-mng-target${term ? '' : ` ml-tree__row--l${Math.min(depth, 5)}`}`, title: folderLabel(p) },
                    radio, icon(p === ROOT ? 'image' : 'folder', 'ml-icon--sm'),
                    h('span', { class: 'ml-mng-target__name' }, h('span', { text: node?.name ?? basename(p) }),
                        // The indenting says where a folder is only while its parent is
                        // listed above it: not in a filtered list, and not under a
                        // folder that cannot be chosen.
                        p !== ROOT && (term || !offered.has(dirname(p))) ? h('span', { class: 'ml-mng-target__where', text: folderLabel(dirname(p)) }) : null),
                    h('span', { class: 'ml-tree__count', text: fmt(node?.count ?? 0) }));
            }));
            if (!rows.length) list.append(h('p', { class: 'ml-pick-none', text: targets.length ? 'No folder matches.' : 'There is no other folder to move to.' }));
        }

        async function submit() {
            if (!chosen) return;
            go.disabled = true;
            d.setError('');
            try {
                const data = await api.move(paths, chosen);
                d.close();
                if (!folder) forget(paths);
                view.announce(`Moved ${what} to ${tree().get(chosen)?.name ?? basename(chosen)}.`);
                const here = view.state().path;
                const moved = Object.entries(data.moved || {}).find(([from]) => here === from || here.startsWith(`${from}/`));
                await reload(moved ? moved[1] + here.slice(moved[0].length) : null);
            } catch (e) {
                d.setError(firstMessage(e));
                go.disabled = false;
            }
        }

        filter.addEventListener('input', draw);
        draw();
        d.body.append(h('div', { class: 'ml-mng-filter' }, filter), list);
        d.actions.append(button('ml-btn ml-btn--ghost', 'Cancel', () => d.close()), go);
        filter.focus();
    }

    /* ----------------------------------------------------------- delete */

    async function deleteDialog(paths) {
        const n = paths.length;
        const d = dialog({ title: `Delete ${plural(n, 'image')}?` });
        d.body.append(h('p', { class: 'ml-mng-text', text: 'Checking where they are used...' }));
        d.actions.append(button('ml-btn ml-btn--ghost', 'Cancel', () => d.close()));

        const inUse = {};
        try {
            await Promise.all(paths.map(async (path) => {
                const known = view.file(path);
                if (known && !(known.usedBy?.total > 0)) return;
                inUse[path] = (await usageOf(path)).uses;
            }));
        } catch (e) {
            d.body.replaceChildren(h('p', { class: 'ml-mng-text', text: 'Where they are used could not be checked, so nothing was deleted.' }));
            d.setError(firstMessage(e));
            return;
        }
        drawDelete(d, deleteSummary(paths, inUse));
    }

    function drawDelete(d, { deletable, blocked }, refusedText = '') {
        const lines = [];
        if (deletable.length) {
            lines.push(h('p', { class: 'ml-mng-text', text: `${plural(deletable.length, 'image')} will be deleted. This cannot be undone.` }));
        }
        if (blocked.length) {
            lines.push(h('p', { class: 'ml-mng-text ml-mng-text--strong', text: deletable.length
                ? `${plural(blocked.length, 'image')} ${blocked.length === 1 ? 'is' : 'are'} in use and will be kept:`
                : `${blocked.length === 1 ? 'This image is' : 'These images are'} in use. Change what uses ${blocked.length === 1 ? 'it' : 'them'} first.` }));
            lines.push(h('ul', { class: 'ml-mng-blocked' }, blocked.map(({ path, uses }) => h('li', null,
                thumbOf(view.file(path)),
                h('span', { class: 'ml-mng-blocked__text' },
                    h('span', { class: 'ml-mng-blocked__name', text: basename(path) }),
                    h('span', { class: 'ml-mng-blocked__uses', text: `Used by ${usesText(uses)}` }))))));
        }
        d.body.replaceChildren(...lines);
        d.setError(refusedText);
        d.actions.replaceChildren(button('ml-btn ml-btn--ghost', deletable.length ? 'Cancel' : 'Close', () => d.close()));

        if (!deletable.length) {
            d.actions.lastChild.focus();
            return;
        }

        const go = button('ml-btn ml-btn--danger', `Delete ${plural(deletable.length, 'image')}`, async () => {
            go.disabled = true;
            try {
                const data = await api.remove(deletable);
                forget(data.deleted || deletable);
                d.close();
                view.announce(`Deleted ${plural((data.deleted || deletable).length, 'image')}.`);
                await reload();
            } catch (e) {
                if (e.kind === 'conflict' && e.data?.inUse) {
                    // Something started using an image after the check: say
                    // which, keep it, and show that the rest went.
                    forget(e.data.deleted || []);
                    reload();
                    drawDelete(d, { deletable: [], blocked: deleteSummary(Object.keys(e.data.inUse), e.data.inUse).blocked }, e.message);
                    return;
                }
                d.setError(firstMessage(e));
                go.disabled = false;
            }
        }, icon('trash', 'ml-icon--sm'));
        d.actions.append(go);
        go.focus();
    }

    function thumbOf(file) {
        const src = file?.thumb || file?.url;
        return src ? h('img', { class: 'ml-mng-thumb', src, alt: '' }) : h('span', { class: 'ml-mng-thumb ml-thumb-blank' }, icon('image', 'ml-icon--sm'));
    }

    /* ------------------------------------------------------------ usage */

    async function usageOf(path) {
        if (!M.usage.has(path)) {
            M.usage.set(path, api.usage(path).catch((e) => {
                M.usage.delete(path);
                throw e;
            }));
        }
        return M.usage.get(path);
    }

    /* ------------------------------------------------------- side panel */

    /**
     * The image the side panel describes: the one selected image when there
     * is exactly one, so the panel never asks for a selection that has been
     * made, otherwise the focused image.
     */
    function panelFile() {
        const picked = view.selection().paths();
        return (picked.length === 1 ? view.file(picked[0]) : null) || view.focused();
    }

    function drawPanel() {
        const file = panelFile();
        const key = file ? `${file.path}:${file.usedBy?.total ?? 0}` : null;
        if (key === M.shown) return;
        M.shown = key;

        if (!file) {
            M.panel.replaceChildren(
                h('h3', { class: 'ml-mng-panel__title', text: 'Details' }),
                h('p', { class: 'ml-mng-text ml-mng-text--quiet', text: 'Select an image to see its details and what uses it.' }));
            return;
        }

        M.panel.replaceChildren(h('h3', { class: 'ml-mng-panel__title', text: 'Details' }), ...details(file, () => M.shown === key));
    }

    /**
     * One image's picture, facts and "Used by" list, for the side panel and
     * for the phone's Details dialog. `current()` says whether the place
     * they were drawn into still shows this image when its users arrive.
     */
    function details(file, current) {
        const facts = [
            ['Size', formatSize(file.size)],
            file.width && file.height ? ['Dimensions', `${file.width} × ${file.height}`] : null,
            ['Folder', folderLabel(file.folder || dirname(file.path))],
        ].filter(Boolean);

        const used = h('div', { class: 'ml-mng-used', 'aria-live': 'polite' });
        const parts = [
            h('a', { class: 'ml-mng-panel__img', href: file.url, target: '_blank', rel: 'noopener', 'aria-label': `Open ${file.name} full size` },
                h('img', { src: file.thumb || file.url, alt: '' })),
            h('p', { class: 'ml-mng-panel__name', text: file.name }),
            h('dl', { class: 'ml-mng-facts' }, facts.map(([term, value]) => [h('dt', { text: term }), h('dd', { text: value })]).flat()),
            h('a', { class: 'ml-link-btn ml-mng-panel__open', href: file.url, target: '_blank', rel: 'noopener' }, 'Open full size'),
            h('h3', { class: 'ml-mng-panel__title ml-mng-panel__title--gap', text: 'Used by' }),
            used,
        ];

        if (!(file.usedBy?.total > 0)) {
            used.append(h('p', { class: 'ml-mng-text ml-mng-text--quiet', text: 'Not used by anything.' }));
            return parts;
        }

        used.append(h('p', { class: 'ml-mng-text ml-mng-text--quiet', text: 'Checking...' }));
        usageOf(file.path).then((data) => {
            if (!current()) return;
            const uses = (data.uses || []).filter((u) => u.count > 0);
            used.replaceChildren(uses.length
                ? h('ul', { class: 'ml-mng-uses' }, uses.map((u) => h('li', { text: kindCount(u) })))
                : h('p', { class: 'ml-mng-text ml-mng-text--quiet', text: 'Not used by anything.' }));
        }).catch((e) => {
            if (!current()) return;
            used.replaceChildren(h('p', { class: 'ml-mng-error', text: firstMessage(e) }));
        });
        return parts;
    }

    /**
     * A phone has no room for the side panel, so the same details open in
     * the library's small dialog from the selection bar.
     */
    function detailsDialog(path) {
        const file = view.file(path) || { path, name: basename(path), size: 0, usedBy: { total: 1 } };
        let open = true;
        const d = dialog({
            title: 'Details',
            refocus: () => view.els.grid.querySelector(`[data-path="${CSS.escape(path)}"]`)?.focus(),
        });
        d.root.classList.add('ml-mng-dialog--details');
        d.body.append(...details(file, () => open));
        const done = button('ml-btn ml-btn--secondary', 'Close', () => d.close());
        d.actions.append(done);
        d.root.addEventListener('close', () => { open = false; });
        done.focus();
    }

    /* ----------------------------------------------- import from a web address */

    function importDialog() {
        const into = view.state().path;
        const d = dialog({ title: 'Import from a web address', labelText: `Into ${tree().get(into)?.name ?? basename(into)}` });
        const input = h('input', { class: 'ml-input', type: 'url', inputmode: 'url', placeholder: 'https://example.com/photo.jpg', autocomplete: 'off', id: 'ml-mng-import-url' });
        const go = button('ml-btn ml-btn--primary', 'Import', () => submit());

        async function submit() {
            const url = input.value.trim();
            if (!url) {
                d.setError('Paste the address of a JPG, PNG or WebP image.');
                input.focus();
                return;
            }
            go.disabled = true;
            input.disabled = true;
            d.setError('');
            try {
                const data = await api.importUrl(url, into);
                const file = (data.files || [])[0];
                d.close();
                if (file) view.selection().add([file.path]);
                view.announce(file ? `${file.name} imported and selected.` : 'Imported.');
                await reload();
            } catch (e) {
                d.setError(firstMessage(e));
                go.disabled = false;
                input.disabled = false;
                input.focus();
            }
        }

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });
        d.body.append(h('label', { class: 'ml-mng-label', for: 'ml-mng-import-url', text: 'Image address' }), input,
            h('p', { class: 'ml-mng-text ml-mng-text--quiet', text: 'A JPG, PNG or WebP image the store can reach on the web.' }));
        d.actions.append(button('ml-btn ml-btn--ghost', 'Cancel', () => d.close()), go);
        input.focus();
    }

    /* ----------------------------------------------------- folder menu */

    function folderMenu({ path, anchor }) {
        const node = tree().get(path);
        if (!node || !folderTools) return;
        const atRoot = path === ROOT;
        view.menu(anchor, [
            can.upload ? {
                label: 'New folder', icon: 'folder-plus',
                // In the tree when it is on screen; a phone has no tree to type in.
                run: () => (view.treeShown() ? view.newFolder(path) : newFolderDialog(path)),
            } : null,
            can.edit && !atRoot ? { label: 'Rename', icon: 'pencil', run: () => renameFolderDialog(path) } : null,
            can.edit && !atRoot ? { label: 'Move', icon: 'move', run: () => moveDialog([path], { folder: true }) } : null,
            can.delete && !atRoot ? {
                label: 'Delete', icon: 'trash', disabled: node.count > 0,
                hint: node.count > 0 ? 'Only an empty folder can be deleted' : null,
                run: () => deleteFolderDialog(path),
            } : null,
        ]);
    }

    function newFolderDialog(parent) {
        const siblings = (tree().get(parent)?.children || []).map((p) => tree().get(p)?.name);
        const d = dialog({ title: 'New folder', labelText: `In ${tree().get(parent)?.name ?? basename(parent)}` });
        const input = h('input', { class: 'ml-input', type: 'text', maxlength: '120', autocomplete: 'off', id: 'ml-mng-new-folder' });
        const go = button('ml-btn ml-btn--primary', 'Make folder', () => submit());

        async function submit() {
            const name = phpTrim(input.value);
            const problem = folderNameError(input.value) || (siblings.includes(name) ? `A folder called ${name} is already here.` : null);
            if (problem) {
                d.setError(problem);
                input.focus();
                return;
            }
            go.disabled = true;
            d.setError('');
            try {
                const data = await api.makeFolder(parent, name);
                d.close();
                view.announce(`Folder ${name} made.`);
                await reload(data.path || `${parent}/${name}`);
            } catch (e) {
                d.setError(firstMessage(e));
                go.disabled = false;
                input.focus();
            }
        }

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });
        d.body.append(h('label', { class: 'ml-mng-label', for: 'ml-mng-new-folder', text: 'Folder name' }), input);
        d.actions.append(button('ml-btn ml-btn--ghost', 'Cancel', () => d.close()), go);
        input.focus();
    }

    function renameFolderDialog(path) {
        const node = tree().get(path);
        const siblings = (tree().get(node.parent)?.children || []).filter((p) => p !== path).map((p) => tree().get(p)?.name);
        const d = dialog({ title: `Rename folder ${node.name}` });
        const input = h('input', { class: 'ml-input', type: 'text', maxlength: '120', autocomplete: 'off', id: 'ml-mng-folder-name' });
        input.value = node.name;
        const go = button('ml-btn ml-btn--primary', 'Rename', () => submit());

        async function submit() {
            const problem = renameError(input.value, siblings, { folder: true });
            if (problem) {
                d.setError(problem);
                input.setAttribute('aria-invalid', 'true');
                input.focus();
                return;
            }
            go.disabled = true;
            d.setError('');
            try {
                const data = await api.rename(path, input.value);
                d.close();
                view.announce(`Folder renamed to ${basename(data.path)}.`);
                const here = view.state().path;
                await reload(here === path || here.startsWith(`${path}/`) ? data.path + here.slice(path.length) : null);
            } catch (e) {
                d.setError(firstMessage(e));
                go.disabled = false;
                input.focus();
            }
        }

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });
        d.body.append(h('label', { class: 'ml-mng-label', for: 'ml-mng-folder-name', text: 'Folder name' }), input);
        d.actions.append(button('ml-btn ml-btn--ghost', 'Cancel', () => d.close()), go);
        input.focus();
        input.select?.();
    }

    function deleteFolderDialog(path) {
        const node = tree().get(path);
        const d = dialog({ title: `Delete folder ${node.name}?` });
        d.body.append(h('p', { class: 'ml-mng-text', text: `${folderLabel(path)} is empty and will be removed.` }));
        const go = button('ml-btn ml-btn--danger', 'Delete folder', async () => {
            go.disabled = true;
            try {
                await api.remove([path]);
                d.close();
                view.announce(`Folder ${node.name} deleted.`);
                const here = view.state().path;
                await reload(here === path || here.startsWith(`${path}/`) ? node.parent || ROOT : null);
            } catch (e) {
                d.setError(firstMessage(e));
                go.disabled = false;
            }
        }, icon('trash', 'ml-icon--sm'));
        d.actions.append(button('ml-btn ml-btn--ghost', 'Cancel', () => d.close()), go);
        go.focus();
    }

    /* ----------------------------------------------------------- hooks */

    const hooks = {
        selectionBar,
        sidePanel: () => M.panel,
        change: () => drawPanel(),
    };

    if (folderTools) {
        hooks.toolbar = () => {
            const b = h('button', {
                type: 'button', class: 'ml-btn ml-btn--ghost ml-mng-folder', 'aria-haspopup': 'menu', 'aria-expanded': 'false',
            }, icon('folder', 'ml-icon--sm'), h('span', { class: 'ml-mng-folder__label', text: 'Folder' }), icon('chevron-down', 'ml-icon--xs'));
            b.addEventListener('click', () => folderMenu({ path: view.state().path, anchor: b }));
            return b;
        };
        hooks.treeMenu = folderMenu;
    }

    if (can.upload) {
        hooks.uploadMenu = () => [{ label: 'Import from a web address', icon: 'link', run: () => importDialog() }];
    }

    return hooks;
}

/* ------------------------------------------------------------- the page */

function boot() {
    const element = document.getElementById('media-library');
    if (!element || element.dataset.mounted) return;
    element.dataset.mounted = 'true';

    const library = window.VentaCart.media.mount(element, { manage: true, title: 'Media library' });

    // "Look through them" and the like: buttons on the page that open a folder.
    document.querySelectorAll('[data-media-go]').forEach((b) => b.addEventListener('click', () => {
        library.go(b.dataset.mediaGo);
        element.scrollIntoView?.({ block: 'start', behavior: 'smooth' });
    }));
}

if (typeof document !== 'undefined') {
    provideManage(createManageHooks);
    if (window.VentaCart?.media) boot();
    else document.addEventListener('ventacart:media-ready', boot, { once: true });
}
