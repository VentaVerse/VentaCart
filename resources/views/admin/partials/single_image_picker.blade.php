{{-- Single Image Picker --}}
{{-- Usage: @include('admin.partials.single_image_picker', ['fieldName' => 'image', 'currentImage' => $model?->image, 'pimToken' => $pimToken]) --}}

@php
    $fieldName = $fieldName ?? 'image';
    $currentImage = $currentImage ?? null;
    $pimToken = $pimToken ?? (string) Str::uuid();
    $componentId = 'sip_' . str_replace(['[', ']', '.'], '_', $fieldName);
@endphp

<div x-data="{{ $componentId }}()" x-init="init()">
    <input type="hidden" name="{{ $fieldName }}" :value="imagePath">

    {{-- Preview --}}
    <div x-show="imagePath" class="mb-3">
        <div class="relative inline-block">
            <img :src="imageUrl" class="h-32 w-32 rounded-lg object-cover border border-gray-200">
            <button type="button" @click="clear()"
                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-2">
        <label class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors cursor-pointer">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Upload
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="handleUpload($event)" class="sr-only">
        </label>

        <button type="button" @click="openBrowser()" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            Browse
        </button>

        <span x-show="uploading" class="text-xs text-gray-400">Uploading...</span>
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-transition.opacity class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-lg shadow-lg text-sm text-white"
         :class="toastType === 'error' ? 'bg-red-600' : 'bg-green-600'">
        <span x-text="toast"></span>
    </div>

    {{-- Server Browser Modal --}}
    <div x-show="browserOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center" style="background: rgba(20,20,20,0.96);" @keydown.escape.window="browserOpen = false">
        <div class="w-full max-w-5xl mx-4 max-h-[85vh] flex flex-col" @click.outside="browserOpen = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-white">
                    <h3 class="text-lg font-semibold">Select Image</h3>
                    <span class="text-gray-400 text-sm">—</span>
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

            <div class="flex items-center gap-3 mb-4">
                <input type="text" x-model="browserSearch" placeholder="Search files..."
                    class="w-64 rounded-lg bg-gray-800 border-gray-700 text-white text-sm placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500">
                <label class="ml-auto px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 cursor-pointer">
                    Upload Here
                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="uploadToCatalog($event)" class="sr-only">
                </label>
            </div>

            <div class="flex-1 overflow-y-auto rounded-lg bg-gray-900 p-4">
                <div x-show="browserLoading" class="text-center py-12 text-gray-500">Loading...</div>

                <div x-show="!browserLoading" class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));">
                    <template x-if="browserCurrentPath !== ''">
                        <button @click="browseDir(browserParent)" class="flex flex-col items-center p-3 rounded-lg hover:bg-gray-800 transition-colors">
                            <svg class="w-10 h-10 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <span class="text-xs text-gray-400 mt-1">..</span>
                        </button>
                    </template>

                    <template x-for="folder in browserFolders" :key="folder.path">
                        <button @click="browseDir(folder.path)" class="flex flex-col items-center p-3 rounded-lg hover:bg-gray-800 transition-colors">
                            <svg class="w-10 h-10 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <span class="text-xs text-gray-300 mt-1 truncate w-full text-center" x-text="folder.name"></span>
                        </button>
                    </template>

                    <template x-for="file in filteredBrowserFiles" :key="file.path">
                        <button @click="selectFile(file)" class="flex flex-col items-center p-2 rounded-lg hover:bg-gray-800 transition-colors w-full">
                            <div class="w-full aspect-square rounded overflow-hidden bg-gray-800">
                                <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                            </div>
                            <span class="text-xs text-gray-400 mt-1 truncate w-full text-center" x-text="file.name"></span>
                        </button>
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

@pushOnce('scripts')
<script>
function {{ $componentId }}() {
    return {
        imagePath: '{{ $currentImage ?? '' }}',
        imageUrl: '{{ $currentImage ? Storage::disk("public")->url($currentImage) : '' }}',
        pimToken: '{{ $pimToken }}',
        uploading: false,
        toast: '',
        toastType: 'success',

        browserOpen: false,
        browserLoading: false,
        browserCurrentPath: '',
        browserParent: '',
        browserFolders: [],
        browserFiles: [],
        browserSearch: '',

        init() {},

        getStoreId() {
            const el = document.getElementById('store_id');
            return el ? el.value : '1';
        },

        showToast(msg, type = 'success') {
            this.toast = msg;
            this.toastType = type;
            setTimeout(() => { this.toast = ''; }, 3000);
        },

        clear() {
            this.imagePath = '';
            this.imageUrl = '';
        },

        async handleUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            event.target.value = '';

            const storeId = this.getStoreId();
            if (!storeId) {
                this.showToast('Please select a store first.', 'error');
                return;
            }

            this.uploading = true;
            const formData = new FormData();
            formData.append('file', file);
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
                    this.imagePath = data.path;
                    this.imageUrl = data.url;
                    this.showToast('Image uploaded.');
                } else {
                    this.showToast(data.message || 'Upload failed.', 'error');
                }
            } catch (e) {
                this.showToast('Upload failed.', 'error');
            }
            this.uploading = false;
        },

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
            return files.sort((a, b) => a.name.localeCompare(b.name));
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

        selectFile(file) {
            this.imagePath = file.path;
            this.imageUrl = file.url;
            this.browserOpen = false;
            this.showToast('Image selected.');
        },

        async uploadToCatalog(event) {
            const file = event.target.files[0];
            if (!file) return;
            event.target.value = '';
            const storeId = this.getStoreId();

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
                this.showToast('Uploaded to catalog.');
            }
        },
    }
}
</script>
@endPushOnce
