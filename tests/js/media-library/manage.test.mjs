import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from '../support/mini-dom.mjs';

installDom();

const { moveTargets, deleteSummary, renameError, createManageHooks } = await import('../../../resources/js/media-library/manage.js');
const { buildTree } = await import('../../../resources/js/media-library/tree.js');
const { createView, extend } = await import('../../../resources/js/media-library/view.js');

const tree = buildTree([
    { path: 'media', name: 'Media', parent: null, count: 6 },
    { path: 'media/products', name: 'products', parent: 'media', count: 4 },
    { path: 'media/products/Demo', name: 'Demo', parent: 'media/products', count: 2 },
    { path: 'media/products/Demo/old', name: 'old', parent: 'media/products/Demo', count: 1 },
    { path: 'media/products/demo', name: 'demo', parent: 'media/products', count: 1 },
    { path: 'media/products/Demo-2', name: 'Demo-2', parent: 'media/products', count: 0 },
    { path: 'media/banners', name: 'banners', parent: 'media', count: 1 },
]);

test('a folder cannot be moved into itself or its children', () => {
    assert.deepEqual(moveTargets(tree, ['media/products/Demo']), [
        'media',
        'media/products/demo',
        'media/products/Demo-2',
        'media/banners',
    ]);

    // "Demo-2" starts with "Demo" but is not inside it; "demo" differs by case.
    assert.ok(moveTargets(tree, ['media/products/Demo']).includes('media/products/Demo-2'));
    assert.ok(moveTargets(tree, ['media/products/Demo']).includes('media/products/demo'));

    // Moving two folders refuses both and everything inside either.
    assert.deepEqual(moveTargets(tree, ['media/products/Demo', 'media/banners']), [
        'media',
        'media/products',
        'media/products/demo',
        'media/products/Demo-2',
    ]);
});

test('images can go to any folder but the one they all sit in', () => {
    assert.deepEqual(moveTargets(tree, ['media/products/demo/demo-001.jpg', 'media/products/demo/demo-002.jpg']), [
        'media',
        'media/products',
        'media/products/Demo',
        'media/products/Demo/old',
        'media/products/Demo-2',
        'media/banners',
    ]);

    // From two folders, either one is still somewhere to send the other.
    assert.equal(moveTargets(tree, ['media/products/demo/demo-001.jpg', 'media/banners/demo-002.jpg']).length, 7);
});

test('images in use are blocked from deletion and listed', () => {
    const summary = deleteSummary(
        ['media/products/demo-001.jpg', 'media/products/demo-002.jpg', 'media/products/demo-003.jpg', 'media/products/demo-001.jpg'],
        {
            'media/products/demo-001.jpg': [{ label: 'products', count: 2 }, { label: 'menus', count: 1 }],
            'media/products/demo-003.jpg': [],
        },
    );

    assert.deepEqual(summary.deletable, ['media/products/demo-002.jpg', 'media/products/demo-003.jpg']);
    assert.deepEqual(summary.blocked, [
        { path: 'media/products/demo-001.jpg', uses: [{ label: 'products', count: 2 }, { label: 'menus', count: 1 }] },
    ]);

    // The in-use list from a refused delete (409) reads the same way.
    const fromServer = deleteSummary(['media/a.jpg'], new Map([['media/a.jpg', [{ label: 'settings', count: 1 }]]]));
    assert.deepEqual(fromServer, { deletable: [], blocked: [{ path: 'media/a.jpg', uses: [{ label: 'settings', count: 1 }] }] });
});

test('a rename is refused with the server\'s own words', () => {
    assert.equal(renameError('   ', [], { folder: true }), 'Give it a name.');
    assert.equal(renameError('', [], { folder: false }), 'Give it a name.');
    assert.equal(renameError('a/b', [], { folder: true }), 'A folder name cannot contain / \\ : * ? " < > or |.');
    assert.equal(renameError('.hidden', [], { folder: true }), 'A folder name cannot start with a dot.');
    assert.equal(renameError('end.', [], { folder: true }), 'A folder name cannot end with a dot.');
    assert.equal(renameError('a..b', [], { folder: true }), 'A folder name cannot contain two dots in a row.');
    assert.equal(renameError('tab\there', [], { folder: true }), 'A folder name cannot contain line breaks, tabs or other hidden characters.');
    assert.equal(renameError('x'.repeat(81), [], { folder: true }), 'A folder name can be at most 80 characters.');
    assert.equal(renameError('é'.repeat(80), [], { folder: true }), null);
});

test('a taken folder name is compared exactly, as the server compares it', () => {
    assert.equal(renameError(' Demo ', ['Demo', 'banners'], { folder: true }), 'A folder called Demo is already here.');
    assert.equal(renameError('demo', ['Demo'], { folder: true }), null);
    assert.equal(renameError('Dem_', ['Demo'], { folder: true }), null);
    // An image's name is made web-safe by the server, which also says when it is taken.
    assert.equal(renameError('Demo', ['Demo'], { folder: false }), null);
});

/* ------------------------------------------------------- the page's tools */

