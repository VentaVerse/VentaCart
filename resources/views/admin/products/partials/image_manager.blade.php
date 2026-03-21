{{-- Image Manager Partial --}}
{{-- Usage: @include('admin.products.partials.image_manager', ['existingImages' => $existingImages ?? []]) --}}

<div x-data="imageManager()" x-init="init()" class="p-6">

    {{-- Hidden JSON field --}}
    <input type="hidden" name="images_json" :value="JSON.stringify(items)">

    {{-- Upload Area --}}
    <div class="flex items-center gap-3 mb-4">
        <label class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors cursor-pointer">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Upload Files
            <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="handleFileSelect($event)" class="sr-only">
        </label>

        <button type="button" @click="showUrlInput = !showUrlInput" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            Import URL
        </button>

        <button type="button" @click="openBrowser()" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            Browse Server
        </button>
    </div>

    {{-- URL Import Input --}}
    <div x-show="showUrlInput" x-transition class="mb-4">
        <div class="flex gap-2">
            <input type="text" x-model="urlInput" placeholder="https://example.com/image.jpg"
                class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                @keydown.enter.prevent="importFromUrl()">
            <button type="button" @click="importFromUrl()" :disabled="importingUrl" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                <span x-show="!importingUrl">Import</span>
                <span x-show="importingUrl">Importing...</span>
            </button>
        </div>
    </div>

    {{-- Drop Zone + Thumbnail Grid --}}
    <div @dragover.prevent="dragover = true"
         @dragleave.prevent="dragover = false"
         @drop.prevent="dragover = false; handleDrop($event)"
         :class="dragover ? 'ring-2 ring-indigo-500 bg-indigo-50' : 'bg-gray-50'"
         class="rounded-lg border-2 border-dashed border-gray-300 p-4 min-h-32 transition-all">

        {{-- Upload Progress --}}
        <div x-show="uploading" class="mb-3">
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="'width: ' + uploadProgress + '%'"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1" x-text="uploadStatus"></p>
        </div>

        {{-- Empty --}}
        <div x-show="items.length === 0 && !uploading" class="text-center py-8 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm">Drag & drop images here, or use the buttons above</p>
        </div>

        {{-- Thumbnail Grid --}}
        <div x-show="items.length > 0" x-ref="sortableGrid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
            <template x-for="(item, index) in items" :key="item.path">
                <div class="relative group rounded-lg overflow-hidden border border-gray-200 bg-white cursor-move" :data-index="index">
                    <div class="aspect-square">
                        <img :src="item.url" :alt="'Image ' + (index + 1)" class="w-full h-full object-cover">
                    </div>
                    {{-- Badge --}}
                    <span class="absolute top-1 left-1 px-1.5 py-0.5 text-xs font-medium rounded"
                          :class="index === 0 ? 'bg-indigo-600 text-white' : 'bg-gray-800 bg-opacity-60 text-white'"
                          x-text="index === 0 ? 'Main' : '#' + (index + 1)"></span>
                    {{-- Remove --}}
                    <button type="button" @click="removeItem(index)"
                        class="absolute top-1 right-1 hidden group-hover:flex w-6 h-6 bg-red-500 text-white rounded-full items-center justify-center hover:bg-red-600 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-transition.opacity class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-lg shadow-lg text-sm text-white"
         :class="toastType === 'error' ? 'bg-red-600' : 'bg-green-600'">
        <span x-text="toast"></span>
    </div>

    {{-- Server Browser Modal --}}
    <div x-show="browserOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center" style="background: rgba(20,20,20,0.96);" @keydown.escape.window="browserOpen = false">
        <div class="w-full max-w-5xl mx-4 max-h-[85vh] flex flex-col" @click.outside="browserOpen = false">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-white">
                    <h3 class="text-lg font-semibold">Server Browser</h3>
                    <span class="text-gray-400 text-sm">—</span>
                    {{-- Breadcrumb --}}
                    <nav class="flex items-center gap-1 text-sm">
                        <button @click="browseDir('')" class="text-indigo-400 hover:text-indigo-300">catalog</button>
                        <template x-for="(crumb, i) in browserBreadcrumbs" :key="i">
                            <span class="flex items-center gap-1">
                                <span class="text-gray-500">/</span>
                                <button @click="browseDir(crumb.path)" class="text-indigo-400 hover:text-indigo-300" x-text="crumb.name"></button>
                            </span>
                        </template>
                    </nav>
                </div>
                <button @click="browserOpen = false" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal Toolbar --}}
            <div class="flex items-center gap-3 mb-4">
                <input type="text" x-model="browserSearch" placeholder="Search files..."
                    class="w-64 rounded-lg bg-gray-800 border-gray-700 text-white text-sm placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500">
                <select x-model="browserSort"
                    class="rounded-lg bg-gray-800 border-gray-700 text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="name">Sort by Name</option>
                    <option value="date">Sort by Date</option>
                </select>
                <label class="ml-auto px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 cursor-pointer">
                    Upload Here
                    <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="uploadToCatalog($event)" class="sr-only">
                </label>
            </div>

            {{-- Modal Content --}}
            <div class="flex-1 overflow-y-auto rounded-lg bg-gray-900 p-4">
                <div x-show="browserLoading" class="text-center py-12 text-gray-500">Loading...</div>

                <div x-show="!browserLoading" class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));">
                    {{-- Parent Folder --}}
                    <template x-if="browserCurrentPath !== ''">
                        <button @click="browseDir(browserParent)" class="flex flex-col items-center p-3 rounded-lg hover:bg-gray-800 transition-colors">
                            <svg class="w-10 h-10 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <span class="text-xs text-gray-400 mt-1">..</span>
                        </button>
                    </template>

                    {{-- Folders --}}
                    <template x-for="folder in browserFolders" :key="folder.path">
                        <button @click="browseDir(folder.path)" class="flex flex-col items-center p-3 rounded-lg hover:bg-gray-800 transition-colors">
                            <svg class="w-10 h-10 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <span class="text-xs text-gray-300 mt-1 truncate w-full text-center" x-text="folder.name"></span>
                        </button>
                    </template>

                    {{-- Files --}}
                    <template x-for="file in filteredBrowserFiles" :key="file.path">
                        <div class="relative group">
                            <button @click="selectServerFile(file)" class="flex flex-col items-center p-2 rounded-lg hover:bg-gray-800 transition-colors w-full">
                                <div class="w-full aspect-square rounded overflow-hidden bg-gray-800">
                                    <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                                </div>
                                <span class="text-xs text-gray-400 mt-1 truncate w-full text-center" x-text="file.name"></span>
                            </button>
                            <button @click="deleteServerFile(file)" class="absolute top-1 right-1 hidden group-hover:flex w-5 h-5 bg-red-500 text-white rounded-full items-center justify-center text-xs hover:bg-red-600">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>

                <div x-show="!browserLoading && browserFolders.length === 0 && browserFiles.length === 0"
                     class="text-center py-12 text-gray-500">
                    Empty folder. Upload images or go back.
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
function imageManager() {
    return {
        items: @json($existingImages ?? []),
        pimToken: '{{ $pimToken ?? Str::uuid() }}',
        uploading: false,
        uploadProgress: 0,
        uploadStatus: '',
        dragover: false,
        showUrlInput: false,
        urlInput: '',
        importingUrl: false,
        toast: '',
        toastType: 'success',

        // Browser
        browserOpen: false,
        browserLoading: false,
        browserCurrentPath: '',
        browserParent: '',
        browserFolders: [],
        browserFiles: [],
        browserSearch: '',
        browserSort: 'name',

        init() {
            this.$nextTick(() => {
                if (this.$refs.sortableGrid) {
                    new Sortable(this.$refs.sortableGrid, {
                        animation: 150,
                        ghostClass: 'opacity-30',
                        onEnd: (evt) => {
                            const moved = this.items.splice(evt.oldIndex, 1)[0];
                            this.items.splice(evt.newIndex, 0, moved);
                        },
                    });
                }
            });
        },

        getStoreId() {
            const el = document.getElementById('store_id');
            return el ? el.value : '1';
        },

        showToast(msg, type = 'success') {
            this.toast = msg;
            this.toastType = type;
            setTimeout(() => { this.toast = ''; }, 3000);
        },

        async handleFileSelect(event) {
            await this.uploadFiles(event.target.files);
            event.target.value = '';
        },

        async handleDrop(event) {
            await this.uploadFiles(event.dataTransfer.files);
        },

        async uploadFiles(files) {
            if (!files.length) return;
            const storeId = this.getStoreId();
            if (!storeId) {
                this.showToast('Please select a store first.', 'error');
                return;
            }

            this.uploading = true;
            this.uploadProgress = 0;
            let uploaded = 0;

            for (let i = 0; i < files.length; i++) {
                this.uploadStatus = `Uploading ${i + 1} of ${files.length}...`;
                const formData = new FormData();
                formData.append('file', files[i]);
                formData.append('store_id', storeId);
                formData.append('pim_token', this.pimToken);

                try {
                    const res = await fetch('{{ route("admin.products.images.upload") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: formData,
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.items.push({ path: data.path, url: data.url });
                        uploaded++;
                    } else {
                        this.showToast(data.message || 'Upload failed.', 'error');
                    }
                } catch (e) {
                    this.showToast('Upload failed.', 'error');
                }
                this.uploadProgress = Math.round(((i + 1) / files.length) * 100);
            }

            this.uploading = false;
            if (uploaded > 0) this.showToast(`${uploaded} image(s) uploaded.`);
        },

        async importFromUrl() {
            if (!this.urlInput.trim()) return;
            const storeId = this.getStoreId();
            if (!storeId) {
                this.showToast('Please select a store first.', 'error');
                return;
            }

            this.importingUrl = true;
            try {
                const res = await fetch('{{ route("admin.products.images.import-url") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ url: this.urlInput, store_id: storeId, pim_token: this.pimToken }),
                });
                const data = await res.json();
                if (data.ok) {
                    this.items.push({ path: data.path, url: data.url });
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

        removeItem(index) {
            this.items.splice(index, 1);
        },

        // --- Server Browser ---
        async openBrowser() {
            const storeId = this.getStoreId();
            if (!storeId) {
                this.showToast('Please select a store first.', 'error');
                return;
            }
            this.browserOpen = true;
            await this.browseDir('');
        },

        get browserBreadcrumbs() {
            if (!this.browserCurrentPath) return [];
            const parts = this.browserCurrentPath.split('/');
            return parts.map((part, i) => ({
                name: part,
                path: parts.slice(0, i + 1).join('/'),
            }));
        },

        get filteredBrowserFiles() {
            let files = [...this.browserFiles];
            if (this.browserSearch) {
                const q = this.browserSearch.toLowerCase();
                files = files.filter(f => f.name.toLowerCase().includes(q));
            }
            files.sort((a, b) => {
                if (this.browserSort === 'date') return b.modified - a.modified;
                return a.name.localeCompare(b.name);
            });
            return files;
        },

        async browseDir(path) {
            this.browserLoading = true;
            const storeId = this.getStoreId();
            const params = new URLSearchParams({ store_id: storeId });
            if (path) params.set('path', path);

            const res = await fetch(`{{ route('admin.products.images.browse') }}?${params}`);
            const data = await res.json();

            if (data.ok) {
                this.browserCurrentPath = data.path;
                this.browserParent = data.parent ?? '';
                this.browserFolders = data.folders;
                this.browserFiles = data.files;
            }
            this.browserLoading = false;
        },

        selectServerFile(file) {
            const already = this.items.find(i => i.path === file.path);
            if (already) {
                this.showToast('Image already added.', 'error');
                return;
            }
            this.items.push({ path: file.path, url: file.url });
            this.showToast('Image added.');
        },

        async deleteServerFile(file) {
            if (!confirm(`Delete "${file.name}" from server?`)) return;
            const storeId = this.getStoreId();

            const res = await fetch('{{ route("admin.products.images.delete") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ path: file.path, store_id: storeId }),
            });
            const data = await res.json();

            if (data.ok) {
                this.browserFiles = this.browserFiles.filter(f => f.path !== file.path);
                this.items = this.items.filter(i => i.path !== file.path);
                this.showToast('File deleted.');
            }
        },

        async uploadToCatalog(event) {
            const files = event.target.files;
            if (!files.length) return;
            const storeId = this.getStoreId();

            for (const file of files) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('store_id', storeId);
                formData.append('catalog_path', this.browserCurrentPath);

                const res = await fetch('{{ route("admin.products.images.upload-to-catalog") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: formData,
                });
                const data = await res.json();
                if (data.ok) {
                    this.browserFiles.push({ name: data.name, path: data.path, url: data.url, size: data.size, modified: Date.now() / 1000 });
                }
            }
            event.target.value = '';
            this.showToast('Uploaded to catalog.');
        },
    }
}
</script>
@endpush
