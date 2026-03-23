{{-- Single Image Picker --}}
{{-- Usage: @include('admin.partials.single_image_picker', ['fieldName' => 'image', 'currentImage' => $model?->image, 'defaultFolder' => 'Banners']) --}}

@php
    $fieldName = $fieldName ?? 'image';
    $currentImage = $currentImage ?? null;
    $label = $label ?? 'Image';
    $hint = $hint ?? '';
    $required = $required ?? false;
    $defaultFolder = $defaultFolder ?? '';
    $componentId = 'sip_' . str_replace(['[', ']', '.'], '_', $fieldName);
@endphp

<div x-data="{{ $componentId }}()" class="space-y-2">
    <label class="block text-sm font-medium text-gray-700">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>

    <input type="hidden" name="{{ $fieldName }}" :value="imagePath">

    {{-- Preview --}}
    <div x-show="imagePath" class="mb-2">
        <div class="relative inline-block">
            <img :src="imageUrl" class="h-32 rounded-lg object-cover border border-gray-200">
            <button type="button" @click="clear()"
                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-2">
        <button type="button" @click="openBrowser()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            Browse
        </button>
    </div>
    @if($hint)
        <p class="text-xs text-gray-400">{{ $hint }}</p>
    @endif

    {{-- Toast --}}
    <div x-show="toast" x-transition.opacity class="fixed bottom-6 right-6 z-[60] px-4 py-3 rounded-lg shadow-lg text-sm text-white"
         :class="toastType === 'error' ? 'bg-red-600' : 'bg-emerald-600'">
        <span x-text="toast"></span>
    </div>

    {{-- Server Browser Modal --}}
    <div x-show="browserOpen" x-transition.opacity.duration.200ms
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.45); backdrop-filter: blur(3px);"
         @keydown.escape.window="browserOpen = false">

        <div class="w-full max-w-5xl max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
             @click.outside="browserOpen = false">

            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        <h3 class="text-sm font-semibold text-gray-900">Select Image</h3>
                    </div>
                    <span class="text-gray-300">|</span>
                    <nav class="flex items-center gap-0.5 text-sm min-w-0 overflow-x-auto">
                        <button type="button" @click="browseDir('')" class="text-indigo-600 hover:text-indigo-800 transition-colors shrink-0 font-medium">catalog</button>
                        <template x-for="(crumb, i) in browserBreadcrumbs" :key="i">
                            <span class="flex items-center gap-0.5 shrink-0">
                                <span class="text-gray-400">/</span>
                                <button type="button" @click="browseDir(crumb.path)" class="text-indigo-600 hover:text-indigo-800 transition-colors font-medium" x-text="crumb.name"></button>
                            </span>
                        </template>
                    </nav>
                </div>
                <button type="button" @click="browserOpen = false" class="text-gray-400 hover:text-gray-700 transition-colors p-1 rounded-lg hover:bg-gray-100 shrink-0 ml-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-100 bg-white">
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="browserSearch" placeholder="Search..."
                        class="w-52 pl-9 pr-3 py-1.5 rounded-lg bg-white border-gray-300 text-gray-900 text-sm placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="ml-auto">
                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 cursor-pointer transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Upload Here
                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="uploadToCatalog($event)" class="sr-only">
                    </label>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-5 bg-gray-50/50">
                <div x-show="browserLoading" class="flex items-center justify-center py-16">
                    <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>

                <div x-show="!browserLoading" class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                    <template x-if="browserCurrentPath !== ''">
                        <button type="button" @click="browseDir(browserParent)" class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                            <svg class="w-12 h-12 text-amber-500 group-hover:text-amber-600 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <span class="text-xs text-gray-500 mt-1.5 font-medium">..</span>
                        </button>
                    </template>

                    <template x-for="folder in browserFolders" :key="folder.path">
                        <button type="button" @click="browseDir(folder.path)" class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                            <svg class="w-12 h-12 text-amber-500 group-hover:text-amber-600 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <span class="text-xs text-gray-600 mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                        </button>
                    </template>

                    <template x-for="file in filteredBrowserFiles" :key="file.path">
                        <button type="button" @click="selectFile(file)" class="flex flex-col items-center p-2 rounded-xl hover:bg-gray-100 transition-colors w-full">
                            <div class="w-full aspect-square rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                                <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                            </div>
                            <span class="text-[11px] text-gray-500 mt-1.5 truncate w-full text-center leading-tight" x-text="file.name"></span>
                        </button>
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

@push('scripts')
<script>
function {{ $componentId }}() {
    return {
        imagePath: '{{ $currentImage ?? '' }}',
        imageUrl: '{{ $currentImage ? "/storage/" . $currentImage : '' }}',
        toast: '',
        toastType: 'success',

        browserOpen: false,
        browserLoading: false,
        browserCurrentPath: '',
        browserParent: '',
        browserFolders: [],
        browserFiles: [],
        browserSearch: '',
        lastBrowsedPath: null,
        defaultFolder: '{{ $defaultFolder }}',

        showToast(msg, type = 'success') {
            this.toast = msg;
            this.toastType = type;
            setTimeout(() => { this.toast = ''; }, 3000);
        },

        clear() {
            this.imagePath = '';
            this.imageUrl = '';
        },

        async openBrowser() {
            this.browserOpen = true;
            const startPath = this.lastBrowsedPath ?? this.defaultFolder;
            await this.browseDir(startPath);
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
            return files.sort((a, b) => b.modified - a.modified);
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
                this.lastBrowsedPath = data.path;
            }
            this.browserLoading = false;
        },

        selectFile(file) {
            this.imagePath = file.path;
            this.imageUrl = '/storage/' + file.path;
            this.browserOpen = false;
            this.showToast('Image selected.');
        },

        async uploadToCatalog(event) {
            const file = event.target.files[0];
            if (!file) return;
            event.target.value = '';

            const formData = new FormData();
            formData.append('file', file);
            formData.append('catalog_path', this.browserCurrentPath);

            const res = await fetch('{{ route("admin.products.images.upload-to-catalog") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData,
            });
            const data = await res.json();
            if (data.ok) {
                this.browserFiles.push({ name: data.name, path: data.path, url: data.url, size: data.size, modified: Date.now() / 1000 });
                this.showToast('Uploaded.');
            }
        },
    }
}
</script>
@endpush
