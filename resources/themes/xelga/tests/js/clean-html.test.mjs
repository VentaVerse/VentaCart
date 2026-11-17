// Run: node --test 'resources/themes/xelga/tests/js/**/*.test.mjs'

/*
 * The cleaner the preview frame and the layout board both use on markup
 * from the server: nothing that can run or load code survives, and what a
 * storefront section needs (its links, photos, icons and data attributes)
 * stays.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { installDom } from './support/mini-dom.mjs';

installDom({ href: 'https://shop.example.com/' });
const { cleanFragment, pickOne, cleanTree, MAX_HTML } = await import('../../editor/js/modules/clean-html.js');

const html = (fragment) => fragment.innerHTML;

test('scripts and every element that can run or load code are removed with their content', () => {
  const out = html(cleanFragment(
    '<div><script>alert(1)</script><iframe src="/x"></iframe><object data="/x"></object><embed src="/x">'
    + '<link rel="stylesheet" href="/x.css"><style>p{}</style><base href="https://example.test/"><meta http-equiv="refresh">'
    + '<noscript><p>no</p></noscript><p>kept</p></div>',
  ));
  assert.equal(out, '<div><p>kept</p></div>');
});

test('animated SVG parts that can rewrite attributes are removed', () => {
  const out = html(cleanFragment('<svg><set attributename="href" to="javascript:alert(1)"></set><animate attributename="href"></animate><foreignobject><p>x</p></foreignobject><rect></rect></svg>'));
  assert.equal(out, '<svg><rect></rect></svg>');
});

test('inline handlers are dropped, whatever their case', () => {
  const out = html(cleanFragment('<img src="/a.jpg" onerror="alert(1)" ONLOAD="x()"><button onclick="x()" data-on="ok">Buy</button>'));
  assert.equal(out, '<img src="/a.jpg"><button data-on="ok">Buy</button>');
});

test('srcdoc is dropped', () => {
  assert.equal(html(cleanFragment('<div srcdoc="<script>alert(1)</script>">x</div>')), '<div>x</div>');
});

test('javascript and other script addresses are dropped, also when hidden with spaces or entities', () => {
  const out = html(cleanFragment(
    '<a href="javascript:alert(1)">a</a><a href=" JaVaScRiPt:alert(1)">b</a><a href="java&#9;script:alert(1)">c</a>'
    + '<a href="vbscript:x">d</a><a href="data:text/html,x">e</a><div data-href="javascript:alert(1)">f</div>',
  ));
  assert.equal(out, '<a>a</a><a>b</a><a>c</a><a>d</a><a>e</a><div>f</div>');
});

test('store links, web addresses, mail and phone links and page fragments stay', () => {
  const kept = '<a href="/products/demo-001">a</a><a href="https://example.com/x">b</a><a href="mailto:juan@example.com">c</a><a href="tel:+10000000000">d</a><a href="#reviews">e</a><a href="?page=2">f</a>';
  assert.equal(html(cleanFragment(kept)), kept);
});

test('a photo may be an inline image, but no other inline data', () => {
  const out = html(cleanFragment('<img src="data:image/png;base64,iVBORw0KGgo="><img src="data:image/svg+xml;base64,PHN2Zz4="><img srcset="/a.jpg 1x, data:text/html,x 2x">'));
  assert.equal(out, '<img src="data:image/png;base64,iVBORw0KGgo="><img><img>');
});

test('srcset keeps only lists made of safe addresses', () => {
  const out = html(cleanFragment('<img srcset="/a-320.jpg 320w, /a-640.jpg 640w"><img srcset="/a.jpg 1x, javascript:x 2x">'));
  assert.equal(out, '<img srcset="/a-320.jpg 320w, /a-640.jpg 640w"><img>');
});

test('an icon only points into the page, never at another file', () => {
  const out = html(cleanFragment('<svg><use href="#i-cart"></use></svg><svg><use href="/sprite.svg#i-cart"></use></svg><svg><use xlink:href="https://example.test/x.svg#a"></use></svg>'));
  assert.equal(out, '<svg><use href="#i-cart"></use></svg><svg><use></use></svg><svg><use></use></svg>');
});

test('a form only submits to the store itself', () => {
  const out = html(cleanFragment('<form action="/search"><button formaction="https://example.test/steal">x</button></form><form action="https://example.test/x"></form>'));
  assert.equal(out, '<form action="/search"><button>x</button></form><form></form>');
});

test('a template inside the markup is cleaned too', () => {
  const out = html(cleanFragment('<template><img src="/a.jpg" onerror="x()"><script>x()</script></template>'));
  assert.equal(out, '<template><img src="/a.jpg"></template>');
});

test('markup over the size limit, or no markup at all, gives an empty fragment', () => {
  assert.equal(html(cleanFragment('x'.repeat(MAX_HTML + 1))), '');
  assert.equal(html(cleanFragment('')), '');
  assert.equal(html(cleanFragment(null)), '');
});

test('pickOne answers the one top-level element asked for, cleaned', () => {
  const markup = '<!--xelga:hero-1--><section data-section-id="hero-1" onclick="x()"><a href="javascript:x">Shop</a><div data-section-id="inner"></div></section><!--/xelga:hero-1--><section data-section-id="other"></section>';
  const el = pickOne(markup, '[data-section-id]', 'hero-1', 'data-section-id');
  assert.equal(el.outerHTML, '<section data-section-id="hero-1"><a>Shop</a><div data-section-id="inner"></div></section>');
  // Nested elements are not top level; an id not in the markup answers null.
  assert.equal(pickOne(markup, '[data-section-id]', 'inner', 'data-section-id'), null);
  assert.equal(pickOne(markup, '[data-section-id]', 'missing', 'data-section-id'), null);
  assert.equal(pickOne('', '[data-section-id]', 'hero-1', 'data-section-id'), null);
});

test('cleanTree cleans a tree in place and answers it', () => {
  const tpl = document.createElement('template');
  tpl.innerHTML = '<p onclick="x()">a</p>';
  assert.equal(cleanTree(tpl.content), tpl.content);
  assert.equal(tpl.innerHTML, '<p>a</p>');
});
