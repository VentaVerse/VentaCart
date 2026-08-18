{{-- Image manager, v dress. Same contract as the v2 partial: the
     hidden images_json input carries the ordered list; the modal
     browses, multi-selects, uploads, imports, and deletes through the
     products image endpoints. Every mutation calls _markDirty() so the
     save doctrine sees edits that never fire an input event. --}}
<div x-data="imageManager()" x-init="init()">

    <input type="hidden" name="images_json" :value="JSON.stringify(items)">

    <div class="v-filterbar v-gap-sm">
        <button type="button" class="v-btn v-btn--secondary" @click="openBrowser()">Image manager</button>
        <span class="v-filterbar__grow"></span>
        <span class="v-cell-quiet v-mono" x-show="items.length > 0" x-text="items.length + ' image' + (items.length !== 1 ? 's' : '')"></span>
    </div>

    <div x-show="items.length === 0" class="v-empty">
        <p class="v-empty__title">No images yet</p>
    </div>

    {{-- Rendered by JS (renderGrid) so the custom drag reorder owns the DOM. --}}
    <div x-show="items.length > 0" x-ref="sortableGrid" class="v-mediagrid"></div>

    {{-- Image manager modal --}}
    <div x-show="browserOpen" x-cloak class="v-overlay"
         @keydown.escape.window="pendingDelete ? pendingDelete = null : browserOpen = false"
         @click.self="browserOpen = false">
        <div class="v-modal v-modal--media" role="dialog" aria-modal="true" aria-label="Image manager">
            <div class="v-between">
                <nav class="v-crumbrow" aria-label="Folder path">
                    <button type="button" @click="browseDir('')" class="v-link">Files</button>
                    <template x-for="(crumb, i) in browserBreadcrumbs" :key="i">
                        <span class="v-crumbrow">
                            <span aria-hidden="true">/</span>
                            <button type="button" @click="browseDir(crumb.path)" class="v-link" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <span class="v-actrow">
                    <span class="v-cell-quiet v-mono" x-show="browserFiles.length > 0"
                          x-text="filteredBrowserFiles.length + ' file' + (filteredBrowserFiles.length !== 1 ? 's' : '')"></span>
                    <input type="search" x-model="browserSearch" aria-label="Search this folder" class="v-control v-narrowsearch" @keydown.enter.prevent>
                    <select x-model="browserSort" aria-label="Sort files" class="v-control v-control--sm">
                        <option value="name">Name</option>
                        <option value="date">Date</option>
                    </select>
                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" :aria-pressed="showUrlInput ? 'true' : 'false'"
                            aria-controls="im-url-bar" :aria-expanded="showUrlInput ? 'true' : 'false'"
                            @click="showUrlInput = !showUrlInput">URL</button>
                    <label class="v-btn v-btn--secondary v-btn--sm">
                        Upload here
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="uploadToCatalog($event)" class="v-sr">
                    </label>
                    <button type="button" @click="browserOpen = false" class="v-row__x" aria-label="Close image manager"><x-admin.icon name="x" /></button>
                </span>
            </div>

            <div id="im-url-bar" x-show="showUrlInput" x-cloak class="v-namerow v-gap-sm">
                <input type="url" x-model="urlInput" aria-label="Image URL" class="v-control" @keydown.enter.prevent="importFromUrl()">
                <button type="button" class="v-btn v-btn--secondary v-btn--sm" :disabled="importingUrl" @click="importFromUrl()">
                    <span x-text="importingUrl ? 'Importing…' : 'Import'"></span>
                </button>
            </div>

            <div class="v-mediagrid-wrap v-dropwrap"
                 @dragover.prevent="browserDragover = true"
                 @dragleave.prevent="browserDragover = false"
                 @drop.prevent="browserDragover = false; handleBrowserDrop($event)">

                <div x-show="browserDragover" class="v-dropzone">Drop to upload here</div>

                <p x-show="browserUploading" class="v-cell-quiet" role="status" aria-live="polite" x-text="browserUploadStatus"></p>

                <div x-show="browserLoading" class="v-empty"><p class="v-empty__title">Loading…</p></div>

                <div x-show="!browserLoading" class="v-mediagrid">
                    <template x-if="browserCurrentPath !== ''">
                        <button type="button" @click="browseDir(browserParent)" aria-label="Go to parent folder" class="v-mediatile">
                            <x-admin.icon name="folder" class="v-mediatile__folder" />
                            <span>..</span>
                        </button>
                    </template>

                    <template x-for="folder in filteredBrowserFolders" :key="folder.path">
                        <button type="button" @click="browseDir(folder.path)" :aria-label="'Open folder ' + folder.name" class="v-mediatile">
                            <x-admin.icon name="folder" class="v-mediatile__folder" />
                            <span x-text="folder.name"></span>
                        </button>
                    </template>

                    {{-- Three states, and the outline is never the only thing
                         saying which: an added file also carries the brass
                         mark, a picked one the green mark. --}}
                    <template x-for="file in filteredBrowserFiles" :key="file.path">
                        <span class="v-tilewrap">
                            <button type="button" @click="toggleFileSelection(file)"
                                    :aria-label="isAlreadyAdded(file.path)
                                        ? file.name + ' (already added)'
                                        : (isSelected(file.path) ? 'Deselect ' + file.name : 'Select ' + file.name)"
                                    :aria-pressed="isSelected(file.path) ? 'true' : 'false'"
                                    class="v-mediatile"
                                    :class="isAlreadyAdded(file.path) ? 'is-added' : (isSelected(file.path) ? 'is-picked' : '')">
                                <span class="v-mediatile__frame"><img :src="file.url" alt="" aria-hidden="true" loading="lazy"></span>
                                <span x-text="file.name"></span>
                                <span x-show="isAlreadyAdded(file.path)" class="v-tilemark"><x-admin.icon name="check" /></span>
                                <span x-show="isSelected(file.path)" class="v-tilemark v-tilemark--picked"><x-admin.icon name="check" /></span>
                            </button>
                            <span class="v-tileacts">
                                <button type="button" class="v-row__x" @click="askDeleteFile(file)"
                                        :aria-label="'Delete ' + file.name + ' from the server'">
                                    <x-admin.icon name="trash" />
                                </button>
                            </span>
                        </span>
                    </template>
                </div>

                <div x-show="!browserLoading && browserFolders.length === 0 && browserFiles.length === 0" class="v-empty">
                    <p class="v-empty__title">Empty folder</p>
                </div>
            </div>

            {{-- Selection footer --}}
            <div x-show="selectedFiles.length > 0" x-cloak class="v-between v-gap-sm">
                <span class="v-actrow">
                    <span><strong x-text="selectedFiles.length"></strong> <span x-text="selectedFiles.length === 1 ? 'image' : 'images'"></span> selected</span>
                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="selectedFiles = []">Clear</button>
                </span>
                <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="addSelectedFiles()">
                    <span x-text="'Add ' + selectedFiles.length + (selectedFiles.length === 1 ? ' image' : ' images')"></span>
                </button>
            </div>

            {{-- Deleting a catalogue file is destructive and irreversible;
                 nested inside the browser so its click-outside is not read
                 as a click off the browser. --}}
            <template x-if="pendingDelete">
                <div class="v-overlay" @click.self="pendingDelete = null">
                    <div class="v-modal" role="dialog" aria-modal="true" aria-label="Delete file">
                        <h2 class="v-modal__title">Delete file</h2>
                        <p class="v-modal__body" x-text="'Delete ' + pendingDelete.name + ' from the server? Any product still using it will lose the image.'"></p>
                        <div class="v-modal__acts">
                            <button type="button" class="v-btn v-btn--ghost" @click="pendingDelete = null">Cancel</button>
                            <button type="button" class="v-btn v-btn--danger-fill" @click="confirmPendingDelete()">Delete file</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function imageManager() {
    return {
        items: @json($existingImages ?? []),
        pimToken: '{{ $pimToken ?? Str::uuid() }}',
        root: 'products',
        defaultFolder: '{{ $defaultFolder ?? 'products' }}',
        showUrlInput: false,
        urlInput: '',
        importingUrl: false,

        // Browser
        browserOpen: false,
        browserLoading: false,
        browserCurrentPath: '',
        browserParent: '',
        browserFolders: [],
        browserFiles: [],
        browserSearch: '',
        browserSort: 'date',
        lastBrowsedPath: null,

        // Selection state for browser
        selectedFiles: [],

        // The file awaiting confirmation in the delete dialog
        pendingDelete: null,

        // Drag & drop in modal
        browserDragover: false,
        browserUploading: false,
        browserUploadStatus: '',

        _dragFromIndex: null,
        _dragEl: null,
        _placeholder: null,

        init() {
            this.$nextTick(() => this.renderGrid());
        },

        // The hidden images_json input tracks `items` through :value, which
        // fires no input event; every mutation routes through here so the
        // doctrine save bar sees it.
        _markDirty() {
            this.$el.closest('form')?.dispatchEvent(new Event('input'));
        },

        _createPlaceholder() {
            const ph = document.createElement('div');
            ph.className = 'v-imgcard__ph';
            ph.dataset.placeholder = 'true';
            return ph;
        },

        renderGrid() {
            const grid = this.$refs.sortableGrid;
            if (!grid) return;
            grid.textContent = '';

            this.items.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'v-imgcard';
                card.draggable = true;
                card.dataset.path = item.path;
                card.dataset.index = index;

                const imgWrap = document.createElement('div');
                imgWrap.className = 'v-imgcard__art';
                const img = document.createElement('img');
                img.src = item.url;
                img.alt = 'Product image ' + (index + 1) + (index === 0 ? ' (main)' : '');
                img.draggable = false;
                imgWrap.appendChild(img);
                card.appendChild(imgWrap);

                const badge = document.createElement('span');
                badge.className = 'v-imgcard__badge' + (index === 0 ? ' v-imgcard__badge--main' : '');
                badge.textContent = index === 0 ? 'Main' : '#' + (index + 1);
                card.appendChild(badge);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'v-row__x v-imgcard__x';
                removeBtn.setAttribute('aria-label', 'Remove image ' + (index + 1) + ' from this product');
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '1.75');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('aria-hidden', 'true');
                const svgPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                svgPath.setAttribute('stroke-linecap', 'round');
                svgPath.setAttribute('stroke-linejoin', 'round');
                svgPath.setAttribute('d', 'M6 18L18 6M6 6l12 12');
                svg.appendChild(svgPath);
                removeBtn.appendChild(svg);
                removeBtn.addEventListener('click', () => {
                    const idx = this.items.findIndex(i => i.path === item.path);
                    if (idx !== -1) this.removeItem(idx);
                });
                card.appendChild(removeBtn);

                card.addEventListener('dragstart', (e) => {
                    this._dragFromIndex = parseInt(card.dataset.index);
                    this._dragEl = card;
                    this._placeholder = this._createPlaceholder();
                    e.dataTransfer.effectAllowed = 'move';
                    // Delay so the browser captures the drag image first
                    requestAnimationFrame(() => {
                        grid.insertBefore(this._placeholder, card);
                        card.style.display = 'none';
                    });
                });

                card.addEventListener('dragend', () => {
                    if (this._placeholder && this._placeholder.parentNode) {
                        this._placeholder.remove();
                    }
                    card.style.display = '';
                    this._dragEl = null;
                    this._dragFromIndex = null;
                    this._placeholder = null;
                });

                grid.appendChild(card);
            });

            // Grid-level dragover to position placeholder
            grid.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (!this._placeholder || !this._dragEl) return;

                const cards = [...grid.querySelectorAll('[data-path]')].filter(c => c !== this._dragEl);
                if (!cards.length) return;

                let inserted = false;
                for (const c of cards) {
                    const rect = c.getBoundingClientRect();
                    if (e.clientY < rect.bottom && e.clientX < rect.right) {
                        if (this._placeholder.nextSibling !== c) {
                            grid.insertBefore(this._placeholder, c);
                        }
                        inserted = true;
                        break;
                    }
                }
                if (!inserted) {
                    grid.appendChild(this._placeholder);
                }
            });

            grid.addEventListener('drop', (e) => {
                e.preventDefault();
                if (this._dragFromIndex === null || !this._placeholder) return;

                const allChildren = [...grid.children].filter(c => c.dataset.path || c.dataset.placeholder);
                let toIndex = 0;
                for (let i = 0; i < allChildren.length; i++) {
                    if (allChildren[i] === this._placeholder) break;
                    if (allChildren[i].dataset.path && allChildren[i] !== this._dragEl) toIndex++;
                }
                if (this._dragFromIndex < toIndex) toIndex--;

                if (toIndex < 0) toIndex = 0;
                if (toIndex >= this.items.length) toIndex = this.items.length - 1;

                if (this._dragFromIndex !== toIndex) {
                    const moved = this.items.splice(this._dragFromIndex, 1)[0];
                    this.items.splice(toIndex, 0, moved);
                    this._markDirty();
                }

                if (this._placeholder && this._placeholder.parentNode) this._placeholder.remove();
                this._dragFromIndex = null;
                this._dragEl = null;
                this._placeholder = null;
                this.renderGrid();
            });
        },

        getStoreId() {
            const el = document.getElementById('store_id');
            return el ? el.value : '1';
        },

        showToast(msg, type = 'success') {
            Alpine.store('toasts').push(msg, type === 'error' ? 'danger' : 'success');
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this._markDirty();
            this.$nextTick(() => this.renderGrid());
        },

        // --- Image manager modal ---
        async openBrowser() {
            this.browserOpen = true;
            this.selectedFiles = [];

            let startPath = '';

            if (this.lastBrowsedPath !== null) {
                startPath = this.lastBrowsedPath;
            } else if (this.items.length > 0 && this.items[0].path) {
                const mainPath = this.items[0].path;
                const lastSlash = mainPath.lastIndexOf('/');
                if (lastSlash > 0) {
                    startPath = mainPath.substring(0, lastSlash);
                }
            } else {
                // New product, no images yet: open at products/{brand} so uploads
                // land in-context (never the bare storage root).
                startPath = this.defaultFolder;
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
            files.sort((a, b) => {
                if (this.browserSort === 'date') return b.modified - a.modified;
                return a.name.localeCompare(b.name);
            });
            return files;
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

        isAlreadyAdded(path) {
            return this.items.some(i => i.path === path);
        },

        isSelected(path) {
            return this.selectedFiles.some(f => f.path === path);
        },

        toggleFileSelection(file) {
            if (this.isAlreadyAdded(file.path)) return;

            const idx = this.selectedFiles.findIndex(f => f.path === file.path);
            if (idx !== -1) {
                this.selectedFiles.splice(idx, 1);
            } else {
                this.selectedFiles.push({ path: file.path, url: file.url });
            }
        },

        addSelectedFiles() {
            let added = 0;
            for (const file of this.selectedFiles) {
                if (!this.isAlreadyAdded(file.path)) {
                    this.items.push({ path: file.path, url: file.url });
                    added++;
                }
            }
            this.selectedFiles = [];
            this.$nextTick(() => this.renderGrid());
            if (added > 0) {
                this._markDirty();
                this.showToast(added + ' image' + (added !== 1 ? 's' : '') + ' added.');
            }
            this.browserOpen = false;
        },

        askDeleteFile(file) {
            this.pendingDelete = file;
        },

        async confirmPendingDelete() {
            const file = this.pendingDelete;
            this.pendingDelete = null;
            if (file) await this.deleteServerFile(file);
        },

        async deleteServerFile(file) {
            const res = await fetch('{{ route("admin.products.images.delete") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ path: file.path }),
            });
            const data = await res.json();

            if (data.ok) {
                this.browserFiles = this.browserFiles.filter(f => f.path !== file.path);
                const had = this.items.length;
                this.items = this.items.filter(i => i.path !== file.path);
                if (this.items.length !== had) this._markDirty();
                this.$nextTick(() => this.renderGrid());
                this.showToast('File deleted.');
            }
        },

        async uploadToCatalog(event) {
            const files = event.target.files;
            if (!files.length) return;
            for (const file of files) {
                await this._uploadFile(file);
            }
            event.target.value = '';
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
            formData.append('catalog_path', this.browserCurrentPath || this.defaultFolder);

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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ url: this.urlInput.trim(), store_id: this.getStoreId(), catalog_path: this.browserCurrentPath || this.defaultFolder }),
                });
                const data = await res.json();
                if (data.ok) {
                    this.browserFiles.push({ name: data.path.split('/').pop(), path: data.path, url: storageUrl(data.path), size: 0, modified: Date.now() / 1000 });
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
