// Venta Admin v3, shared Alpine registrations. One module, two hosts:
// the standalone v3 runtime (admin3.js, Settings and module spaces) and
// the classic admin bundle (admin/admin.js) whose shell now chromes the
// migrated work pages (owner ruling 2026-08-17: one admin, one chrome).
// Registering on whichever Alpine the page booted keeps v3Form working
// under either roof without ever starting a second Alpine.
export function registerV3(Alpine) {
    // ------------------------------------------------------------------
    // The save doctrine. One page is one save scope: a v3Form snapshots
    // its fields on load, flips dirty on any input/change (or an explicit
    // touch() from dynamic editors), shows the save bar, and guards
    // navigation while dirty. Discard reloads: the server's truth is the
    // only clean state worth restoring.
    // ------------------------------------------------------------------
    // formEl lives in a closure, not on the component: methods invoked
    // through a child scope chain (an inner editor calling touch()) run
    // with the CHILD's $el, and FormData(childDiv) throws.
    Alpine.data('v3Form', () => { let formEl = null; return {
        dirty: false,
        saving: false,
        baseline: '',

        init() {
            formEl = this.$el;
            this.$nextTick(() => { this.baseline = this.snapshot(); });

            formEl.addEventListener('input', () => this.check());
            formEl.addEventListener('change', () => this.check());
            // Async widgets dispatch this when hydration settles, so the
            // hydrated state becomes the clean state (see rebaseline()).
            formEl.addEventListener('v3-rebaseline', () => this.rebaseline());

            formEl.addEventListener('submit', () => {
                this.saving = true;
                this.dirty = false; // submitting IS the save; do not block the unload
            });

            this._unload = (e) => {
                if (!this.dirty) return;
                e.preventDefault();
                e.returnValue = '';
            };
            window.addEventListener('beforeunload', this._unload);
        },

        destroy() {
            window.removeEventListener('beforeunload', this._unload);
        },

        snapshot() {
            const data = new FormData(formEl);
            const pairs = [];
            for (const [k, v] of data.entries()) {
                if (typeof v === 'string') pairs.push(k + '=' + v);
                // An untouched file input contributes an empty File, so
                // name+size stays stable until the user actually picks one.
                else pairs.push(k + '=file:' + v.name + ':' + v.size);
            }
            return pairs.join('&');
        },

        check() {
            this.dirty = this.snapshot() !== this.baseline;
        },

        // Async widgets that hydrate field values AFTER load (a cascade
        // restoring saved dropdowns) call this when hydration completes,
        // so the restored state is the clean state, not a phantom edit.
        rebaseline() {
            this.baseline = this.snapshot();
            this.dirty = false;
        },

        // Dynamic editors (rows added or removed outside input events)
        // call this to mark work the snapshot diff will also confirm.
        touch() {
            this.$nextTick(() => this.check());
            this.dirty = true;
        },

        discard() {
            this.dirty = false;
            window.location.reload();
        },
    }; });

    // The masked-secret field. A closure holds the component root: the
    // buttons live inside x-if clones that die on mode switches, so any
    // deferred $root/$el reference would point at a detached node.
    Alpine.data('v3Secret', (cfg) => { let root = null; return {
        mode: cfg.display ? 'rest' : 'edit',
        shown: null,
        busy: false,

        init() { root = this.$el; },

        edit() {
            this.mode = 'edit';
            this.shown = null;
            this.$nextTick(() => root.querySelector('input')?.focus());
        },

        cancel() {
            this.mode = 'rest';
            this.$nextTick(() => root.closest('form')?.dispatchEvent(new Event('input')));
        },

        async reveal() {
            if (this.shown !== null) { this.shown = null; return; }
            this.busy = true;
            try {
                const res = await fetch(cfg.revealUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                });
                const body = await res.json();
                if (body.ok) { this.shown = body.value; }
                else { Alpine.store('toasts').push(body.message || 'Could not reveal the key.', 'bad'); }
            } catch (e) {
                Alpine.store('toasts').push('Could not reach the server.', 'bad');
            }
            this.busy = false;
        },
    }; });
}
