// The menu as a tree the merchant arranges. Order and nesting are the
// page's one save; everything about an item is its own form.
//
// Every move re-renders the list, and moving a row in the DOM drops the
// keyboard focus. After each move the focus goes back to the same control
// on the row that moved, or to its nearest usable neighbor when that
// control is now disabled, so arranging by keyboard never loses its place.

const ACTIONS = ['up', 'down', 'in', 'out'];

export function registerMenuTree(Alpine) {
    Alpine.data('vMenuTree', (seed) => {
        let root = null;

        return {
            items: Array.isArray(seed) ? seed : [],
            announcement: '',
            sayTimer: null,

            init() {
                root = this.$el;
            },

            /** The tree as rows with depth and parent label, for drawing. */
            flat() {
                const out = [];
                const walk = (list, depth, parent) => list.forEach((n) => {
                    out.push({ ...n, depth, parentName: parent ? this.name(parent) : '' });
                    walk(n.children, depth + 1, n);
                });
                walk(this.items, 0, null);
                return out;
            },

            name(node) {
                return node.label || node.kind;
            },

            /** Where a node sits: its siblings list, index and parent. */
            locate(id, list = this.items, parent = null) {
                for (let i = 0; i < list.length; i++) {
                    if (list[i].id === id) return { list, i, parent };
                    const found = this.locate(id, list[i].children, list[i]);
                    if (found) return found;
                }
                return null;
            },

            can(id, action) {
                const at = this.locate(id);
                if (!at) return false;
                if (action === 'up') return at.i > 0;
                if (action === 'down') return at.i < at.list.length - 1;
                // Under the item above it, one level at most: the header
                // menu is two levels deep.
                if (action === 'in') return at.i > 0 && at.parent === null;
                if (action === 'out') return at.parent !== null;
                return false;
            },

            act(id, action) {
                if (!this.can(id, action)) return;
                const at = this.locate(id);
                const [node] = at.list.splice(at.i, 1);
                let message = '';

                if (action === 'up' || action === 'down') {
                    at.list.splice(at.i + (action === 'up' ? -1 : 1), 0, node);
                    message = this.name(node) + ' moved ' + action;
                } else if (action === 'in') {
                    const host = at.list[at.i - 1];
                    // Its own sub-items come up a level with it, so the menu
                    // stays two levels deep.
                    host.children.push(node, ...node.children);
                    node.children = [];
                    message = this.name(node) + ' is now under ' + this.name(host);
                } else {
                    const up = this.locate(at.parent.id);
                    up.list.splice(up.i + 1, 0, node);
                    message = this.name(node) + ' moved up a level';
                }

                this.items = [...this.items];
                this.say(message + '. Save to keep it.');
                root.dispatchEvent(new Event('change', { bubbles: true }));
                this.$nextTick(() => this.refocus(id, action));
            },

            refocus(id, action) {
                const row = root.querySelector('[data-item="' + id + '"]');
                if (!row) return;
                const order = [action, ...ACTIONS.filter((a) => a !== action)];
                for (const a of order) {
                    const button = row.querySelector('[data-act="' + a + '"]');
                    if (button && !button.disabled) {
                        button.focus();
                        return;
                    }
                }
                row.querySelector('a, button:not(:disabled)')?.focus();
            },

            say(message) {
                this.announcement = message;
                clearTimeout(this.sayTimer);
                this.sayTimer = setTimeout(() => { this.announcement = ''; }, 5000);
            },

            payload() {
                const out = [];
                const walk = (list, parentId) => list.forEach((n) => {
                    out.push({ id: n.id, parent_id: parentId });
                    walk(n.children, n.id);
                });
                walk(this.items, null);
                return JSON.stringify({ items: out });
            },
        };
    });

    // The add form and the item editor. The chosen kind decides which fields
    // show. Choosing a category, page or store page fills an empty label with
    // its name, and keeps following the choice until the label is typed in.
    Alpine.data('vMenuItemFields', (kind) => ({
        kind,
        autoLabel: '',

        fillLabel(event) {
            const label = this.$refs.label;
            const option = event.target.selectedOptions[0];
            if (!label || !option) return;
            const name = option.value === '' ? '' : option.text.replace(/^(-\s)+/, '').trim();
            if (label.value === '' || label.value === this.autoLabel) {
                label.value = name;
                this.autoLabel = name;
                label.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    }));
}
