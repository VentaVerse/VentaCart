{{-- Image Manager Partial --}}
{{-- Usage: @include('admin.products.partials.image_manager', ['existingImages' => $existingImages ?? []]) --}}

<div x-data="imageManager()" x-init="init()" class="p-6">

    {{-- Hidden JSON field --}}
    <input type="hidden" name="images_json" :value="JSON.stringify(items)">

    {{-- Toolbar --}}
    <div class="flex items-center gap-2 mb-5">
        <label class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors cursor-pointer shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Upload
            <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="handleFileSelect($event)" class="sr-only">
        </label>

        <button type="button" @click="showUrlInput = !showUrlInput"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
            :class="showUrlInput ? 'bg-gray-100 border-indigo-300 text-indigo-700' : ''">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            URL
        </button>

        <button type="button" @click="openBrowser()"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            Browse Server
        </button>

        <span class="ml-auto text-xs text-gray-400" x-show="items.length > 0" x-text="items.length + ' image' + (items.length !== 1 ? 's' : '')"></span>
    </div>

    {{-- URL Import Input --}}
    <div x-show="showUrlInput" x-transition class="mb-5">
        <div class="flex gap-2">
            <input type="text" x-model="urlInput" placeholder="https://example.com/image.jpg"
                class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                @keydown.enter.prevent="importFromUrl()">
            <button type="button" @click="importFromUrl()" :disabled="importingUrl"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                <span x-show="!importingUrl">Import</span>
                <span x-show="importingUrl">Importing...</span>
            </button>
        </div>
    </div>

    {{-- Drop Zone + Thumbnail Grid --}}
    <div @dragover.prevent="dragover = true"
         @dragleave.prevent="dragover = false"
         @drop.prevent="dragover = false; handleDrop($event)"
         :class="dragover ? 'ring-2 ring-indigo-400 bg-indigo-50 border-indigo-300' : 'bg-gray-50 border-gray-300'"
         class="rounded-xl border-2 border-dashed p-4 min-h-[10rem] transition-all">

        {{-- Upload Progress --}}
        <div x-show="uploading" class="mb-4">
            <div class="flex items-center gap-3">
                <div class="flex-1 bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                </div>
                <span class="text-xs text-gray-500 whitespace-nowrap" x-text="uploadStatus"></span>
            </div>
        </div>

        {{-- Empty State --}}
        <div x-show="items.length === 0 && !uploading" class="text-center py-10 text-gray-400">
            <svg class="w-14 h-14 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm font-medium text-gray-500">No images yet</p>
            <p class="text-xs mt-1">Drag & drop files here, or use the toolbar above</p>
        </div>

        {{-- Thumbnail Grid --}}
        <div x-show="items.length > 0" x-ref="sortableGrid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
            <template x-for="(item, index) in items" :key="item.path">
                <div class="relative group rounded-lg overflow-hidden bg-white shadow-sm border border-gray-200 cursor-move hover:shadow-md transition-shadow" :data-index="index">
                    <div class="aspect-square">
                        <img :src="item.url" :alt="'Image ' + (index + 1)" class="w-full h-full object-cover">
                    </div>
                    {{-- Badge --}}
                    <span class="absolute top-1.5 left-1.5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded shadow-sm"
                          :class="index === 0 ? 'bg-indigo-600 text-white' : 'bg-black/50 text-white backdrop-blur-sm'"
                          x-text="index === 0 ? 'Main' : '#' + (index + 1)"></span>
                    {{-- Remove --}}
                    <button type="button" @click="removeItem(index)"
                        class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-all shadow-sm">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-transition.opacity class="fixed bottom-6 right-6 z-[60] px-4 py-3 rounded-lg shadow-lg text-sm text-white"
         :class="toastType === 'error' ? 'bg-red-600' : 'bg-emerald-600'">
        <span x-text="toast"></span>
    </div>

    {{-- Server Browser Modal --}}
    <div x-show="browserOpen" x-transition.opacity.duration.200ms
         class="fixed inset-0 z-[70] flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.45); backdrop-filter: blur(3px);"
         @keydown.escape.window="browserOpen = false">

        <div class="w-full max-w-5xl max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
             @click.outside="browserOpen = false">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <h3 class="text-sm font-semibold text-gray-900">Server Browser</h3>
                        </div>
                        <span class="text-gray-300">|</span>
                        {{-- Breadcrumb --}}
                        <nav class="flex items-center gap-0.5 text-sm min-w-0 overflow-x-auto">
                            <button type="button" @click="browseDir('')"
                                class="text-indigo-600 hover:text-indigo-800 transition-colors shrink-0 font-medium">catalog</button>
                            <template x-for="(crumb, i) in browserBreadcrumbs" :key="i">
                                <span class="flex items-center gap-0.5 shrink-0">
                                    <span class="text-gray-400">/</span>
                                    <button type="button" @click="browseDir(crumb.path)"
                                        class="text-indigo-600 hover:text-indigo-800 transition-colors font-medium"
                                        x-text="crumb.name"></button>
                                </span>
                            </template>
                        </nav>
                    </div>
                    <button type="button" @click="browserOpen = false"
                        class="text-gray-400 hover:text-gray-700 transition-colors p-1 rounded-lg hover:bg-gray-100 shrink-0 ml-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal Toolbar --}}
                <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-100 bg-white">
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="browserSearch" placeholder="Search..."
                            class="w-52 pl-9 pr-3 py-1.5 rounded-lg bg-white border-gray-300 text-gray-900 text-sm placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <select x-model="browserSort"
                        class="rounded-lg bg-white border-gray-300 text-gray-700 text-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                        <option value="name">Name</option>
                        <option value="date">Date</option>
                    </select>
                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-xs text-gray-400" x-show="browserFiles.length > 0"
                              x-text="filteredBrowserFiles.length + ' file' + (filteredBrowserFiles.length !== 1 ? 's' : '')"></span>
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 cursor-pointer transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Upload Here
                            <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="uploadToCatalog($event)" class="sr-only">
                        </label>
                    </div>
                </div>

                {{-- Modal Content --}}
                <div class="flex-1 overflow-y-auto p-5 bg-gray-50/50">
                    <div x-show="browserLoading" class="flex items-center justify-center py-16">
                        <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>

                    <div x-show="!browserLoading" class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                        {{-- Parent Folder --}}
                        <template x-if="browserCurrentPath !== ''">
                            <button type="button" @click="browseDir(browserParent)"
                                class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                                <svg class="w-12 h-12 text-amber-500 group-hover:text-amber-600 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                <span class="text-xs text-gray-500 mt-1.5 font-medium">..</span>
                            </button>
                        </template>

                        {{-- Folders --}}
                        <template x-for="folder in browserFolders" :key="folder.path">
                            <button type="button" @click="browseDir(folder.path)"
                                class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                                <svg class="w-12 h-12 text-amber-500 group-hover:text-amber-600 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                <span class="text-xs text-gray-600 mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                            </button>
                        </template>

                        {{-- Files --}}
                        <template x-for="file in filteredBrowserFiles" :key="file.path">
                            <div class="relative group">
                                <button type="button" @click="selectServerFile(file)"
                                    class="flex flex-col items-center p-2 rounded-xl hover:bg-gray-100 transition-colors w-full"
                                    :class="items.find(i => i.path === file.path) ? 'ring-2 ring-indigo-500 bg-indigo-50' : ''">
                                    <div class="w-full aspect-square rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                                        <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                                    </div>
                                    <span class="text-[11px] text-gray-500 mt-1.5 truncate w-full text-center leading-tight" x-text="file.name"></span>
                                </button>
                                {{-- Already-added indicator --}}
                                <div x-show="items.find(i => i.path === file.path)"
                                     class="absolute top-3 left-3 w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center pointer-events-none shadow-sm">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <button type="button" @click="deleteServerFile(file)"
                                    class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 transition-all shadow-sm">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div x-show="!browserLoading && browserFolders.length === 0 && browserFiles.length === 0"
                         class="text-center py-16">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        <p class="text-sm text-gray-500 font-medium">Empty folder</p>
                        <p class="text-xs text-gray-400 mt-1">Upload images or navigate to another folder</p>
                    </div>
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
        brandFolder: '{{ $brandFolder ?? '' }}',
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
        browserSort: 'date',

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
            this.browserOpen = true;
            await this.browseDir(this.brandFolder || '');
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
            const params = new URLSearchParams();
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

            const res = await fetch('{{ route("admin.products.images.delete") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ path: file.path }),
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

            for (const file of files) {
                const formData = new FormData();
                formData.append('file', file);
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
