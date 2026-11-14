// One value picked from a long list by typing: a search box that narrows
// the list as you type, picks with a click or Enter, and posts the picked
// value through a hidden input. Arrow keys move through the matches;
// Escape and Tab close the list without changing the pick.

export function registerCombobox(Alpine) {
    Alpine.data('vCombobox', (cfg) => ({
        options: Array.isArray(cfg.options) ? cfg.options : [],
        value: cfg.value === null || cfg.value === undefined ? '' : String(cfg.value),
        emptyLabel: cfg.emptyLabel || '',
        q: '',
        open: false,
        active: 0,

        get picked() {
            return this.options.find((o) => String(o.id) === this.value) || null;
        },

        get label() {
            return this.picked ? this.picked.name : this.emptyLabel;
        },

        /** The empty choice first (when there is one), then the matches. */
        get matches() {
            const q = this.q.trim().toLowerCase();
            const list = q ? this.options.filter((o) => o.name.toLowerCase().includes(q)) : this.options;

            return this.emptyLabel && !q ? [{ id: '', name: this.emptyLabel }, ...list] : list;
        },

        show() {
            this.open = true;
            this.q = '';
            const at = this.matches.findIndex((o) => String(o.id) === this.value);
            this.active = at < 0 ? 0 : at;
            this.$nextTick(() => this.reveal());
        },

        close() {
            this.open = false;
            this.q = '';
        },

        pick(option) {
            if (String(option.id) !== this.value) {
                this.value = String(option.id);
                // The surrounding form counts this as an edit.
                this.$nextTick(() => this.$refs.value.dispatchEvent(new Event('change', { bubbles: true })));
            }
            this.close();
        },

        move(step) {
            if (!this.open) {
                this.show();
                return;
            }
            const n = this.matches.length;
            if (!n) return;
            this.active = (this.active + step + n) % n;
            this.$nextTick(() => this.reveal());
        },

        enter() {
            if (this.open && this.matches[this.active]) this.pick(this.matches[this.active]);
        },

        reveal() {
            this.$refs.list?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
        },
    }));
}
