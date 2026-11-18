// An admin image field. It shows what the field holds and opens the shared
// media library (window.VentaCart.media) to change it; browsing, uploading
// and managing files all happen in the library.
//
// Registered once on the admin's Alpine. A page with many fields, or a
// variant table that aims one field at many rows, opens one library dialog
// at a time.

/** Runs `use` with the library hook, waiting for its script if it has not loaded yet. */
function withLibrary(use) {
    const media = globalThis.window?.VentaCart?.media;
    if (media) {
        use(media);
        return;
    }
    document.addEventListener('ventacart:media-ready', (event) => use(event.detail || window.VentaCart.media), { once: true });
}

const nameOf = (path) => String(path || '').split('/').pop();

export function registerMediaPicker(Alpine) {
    Alpine.data('vMediaPicker', (cfg = {}) => createMediaPicker(cfg));
}

/** The field's state and behavior, apart from Alpine so it can be tested alone. */
export function createMediaPicker(cfg = {}) {
    const mode = cfg.mode === 'multi' ? 'multi' : 'single';

    return {
        folder: cfg.folder || 'media',
        kind: cfg.kind || 'default',
        mode,
        limit: cfg.limit ?? null,
        // Extensions the library lets this field choose; null accepts any.
        accept: cfg.accept ?? null,
        // Disk-relative library paths: the server accepts nothing else.
        value: cfg.value ?? (mode === 'multi' ? [] : ''),
        // Preview addresses by path, seeded by the server and filled in by
        // the library as images are chosen.
        urls: { ...(cfg.urls || {}) },
        opening: false,
        dialog: null,

        get chosen() { return this.mode === 'multi' ? this.value : (this.value ? [this.value] : []); },

        get payload() { return this.mode === 'multi' ? JSON.stringify(this.value) : this.value; },

        nameOf,

        /**
         * Opens the library. A single field opens beside the image it holds;
         * a multi field adds to what it holds, so those images show as
         * already added and cannot be picked twice.
         */
        show(opener) {
            const from = opener instanceof Element ? opener : (opener?.currentTarget || null);
            if (this.opening || this.dialog) return;
            this.opening = true;

            withLibrary((media) => {
                this.opening = false;
                const multi = this.mode === 'multi';
                const held = [...this.chosen];

                this.dialog = media.open({
                    mode: this.mode,
                    limit: multi ? (this.limit === null ? null : Math.max(this.limit - held.length, 0)) : 1,
                    value: multi ? [] : this.value,
                    inUse: multi ? held : [],
                    kind: this.kind,
                    ...(this.accept ? { accept: this.accept } : {}),
                    folder: this.folder,
                    opener: from,
                    onChoose: (files) => this.take(files),
                    onClose: () => { this.dialog = null; },
                });
            });
        },

        take(files) {
            const list = (files || []).filter((f) => f && typeof f.path === 'string' && f.path !== '');
            if (!list.length) return;

            list.forEach((f) => { this.urls[f.path] = f.thumb || f.url || ''; });

            if (this.mode === 'single') {
                this.value = list[0].path;
                this.emit([list[0].path], [list[0]]);
                return;
            }

            const added = list.filter((f) => !this.value.includes(f.path));
            this.value = [...this.value, ...added.map((f) => f.path)];
            this.emit(added.map((f) => f.path), added);
        },

        clear() {
            this.value = this.mode === 'multi' ? [] : '';
            this.emit([], []);
        },

        drop(path) {
            this.value = this.value.filter((p) => p !== path);
            this.emit(this.value, []);
        },

        /**
         * Tells the page what changed. A :value binding fires no input event,
         * so the form's unsaved-changes bar would never hear of a new image.
         */
        emit(paths, files) {
            this.$el.dispatchEvent(new CustomEvent('media:picked', { detail: { paths, files }, bubbles: true }));
            this.$el.closest('form')?.dispatchEvent(new Event('input', { bubbles: true }));
        },
    };
}
