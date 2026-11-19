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

test('a folder\'s own folders show above its images on a wide screen', () => {
    const wide = rules(read('media-library.css')).filter((r) => r.media === '' && r.selectors.includes('.ml-folders.is-rows'));

    assert.ok(wide.length, 'the folders in view have a wide-screen rule');
    assert.ok(wide.every((r) => display(r) !== 'none'), 'and it does not hide them');
    assert.ok(wide.some((r) => display(r)), 'and it shows them');
});
