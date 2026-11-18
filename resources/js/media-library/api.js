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

/** The error a failed answer stands for, whichever way it was fetched. */
function failure(status, data, retryAfterHeader) {
    const kind = kindOf(status);
    const retry = Number.parseInt(retryAfterHeader ?? '', 10);

    return new MediaApiError({
        kind,
        status,
        message: data.message || FALLBACKS[kind],
        fields: data.errors || {},
        retryAfter: Number.isFinite(retry) ? retry : null,
        data: kind === 'conflict' ? data : {},
    });
}

const NETWORK = 'Could not reach the server. Check your connection.';

/**
 * @param {object} config        { token, urls: { tree, browse, upload, ... } }
 * @param {Function} fetchImpl   fetch, replaceable in tests
 * @param {Function} XhrImpl     XMLHttpRequest, replaceable in tests; used
 *                               for uploads that report progress, since
 *                               fetch cannot report how much it has sent
 */
export function createApi(config, fetchImpl = globalThis.fetch, XhrImpl = globalThis.XMLHttpRequest) {
    const urls = config.urls || {};
    const readHeaders = () => ({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' });

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

        const headers = readHeaders();
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
            throw new MediaApiError({ kind: 'network', message: NETWORK });
        }

        let data = {};
        try {
            data = await response.json();
        } catch {
            data = {};
        }

        if (response.ok && data.ok !== false) return data;

        throw failure(response.status, data, response.headers?.get?.('Retry-After'));
    }

    /**
     * The same POST as send(), through XMLHttpRequest so onProgress hears
     * how much of the body has gone: a number from 0 to 100.
     */
    function sendWithProgress(url, body, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XhrImpl();
            xhr.open('POST', url);
            Object.entries({ ...readHeaders(), 'X-CSRF-TOKEN': config.token || '' })
                .forEach(([name, value]) => xhr.setRequestHeader(name, value));

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable && e.total > 0) onProgress(Math.min(100, Math.round((e.loaded / e.total) * 100)));
            };
            xhr.onerror = () => reject(new MediaApiError({ kind: 'network', message: NETWORK }));
            xhr.onload = () => {
                let data = {};
                try {
                    data = JSON.parse(xhr.responseText || '{}') || {};
                } catch {
                    data = {};
                }
                const ok = xhr.status >= 200 && xhr.status < 300;
                if (ok && data.ok !== false) {
                    onProgress(100);
                    resolve(data);
                    return;
                }
                reject(failure(xhr.status, data, xhr.getResponseHeader('Retry-After')));
            };
            xhr.send(body);
        });
    }

    return {
        tree: () => send(urls.tree),
        browse: ({ path, q, sort, page } = {}) =>
            send(urls.browse, { query: { path, q, sort: SORTS[sort] || sort, page } }),
        /** With onProgress, the upload reports how much has been sent (0 to 100). */
        upload(files, path, { onProgress } = {}) {
            const form = new FormData();
            Array.from(files).forEach((file) => form.append('files[]', file));
            if (path) form.append('path', path);
            if (onProgress && XhrImpl) return sendWithProgress(urls.upload, form, onProgress);
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
