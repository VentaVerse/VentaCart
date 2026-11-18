import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Selection } from '../../../resources/js/media-library/selection.js';

const list = ['a', 'b', 'c', 'd', 'e'];

test('numbers follow the order picked', () => {
    const s = new Selection({ mode: 'multi', limit: 5 });
    s.toggle('c');
    s.toggle('a');
    assert.equal(s.number('c'), 1);
    assert.equal(s.number('a'), 2);
    assert.equal(s.number('b'), null);
    s.toggle('c');
    assert.equal(s.number('a'), 1);
});

test('single mode replaces the pick', () => {
    const s = new Selection({ mode: 'single', value: 'a' });
    s.set('b');
    assert.deepEqual(s.paths(), ['b']);
    assert.equal(s.buttonLabel(), 'Choose');
});

test('shift picks a run and skips images already in the field', () => {
    const s = new Selection({ mode: 'multi', limit: 10, inUse: ['c'] });
    s.toggle('a');
    s.toggle('e', { range: true, list });
    assert.deepEqual(s.paths(), ['a', 'b', 'd', 'e']);
    assert.equal(s.disabled('c'), true);
});

test('a pick beyond the limit is ignored', () => {
    const s = new Selection({ mode: 'multi', limit: 2 });
    s.toggle('a');
    s.toggle('b');
    s.toggle('c');
    assert.deepEqual(s.paths(), ['a', 'b']);
    assert.equal(s.remaining(), 0);
});

test('uploads join the selection up to the limit', () => {
    const s = new Selection({ mode: 'multi', limit: 3 });
    s.toggle('a');
    assert.deepEqual(s.add(['x', 'y', 'z']), ['x', 'y']);
    assert.deepEqual(s.paths(), ['a', 'x', 'y']);
});

test('the button names how many will be added', () => {
    const s = new Selection({ mode: 'multi', limit: 5 });
    s.toggle('a');
    assert.equal(s.buttonLabel(), 'Add 1 image');
    s.toggle('b');
    s.toggle('c');
    assert.equal(s.buttonLabel(), 'Add 3 images');
});
