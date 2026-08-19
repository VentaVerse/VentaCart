// Venta Admin v, shared Alpine registrations and window helpers.
// One chrome for the whole admin (owner ruling 2026-08-17): the classic
// bundle (admin/admin.js) is the one host, registering these on the one
// Alpine that app.js boots. No page loads a second Alpine.
export function registerV(Alpine) {
    // ------------------------------------------------------------------
    // The save doctrine. One page is one save scope: a vForm snapshots
    // its fields on load, flips dirty on any input/change (or an explicit
    // touch() from dynamic editors), shows the save bar, and guards
    // navigation while dirty. Discard reloads: the server's truth is the
    // only clean state worth restoring.
    // ------------------------------------------------------------------
    // formEl lives in a closure, not on the component: methods invoked
    // through a child scope chain (an inner editor calling touch()) run
    // with the CHILD's $el, and FormData(childDiv) throws.
    Alpine.data('vForm', () => { let formEl = null; return {
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
            formEl.addEventListener('v-rebaseline', () => this.rebaseline());

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

            // Pickers write hidden inputs through :value bindings, which
            // fire no input event; a picker whose author forgot touch()
            // shipped a silent save bar more than once (the product
            // form's brand search was the latest). The observer makes
            // the doctrine notice every serialized mutation itself:
            // attribute writes on the fields and rows added or removed
            // by x-for. Coalesced to one check per frame.
            this._mo = new MutationObserver(() => {
                if (this._moQueued) return;
                this._moQueued = true;
                requestAnimationFrame(() => {
                    this._moQueued = false;
                    this.check();
                });
            });
            this._mo.observe(formEl, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['value'],
            });
        },

        destroy() {
            window.removeEventListener('beforeunload', this._unload);
            this._mo?.disconnect();
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
            // Once the form is committing, nothing may re-arm the guard.
            // The save bar lives INSIDE the form, so flipping `saving`
            // swaps its button text ("Save" -> "Saving…") — a childList
            // mutation the observer below dutifully reports, one frame
            // after submit() cleared `dirty`. Re-arming there put the
            // browser's "Leave Site?" dialog in front of every successful
            // save, which is precisely the moment the guard exists to
            // stay out of.
            if (this.saving) return;
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
    Alpine.data('vSecret', (cfg) => { let root = null; return {
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
                else { Alpine.store('toasts').push(body.message || 'Could not reveal the key.', 'danger'); }
            } catch (e) {
                Alpine.store('toasts').push('Could not reach the server.', 'danger');
            }
            this.busy = false;
        },
    }; });
}

// The v window helpers ride along with the registrations: their last
// standalone host (admin.js) is gone, so the classic bundle is where
// every page gets them. Tones use the classic toast vocabulary.
window.storageUrl = window.storageUrl || function (path) {
    if (!path) return '';
    return '/storage/' + path.split('/').map(encodeURIComponent).join('/');
};

// One copy mechanism. navigator.clipboard exists only in secure
// contexts and this admin is browsed over plain http on a LAN as often
// as https, so the fallback path is the normal path. Returns a boolean;
// callers flip their "Copied" label only on true.
window.vCopy = async function (text) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        ta.remove();
        if (!ok) throw new Error('execCommand failed');
        return true;
    } catch (e) {
        window.Alpine?.store('toasts')?.push('Could not copy. Select the text and copy it manually.', 'danger');
        return false;
    }
};

// One mechanism for every in-page action button (test sends, generate
// buttons): POST as JSON, never navigate, toast the server's own verdict.
// Returns { ok, message } so a caller can react beyond the toast.
window.vPost = async function (url, data = {}) {
    let result;
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            },
            body: JSON.stringify(data),
        });
        const body = await res.json().catch(() => ({}));
        if (res.status === 422 && body.errors) {
            result = { ok: false, message: Object.values(body.errors).flat()[0] || 'Validation failed.' };
        } else {
            result = { ok: body.ok ?? res.ok, message: body.message || (res.ok ? 'Done.' : 'The request failed.') };
        }
    } catch (e) {
        result = { ok: false, message: 'Could not reach the server. Check the connection and retry.' };
    }
    window.Alpine?.store('toasts')?.push(result.message, result.ok ? 'success' : 'danger');
    return result;
};
