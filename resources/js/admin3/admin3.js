// Venta Admin v3 runtime. Self-contained: boots its own Alpine so a v3
// page never depends on the v2 bundles while the areas migrate.
import Alpine from 'alpinejs';
import { registerV3 } from './register';

// Shared with v2 pages that still need it during migration.
window.storageUrl = window.storageUrl || function (path) {
    if (!path) return '';
    return '/storage/' + path.split('/').map(encodeURIComponent).join('/');
};

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

    registerV3(Alpine);
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
    // Paint first, hydrate after (see resources/js/app.js).
    requestAnimationFrame(() => requestAnimationFrame(() => Alpine.start()));
}
