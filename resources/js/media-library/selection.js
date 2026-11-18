/**
 * What is picked in the library, in the order it was picked. No DOM.
 *
 * In `single` mode one image is held at a time. In `multi` mode picks are
 * numbered, `limit` caps them, and `inUse` paths (images the field already
 * holds) cannot be picked again.
 */
export class Selection {
    constructor({ mode = 'single', limit = 1, value = null, inUse = [] } = {}) {
        this.mode = mode;
        this.limit = mode === 'single' ? 1 : limit;
        this.inUse = new Set(inUse);
        this.picked = [];

        const start = Array.isArray(value) ? value : value ? [value] : [];
        start.forEach((path) => {
            if (!this.picked.includes(path) && (this.limit === null || this.picked.length < this.limit)) {
                this.picked.push(path);
            }
        });
    }

    full() {
        return this.limit !== null && this.picked.length >= this.limit;
    }

    toggle(path, { range = false, list = [] } = {}) {
        if (this.mode === 'single') return this.set(path);
        if (this.disabled(path)) return;

        if (this.has(path)) {
            this.remove(path);
            return;
        }

        const anchor = this.picked[this.picked.length - 1];
        const from = list.indexOf(anchor);
        const to = list.indexOf(path);

        if (range && from !== -1 && to !== -1) {
            const [lo, hi] = from < to ? [from, to] : [to, from];
            const run = list.slice(lo, hi + 1).filter((p) => !this.disabled(p));
            // The run reads from the last pick outward, so the numbers follow it.
            this.add(from < to ? run : run.reverse());
            return;
        }

        this.add([path]);
    }

    set(path) {
        if (this.disabled(path)) return;
        this.picked = [path];
    }

    add(paths) {
        const added = [];
        paths.forEach((path) => {
            if (this.full() || this.has(path) || this.disabled(path)) return;
            this.picked.push(path);
            added.push(path);
        });
        return added;
    }

    remove(path) {
        this.picked = this.picked.filter((p) => p !== path);
    }

    has(path) {
        return this.picked.includes(path);
    }

    number(path) {
        if (this.mode === 'single') return null;
        const at = this.picked.indexOf(path);
        return at === -1 ? null : at + 1;
    }

    disabled(path) {
        return this.mode === 'multi' && this.inUse.has(path);
    }

    paths() {
        return [...this.picked];
    }

    remaining() {
        if (this.mode === 'single' || this.limit === null) return null;
        return Math.max(0, this.limit - this.picked.length);
    }

    buttonLabel() {
        if (this.mode === 'single') return 'Choose';
        const n = this.picked.length;
        if (n === 0) return 'Add images';
        return n === 1 ? 'Add 1 image' : `Add ${n} images`;
    }
}
