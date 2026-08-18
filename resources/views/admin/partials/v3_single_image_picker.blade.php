{{-- Single image picker, v3 dress. Same contract as the v2 partial it
     replaces: hidden input carries the storage path; the browser modal
     browses/uploads/imports through the products image endpoints.
     Usage: @include('admin.partials.v3_single_image_picker', ['fieldName' => 'image', 'currentImage' => $model?->image, 'root' => 'brands']) --}}
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

<div x-data="{{ $componentId }}()" class="v3-field">
    <span class="v3-field__label">
        {{ $label }}
        @if ($required)<span class="v3-req">Required</span>@endif
        @if ($hint)<x-admin3.help :text="$hint" />@endif
    </span>

    <input type="hidden" name="{{ $fieldName }}" :value="imagePath">

    <div x-show="imagePath" class="v3-gap-sm">
        {{-- Decorative: the file's name is the state; alt would repeat it. --}}
        <img :src="imageUrl" alt="" aria-hidden="true" class="v3-imgpreview">
    </div>

    <span class="v3-actrow">
        <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="openBrowser()">Browse</button>
        <template x-if="imagePath">
            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="clear(); touch()">Remove</button>
        </template>
    </span>

    {{-- Image manager modal --}}
    <div x-show="browserOpen" x-cloak class="v3-overlay"
         @keydown.escape.window="browserOpen = false" @click.self="browserOpen = false">
        <div class="v3-modal v3-modal--media" role="dialog" aria-modal="true" aria-label="Image manager">
            <div class="v3-between">
                <nav class="v3-crumbrow" aria-label="Folder path">
                    <button type="button" @click="browseDir('')" class="v3-link">Files</button>
                    <template x-for="(crumb, i) in browserBreadcrumbs" :key="i">
                        <span class="v3-crumbrow">
                            <span aria-hidden="true">/</span>
                            <button type="button" @click="browseDir(crumb.path)" class="v3-link" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <span class="v3-actrow">
                    <input type="search" x-model="browserSearch" aria-label="Search this folder" class="v3-control v3-narrowsearch" @keydown.enter.prevent>
                    <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" :aria-pressed="showUrlInput ? 'true' : 'false'"
                            @click="showUrlInput = !showUrlInput">URL</button>
                    <label class="v3-btn v3-btn--secondary v3-btn--sm">
                        Upload here
                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="uploadToCatalog($event); touch()" class="v3-sr">
                    </label>
                    <button type="button" @click="browserOpen = false" class="v3-row__x" aria-label="Close image manager"><x-admin3.icon name="x" /></button>
                </span>
            </div>

            <div x-show="showUrlInput" x-cloak class="v3-namerow v3-gap-sm">
                <input type="url" x-model="urlInput" aria-label="Image URL" class="v3-control"
                       @keydown.enter.prevent="importFromUrl()">
                <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" :disabled="importingUrl" @click="importFromUrl()">
                    <span x-text="importingUrl ? 'Importing…' : 'Import'"></span>
                </button>
            </div>

            <div class="v3-mediagrid-wrap v3-dropwrap"
                 @dragover.prevent="browserDragover = true"
                 @dragleave.prevent="browserDragover = false"
                 @drop.prevent="browserDragover = false; handleBrowserDrop($event)">

                <div x-show="browserDragover" class="v3-dropzone">Drop to upload here</div>

                <p x-show="browserUploading" class="v3-cell-quiet" role="status" aria-live="polite" x-text="browserUploadStatus"></p>

                <div x-show="browserLoading" class="v3-empty"><p class="v3-empty__title">Loading…</p></div>

                <div x-show="!browserLoading" class="v3-mediagrid">
                    <template x-if="browserCurrentPath !== ''">
                        <button type="button" @click="browseDir(browserParent)" aria-label="Go to parent folder" class="v3-mediatile">
                            <x-admin3.icon name="folder" class="v3-mediatile__folder" />
                            <span>..</span>
                        </button>
                    </template>
                    <template x-for="folder in filteredBrowserFolders" :key="folder.path">
                        <button type="button" @click="browseDir(folder.path)" :aria-label="'Open folder ' + folder.name" class="v3-mediatile">
                            <x-admin3.icon name="folder" class="v3-mediatile__folder" />
                            <span x-text="folder.name"></span>
                        </button>
                    </template>
                    <template x-for="file in filteredBrowserFiles" :key="file.path">
                        <button type="button" @click="selectFile(file); touch()" :aria-label="'Select ' + file.name" class="v3-mediatile"
                                :aria-pressed="imagePath === file.path ? 'true' : 'false'">
                            <span class="v3-mediatile__frame"><img :src="file.url" alt="" aria-hidden="true" loading="lazy"></span>
                            <span x-text="file.name"></span>
                        </button>
                    </template>
                </div>

                <div x-show="!browserLoading && browserFolders.length === 0 && browserFiles.length === 0" class="v3-empty">
                    <p class="v3-empty__title">Empty folder</p>
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

        showUrlInput: false,
        urlInput: '',
        importingUrl: false,

        browserDragover: false,
        browserUploading: false,
        browserUploadStatus: '',

        showToast(msg, type = 'success') {
            Alpine.store('toasts').push(msg, type === 'error' ? 'danger' : 'success');
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
            this.browserUploadStatus = 'Uploading ' + file.name + '…';

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
