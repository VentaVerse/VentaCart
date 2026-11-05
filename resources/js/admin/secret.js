// The stored-secret control (x-admin.secret). States: 'rest' shows the
// mask, 'edit' swaps in an input, 'removed' holds a pending removal until
// the page is saved. The plaintext only ever arrives from the reveal
// endpoint, on Show or Copy, and is dropped again on Hide, Change or Remove.
//
// The component root is held in a closure: x-if clones die on every state
// switch, and a method called from inside one runs with the clone's $el.
export function registerSecret(Alpine) {
    Alpine.data('vSecret', (cfg) => { let root = null; let copiedTimer = null; return {
        // A save that failed validation reopens the field it rejected.
        mode: (cfg.editing || !cfg.stored) ? 'edit' : 'rest',
        shown: null,
        busy: null,          // 'show' | 'copy' while a fetch is out
        copied: false,
        menu: false,
        unreadable: !!cfg.unreadable,
        confirming: false,
        announce: '',

        init() { root = this.$el; },

        focus(selector) {
            this.$nextTick(() => root.querySelector(selector)?.focus());
        },

        say(message) {
            this.announce = '';
            this.$nextTick(() => { this.announce = message; });
        },

        // The form snapshots its fields on input; a state switch
        // adds or removes fields without typing, so it says so itself.
        changed() {
            this.$nextTick(() => root.closest('form')?.dispatchEvent(new Event('input')));
        },

        edit() {
            this.menu = false;
            this.mode = 'edit';
            this.shown = null;
            this.focus('input:not([type=hidden])');
        },

        cancel() {
            this.mode = 'rest';
            this.changed();
            this.focus('[data-secret-change]');
        },

        closeMenu() {
            if (!this.menu) return;
            this.menu = false;
            this.focus('[data-secret-more]');
        },

        askRemove() {
            this.menu = false;
            this.confirming = true;
            this.focus('[data-secret-keep]');
        },

        keep() {
            if (!this.confirming) return;
            this.confirming = false;
            this.focus('[data-secret-more]');
        },

        remove() {
            this.confirming = false;
            this.shown = null;
            this.mode = 'removed';
            this.changed();
            this.focus('[data-secret-undo]');
        },

        undo() {
            this.mode = 'rest';
            this.changed();
            this.focus('[data-secret-change]');
        },

        async toggle() {
            if (this.shown !== null) {
                this.shown = null;
                this.say(cfg.label + ' hidden');
                return;
            }
            const value = await this.fetchValue('show');
            if (value !== null) {
                this.shown = value;
                this.say(cfg.label + ' shown');
            }
        },

        async copy() {
            const value = this.shown ?? await this.fetchValue('copy');
            if (value === null) return;
            if (!await window.vCopy(value)) return;
            this.copied = true;
            this.say(cfg.label + ' copied');
            clearTimeout(copiedTimer);
            copiedTimer = setTimeout(() => { this.copied = false; }, 1600);
        },

        // One saved value from the reveal endpoint, or null after saying
        // why not. Two response shapes are read: { success, data: { value } }
        // and the older { ok, value } of core's settings screens.
        async fetchValue(kind) {
            if (this.busy !== null) return null;
            this.busy = kind;
            const toast = (message) => Alpine.store('toasts').push(message, 'danger');
            try {
                const res = await fetch(cfg.revealUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                });
                if (res.status === 429) {
                    toast('Too many views in a short time. Try again in a minute.');
                    return null;
                }
                if (res.status === 419) {
                    toast('This page has expired. Reload it and try again.');
                    return null;
                }
                const body = await res.json().catch(() => ({}));
                const ok = body.success ?? body.ok;
                const value = body.data?.value ?? body.value;
                if (ok && typeof value === 'string') return value;
                if (body.data?.reason === 'unreadable') {
                    this.unreadable = true;
                    this.say('The saved ' + cfg.label + ' can’t be read');
                    this.focus('[data-secret-change]');
                } else {
                    toast(body.message || 'Could not show the ' + cfg.label + '.');
                }
                return null;
            } catch (e) {
                toast('Could not reach the server.');
                return null;
            } finally {
                this.busy = null;
            }
        },
    }; });
}
