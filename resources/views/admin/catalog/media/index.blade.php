<x-admin.shell title="Media Manager" active="catalog">
    <div x-data="mediaManager()">
        <div class="v-head">
            <div class="v-head__body">
                <h1 class="v-head__title">Media Manager</h1>
            </div>
        </div>

        <div class="v-settle">
            <div class="v-filterbar">
                <nav class="v-crumbrow" aria-label="Folder path">
                    <button type="button" @click="browseDir('')" class="v-link">Files</button>
                    <template x-for="(crumb, i) in breadcrumbs" :key="i">
                        <span class="v-crumbrow">
                            <span aria-hidden="true">/</span>
                            <button type="button" @click="browseDir(crumb.path)" class="v-link" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <span class="v-filterbar__grow"></span>
                <span class="v-cell-quiet v-mono" x-show="files.length > 0" x-text="filteredFiles.length + ' files'"></span>
                <input type="search" x-model="search" aria-label="Filter this folder" class="v-control v-narrowsearch" @keydown.enter.prevent>
                <button type="button" class="v-btn v-btn--ghost v-btn--sm" :aria-pressed="showUrlInput ? 'true' : 'false'"
                        aria-controls="media-url-bar" :aria-expanded="showUrlInput ? 'true' : 'false'"
                        @click="showUrlInput = !showUrlInput">URL</button>
                <label class="v-btn v-btn--primary v-btn--sm">
                    Upload here
                    <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="uploadFiles($event)" class="v-sr">
                </label>
            </div>

            <div id="media-url-bar" x-show="showUrlInput" x-cloak class="v-namerow v-gap-sm">
                <input type="url" x-model="urlInput" aria-label="Image URL" class="v-control" @keydown.enter.prevent="importFromUrl()">
                <button type="button" class="v-btn v-btn--secondary v-btn--sm" :disabled="importingUrl" @click="importFromUrl()">
                    <span x-text="importingUrl ? 'Importing…' : 'Import'"></span>
                </button>
            </div>

            <x-admin.panel>
                <div class="v-dropwrap"
                     @dragover.prevent="dragover = true"
                     @dragleave.prevent="dragover = false"
                     @drop.prevent="dragover = false; handleDrop($event)">

                    <div x-show="dragover" x-cloak class="v-dropzone">Drop files to upload</div>

                    <p x-show="uploading" x-cloak class="v-cell-quiet" role="status" aria-live="polite" x-text="uploadStatus"></p>

                    <div x-show="loading" class="v-empty"><p class="v-empty__title">Loading…</p></div>

                    <div x-show="!loading" class="v-mediagrid">
                        <template x-if="currentPath !== ''">
                            <button type="button" @click="browseDir(parentPath)" class="v-mediatile" aria-label="Up one folder">
                                <x-admin.icon name="folder" class="v-mediatile__folder" />
                                <span>..</span>
                            </button>
                        </template>

                        <template x-for="folder in filteredFolders" :key="folder.path">
                            <button type="button" @click="browseDir(folder.path)" class="v-mediatile">
                                <x-admin.icon name="folder" class="v-mediatile__folder" />
                                <span x-text="folder.name"></span>
                            </button>
                        </template>

                        <template x-for="file in filteredFiles" :key="file.path">
                            <div class="v-tilewrap">
                                <span class="v-mediatile">
                                    <span class="v-mediatile__frame"><img :src="file.url" :alt="file.name" loading="lazy"></span>
                                    <span x-text="file.name"></span>
                                </span>
                                <span class="v-tileacts">
                                    <button type="button" class="v-row__x" @click="copyPath(file.path)" :aria-label="'Copy path to ' + file.name">
                                        <x-admin.icon name="link" />
                                    </button>
                                    <button type="button" class="v-row__x" @click="askDelete(file)" :aria-label="'Delete ' + file.name">
                                        <x-admin.icon name="trash" />
                                    </button>
                                </span>
                            </div>
                        </template>
                    </div>

                    <div x-show="!loading && folders.length === 0 && files.length === 0" class="v-empty">
                        <p class="v-empty__title">Empty folder</p>
                        <p class="v-empty__line">Drop files here or use Upload above.</p>
                    </div>
                </div>
            </x-admin.panel>
        </div>

        {{-- JS-callback confirm (automations precedent: the shared confirm
             component only wraps form actions). --}}
        <template x-if="pendingDelete">
            <div class="v-overlay" @click.self="pendingDelete = null" @keydown.escape.window="pendingDelete = null">
                <div class="v-modal" role="dialog" aria-modal="true" aria-label="Delete this file?">
                    <h2 class="v-modal__title">Delete this file?</h2>
                    <p class="v-modal__body" x-text="pendingDelete.name + ' is deleted from the server. Any product or page still pointing at it loses its image.'"></p>
                    <div class="v-modal__acts">
                        <button type="button" class="v-btn v-btn--ghost" @click="pendingDelete = null">Cancel</button>
                        <button type="button" class="v-btn v-btn--danger-fill" @click="deleteFile(pendingDelete); pendingDelete = null">Delete</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @push('scripts')
    <script>
    function mediaManager() {
        return {
            loading: false,
            currentPath: '',
            parentPath: '',
            folders: [],
            files: [],
            search: '',
            showUrlInput: false,
            urlInput: '',
            importingUrl: false,
            dragover: false,
            uploading: false,
            uploadStatus: '',
            pendingDelete: null,

            init() {
                this.browseDir('');
            },

            get breadcrumbs() {
                if (!this.currentPath) return [];
                const parts = this.currentPath.split('/');
                return parts.map(function(part, i) {
                    return { name: part, path: parts.slice(0, i + 1).join('/') };
                });
            },

            get filteredFolders() {
                if (!this.search) return this.folders;
                const q = this.search.toLowerCase();
                return this.folders.filter(function(f) { return f.name.toLowerCase().indexOf(q) !== -1; });
            },

            get filteredFiles() {
                if (!this.search) return this.files;
                const q = this.search.toLowerCase();
                return this.files.filter(function(f) { return f.name.toLowerCase().indexOf(q) !== -1; });
            },

            showToast(msg, type) {
                Alpine.store('toasts').push(msg, type === 'error' ? 'danger' : 'success');
            },

            async browseDir(path) {
                this.loading = true;
                this.search = '';
                const params = new URLSearchParams();
                if (path) params.set('path', path);

                const res = await fetch('{{ route("admin.products.images.browse") }}?' + params);
                const data = await res.json();

                if (data.ok) {
                    this.currentPath = data.path;
                    this.parentPath = data.parent || '';
                    this.folders = data.folders;
                    this.files = data.files;
                }
                this.loading = false;
            },

            async uploadFiles(event) {
                const files = event.target.files;
                if (!files.length) return;
                for (const file of files) {
                    await this._upload(file);
                }
                event.target.value = '';
            },

            async handleDrop(event) {
                const files = event.dataTransfer?.files;
                if (!files || files.length === 0) return;
                for (const file of files) {
                    if (file.type.startsWith('image/')) {
                        await this._upload(file);
                    }
                }
            },

            async _upload(file) {
                this.uploading = true;
                this.uploadStatus = 'Uploading ' + file.name + '…';

                const formData = new FormData();
                formData.append('file', file);
                formData.append('catalog_path', this.currentPath);

                try {
                    const res = await fetch('{{ route("admin.products.images.upload-to-catalog") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: formData,
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.files.push({ name: data.name, path: data.path, url: data.url, size: data.size, modified: Date.now() / 1000 });
                        this.showToast('Uploaded ' + data.name);
                    } else {
                        this.showToast(data.message || 'Upload failed.', 'error');
                    }
                } catch (e) {
                    this.showToast('Upload failed.', 'error');
                }
                this.uploading = false;
                this.uploadStatus = '';
            },

            async importFromUrl() {
                if (!this.urlInput.trim()) return;
                this.importingUrl = true;

                try {
                    const res = await fetch('{{ route("admin.products.images.import-url") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ url: this.urlInput.trim(), store_id: 1, catalog_path: this.currentPath }),
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.files.push({ name: data.path.split('/').pop(), path: data.path, url: storageUrl(data.path), size: 0, modified: Date.now() / 1000 });
                        this.urlInput = '';
                        this.showUrlInput = false;
                        this.showToast('Image imported.');
                    } else {
                        this.showToast(data.message || 'Import failed.', 'error');
                    }
                } catch (e) {
                    this.showToast('Import failed.', 'error');
                }
                this.importingUrl = false;
            },

            async copyPath(path) {
                if (await window.vCopy(path)) this.showToast('Path copied');
            },

            askDelete(file) {
                this.pendingDelete = file;
            },

            async deleteFile(file) {
                if (!file) return;

                const res = await fetch('{{ route("admin.products.images.delete") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ path: file.path }),
                });
                const data = await res.json();
                if (data.ok) {
                    this.files = this.files.filter(function(f) { return f.path !== file.path; });
                    this.showToast('Deleted.');
                } else {
                    this.showToast('Delete failed.', 'error');
                }
            },
        };
    }
    </script>
    @endpush
</x-admin.shell>
