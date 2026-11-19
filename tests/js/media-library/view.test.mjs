import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from '../support/mini-dom.mjs';

installDom();

const { createView, folderRowShows } = await import('../../../resources/js/media-library/view.js');

const folders = [
    { path: 'media', name: 'Media', parent: null, count: 2 },
    { path: 'media/products', name: 'products', parent: 'media', count: 2 },
    { path: 'media/products/Demo', name: 'Demo', parent: 'media/products', count: 1 },
    { path: 'media/products/demo', name: 'demo', parent: 'media/products', count: 1 },
    { path: 'media/banners', name: 'banners', parent: 'media', count: 0 },
];

const image = { path: 'media/products/demo-001.jpg', name: 'demo-001.jpg', folder: 'media/products', url: '/s/1.jpg', thumb: '/s/1_thumb.jpg', size: 2048, width: 800, height: 600, usedBy: { total: 0, label: null } };

/** A library whose every folder holds the given images and its own folders. */
async function libraryAt(path, files) {
    const view = createView({
        api: {
            tree: async () => ({ folders }),
            browse: async ({ path: at }) => ({
                files,
                folders: folders.filter((f) => f.parent === at),
                page: 1,
                lastPage: 1,
                total: files.length,
            }),
            usage: async () => ({ uses: [], total: 0 }),
        },
        config: { can: { view: true, upload: true }, uploadAccept: ['jpg'] },
        options: { mode: 'single', limit: null, folder: path },
        modal: false,
    });
    document.body.append(view.root);
    await view.start();
    return view;
}

const chips = (view) => view.root.querySelectorAll('.ml-folders .ml-fchip').map((b) => b.textContent.trim());

test('a folder holding only folders shows them and is not called empty', async () => {
    const view = await libraryAt('media/products', []);

    assert.match(view.els.folders.className, /\bis-rows\b/, 'the folders are drawn above the images');
    assert.deepEqual(chips(view), ['Demo1', 'demo1']);
    assert.equal(view.els.state.hidden, true, 'no "No images" message while it has folders');
    view.destroy();
});

test('on a wide screen a folder with images leaves its folders to the tree', async () => {
    const view = await libraryAt('media/products', [image]);

    assert.deepEqual(chips(view), [], 'no folder row: the tree lists them');
    assert.doesNotMatch(view.els.folders.className, /\bis-rows\b/);
    assert.equal(view.els.count.textContent, '1 image', 'and the count leaves out the folders too');
    assert.equal(view.els.grid.querySelectorAll('.ml-tile').length, 1);
    assert.equal(view.els.state.hidden, true);
    view.destroy();
});

test('on a phone a folder with images shows its folders above them', async () => {
    const wide = globalThis.matchMedia;
    globalThis.matchMedia = () => ({ matches: true, addEventListener() {}, removeEventListener() {} });
    try {
        const view = await libraryAt('media/products', [image]);

        assert.deepEqual(chips(view), ['Demo1', 'demo1']);
        assert.equal(view.els.count.textContent, '1 image, 2 folders');
        assert.equal(view.els.grid.querySelectorAll('.ml-tile').length, 1);
        view.destroy();
    } finally {
        globalThis.matchMedia = wide;
    }
});

test('the folder row shows in search results, on a phone, or in place of an empty grid', () => {
    // Nothing to show without folders.
    assert.equal(folderRowShows({ folders: 0 }), false);
    assert.equal(folderRowShows({ searching: true, folders: 0, phone: true }), false);
    // Wide screen: only in place of an empty grid, or for search results.
    assert.equal(folderRowShows({ folders: 2, images: 0 }), true);
    assert.equal(folderRowShows({ folders: 2, images: 5 }), false);
    assert.equal(folderRowShows({ searching: true, folders: 1, images: 5 }), true);
    // Phone: whenever there are folders, since the tree is hidden.
    assert.equal(folderRowShows({ folders: 2, images: 5, phone: true }), true);
});

test('a folder with no folders and no images says it has no images', async () => {
    const view = await libraryAt('media/banners', []);

    assert.deepEqual(chips(view), []);
    assert.equal(view.els.state.hidden, false);
    assert.match(view.els.state.textContent, /No images in banners/);
    view.destroy();
});
