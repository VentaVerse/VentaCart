/**
 * Requests to the media library endpoints. No DOM; the URLs and the CSRF
 * token come in through `config`, so the same code serves every page that
 * hosts the library.
 */

export class MediaApiError extends Error {
    constructor({ kind, status = 0, message = '', fields = {}, retryAfter = null, data = {} }) {
        super(message);
        this.name = 'MediaApiError';
        this.kind = kind;
        this.status = status;
        this.fields = fields;
        this.retryAfter = retryAfter;
        this.data = data;
    }
}

const SORTS = { newest: 'modified', name: 'name' };

function kindOf(status) {
    if (status === 401 || status === 419) return 'unauthenticated';
    if (status === 403) return 'forbidden';
    if (status === 404) return 'not-found';
    if (status === 409) return 'conflict';
    if (status === 413) return 'too-large';
    if (status === 422) return 'validation';
    if (status === 429) return 'too-many';
    return 'server';
}

const FALLBACKS = {
    unauthenticated: 'Your session has ended. Sign in again.',
    forbidden: 'You are not allowed to do that.',
    'not-found': 'That could not be found.',
    conflict: 'That could not be done because something is using it.',
    'too-large': 'That is larger than the server accepts.',
    validation: 'Check what you entered and try again.',
    'too-many': 'Too many requests. Wait a moment and try again.',
    server: 'Something went wrong. Try again.',
};

export function createApi(config, fetchImpl = globalThis.fetch) {
    const urls = config.urls || {};

    async function send(url, { method = 'GET', query = null, body = null } = {}) {
        let target = url;
        if (query) {
            const params = new URLSearchParams();
            Object.entries(query).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') params.set(key, String(value));
            });
            const text = params.toString();
            if (text) target += (url.includes('?') ? '&' : '?') + text;
        }

        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const init = { method, headers, credentials: 'same-origin' };

        if (method !== 'GET') {
            headers['X-CSRF-TOKEN'] = config.token || '';
            if (body instanceof FormData) {
                init.body = body;
            } else {
                headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(body ?? {});
            }
        }

        let response;
        try {
            response = await fetchImpl(target, init);
        } catch {
            throw new MediaApiError({ kind: 'network', message: 'Could not reach the server. Check your connection.' });
        }

        let data = {};
        try {
            data = await response.json();
        } catch {
            data = {};
        }

        if (response.ok && data.ok !== false) return data;

        const kind = kindOf(response.status);
        const retry = Number.parseInt(response.headers?.get?.('Retry-After') ?? '', 10);

        throw new MediaApiError({
            kind,
            status: response.status,
            message: data.message || FALLBACKS[kind],
            fields: data.errors || {},
            retryAfter: Number.isFinite(retry) ? retry : null,
            data: kind === 'conflict' ? data : {},
        });
    }

    return {
        tree: () => send(urls.tree),
        browse: ({ path, q, sort, page } = {}) =>
            send(urls.browse, { query: { path, q, sort: SORTS[sort] || sort, page } }),
        upload(files, path) {
            const form = new FormData();
            Array.from(files).forEach((file) => form.append('files[]', file));
            if (path) form.append('path', path);
            return send(urls.upload, { method: 'POST', body: form });
        },
        importUrl: (url, path) => send(urls.importUrl, { method: 'POST', body: { url, path } }),
        makeFolder: (path, name) => send(urls.folder, { method: 'POST', body: { path, name } }),
        rename: (path, name) => send(urls.rename, { method: 'POST', body: { path, name } }),
        move: (paths, to) => send(urls.move, { method: 'POST', body: { paths, to } }),
        remove: (paths) => send(urls.remove, { method: 'POST', body: { paths } }),
        usage: (path) => send(urls.usage, { query: { path } }),
    };
}
