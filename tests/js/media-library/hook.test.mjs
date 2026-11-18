import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from '../support/mini-dom.mjs';

const { document } = installDom();

function setConfig(can) {
    document.getElementById('media-library-config')?.remove();
    const script = document.createElement('script');
    script.setAttribute('type', 'application/json');
    script.setAttribute('id', 'media-library-config');
    script.textContent = JSON.stringify({
        endpoints: { browse: '/b', tree: '/t', upload: '/u', importUrl: '/i', folder: '/f', rename: '/r', move: '/m', delete: '/d', usage: '/us' },
        token: 'tok',
        can,
        maxBytes: 5242880,
        maxFiles: 20,
        perPage: 60,
        accept: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        uploadAccept: ['jpg', 'jpeg', 'png', 'webp'],
    });
    document.body.append(script);
}

setConfig({ view: true, upload: true, edit: true, delete: true });

let announced = 0;
document.addEventListener('ventacart:media-ready', () => { announced += 1; });

await import('../../../resources/js/media-library/index.js');

test('importing the entry registers open and mount and announces itself', () => {
    const media = window.VentaCart.media;
    assert.equal(media.version, 1);
    assert.equal(typeof media.open, 'function');
    assert.equal(typeof media.mount, 'function');
    assert.equal(announced, 1);
});

test('the library explains a missing permission instead of opening empty', () => {
    setConfig({ view: false, upload: false, edit: false, delete: false });

    let requests = 0;
    globalThis.fetch = async () => {
        requests += 1;
        return new Response('{"ok":true}');
    };

    const opener = document.createElement('button');
    document.body.append(opener);
    opener.focus();

    let closed = 0;
    const handle = window.VentaCart.media.open({ opener, onClose: () => { closed += 1; } });

    const dialog = document.querySelector('dialog.ml-root');
    assert.ok(dialog, 'a dialog is shown');
    assert.ok(dialog.open);
    assert.match(dialog.textContent, /Ask an admin for the Media view permission\./);
    assert.equal(dialog.querySelector('.ml-grid'), null, 'no image grid is drawn');
    assert.equal(requests, 0);

    handle.close();
    assert.equal(dialog.open, false);
    assert.equal(dialog.isConnected, false);
    assert.equal(document.activeElement, opener);
    assert.equal(closed, 1);
    assert.equal(requests, 0);
});
