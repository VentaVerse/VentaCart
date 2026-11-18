import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkFiles, usedByText } from '../../../resources/js/media-library/files.js';

const file = (name, type, size) => ({ name, type, size });

test('files are checked for type and size', () => {
    const big = file('big.png', 'image/png', 6 * 1024 * 1024);
    const gif = file('a.gif', 'image/gif', 10);
    const fine = file('a.webp', 'image/webp', 10);
    const { ok, refused } = checkFiles([big, gif, fine], { maxBytes: 5 * 1024 * 1024 });
    assert.deepEqual(ok, [fine]);
    assert.deepEqual(refused, [
        { file: big, reason: 'Larger than 5 MB' },
        { file: gif, reason: 'Not a JPG, PNG or WebP file' },
    ]);
});

test('used-by text names the kind or counts places', () => {
    assert.equal(usedByText({ total: 0, label: null }), '');
    assert.equal(usedByText({ total: 1, label: 'products' }), 'Used by 1 product');
    assert.equal(usedByText({ total: 2, label: 'products' }), 'Used by 2 products');
    assert.equal(usedByText({ total: 1, label: 'blog posts' }), 'Used by 1 blog post');
    assert.equal(usedByText({ total: 3, label: null }), 'Used in 3 places');
    assert.equal(usedByText({ total: 2, label: 'widgets' }), 'Used by 2 widgets');
});
