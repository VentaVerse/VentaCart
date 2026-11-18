import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildTree, filterTree, ancestors, folderLabel } from '../../../resources/js/media-library/tree.js';

const folders = [
    { path: 'media', name: 'media', parent: null, count: 5 },
    { path: 'media/products', name: 'products', parent: 'media', count: 4 },
    { path: 'media/products/Demo', name: 'Demo', parent: 'media/products', count: 2 },
    { path: 'media/products/demo', name: 'demo', parent: 'media/products', count: 1 },
    { path: 'media/banners', name: 'banners', parent: 'media', count: 1 },
];

test('filtering keeps the folders a match sits in', () => {
    const keep = filterTree(buildTree(folders), 'DEMO');
    assert.deepEqual([...keep].sort(), ['media', 'media/products', 'media/products/Demo', 'media/products/demo']);
});

test('folders differing by case stay separate', () => {
    const tree = buildTree(folders);
    assert.equal(tree.get('media/products').children.length, 2);
    assert.equal(tree.get('media/products/Demo').count, 2);
    assert.equal(tree.get('media/products/demo').count, 1);
});

test('ancestors and labels', () => {
    assert.deepEqual(ancestors('media/products/Demo'), ['media', 'media/products']);
    assert.equal(folderLabel('media/products/Demo'), 'Media / products / Demo');
});
