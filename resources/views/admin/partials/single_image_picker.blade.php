{{-- Single Image Picker --}}
{{-- Usage: @include('admin.partials.single_image_picker', ['fieldName' => 'image', 'currentImage' => $model?->image, 'defaultFolder' => 'Banners']) --}}

@php
    $fieldName = $fieldName ?? 'image';
    $currentImage = $currentImage ?? null;
    $label = $label ?? 'Image';
    $hint = $hint ?? '';
    $required = $required ?? false;
    $defaultFolder = $defaultFolder ?? '';
    $root = $root ?? '';
    $componentId = 'sip_' . str_replace(['[', ']', '.'], '_', $fieldName);
@endphp

{{-- The picker's value lives in a hidden input, so there is no control a
     <label for> could point at and x-admin.field cannot wrap it. It wears
     the same v-field primitives that component wears instead. --}}
<div x-data="{{ $componentId }}()" class="v-field">
    <span class="v-field__label v-label">
        {{ $label }}
        @if ($required)<span class="v-req">Required</span>@endif
    </span>

    <input type="hidden" name="{{ $fieldName }}" :value="imagePath">

    {{-- Preview --}}
    <div x-show="imagePath" class="mb-2">
        <div class="relative inline-block">
            {{-- Decorative: the picker's whole job is to show which file is
                 chosen, and the file's name is already the button beside it,
                 so a repeated alt would only be noise to a screen reader. --}}
            <img :src="imageUrl" alt="" aria-hidden="true" class="h-32 rounded-v-md object-cover border border-v-strong">
            <button type="button" @click="clear()" aria-label="Remove {{ $label }}"
                class="absolute -top-2 -right-2 w-6 h-6 bg-v-surface border border-v-danger text-v-danger rounded-v-pill flex items-center justify-center hover:bg-[var(--bl-bad-wash)] transition-colors shadow-v-lift">
                <x-admin.icon name="x" class="w-3.5 h-3.5" />
            </button>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-2">
        <x-admin.button variant="primary" @click="openBrowser()">
            <x-admin.icon name="media" class="w-4 h-4" />
            Browse
        </x-admin.button>
    </div>

    @if ($hint)
        <div class="v-field__hint">{{ $hint }}</div>
    @endif

    {{-- Toast --}}
    <div x-show="toast" x-transition.opacity
         class="fixed bottom-6 right-6 z-[10001] flex items-start gap-2.5 px-3.5 py-2.5 bg-v-surface border border-v-border rounded-v-md shadow-v-lift-2 text-[13px] text-v-text"
         role="status" aria-live="polite">
        <x-admin.icon name="check" x-show="toastType !== 'error'" class="w-4 h-4 text-v-accent mt-0.5 shrink-0" />
        <x-admin.icon name="warning" x-show="toastType === 'error'" class="w-4 h-4 text-v-danger mt-0.5 shrink-0" />
        <span x-text="toast"></span>
    </div>

    {{-- Image Manager Modal --}}
    <div x-show="browserOpen" x-transition.opacity.duration.200ms
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/45 backdrop-blur-[3px]"
         @keydown.escape.window="browserOpen = false">

        <div class="v-modal w-full max-w-5xl max-h-[85vh] flex flex-col bg-v-surface rounded-v-lg border border-v-border overflow-hidden"
             role="dialog" aria-modal="true" aria-label="Image manager"
             @click.outside="browserOpen = false">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-v-border bg-v-sunken">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex items-center gap-2">
                        <x-admin.icon name="media" class="w-5 h-5 text-v-accent shrink-0" />
                        <h3 class="text-[15px] font-semibold text-v-text">Image Manager</h3>
                    </div>
                    <span class="text-v-border" aria-hidden="true">|</span>
                    <nav aria-label="Folder path" class="flex items-center gap-0.5 text-[13px] min-w-0 v-scroll">
                        <button type="button" @click="browseDir('')" class="text-v-accent hover:text-v-accent-hover transition-colors shrink-0 font-medium flex items-center gap-1">
                            <x-admin.icon name="dashboard" class="w-4 h-4" />
                            Files
                        </button>
                        <template x-for="(crumb, i) in browserBreadcrumbs" :key="i">
                            <span class="flex items-center gap-0.5 shrink-0">
                                <span class="text-v-muted" aria-hidden="true">/</span>
                                <button type="button" @click="browseDir(crumb.path)" class="text-v-accent hover:text-v-accent-hover transition-colors font-medium" x-text="crumb.name"></button>
                            </span>
                        </template>
                    </nav>
                </div>
                <button type="button" @click="browserOpen = false" aria-label="Close image manager"
                    class="text-v-muted hover:text-v-text transition-colors p-1 rounded-v-md hover:bg-v-hover shrink-0 ml-3">
                    <x-admin.icon name="x" class="w-5 h-5" />
                </button>
            </div>

            {{-- Toolbar: Search + URL Import + Upload Here --}}
            <div class="flex items-center gap-2 px-5 py-3 border-b border-v-border bg-v-surface">
                <div class="relative">
                    <x-admin.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-v-muted" />
                    <input type="text" x-model="browserSearch" placeholder="Search..." aria-label="Search this folder"
                        @keydown.enter.prevent
                        class="v-control w-44 h-[var(--control-h)] pl-9 pr-3 text-[13px] bg-v-surface text-v-text border border-v-strong rounded-v-md placeholder:text-v-muted">
                </div>
                {{-- URL Import toggle --}}
                <x-admin.button variant="secondary" size="md" aria-controls="{{ $componentId }}-url"
                    x-bind:aria-expanded="showUrlInput ? 'true' : 'false'"
                    x-bind:aria-pressed="showUrlInput ? 'true' : 'false'"
                    x-bind:class="showUrlInput && '!bg-v-accent-weak !border-v-accent !text-v-accent'"
                    @click="showUrlInput = !showUrlInput">
                    <x-admin.icon name="external" class="w-3.5 h-3.5" />
                    URL
                </x-admin.button>
                <div class="ml-auto">
                    <label class="v-btn v-btn--primary inline-flex items-center justify-center gap-1.5 h-[var(--control-h)] px-3.5 text-[13px] bg-v-accent border border-v-accent text-v-accent-text rounded-v-md shadow-v-lift hover:bg-v-accent-hover cursor-pointer transition-colors">
                        <x-admin.icon name="upload" class="w-3.5 h-3.5" />
                        Upload Here
                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="uploadToCatalog($event)" class="sr-only">
                    </label>
                </div>
            </div>

            {{-- URL Import Bar (collapsible) --}}
            <div id="{{ $componentId }}-url" x-show="showUrlInput" x-transition class="px-5 py-2.5 border-b border-v-border bg-v-sunken">
                <div class="flex gap-2">
                    <input type="text" x-model="urlInput" placeholder="https://example.com/image.jpg" aria-label="Image URL"
                        class="v-control flex-1 h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-strong rounded-v-md placeholder:text-v-muted"
                        @keydown.enter.prevent="importFromUrl()">
                    <x-admin.button variant="primary" x-bind:disabled="importingUrl" @click="importFromUrl()">
                        <span x-show="!importingUrl">Import</span>
                        <span x-show="importingUrl">Importing...</span>
                    </x-admin.button>
                </div>
            </div>

            {{-- Content: drag & drop zone wrapping file grid --}}
            <div class="flex-1 overflow-y-auto p-5 bg-v-canvas relative"
                 @dragover.prevent="browserDragover = true"
                 @dragleave.prevent="browserDragover = false"
                 @drop.prevent="browserDragover = false; handleBrowserDrop($event)">

                {{-- Drag overlay --}}
                <div x-show="browserDragover" class="absolute inset-0 z-10 flex items-center justify-center bg-v-accent-weak border-2 border-dashed border-v-mark rounded-v-lg m-2 pointer-events-none">
                    <div class="text-center">
                        <x-admin.icon name="upload" class="w-10 h-10 mx-auto mb-2 text-v-accent" />
                        <p class="text-[13px] font-medium text-v-accent">Drop to upload here</p>
                    </div>
                </div>

                {{-- Upload progress --}}
                <div x-show="browserUploading" class="mb-3 flex items-center gap-2" role="status" aria-live="polite">
                    <svg class="w-4 h-4 animate-spin text-v-accent shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-xs text-v-secondary" x-text="browserUploadStatus"></span>
                </div>

                <div x-show="browserLoading" class="flex items-center justify-center py-16">
                    <svg class="w-6 h-6 animate-spin text-v-accent" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>

                <div x-show="!browserLoading" class="grid gap-2 grid-cols-[repeat(auto-fill,minmax(120px,1fr))]">
                    <template x-if="browserCurrentPath !== ''">
                        <button type="button" @click="browseDir(browserParent)" aria-label="Go to parent folder" class="flex flex-col items-center p-3 rounded-v-md hover:bg-v-hover transition-colors">
                            <x-admin.icon name="categories" class="w-12 h-12 text-v-mark" />
                            <span class="text-xs text-v-secondary mt-1.5 font-medium">..</span>
                        </button>
                    </template>

                    <template x-for="folder in filteredBrowserFolders" :key="folder.path">
                        <button type="button" @click="browseDir(folder.path)" x-bind:aria-label="'Open folder ' + folder.name" class="flex flex-col items-center p-3 rounded-v-md hover:bg-v-hover transition-colors">
                            <x-admin.icon name="categories" class="w-12 h-12 text-v-mark" />
                            <span class="text-xs text-v-secondary mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                        </button>
                    </template>

                    <template x-for="file in filteredBrowserFiles" :key="file.path">
                        <button type="button" @click="selectFile(file)" x-bind:aria-label="'Select ' + file.name" class="flex flex-col items-center p-2 rounded-v-md hover:bg-v-hover transition-colors w-full">
                            <div class="w-full aspect-square rounded-v-md overflow-hidden bg-v-sunken border border-v-border">
                                <img :src="file.url" alt="" aria-hidden="true" class="w-full h-full object-cover" loading="lazy">
                            </div>
                            <span class="text-[11px] text-v-secondary mt-1.5 truncate w-full text-center leading-tight" x-text="file.name"></span>
                        </button>
                    </template>
                </div>

                <div x-show="!browserLoading && browserFolders.length === 0 && browserFiles.length === 0">
                    <x-admin.empty-state title="Empty folder" />
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
        imageUrl: '{{ $currentImage ? storage_url($currentImage) : '' }}',
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
        root: '{{ $root }}',

        // URL import
        showUrlInput: false,
        urlInput: '',
        importingUrl: false,

        // Drag & drop
        browserDragover: false,
        browserUploading: false,
        browserUploadStatus: '',

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

            let startPath = '';

            if (this.lastBrowsedPath !== null) {
                // User already browsed somewhere this session, return there
                startPath = this.lastBrowsedPath;
            } else if (this.imagePath) {
                // First open with existing image: navigate to its folder
                const lastSlash = this.imagePath.lastIndexOf('/');
                if (lastSlash > 0) {
                    startPath = this.imagePath.substring(0, lastSlash);
                }
            } else {
                // New entity, no image yet: open at the entity root (brands/,
                // categories/, ...); defaultFolder kept only as a legacy fallback.
                startPath = this.root || this.defaultFolder;
            }

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

        get filteredBrowserFolders() {
            if (!this.browserSearch) return this.browserFolders;
            const q = this.browserSearch.toLowerCase();
            return this.browserFolders.filter(f => f.name.toLowerCase().includes(q));
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
            this.browserSearch = ''; // reset the filter on navigation: a stale search hides files in the new folder
            const params = new URLSearchParams();
            if (path) params.set('path', path);
            if (this.root) params.set('root', this.root);

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
            this.imageUrl = storageUrl(file.path);
            this.browserOpen = false;
            this.showToast('Image selected.');
        },

        async uploadToCatalog(event) {
            const file = event.target.files[0];
            if (!file) return;
            event.target.value = '';
            await this._uploadFile(file);
        },

        async handleBrowserDrop(event) {
            const files = event.dataTransfer?.files;
            if (!files || files.length === 0) return;
            for (const file of files) {
                if (file.type.startsWith('image/')) {
                    await this._uploadFile(file);
                }
            }
        },

        async _uploadFile(file) {
            this.browserUploading = true;
            this.browserUploadStatus = 'Uploading ' + file.name + '...';

            const formData = new FormData();
            formData.append('file', file);
            formData.append('catalog_path', this.browserCurrentPath || this.root);

            try {
                const res = await fetch('{{ route("admin.products.images.upload-to-catalog") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await res.json();
                if (data.ok) {
                    this.browserFiles.push({ name: data.name, path: data.path, url: data.url, size: data.size, modified: Date.now() / 1000 });
                    this.showToast('Uploaded ' + data.name);
                } else {
                    this.showToast(data.message || 'Upload failed.', 'error');
                }
            } catch (e) {
                this.showToast('Upload failed.', 'error');
            }
            this.browserUploading = false;
            this.browserUploadStatus = '';
        },

        async importFromUrl() {
            if (!this.urlInput.trim()) return;
            this.importingUrl = true;

            try {
                const res = await fetch('{{ route("admin.products.images.import-url") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        url: this.urlInput.trim(),
                        store_id: 1,
                        catalog_path: this.browserCurrentPath || this.root,
                    }),
                });
                const data = await res.json();
                if (data.ok) {
                    this.browserFiles.push({ name: data.name || data.path.split('/').pop(), path: data.path, url: storageUrl(data.path), size: 0, modified: Date.now() / 1000 });
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
    }
}
</script>
@endpush
