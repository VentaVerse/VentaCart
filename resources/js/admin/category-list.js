// The category list: tick boxes for the bulk bar, and the tree arranged by
// hand. A row carries its level (data-depth); a category's subcategories
// are the rows right below it that sit deeper, and they always move with it.
//
// Drag a row's handle: where it is dropped sets its place, how far left or
// right sets its level, from the top level down to one level under the row
// above. Without a pointer the handle takes the arrow keys: Up and Down move
// among siblings, Right puts it under the sibling above, Left takes it one
// level out. Nothing is saved until Save: the order rides a hidden field of
// the page's form, and changing it marks the form as edited.

// One level's indent, read from the stylesheet (--cat-indent) so a phone's
// narrower steps move the same as they look.
const INDENT = 22;

export function registerCategoryList(Alpine) {
    Alpine.data('vCategoryList', (cfg) => ({
        selected: [],
        ids: Array.isArray(cfg.ids) ? cfg.ids : [],
        dragging: false,
        announcement: '',

        get body() { return this.$refs.body; },

        indent() {
            const wrap = this.$refs.wrap;
            const px = wrap ? parseFloat(getComputedStyle(wrap).getPropertyValue('--cat-indent')) : NaN;
            return px > 0 ? px : INDENT;
        },

        rows() { return [...this.body.querySelectorAll('tr[data-id]')]; },
        depth(tr) { return Number(tr.dataset.depth) || 0; },
        setDepth(tr, d) { tr.dataset.depth = String(d); tr.style.setProperty('--depth', d); },
        name(tr) { return tr.dataset.name || ''; },

        /** The row and its subcategories, in order. */
        block(tr) {
            const rows = this.rows();
            const at = rows.indexOf(tr);
            const d = this.depth(tr);
            const out = [tr];
            for (let i = at + 1; i < rows.length && this.depth(rows[i]) > d; i++) out.push(rows[i]);
            return out;
        },

        /** Put a block before a row (or at the end) and shift its levels. */
        place(block, before, depth) {
            const shift = depth - this.depth(block[0]);
            block.forEach((tr) => {
                this.body.insertBefore(tr, before || null);
                this.setDepth(tr, this.depth(tr) + shift);
            });
            this.changed();
        },

        /** Renumber each row's place among its siblings and hand the order to the form. */
        changed() {
            const counts = [];
            const order = [];
            this.rows().forEach((tr) => {
                const d = this.depth(tr);
                counts.length = d + 1;
                counts[d] = counts[d] === undefined ? 0 : counts[d] + 1;
                const sort = tr.querySelector('[data-sort]');
                if (sort) sort.textContent = String(counts[d]);
                order.push([Number(tr.dataset.id), d]);
            });
            // The field sits in the arrange form, a component of its own,
            // so it is found in the list's markup rather than by $refs.
            const field = this.$root.querySelector('[data-order]');
            if (field) {
                field.value = JSON.stringify(order);
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        say(text) { this.announcement = ''; this.$nextTick(() => { this.announcement = text; }); },

        // ---- dragging ----------------------------------------------------
        start(event, tr) {
            if (event.button !== undefined && event.button !== 0) return;
            event.preventDefault();
            const handle = event.currentTarget;
            handle.setPointerCapture?.(event.pointerId);
            const block = this.block(tr);
            this.drag = { block, startX: event.clientX, base: this.depth(tr), target: null, handle };
            block.forEach((r) => r.classList.add('is-dragging'));
            this.dragging = true;
        },

        move(event) {
            if (!this.drag) return;
            const { block, startX, base } = this.drag;
            const others = this.rows().filter((r) => !block.includes(r));
            let index = others.length;
            for (let i = 0; i < others.length; i++) {
                const box = others[i].getBoundingClientRect();
                if (event.clientY < box.top + box.height / 2) { index = i; break; }
            }
            const prev = others[index - 1];
            const next = others[index];
            const max = prev ? this.depth(prev) + 1 : 0;
            const min = next ? Math.min(this.depth(next), max) : 0;
            const wanted = base + Math.round((event.clientX - startX) / this.indent());
            const depth = Math.max(min, Math.min(max, wanted));
            this.drag.target = { before: next || null, depth };
            this.marker(prev, next, depth);

            // Near the edge of the window, keep the list moving.
            if (event.clientY < 70) window.scrollBy(0, -14);
            else if (event.clientY > window.innerHeight - 70) window.scrollBy(0, 14);
        },

        /** The drop line, drawn at the place and level the row would land. */
        marker(prev, next, depth) {
            const line = this.$refs.drop;
            const wrap = this.$refs.wrap;
            if (!line || !wrap) return;
            const wrapBox = wrap.getBoundingClientRect();
            const anchor = next || prev;
            if (!anchor) { line.hidden = true; return; }
            const box = anchor.getBoundingClientRect();
            const nameCell = anchor.querySelector('.v-catname');
            const left = (nameCell ? nameCell.getBoundingClientRect().left - wrapBox.left : 0)
                + (depth - this.depth(anchor)) * this.indent();
            line.hidden = false;
            line.style.top = `${(next ? box.top : box.bottom) - wrapBox.top + wrap.scrollTop - 1}px`;
            line.style.left = `${Math.max(0, left)}px`;
            line.style.right = '0px';
        },

        end() {
            if (!this.drag) return;
            const { block, target } = this.drag;
            block.forEach((r) => r.classList.remove('is-dragging'));
            if (this.$refs.drop) this.$refs.drop.hidden = true;
            this.drag = null;
            this.dragging = false;
            if (target) {
                this.place(block, target.before, target.depth);
                this.say(`${this.name(block[0])} moved. Save to keep the new order.`);
            }
        },

        // ---- the keyboard ------------------------------------------------
        key(event, tr) {
            const moves = { ArrowUp: 'up', ArrowDown: 'down', ArrowRight: 'in', ArrowLeft: 'out' };
            const how = moves[event.key];
            if (!how) return;
            event.preventDefault();
            if (this[how](tr)) {
                this.$nextTick(() => tr.querySelector('[data-handle]')?.focus());
            }
        },

        siblingBefore(tr) {
            const rows = this.rows();
            const d = this.depth(tr);
            for (let i = rows.indexOf(tr) - 1; i >= 0; i--) {
                const dd = this.depth(rows[i]);
                if (dd === d) return rows[i];
                if (dd < d) return null;
            }
            return null;
        },

        siblingAfter(tr) {
            const rows = this.rows();
            const d = this.depth(tr);
            const block = this.block(tr);
            const after = rows[rows.indexOf(block[block.length - 1]) + 1];
            return after && this.depth(after) === d ? after : null;
        },

        up(tr) {
            const sib = this.siblingBefore(tr);
            if (!sib) return false;
            this.place(this.block(tr), sib, this.depth(tr));
            this.say(`${this.name(tr)} moved up.`);
            return true;
        },

        down(tr) {
            const sib = this.siblingAfter(tr);
            if (!sib) return false;
            const sibBlock = this.block(sib);
            const rows = this.rows();
            this.place(this.block(tr), rows[rows.indexOf(sibBlock[sibBlock.length - 1]) + 1] || null, this.depth(tr));
            this.say(`${this.name(tr)} moved down.`);
            return true;
        },

        /** Under the sibling above, as its last subcategory. */
        in(tr) {
            const sib = this.siblingBefore(tr);
            if (!sib) return false;
            const block = this.block(tr);
            const rows = this.rows();
            this.place(block, rows[rows.indexOf(block[block.length - 1]) + 1] || null, this.depth(tr) + 1);
            this.say(`${this.name(tr)} is now under ${this.name(sib)}.`);
            return true;
        },

        /** One level out, right after its parent's branch. */
        out(tr) {
            const d = this.depth(tr);
            if (d === 0) return false;
            const rows = this.rows();
            let parent = null;
            for (let i = rows.indexOf(tr) - 1; i >= 0; i--) {
                if (this.depth(rows[i]) === d - 1) { parent = rows[i]; break; }
            }
            if (!parent) return false;
            const block = this.block(tr);
            const parentBlock = this.block(parent).filter((r) => !block.includes(r));
            const now = this.rows().filter((r) => !block.includes(r));
            const before = now[now.indexOf(parentBlock[parentBlock.length - 1]) + 1] || null;
            this.place(block, before, d - 1);
            this.say(`${this.name(tr)} moved out a level.`);
            return true;
        },
    }));
}
