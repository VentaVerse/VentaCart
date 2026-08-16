// Venta Admin v3 runtime. Self-contained: boots its own Alpine so a v3
// page never depends on the v2 bundles while the areas migrate.
import Alpine from 'alpinejs';

// Shared with v2 pages that still need it during migration.
window.storageUrl = window.storageUrl || function (path) {
    if (!path) return '';
    return '/storage/' + path.split('/').map(encodeURIComponent).join('/');
};

// A cross-document view transition freezes input until it finishes,
// so it must only run where it means something: the work<->settings
// crossing (the rail morph). Same-side navigations skip it and stay
// instant; a routine click must never wait out an animation.
// The boundary is /<admin-prefix>/settings, matched as a path
// segment: substring checks would trip on routes that merely
// contain the word (e.g. a module's own /settings subpage).
const v3InSettings = (p) => {
    const root = window.__vSettingsRoot;
    if (root) return p === root || p.startsWith(root + '/');
    return /^\/[^/]+\/settings(\/|$)/.test(p);
};
addEventListener('pageswap', (e) => {
    if (!e.viewTransition) return;
    const here = v3InSettings(location.pathname);
    const to = e.activation?.entry?.url;
    const there = to ? v3InSettings(new URL(to, location.origin).pathname) : here;
    if (here === there) e.viewTransition.skipTransition();
});


document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
        items: [],
        push(message, tone = 'good') {
            const id = ++this._id;
            this.items.push({ id, message, tone });
            setTimeout(() => this.dismiss(id), 6000);
        },
        dismiss(id) {
            this.items = this.items.filter(t => t.id !== id);
        },
        _id: 0,
    });

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
});

// One copy mechanism. navigator.clipboard exists only in secure
// contexts and this admin is browsed over plain http on a LAN as often
// as https, so the fallback path is the normal path. Returns a boolean;
// callers flip their "Copied" label only on true.
window.v3Copy = async function (text) {
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
        window.Alpine?.store('toasts')?.push('Could not copy. Select the text and copy it manually.', 'bad');
        return false;
    }
};

// One mechanism for every in-page action button (test sends, generate
// buttons): POST as JSON, never navigate, toast the server's own verdict.
// Returns { ok, message } so a caller can react beyond the toast.
window.v3Post = async function (url, data = {}) {
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
    window.Alpine?.store('toasts')?.push(result.message, result.ok ? 'good' : 'bad');
    return result;
};

window.Alpine = window.Alpine || Alpine;
if (!window.__v3AlpineStarted) {
    window.__v3AlpineStarted = true;
    Alpine.start();
}
