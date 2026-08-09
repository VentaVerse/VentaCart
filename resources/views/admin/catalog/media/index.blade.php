<x-admin.shell title="Media Manager">
    <x-admin.page title="Media Manager">
        <div x-data="mediaManager()" class="bg-v-surface border border-v-border rounded-v-lg" style="min-height: 70vh;">

            {{-- Toolbar --}}
            <div class="flex items-center gap-2 px-5 py-3 border-b border-v-border">
                {{-- Breadcrumbs --}}
                <nav class="flex items-center gap-0.5 text-[13px] min-w-0 overflow-x-auto flex-1">
                    <button type="button" @click="browseDir('')" class="text-v-accent hover:opacity-80 font-medium shrink-0 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"/></svg>
                        Files
                    </button>
                    <template x-for="(crumb, i) in breadcrumbs" :key="i">
                        <span class="flex items-center gap-0.5 shrink-0">
                            <span class="text-v-muted">/</span>
                            <button type="button" @click="browseDir(crumb.path)" class="text-v-accent hover:opacity-80 font-medium" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>

                {{-- Search --}}
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-v-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="search" placeholder="Filter..."
                        @keydown.enter.prevent
                        class="v-control w-40 h-[var(--control-h)] pl-8 pr-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md placeholder:text-v-muted">
                </div>

                {{-- URL Import --}}
                <button type="button" @click="showUrlInput = !showUrlInput"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[13px] font-medium rounded-v-md border transition-colors"
                    :class="showUrlInput ? 'bg-v-accent-weak border-v-accent text-v-accent' : 'border-v-border text-v-secondary hover:bg-v-hover'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    URL
                </button>

                {{-- Upload --}}
                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-v-accent text-v-accent-text text-[13px] font-medium rounded-v-md hover:bg-v-accent-hover cursor-pointer transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Here
                    <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="uploadFiles($event)" class="sr-only">
                </label>

                {{-- File count --}}
                <span class="text-xs text-v-muted shrink-0" x-show="files.length > 0" x-text="filteredFiles.length + ' files'"></span>
            </div>

            {{-- URL Import Bar --}}
            <div x-show="showUrlInput" x-transition class="px-5 py-2.5 border-b border-v-border bg-v-sunken">
                <div class="flex gap-2">
                    <input type="text" x-model="urlInput" placeholder="https://example.com/image.jpg"
                        class="v-control flex-1 h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md placeholder:text-v-muted"
                        @keydown.enter.prevent="importFromUrl()">
                    <x-admin.button type="button" variant="primary" @click="importFromUrl()" x-bind:disabled="importingUrl">
                        <span x-show="!importingUrl">Import</span>
                        <span x-show="importingUrl" x-cloak>Importing...</span>
                    </x-admin.button>
                </div>
            </div>

            {{-- Content --}}
            <div class="flex-1 p-5 relative"
                 @dragover.prevent="dragover = true"
                 @dragleave.prevent="dragover = false"
                 @drop.prevent="dragover = false; handleDrop($event)">

                {{-- Drag overlay --}}
                <div x-show="dragover" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-v-accent-weak border-2 border-dashed border-v-accent rounded-v-lg m-2 pointer-events-none">
                    <div class="text-center">
                        <svg class="w-10 h-10 mx-auto mb-2 text-v-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p class="text-[13px] font-medium text-v-accent">Drop files to upload</p>
                    </div>
                </div>

                {{-- Upload progress --}}
                <div x-show="uploading" x-cloak class="mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin text-v-accent shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-xs text-v-muted" x-text="uploadStatus"></span>
                </div>

                {{-- Loading --}}
                <div x-show="loading" class="flex items-center justify-center py-20">
                    <svg class="w-6 h-6 animate-spin text-v-accent" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>

                {{-- File grid --}}
                <div x-show="!loading" class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                    {{-- Back folder --}}
                    <template x-if="currentPath !== ''">
                        <button type="button" @click="browseDir(parentPath)" class="flex flex-col items-center p-3 rounded-v-lg hover:bg-v-hover transition-colors group">
                            <svg class="w-14 h-14 text-v-muted group-hover:text-v-secondary transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <span class="text-xs text-v-muted mt-1.5 font-medium">..</span>
                        </button>
                    </template>

                    {{-- Folders --}}
                    <template x-for="folder in filteredFolders" :key="folder.path">
                        <button type="button" @click="browseDir(folder.path)" class="flex flex-col items-center p-3 rounded-v-lg hover:bg-v-hover transition-colors group">
                            <svg class="w-14 h-14 text-v-muted group-hover:text-v-secondary transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <span class="text-xs text-v-secondary mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                        </button>
                    </template>

                    {{-- Files --}}
                    <template x-for="file in filteredFiles" :key="file.path">
                        <div class="relative group">
                            <div class="flex flex-col items-center p-2 rounded-v-lg hover:bg-v-hover transition-colors">
                                <div class="w-full aspect-square rounded-v-md overflow-hidden bg-v-sunken border border-v-border">
                                    <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                                </div>
                                <span class="text-[11px] text-v-muted mt-1.5 truncate w-full text-center leading-tight" x-text="file.name"></span>
                            </div>
                            {{-- Copy path + Delete --}}
                            <div class="absolute top-3 right-3 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button type="button" @click="copyPath(file.path)" :aria-label="'Copy path to ' + file.name"
                                    class="w-6 h-6 bg-v-surface/90 backdrop-blur-sm rounded-full flex items-center justify-center text-v-muted hover:text-v-accent shadow-sm border border-v-border" title="Copy path">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                                <button type="button" @click="askDelete(file)" :aria-label="'Delete ' + file.name"
                                    class="w-6 h-6 bg-v-surface/90 backdrop-blur-sm rounded-full flex items-center justify-center text-v-muted hover:text-v-danger shadow-sm border border-v-border" title="Delete">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Empty --}}
                <div x-show="!loading && folders.length === 0 && files.length === 0">
                    <x-admin.empty-state icon="media" title="Empty folder" description="Drop files here or use Upload above" />
                </div>
            </div>

            {{-- Toast --}}
            <div x-show="toast" x-cloak x-transition.opacity class="fixed bottom-6 right-6 z-[60] px-4 py-3 rounded-v-md shadow-lg text-[13px]"
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