const files = [
    { path: 'media/products/demo-001.jpg', name: 'demo-001.jpg', folder: 'media/products', url: '/s/1.jpg', thumb: '/s/1_thumb.jpg', size: 2048, width: 800, height: 600, usedBy: { total: 2, label: 'products' } },
    { path: 'media/products/demo-002.jpg', name: 'demo-002.jpg', folder: 'media/products', url: '/s/2.jpg', thumb: '/s/2_thumb.jpg', size: 1024, width: 400, height: 400, usedBy: { total: 0, label: null } },
];

function fakeApi() {
    return {
        tree: async () => ({ folders: [...tree.values()].map(({ path, name, parent, count }) => ({ path, name, parent, count })) }),
        browse: async () => ({ files, folders: [], page: 1, lastPage: 1, total: files.length }),
        usage: async () => ({ uses: [{ label: 'products', count: 2 }], total: 2 }),
    };
}

async function pageWith(can) {
    const view = createView({
        api: fakeApi(),
        config: { can: { view: true, ...can }, uploadAccept: ['jpg'] },
        options: { mode: 'multi', limit: null },
        modal: false,
    });
    document.body.append(view.root);
    extend(view, createManageHooks(view));
    await view.start();
    view.selection().add([files[0].path]);
    view.redraw();
    return view;
}

const labels = (root, selector) => root.querySelectorAll(selector).map((b) => b.textContent.trim());

test('the tools the user lacks are not drawn', async () => {
    const looker = await pageWith({ upload: false, edit: false, delete: false });
    // Details is the only tool: it opens what the side panel shows, for phones.
    assert.deepEqual(labels(looker.els.picked, '.ml-mng-acts button'), ['Details']);
    assert.equal(looker.els.upload, null, 'no Upload without media.create');
    assert.equal(looker.root.querySelector('.ml-mng-folder'), null, 'no folder menu with nothing in it');
    assert.ok(looker.root.querySelector('.ml-panel'), 'the Used by panel is still there to look at');
    looker.destroy();

    const editor = await pageWith({ upload: false, edit: true, delete: false });
    assert.deepEqual(labels(editor.els.picked, '.ml-mng-acts button'), ['Details', 'Rename', 'Move to...']);
    editor.destroy();

    const all = await pageWith({ upload: true, edit: true, delete: true });
    assert.deepEqual(labels(all.els.picked, '.ml-mng-acts button'), ['Details', 'Rename', 'Move to...', 'Delete']);
    assert.equal(all.els.upload.getAttribute('aria-haspopup'), 'menu');

    // Rename is for one image at a time.
    all.selection().add([files[1].path]);
    all.redraw();
    assert.deepEqual(labels(all.els.picked, '.ml-mng-acts button'), ['Move to...', 'Delete']);
    all.destroy();
});

test('with one image selected, a user who may only look sees it, not a request to select one', async () => {
    const looker = await pageWith({ upload: false, edit: false, delete: false });
    assert.doesNotMatch(looker.els.picked.textContent, /Select an image/);
    assert.match(looker.els.picked.textContent, /1 selected/);

    // The side panel describes the selected image even when none has focus.
    const panel = looker.root.querySelector('.ml-mng-panel');
    assert.match(panel.textContent, /demo-001\.jpg/);
    assert.doesNotMatch(panel.textContent, /Select an image/);

    // Two selected: there is no one image to describe, so the hint returns.
    looker.selection().add([files[1].path]);
    looker.redraw();
    assert.match(looker.els.picked.textContent, /Select one image to see where it is used\./);
    looker.destroy();
});

test('Details shows the image and what uses it in a dialog that gives focus back', async () => {
    const looker = await pageWith({ upload: false, edit: false, delete: false });
    const opener = looker.els.picked.querySelector('.ml-mng-details');
    opener.focus();
    opener.click();

    const dialog = document.querySelector('dialog.ml-mng-dialog');
    assert.ok(dialog?.open, 'the dialog is open');
    assert.match(dialog.textContent, /demo-001\.jpg/);
    assert.match(dialog.textContent, /Used by/);
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.match(dialog.textContent, /2 products/);

    [...dialog.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Close').click();
    assert.equal(document.querySelector('dialog.ml-mng-dialog'), null, 'the dialog is gone');
    assert.equal(document.activeElement, opener, 'focus is back on Details');
    looker.destroy();
});

test('a folder listed under one that cannot be chosen says where it is', async () => {
    const page = await pageWith({ upload: false, edit: true, delete: false });
    [...page.els.picked.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Move to...').click();

    const dialog = document.querySelector('dialog.ml-mng-dialog');
    const row = (path) => dialog.querySelectorAll('.ml-mng-target').find((l) => l.querySelector('input').getAttribute('value') === path);
    const where = (path) => row(path).querySelector('.ml-mng-target__where')?.textContent ?? null;

    // The image sits in products, so products is not offered, and its
    // folders would otherwise read as folders of the one listed above them.
    assert.equal(row('media/products'), undefined);
    assert.equal(where('media/products/Demo'), 'Media / products');
    assert.equal(where('media/products/demo'), 'Media / products');
    // A folder whose parent is in the list keeps the indenting alone.
    assert.equal(where('media/products/Demo/old'), null);
    assert.equal(where('media/banners'), null);

    [...dialog.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Cancel').click();
    page.destroy();
});
