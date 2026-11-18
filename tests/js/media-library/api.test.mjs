import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createApi, MediaApiError } from '../../../resources/js/media-library/api.js';

const config = { token: 'tok', urls: { tree: '/t', browse: '/b', upload: '/u', importUrl: '/i', folder: '/f', rename: '/r', move: '/m', remove: '/d', usage: '/us' } };
const reply = (status, body, headers = {}) => async () => new Response(JSON.stringify(body), { status, headers });

test('browse sends the server sort name and skips empty values', async () => {
    let seen;
    const api = createApi(config, async (url, init) => {
        seen = { url, init };
        return new Response('{"ok":true}');
    });
    await api.browse({ path: 'media/a', q: '', sort: 'newest', page: 2 });
    assert.equal(seen.url, '/b?path=media%2Fa&sort=modified&page=2');
    assert.equal(seen.init.method, 'GET');
});

test('writes carry the token and a JSON body', async () => {
    let seen;
    const api = createApi(config, async (url, init) => {
        seen = { url, init };
        return new Response('{"ok":true}');
    });
    await api.move(['a'], 'media/b');
    assert.equal(seen.init.headers['X-CSRF-TOKEN'], 'tok');
    assert.deepEqual(JSON.parse(seen.init.body), { paths: ['a'], to: 'media/b' });
});

test('upload sends the files as form data', async () => {
    let body;
    const api = createApi(config, async (url, init) => {
        body = init.body;
        return new Response('{"ok":true}');
    });
    await api.upload([new File(['x'], 'a.png', { type: 'image/png' })], 'media');
    assert.equal(body.getAll('files[]').length, 1);
    assert.equal(body.get('path'), 'media');
});

test('a 419 is unauthenticated and a 429 carries retryAfter', async () => {
    await assert.rejects(createApi(config, reply(419, { message: 'Expired' })).tree(), (e) => e instanceof MediaApiError && e.kind === 'unauthenticated' && e.status === 419);
    await assert.rejects(createApi(config, reply(429, {}, { 'Retry-After': '30' })).tree(), (e) => e.kind === 'too-many' && e.retryAfter === 30);
});

test('a 409 carries the in-use list and a 422 its fields', async () => {
    const inUse = { 'media/a.jpg': [{ label: 'products', count: 1 }] };
    await assert.rejects(createApi(config, reply(409, { ok: false, message: 'In use', inUse })).remove(['x']), (e) => e.kind === 'conflict' && e.data.inUse['media/a.jpg'][0].count === 1);
    await assert.rejects(createApi(config, reply(422, { message: 'Bad', errors: { name: ['Give it a name.'] } })).rename('a', ''), (e) => e.kind === 'validation' && e.fields.name[0] === 'Give it a name.');
});

test('other failures are classified', async () => {
    await assert.rejects(createApi(config, reply(403, {})).tree(), (e) => e.kind === 'forbidden');
    await assert.rejects(createApi(config, reply(413, {})).tree(), (e) => e.kind === 'too-large');
    await assert.rejects(createApi(config, reply(500, {})).tree(), (e) => e.kind === 'server');
    await assert.rejects(createApi(config, async () => { throw new TypeError('down'); }).tree(), (e) => e.kind === 'network');
});
