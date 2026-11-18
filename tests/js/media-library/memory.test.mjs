import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { lastFolder, rememberFolder, thumbSize, rememberThumbSize } from '../../../resources/js/media-library/memory.js';

function fakeStorage() {
    const data = new Map();
    return { getItem: (k) => (data.has(k) ? data.get(k) : null), setItem: (k, v) => data.set(k, String(v)), data };
}

beforeEach(() => {
    globalThis.localStorage = fakeStorage();
});

test('lastFolder falls back when the folder is gone', () => {
    rememberFolder('product', 'media/products');
    assert.equal(globalThis.localStorage.data.get('media-library:last:product'), 'media/products');
    assert.equal(lastFolder('product', () => true), 'media/products');
    assert.equal(lastFolder('product', () => false), null);
    assert.equal(lastFolder('brand', () => true), null);
});

test('thumb size defaults to small and remembers large', () => {
    assert.equal(thumbSize(), 'small');
    rememberThumbSize('large');
    assert.equal(thumbSize(), 'large');
});

test('storage that throws is treated as empty', () => {
    const boom = () => {
        throw new Error('blocked');
    };
    globalThis.localStorage = { getItem: boom, setItem: boom };
    assert.equal(lastFolder('product', () => true), null);
    assert.equal(thumbSize(), 'small');
    rememberFolder('product', 'media/x');
    rememberThumbSize('large');
});
