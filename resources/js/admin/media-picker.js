// The one image browser.
//
// There were five of these, hand-rolled, all calling the product image
// endpoints and all drifting: delete existed in two, sort in one,
// multi-select in one, and the root crumb had three different labels. Four of
// the five swallowed browse errors entirely, so a session expiry showed
// "Loading..." forever. Every fix had to be made five times and was usually
// made once.
//
// Registered once, on the one Alpine the admin boots. It is deliberately NOT
// a factory-per-field like the partial it replaces: a product with two variant
// axes would have minted thirty-six of those, each with its own modal.
export function registerMediaPicker(Alpine) {
    Alpine.data('vMediaPicker', (cfg) => ({
        // --- what the caller fixed
        root: cfg.root || 'products',
        mode: cfg.mode || 'single',
        can: (cfg.can || 'upload').split(',').map((c) => c.trim()),
        // 'add' hands the selection to the caller; 'manage' deletes it.
        commitAction: cfg.commit === 'manage' ? 'manage' : 'add',
        endpoints: cfg.endpoints,

        // --- what the merchant chose
        value: cfg.value || (cfg.mode === 'multi' ? [] : ''),

        // --- the browser
        open: false,
        path: '',
        crumbs: [],
        parent: null,
        folders: [],
        files: [],
        page: 1,
        lastPage: 1,
        total: 0,
        searching: false,
        q: '',
        sort: 'modified',
        loading: false,
        busy: '',
        picked: [],
        lastPath: null,
        opener: null,
        newFolder: '',
        confirming: null,
        _searchTimer: null,

        init() {
            this.$watch('q', () => {
                clearTimeout(this._searchTimer);
                this._searchTimer = setTimeout(() => this.browse(this.path, 1), 250);
            });
        },

        able(what) { return this.can.includes(what); },

        get chosen() { return this.mode === 'multi' ? this.value : (this.value ? [this.value] : []); },

        get payload() { return this.mode === 'multi' ? JSON.stringify(this.value) : this.value; },

        /**
         * Open where the answer probably is: the folder the current image
         * already lives in, else where the merchant was last, else the root
         * this caller declared. Opening at the bare disk root is how the old
         * copies put the store's payment QR code in front of a merchant
         * looking for a product photo.
         */
        async show(opener) {
            this.opener = opener instanceof Element ? opener : (opener?.currentTarget || null);
            this.open = true;
            this.picked = [];
            this.q = '';

            const current = this.chosen[0];
            const at = current && current.includes('/')
                ? current.slice(0, current.lastIndexOf('/'))
                : (this.lastPath || this.root);

            await this.browse(at, 1);
        },

        hide() {
            this.open = false;
            this.confirming = null;
            // x-dialog restores focus to whatever opened it, so this only
            // covers the case where the trigger was replaced while open.
            if (this.opener && !document.contains(document.activeElement)) this.opener.focus();
        },

        /**
         * Every call through one place, so a failure is never silent.
         *
         * A session expiry returns the login page as HTML, res.json() throws,
         * and the four copies with no catch left the spinner up forever.
         */
        async ask(url, options = {}) {
            try {
                const res = await fetch(url, options);

                if (res.status === 401 || res.status === 419) {
                    this.toast('Your session expired. Sign in again in another tab, then retry.', 'danger');
                    return null;
                }

                const body = await res.json().catch(() => null);

                if (!res.ok || !body || body.ok === false) {
                    this.toast(body?.message || 'That did not work. Nothing was changed.', 'danger');
                    return null;
                }

                return body;
            } catch (e) {
                this.toast('Could not reach the server. Check the connection and retry.', 'danger');
                return null;
            }
        },

        toast(message, tone = 'success') {
            window.Alpine?.store('toasts')?.push(message, tone);
        },

        async browse(path, page = 1) {
            this.loading = true;

            const params = new URLSearchParams({ root: this.root, sort: this.sort, page });
            if (path) params.set('path', path);
            if (this.q.trim()) params.set('q', this.q.trim());

            const data = await this.ask(`${this.endpoints.browse}?${params}`);

            if (data) {
                this.path = data.path;
                this.crumbs = data.crumbs;
                this.parent = data.parent;
                this.folders = data.folders;
                this.files = data.files;
                this.page = data.page;
                this.lastPage = data.lastPage;
                this.total = data.total;
                this.searching = data.searching;
                this.lastPath = data.path;
            }

            this.loading = false;
        },

        resort(by) { this.sort = by; this.browse(this.path, 1); },

        isChosen(file) { return this.chosen.includes(file.path); },
        isPicked(file) { return this.picked.includes(file.path); },

        /**
         * Single commits and closes. Multi stages, because the Images tab has
         * always staged and its Save is the thing that writes.
         */
        choose(file) {
            if (this.mode === 'single') {
                this.value = file.path;
                this.emit('picked', { paths: [file.path] });
                this.hide();
                return;
            }

            const at = this.picked.indexOf(file.path);
            at === -1 ? this.picked.push(file.path) : this.picked.splice(at, 1);
        },

        take() {
            const added = this.picked.filter((p) => !this.value.includes(p));
            this.value = [...this.value, ...added];
            this.emit('picked', { paths: added });
            this.hide();
        },

        clear() {
            this.value = this.mode === 'multi' ? [] : '';
            this.emit('picked', { paths: [] });
        },

        drop(path) {
            this.value = this.value.filter((p) => p !== path);
            this.emit('picked', { paths: this.value });
        },

        /**
         * The doctrine signal, dispatched once from here.
         *
         * A :value binding fires no input event, so every copy had to say so
         * by hand and three of them forgot. The component cannot forget.
         */
        emit(name, detail) {
            this.$el.dispatchEvent(new CustomEvent('media:' + name, { detail, bubbles: true }));
            this.$el.closest('form')?.dispatchEvent(new Event('input', { bubbles: true }));
        },

        async upload(event) {
            const files = [...(event.target.files || event.dataTransfer?.files || [])].filter((f) => f.type.startsWith('image/'));
            if (event.target) event.target.value = '';
            if (!files.length) return;

            this.busy = files.length === 1 ? 'Uploading ' + files[0].name : 'Uploading ' + files.length + ' files';

            const body = new FormData();
            files.forEach((f) => body.append('files[]', f));
            body.append('path', this.path);
            body.append('root', this.root);

            const data = await this.ask(this.endpoints.upload, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.endpoints.token, Accept: 'application/json' },
                body,
            });

            this.busy = '';

            if (data) {
                this.toast(data.files.length === 1 ? 'Uploaded ' + data.files[0].name : 'Uploaded ' + data.files.length + ' files');
                data.files.forEach((f) => this.emit('uploaded', { path: f.path, url: f.url }));
                await this.browse(this.path, 1);
            }
        },

        async importUrl(url) {
            if (!url.trim()) return;
            this.busy = 'Fetching';

            const data = await this.ask(this.endpoints.importUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.endpoints.token, Accept: 'application/json' },
                body: JSON.stringify({ url, path: this.path, root: this.root }),
            });

            this.busy = '';

            if (data) {
                this.toast('Fetched ' + data.files[0].name);
                this.emit('uploaded', { path: data.files[0].path, url: data.files[0].url });
                await this.browse(this.path, 1);
            }
        },

        /**
         * The merchant is told the cost before they decide.
         *
         * browse returns usedBy on every file, so the confirm can name the
         * number without another round trip. The old endpoint asked nothing
         * and said nothing, and one image in this catalogue is on fourteen
         * products.
         */
        askDelete(file) { this.confirming = [file]; },

        /** The whole selection, so the cost is stated once for all of them. */
        askDeleteMany() {
            this.confirming = this.files.filter((f) => this.picked.includes(f.path));
        },

        get confirmTitle() {
            const n = this.confirming?.length || 0;

            return n > 1 ? 'Delete ' + n + ' files?' : 'Delete this file?';
        },

        get confirmNames() {
            const names = (this.confirming || []).map((f) => f.name);
            if (names.length <= 3) return names.join(', ');

            return names.slice(0, 3).join(', ') + ' and ' + (names.length - 3) + ' more';
        },

        get deleteWarning() {
            const files = this.confirming || [];
            const using = files.filter((f) => f.usedBy > 0);
            const products = files.reduce((n, f) => n + (f.usedBy || 0), 0);

            if (!products) return 'Nothing is using them.';

            const where = using.length === files.length || files.length === 1
                ? ''
                : ' across ' + using.length + ' of them';

            return products === 1
                ? '1 product uses ' + (files.length > 1 ? 'one of these' : 'it') + ' and will lose its image.'
                : products + ' products use ' + (files.length > 1 ? 'these' : 'it') + where + ' and will lose their image.';
        },

        async confirmDelete() {
            const files = this.confirming || [];
            this.confirming = null;
            if (!files.length) return;

            const paths = files.map((f) => f.path);

            const data = await this.ask(this.endpoints.delete, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.endpoints.token, Accept: 'application/json' },
                body: JSON.stringify({ paths }),
            });

            if (data) {
                const what = files.length === 1 ? files[0].name : files.length + ' files';
                this.toast('Deleted ' + what + (data.detached ? ', removed from ' + data.detached + ' products' : ''));

                paths.forEach((path) => {
                    if (this.mode === 'multi') this.drop(path);
                    else if (this.value === path) this.clear();
                });

                this.picked = this.picked.filter((p) => !paths.includes(p));
                await this.browse(this.path, this.page);
            }
        },

        async makeFolder() {
            const name = this.newFolder.trim();
            if (!name) return;

            const data = await this.ask(this.endpoints.folder, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.endpoints.token, Accept: 'application/json' },
                body: JSON.stringify({ path: this.path, name }),
            });

            if (data) {
                this.newFolder = '';
                this.toast('Created ' + name);
                await this.browse(data.path, 1);
            }
        },

        /** Innermost first: the confirm, then the panel. */
        escape() {
            if (this.confirming) { this.confirming = null; return; }
            if (this.open) this.hide();
        },
    }));
}
