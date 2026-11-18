/**
 * The media library's public hook, window.VentaCart.media. Admin image
 * fields, the Media library page and theme editors all open the library
 * through it, so none of them needs to know how it is built.
 *
 *   VentaCart.media.open({ mode, limit, value, inUse, kind, folder, accept,
 *                          title, where, opener, onChoose, onClose })
 *     opens the library in a dialog and returns { close() }. onChoose
 *     always receives an array of { path, url, thumb, name, folder, width,
 *     height, size, usedBy }; onClose runs whenever the dialog closes.
 *
 *   VentaCart.media.mount(element, options)
 *     shows the same library inside a page and returns
 *     { go(path), refresh(), destroy() }.
 *
 * The settings (addresses, limits, permissions) come from the JSON that
 * <x-media-library /> prints. `ventacart:media-ready` on document says the
 * hook is in place, for code that loads before this file.
 */

import { createApi } from './api.js';
import { ensureIcons } from './icons.js';
import { createView, createRefusal, extend, manageHooksFor } from './view.js';

const CONFIG_ID = 'media-library-config';

/** Read on every open, so a page that replaces the settings is obeyed. */
function readConfig() {
    const node = document.getElementById(CONFIG_ID);
    if (!node) {
        throw new Error('The media library has no settings on this page. Add <x-media-library /> to the view.');
    }
    return JSON.parse(node.textContent || '{}');
}

function apiFor(config) {
    const e = config.endpoints || {};
    return createApi({
        token: config.token,
        urls: {
            tree: e.tree,
            browse: e.browse,
            upload: e.upload,
            importUrl: e.importUrl,
            folder: e.folder,
            rename: e.rename,
            move: e.move,
            remove: e.delete,
            usage: e.usage,
        },
    });
}

function open(options = {}) {
    const config = readConfig();
    ensureIcons(document);

    const opener = options.opener || document.activeElement;
    let closed = false;
    let dialog = null;
    let teardown = () => {};

    function close(chosen = null) {
        if (closed) return;
        closed = true;
        if (dialog.open) dialog.close();
        teardown();
        if (opener?.isConnected && typeof opener.focus === 'function') opener.focus();
        if (chosen && options.onChoose) options.onChoose(chosen);
        options.onClose?.();
    }

    if (!config.can?.view) {
        const refusal = createRefusal({ modal: true, onClose: () => close() });
        dialog = refusal.root;
        teardown = () => dialog.remove();
        document.body.append(dialog);
        dialog.showModal();
        refusal.focus();
        return { close: () => close() };
    }

    const view = createView({ api: apiFor(config), config, options, modal: true, onDone: (chosen) => close(chosen) });
    dialog = view.root;
    teardown = () => view.destroy();
    document.body.append(dialog);
    dialog.showModal();
    view.start();

    return { close: () => close() };
}

function mount(element, options = {}) {
    const config = readConfig();
    ensureIcons(document);

    if (!config.can?.view) {
        const refusal = createRefusal({ modal: false });
        element.append(refusal.root);
        return { go() {}, refresh() {}, destroy: () => refusal.root.remove() };
    }

    const settings = { mode: 'multi', limit: null, ...options };
    const view = createView({
        api: apiFor(config),
        config,
        options: settings,
        modal: false,
        onDone: (chosen) => {
            if (chosen) options.onChoose?.(chosen);
        },
    });
    element.append(view.root);

    if (options.manage === true) {
        const hooks = manageHooksFor(view);
        if (hooks) extend(view, hooks);
    }
    view.start();

    return {
        go: (path) => view.go(path),
        refresh: () => view.refresh(),
        destroy: () => view.destroy(),
    };
}

window.VentaCart = window.VentaCart || {};
window.VentaCart.media = { version: 1, open, mount };
document.dispatchEvent(new CustomEvent('ventacart:media-ready', { detail: window.VentaCart.media }));
