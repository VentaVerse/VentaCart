<x-admin.shell title="Media Manager">
    <x-admin.page title="Media Manager">
        <div x-data="mediaManager()">

            {{-- The toolbar is the row above the grid, not a band inside a
                 sheet: the same anatomy every index in the admin uses. --}}
            <x-admin.toolbar>
                {{-- Breadcrumbs --}}
                <nav aria-label="Folder path" class="v-scroll flex flex-1 min-w-0 items-center gap-0.5 self-center overflow-x-auto text-[13px]">
                    <button type="button" @click="browseDir('')" class="v-quietlink shrink-0 flex items-center gap-1 font-semibold">
                        <x-admin.icon name="home" class="w-4 h-4" />
                        Files
                    </button>
                    <template x-for="(crumb, i) in breadcrumbs" :key="i">
                        <span class="flex items-center gap-0.5 shrink-0">
                            <span class="text-v-muted" aria-hidden="true">/</span>
                            <button type="button" @click="browseDir(crumb.path)" class="v-quietlink font-semibold" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>

                <div class="v-toolbar__actions">
                    {{-- File count --}}
                    <span class="self-center shrink-0 text-xs text-v-muted" x-show="files.length > 0" x-text="filteredFiles.length + ' files'"></span>

                    {{-- Search --}}
                    <div class="relative">
                        <x-admin.icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-v-muted" />
                        <input type="text" x-model="search" placeholder="Filter&hellip;" aria-label="Filter this folder"
                            @keydown.enter.prevent
                            class="v-control h-[var(--control-h)] w-40 rounded-v-md border bg-v-surface pl-8 pr-3 text-[13px] text-v-text placeholder:text-v-muted">
                    </div>

                    {{-- URL Import --}}
                    <x-admin.button type="button" variant="secondary" aria-controls="media-url-bar"
                        x-bind:aria-expanded="showUrlInput ? 'true' : 'false'"
                        x-bind:aria-pressed="showUrlInput ? 'true' : 'false'"
                        x-bind:class="showUrlInput && '!bg-v-accent-weak !border-v-accent !text-v-accent'"
                        @click="showUrlInput = !showUrlInput">
                        <x-admin.icon name="link" class="w-3.5 h-3.5" />
                        URL
                    </x-admin.button>

                    {{-- Upload --}}
                    <label class="v-btn v-btn--primary inline-flex h-[var(--control-h)] cursor-pointer items-center justify-center gap-1.5 rounded-v-md border border-v-accent bg-v-accent px-3.5 text-[13px] text-v-accent-text shadow-v-lift transition-colors hover:bg-v-accent-hover">
                        <x-admin.icon name="upload" class="w-3.5 h-3.5" />
                        Upload Here
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="uploadFiles($event)" class="sr-only">
                    </label>
                </div>
            </x-admin.toolbar>

            {{-- URL Import Bar --}}
            <div id="media-url-bar" x-show="showUrlInput" x-cloak x-transition class="mb-3 flex gap-2">
                <input type="text" x-model="urlInput" placeholder="https://example.com/image.jpg" aria-label="Image URL"
                    class="v-control h-[var(--control-h)] flex-1 rounded-v-md border bg-v-surface px-3 text-[13px] text-v-text placeholder:text-v-muted"
                    @keydown.enter.prevent="importFromUrl()">
                <x-admin.button type="button" variant="primary" @click="importFromUrl()" x-bind:disabled="importingUrl">
                    <span x-show="!importingUrl">Import</span>
                    <span x-show="importingUrl" x-cloak>Importing&hellip;</span>
                </x-admin.button>
            </div>

            {{-- Content --}}
            <x-admin.panel flush class="relative min-h-[70vh]"
                 @dragover.prevent="dragover = true"
                 @dragleave.prevent="dragover = false"
                 @drop.prevent="dragover = false; handleDrop($event)">

                {{-- Drag overlay --}}
                <div x-show="dragover" x-cloak class="v-drop">
                    <x-admin.icon name="upload" class="w-8 h-8 text-v-accent" />
                    <p class="v-drop__text">Drop files to upload</p>
                </div>

                <div class="v-panel__body">
                    {{-- Upload progress --}}
                    <div x-show="uploading" x-cloak class="mb-3 flex items-center gap-2" role="status" aria-live="polite">
                        <x-admin.icon name="spinner" class="w-4 h-4 shrink-0 animate-spin text-v-accent" />
                        <span class="text-xs text-v-muted" x-text="uploadStatus"></span>
                    </div>

                    {{-- Loading --}}
                    <div x-show="loading" class="flex items-center justify-center py-20">
                        <x-admin.icon name="spinner" class="w-6 h-6 animate-spin text-v-accent" />
                    </div>

                    {{-- File grid --}}
                    <div x-show="!loading" class="v-tiles">
                        {{-- Back folder --}}
                        <template x-if="currentPath !== ''">
                            <button type="button" @click="browseDir(parentPath)" class="v-tile v-tile--dir" aria-label="Up one folder">
                                <span class="v-tile__art"><x-admin.icon name="folder" class="v-tile__mark" /></span>
                                <span class="v-tile__name v-tile__name--dim">..</span>
                            </button>
                        </template>

                        {{-- Folders --}}
                        <template x-for="folder in filteredFolders" :key="folder.path">
                            <button type="button" @click="browseDir(folder.path)" class="v-tile v-tile--dir">
                                <span class="v-tile__art"><x-admin.icon name="folder" class="v-tile__mark" /></span>
                                <span class="v-tile__name" x-text="folder.name"></span>
                            </button>
                        </template>

                        {{-- Files --}}
                        <template x-for="file in filteredFiles" :key="file.path">
                            <div class="v-tile-wrap">
                                <div class="v-tile">
                                    <span class="v-tile__art">
                                        <img :src="file.url" :alt="file.name" class="v-tile__img" loading="lazy">
                                    </span>
                                    <span class="v-tile__name" x-text="file.name"></span>
                                </div>
                                {{-- Copy path + Delete --}}
                                <div class="v-tile__acts">
                                    <button type="button" class="v-tile__act" @click="copyPath(file.path)" :aria-label="'Copy path to ' + file.name">
                                        <x-admin.icon name="copy" class="w-3.5 h-3.5" />
                                    </button>
                                    <button type="button" class="v-tile__act v-tile__act--danger" @click="askDelete(file)" :aria-label="'Delete ' + file.name">
                                        <x-admin.icon name="trash" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Empty --}}
                    <div x-show="!loading && folders.length === 0 && files.length === 0">
                        <x-admin.empty-state title="Empty folder" description="Drop files here or use Upload above" />
                    </div>
                </div>
            </x-admin.panel>

            {{-- Toast --}}
            <div x-show="toast" x-cloak x-transition.opacity role="status" aria-live="polite"
                 class="v-modal fixed bottom-6 right-6 z-[60] rounded-v-md px-4 py-3 text-[13px]"
                 :class="toastType === 'error' ? 'bg-v-danger text-v-danger-text' : 'bg-v-accent text-v-accent-text'">
                <span x-text="toast"></span>
            </div>

            {{-- Inside mediaManager's scope so the dialog can read the file
                 the person picked and call the same deleteFile() the button
                 used to call directly. --}}
            <x-admin.confirm-dialog name="delete-media-file" title="Delete this file?"
                confirm="Delete" onConfirm="deleteFile(pendingDelete)">
                <span x-text="pendingDelete
                    ? pendingDelete.name + ' is deleted from the server. Any product or page still pointing at it loses its image.'
                    : ''"></span>
            </x-admin.confirm-dialog>
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
                toast: '',
                toastType: 'success',
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
                    this.toast = msg;
                    this.toastType = type || 'success';
                    setTimeout(() => { this.toast = ''; }, 3000);
                },

                async browseDir(path) {
                    this.loading = true;
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
                    this.uploadStatus = 'Uploading ' + file.name + '...';

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

                copyPath(path) {
                    navigator.clipboard.writeText(path).then(() => {
                        this.showToast('Path copied');
                    });
                },

                askDelete(file) {
                    this.pendingDelete = file;
                    this.$dispatch('open-modal', 'delete-media-file');
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
                    }
                },
            };
        }
        </script>
        @endpush
    </x-admin.page>
</x-admin.shell>
