// Admin v2 behavior. Registered on alpine:init so this file can load
// before app.js (which calls Alpine.start()).

// Shared with the storefront helper of the same name (resources/themes/*/layouts/store.blade.php)
// and the legacy layouts/admin.blade.php: turns a stored relative path into a
// public /storage/... URL. Needed by any v-admin page that embeds the image
// picker/manager partials (single_image_picker.blade.php, media/index.blade.php),
// which call storageUrl() directly — those partials assumed the old admin
// layout's inline <script> defined it, which the v-admin shell never did.
window.storageUrl = function (path) {
    if (!path) return '';
    return '/storage/' + path.split('/').map(encodeURIComponent).join('/');
};

document.addEventListener('alpine:init', () => {
    window.Alpine.store('toasts', {
        items: [],
        counter: 0,
        push(message, tone = 'success') {
            const id = ++this.counter;
            this.items.push({ id, message, tone });
            setTimeout(() => this.dismiss(id), 4500);
        },
        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    window.Alpine.data('vPalette', (destinations, searchUrl) => ({
        open: false,
        q: '',
        results: [],
        selected: 0,
        searchTimer: null,
        controller: null,
        init() {
            window.addEventListener('open-palette', () => this.show());
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.show();
                }
            });
        },
        show() {
            this.open = true;
            this.q = '';
            this.results = this.pageMatches('');
            this.selected = 0;
            this.$nextTick(() => this.$refs.input.focus());
        },
        pageMatches(q) {
            const needle = q.toLowerCase();
            return destinations
                .filter((d) => !needle
                    || d.label.toLowerCase().includes(needle)
                    || d.keywords.some((k) => k.toLowerCase().includes(needle)))
                .slice(0, 8)
                .map((d) => ({ label: d.label, sub: d.group, url: d.url, group: 'Pages' }));
        },
        search() {
            this.results = this.pageMatches(this.q);
            this.selected = 0;
            clearTimeout(this.searchTimer);
            if (this.q.length < 2) return;
            this.searchTimer = setTimeout(async () => {
                this.controller?.abort();
                this.controller = new AbortController();
                try {
                    const res = await fetch(`${searchUrl}?q=${encodeURIComponent(this.q)}`, {
                        headers: { Accept: 'application/json' },
                        signal: this.controller.signal,
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.results = [...this.pageMatches(this.q), ...data.results];
                } catch (e) {
                    // Aborted (superseded by a newer request) or network error:
                    // keep the current results (page matches) silently.
                    return;
                }
            }, 200);
        },
        go() {
            const hit = this.results[this.selected];
            if (hit) window.location.assign(hit.url);
        },
        move(delta) {
            const max = this.results.length - 1;
            this.selected = Math.min(Math.max(this.selected + delta, 0), Math.max(max, 0));
        },
    }));
});

// Any form marked data-v-form: on submit, flip its submit buttons into the
// loading state (spinner + no double-submit). Guard against re-entry.
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-v-form]');
    if (!form) return;
    if (form.dataset.vSubmitting === 'true') { e.preventDefault(); return; }
    form.dataset.vSubmitting = 'true';
    form.querySelectorAll('button[type="submit"]').forEach((b) => b.setAttribute('data-loading', 'true'));
});

// bfcache restore (browser Back after a submit) leaves the frozen DOM with
// the submit-guard still latched: buttons stuck spinning and the form
// inert. pageshow fires on bfcache restore (and on a normal load), so use
// it to clear the guard for every data-v-form on the page. Mirrors the
// storefront fix in commit 5e0fa9b.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-v-form]').forEach((form) => {
        delete form.dataset.vSubmitting;
        form.querySelectorAll('button[type="submit"]').forEach((b) => b.removeAttribute('data-loading'));
    });
});

// Rows with data-href navigate on click, unless the click hit an
// interactive element inside the row (link, button, input).
// Supports modifier clicks (ctrl/cmd) to open in new tab.
document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, textarea, label')) return;
    if (e.ctrlKey || e.metaKey) {
        window.open(row.dataset.href, '_blank');
    } else {
        window.location.assign(row.dataset.href);
    }
});

// Keyboard navigation: Enter on focused row with data-href navigates it.
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, textarea, label')) return;
    if (e.ctrlKey || e.metaKey) {
        window.open(row.dataset.href, '_blank');
    } else {
        window.location.assign(row.dataset.href);
    }
});
