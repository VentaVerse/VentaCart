/**
 * What the library remembers between visits: the last folder for each kind of
 * field and the thumbnail size. Storage can be missing or refuse access
 * (private windows, blocked site data), so every access is guarded.
 */

function read(key) {
    try {
        return globalThis.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key, value) {
    try {
        globalThis.localStorage.setItem(key, value);
    } catch {
        // Not remembering is harmless.
    }
}

/** The remembered folder, or null when none is kept or it no longer exists. */
export function lastFolder(kind, exists) {
    const path = read(`media-library:last:${kind}`);
    return path && exists(path) ? path : null;
}

export function rememberFolder(kind, path) {
    write(`media-library:last:${kind}`, path);
}

export function thumbSize() {
    return read('media-library:size') === 'large' ? 'large' : 'small';
}

export function rememberThumbSize(size) {
    write('media-library:size', size === 'large' ? 'large' : 'small');
}
