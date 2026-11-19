import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (name) => readFileSync(new URL(`../../../resources/css/media-library/${name}`, import.meta.url), 'utf8');

/**
 * The style rules of a sheet as { media, selectors, body }, where media is
 * the condition of the @media block around the rule ('' outside any).
 * Enough for these sheets: no nesting beyond one @media level.
 */
function rules(css) {
    const out = [];
    const text = css.replace(/\/\*[\s\S]*?\*\//g, '');
    const walk = (chunk, media) => {
        let i = 0;
        while (i < chunk.length) {
            const open = chunk.indexOf('{', i);
            if (open < 0) break;
            const head = chunk.slice(i, open).trim();
            let depth = 1;
            let j = open + 1;
            while (j < chunk.length && depth) {
                if (chunk[j] === '{') depth++;
                if (chunk[j] === '}') depth--;
                j++;
            }
            const body = chunk.slice(open + 1, j - 1);
            if (head.startsWith('@media')) walk(body, head.slice(6).trim());
            else if (!head.startsWith('@')) out.push({ media, selectors: head.split(',').map((s) => s.trim()), body });
            i = j;
        }
    };
    walk(text, '');
    return out;
}

const display = (rule) => /display:\s*([a-z-]+)/.exec(rule.body)?.[1] ?? null;
const classes = (selector) => (selector.match(/\.[\w-]+/g) || []).length;

test('a folder\'s own folders show above its images on a wide screen', () => {
    const wide = rules(read('media-library.css')).filter((r) => r.media === '' && r.selectors.includes('.ml-folders.is-rows'));

    assert.ok(wide.length, 'the folders in view have a wide-screen rule');
    assert.ok(wide.every((r) => display(r) !== 'none'), 'and it does not hide them');
    assert.ok(wide.some((r) => display(r)), 'and it shows them');
});

test('the phone Details button is hidden on a wide screen, whatever sheet loads last', () => {
    const btn = rules(read('media-library.css')).find((r) => r.media === '' && r.selectors.includes('.ml-btn'));
    assert.equal(display(btn), 'inline-flex', 'a library button shows itself');

    const manage = rules(read('manage.css'));
    const hide = manage.filter((r) => r.media === '' && display(r) === 'none' && r.selectors.some((s) => s.endsWith('.ml-mng-details')));
    assert.ok(hide.length, 'Details has a wide-screen rule that hides it');
    // The two sheets load in either order, so the hiding rule must outrank
    // the button's own rule rather than come after it.
    assert.ok(hide.every((r) => r.selectors.filter((s) => s.endsWith('.ml-mng-details')).every((s) => classes(s) > 1)));

    const show = manage.filter((r) => /max-width:\s*719px/.test(r.media) && display(r) && r.selectors.some((s) => s.endsWith('.ml-mng-details')));
    assert.ok(show.length, 'Details shows under 720 px');
    assert.ok(show.every((r) => r.selectors.filter((s) => s.endsWith('.ml-mng-details')).every((s) => classes(s) > 1)));
});
