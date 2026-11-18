import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from '../support/mini-dom.mjs';

const { document } = installDom();
const { createMediaPicker } = await import('../../../resources/js/admin/media-picker.js');

/** A field wired to a stand-in library that records what it was asked. */
function field(cfg) {
    const picker = createMediaPicker(cfg);
    const events = [];
    let formInputs = 0;
    picker.$el = {
        dispatchEvent: (e) => events.push({ type: e.type, detail: e.detail }),
        closest: () => ({ dispatchEvent: () => { formInputs += 1; } }),
    };
    const calls = [];
    window.VentaCart = {
        media: {
            open(options) {
                calls.push(options);
                return { close() {} };
            },
        },
    };
    return { picker, events, calls, inputs: () => formInputs };
}

const file = (path) => ({ path, url: `/storage/${path}`, thumb: `/storage/${path}`, name: path.split('/').pop() });

test('a single field opens beside its image and keeps the chosen path', () => {
    const { picker, events, calls, inputs } = field({ folder: 'media/brands', kind: 'brands', mode: 'single', value: 'media/brands/demo-001.jpg' });

    picker.show(null);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].mode, 'single');
    assert.equal(calls[0].kind, 'brands');
    assert.equal(calls[0].folder, 'media/brands');
    assert.equal(calls[0].value, 'media/brands/demo-001.jpg');
    assert.deepEqual(calls[0].inUse, []);

    calls[0].onChoose([file('media/brands/demo-002.jpg')]);
    assert.equal(picker.value, 'media/brands/demo-002.jpg');
    assert.equal(picker.payload, 'media/brands/demo-002.jpg');
    assert.equal(picker.urls['media/brands/demo-002.jpg'], '/storage/media/brands/demo-002.jpg');
    assert.deepEqual(events.at(-1), { type: 'media:picked', detail: { paths: ['media/brands/demo-002.jpg'], files: [file('media/brands/demo-002.jpg')] } });
    assert.equal(inputs(), 1, 'the form hears the change');

    picker.clear();
    assert.equal(picker.payload, '');
    assert.deepEqual(events.at(-1).detail.paths, []);
});

test('a multi field adds to what it holds and marks those as in use', () => {
    const { picker, events, calls } = field({ folder: 'media/products', kind: 'products', mode: 'multi', value: ['media/products/a.jpg'] });

    picker.show(null);
    assert.deepEqual(calls[0].value, []);
    assert.deepEqual(calls[0].inUse, ['media/products/a.jpg']);
    assert.equal(calls[0].limit, null);

    calls[0].onChoose([file('media/products/a.jpg'), file('media/products/b.jpg'), file('media/products/c.jpg')]);
    assert.deepEqual(picker.value, ['media/products/a.jpg', 'media/products/b.jpg', 'media/products/c.jpg']);
    assert.deepEqual(events.at(-1).detail.paths, ['media/products/b.jpg', 'media/products/c.jpg'], 'only the new paths are reported');
    assert.equal(picker.payload, JSON.stringify(picker.value));

    picker.drop('media/products/b.jpg');
    assert.deepEqual(picker.value, ['media/products/a.jpg', 'media/products/c.jpg']);
});

test('a multi field with a limit asks only for the room it has left', () => {
    const { picker, calls } = field({ mode: 'multi', limit: 3, value: ['media/products/a.jpg'] });
    picker.show(null);
    assert.equal(calls[0].limit, 2);
});

test('one dialog at a time, and a closed dialog can be opened again', () => {
    const { picker, calls } = field({ mode: 'single' });
    picker.show(null);
    picker.show(null);
    assert.equal(calls.length, 1);

    calls[0].onClose();
    picker.show(null);
    assert.equal(calls.length, 2);
});

test('the field waits for the library script when it loads later', () => {
    const picker = createMediaPicker({ mode: 'single', kind: 'blog' });
    picker.$el = { dispatchEvent() {}, closest: () => null };
    delete window.VentaCart;

    picker.show(null);

    const calls = [];
    const media = { open: (options) => { calls.push(options); return { close() {} }; } };
    window.VentaCart = { media };
    document.dispatchEvent(new CustomEvent('ventacart:media-ready', { detail: media }));

    assert.equal(calls.length, 1);
    assert.equal(calls[0].kind, 'blog');
});
