{{-- Image Manager Partial --}}
{{-- Usage: @include('admin.catalog.products.partials.image_manager', ['existingImages' => $existingImages ?? []]) --}}

<div x-data="imageManager()" x-init="init()" class="p-6">

    {{-- Hidden JSON field --}}
    <input type="hidden" name="images_json" :value="JSON.stringify(items)">

    {{-- Toolbar --}}
    <div class="flex items-center gap-2 mb-5">
        <x-admin.button variant="primary" @click="openBrowser()">
            <x-admin.icon name="media" class="w-4 h-4" />
            Image Manager
        </x-admin.button>
        <span class="ml-auto text-xs text-v-muted" x-show="items.length > 0" x-text="items.length + ' image' + (items.length !== 1 ? 's' : '')"></span>
    </div>

    {{-- Thumbnail Grid --}}
    <div class="rounded-v-lg border-2 border-dashed border-v-strong bg-v-sunken p-4 min-h-[10rem]">
        {{-- Empty State --}}
        <div x-show="items.length === 0">
            <x-admin.empty-state title="No images yet" />
        </div>

        {{-- Grid (no x-for, rendered via JS to avoid SortableJS conflict) --}}
        <div x-show="items.length > 0" x-ref="sortableGrid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3"></div>
    </div>

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
         @keydown.escape.window="pendingDelete ? pendingDelete = null : browserOpen = false">

        <div class="v-modal w-full max-w-5xl max-h-[85vh] flex flex-col bg-v-surface rounded-v-lg border border-v-border overflow-hidden"
             role="dialog" aria-modal="true" aria-label="Image manager"
             @click.outside="browserOpen = false">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-v-border bg-v-sunken">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex items-center gap-2">
                            <x-admin.icon name="media" class="w-5 h-5 text-v-accent shrink-0" />
                            <h3 class="text-[15px] font-semibold text-v-text">Image Manager</h3>
                        </div>
                        <span class="text-v-border" aria-hidden="true">|</span>
                        {{-- Breadcrumb --}}
                        <nav aria-label="Folder path" class="flex items-center gap-0.5 text-[13px] min-w-0 v-scroll">
                            <button type="button" @click="browseDir('')"
                                class="text-v-accent hover:text-v-accent-hover transition-colors shrink-0 font-medium flex items-center gap-1">
                                <x-admin.icon name="dashboard" class="w-4 h-4" />
                                Files
                            </button>
                            <template x-for="(crumb, i) in browserBreadcrumbs" :key="i">
                                <span class="flex items-center gap-0.5 shrink-0">
                                    <span class="text-v-muted" aria-hidden="true">/</span>
                                    <button type="button" @click="browseDir(crumb.path)"
                                        class="text-v-accent hover:text-v-accent-hover transition-colors font-medium"
                                        x-text="crumb.name"></button>
                                </span>
                            </template>
                        </nav>
                    </div>
                    <button type="button" @click="browserOpen = false" aria-label="Close image manager"
                        class="text-v-muted hover:text-v-text transition-colors p-1 rounded-v-md hover:bg-v-hover shrink-0 ml-3">
                        <x-admin.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Modal Toolbar: Search + URL + Sort + Upload Here --}}
                <div class="flex items-center gap-2 px-5 py-3 border-b border-v-border bg-v-surface">
                    <div class="relative">
                        <x-admin.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-v-muted" />
                        <input type="text" x-model="browserSearch" placeholder="Search..." aria-label="Search this folder"
                            @keydown.enter.prevent
                            class="v-control w-44 h-[var(--control-h)] pl-9 pr-3 text-[13px] bg-v-surface text-v-text border border-v-strong rounded-v-md placeholder:text-v-muted">
                    </div>
                    <x-admin.button variant="secondary" aria-controls="im-url-bar"
                        x-bind:aria-expanded="showUrlInput ? 'true' : 'false'"
                        x-bind:aria-pressed="showUrlInput ? 'true' : 'false'"
                        x-bind:class="showUrlInput && '!bg-v-accent-weak !border-v-accent !text-v-accent'"
                        @click="showUrlInput = !showUrlInput">
                        <x-admin.icon name="external" class="w-3.5 h-3.5" />
                        URL
                    </x-admin.button>
                    <select x-model="browserSort" aria-label="Sort files"
                        class="v-control h-[var(--control-h)] px-2.5 py-0 text-[13px] leading-[normal] bg-v-surface text-v-text border border-v-strong rounded-v-md">
                        <option value="name">Name</option>
                        <option value="date">Date</option>
                    </select>
                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-xs text-v-muted" x-show="browserFiles.length > 0"
                              x-text="filteredBrowserFiles.length + ' file' + (filteredBrowserFiles.length !== 1 ? 's' : '')"></span>
                        <label class="v-btn v-btn--primary inline-flex items-center justify-center gap-1.5 h-[var(--control-h)] px-3.5 text-[13px] bg-v-accent border border-v-accent text-v-accent-text rounded-v-md shadow-v-lift hover:bg-v-accent-hover cursor-pointer transition-colors">
                            <x-admin.icon name="upload" class="w-3.5 h-3.5" />
                            Upload Here
                            <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="uploadToCatalog($event)" class="sr-only">
                        </label>
                    </div>
                </div>

                {{-- URL Import Bar --}}
                <div id="im-url-bar" x-show="showUrlInput" x-transition class="px-5 py-2.5 border-b border-v-border bg-v-sunken">
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

                {{-- Modal Content with drag & drop --}}
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
                        <x-admin.icon name="spinner" class="w-4 h-4 animate-spin text-v-accent shrink-0" />
                        <span class="text-xs text-v-secondary" x-text="browserUploadStatus"></span>
                    </div>

                    <div x-show="browserLoading" class="flex items-center justify-center py-16">
                        <x-admin.icon name="spinner" class="w-6 h-6 animate-spin text-v-accent" />
                    </div>

                    <div x-show="!browserLoading" class="grid gap-2 grid-cols-[repeat(auto-fill,minmax(120px,1fr))]">
                        {{-- Parent Folder --}}
                        <template x-if="browserCurrentPath !== ''">
                            <button type="button" @click="browseDir(browserParent)" aria-label="Go to parent folder"
                                class="flex flex-col items-center p-3 rounded-v-md hover:bg-v-hover transition-colors">
                                <x-admin.icon name="folder" class="w-12 h-12 text-v-mark" />
                                <span class="text-xs text-v-secondary mt-1.5 font-medium">..</span>
                            </button>
                        </template>

                        {{-- Folders --}}
                        <template x-for="folder in filteredBrowserFolders" :key="folder.path">
                            <button type="button" @click="browseDir(folder.path)" x-bind:aria-label="'Open folder ' + folder.name"
                                class="flex flex-col items-center p-3 rounded-v-md hover:bg-v-hover transition-colors">
                                <x-admin.icon name="folder" class="w-12 h-12 text-v-mark" />
                                <span class="text-xs text-v-secondary mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                            </button>
                        </template>

                        {{-- Files --}}
                        <template x-for="file in filteredBrowserFiles" :key="file.path">
                            <div class="relative group">
                                <button type="button" @click="toggleFileSelection(file)"
                                    x-bind:aria-label="isAlreadyAdded(file.path)
                                        ? file.name + ' (already added)'
                                        : (isSelected(file.path) ? 'Deselect ' + file.name : 'Select ' + file.name)"
                                    x-bind:aria-pressed="isSelected(file.path) ? 'true' : 'false'"
                                    class="flex flex-col items-center p-2 rounded-v-md transition-colors w-full"
                                    x-bind:class="isAlreadyAdded(file.path)
                                        ? 'ring-2 ring-v-mark bg-v-accent-weak opacity-60 cursor-default'
                                        : isSelected(file.path)
                                            ? 'ring-2 ring-[var(--bl-good)] bg-[var(--bl-good-wash)]'
                                            : 'hover:bg-v-hover'">
                                    <div class="w-full aspect-square rounded-v-md overflow-hidden bg-v-sunken border border-v-border">
                                        <img :src="file.url" alt="" aria-hidden="true" class="w-full h-full object-cover" loading="lazy">
                                    </div>
                                    <span class="text-[11px] text-v-secondary mt-1.5 truncate w-full text-center leading-tight" x-text="file.name"></span>
                                </button>
                                {{-- Already-added indicator --}}
                                <div x-show="isAlreadyAdded(file.path)"
                                     class="absolute top-3 left-3 w-5 h-5 bg-v-mark text-v-mark-ink rounded-v-pill flex items-center justify-center pointer-events-none shadow-v-lift">
                                    <x-admin.icon name="check" class="w-3 h-3" />
                                </div>
                                {{-- Selection indicator --}}
                                <div x-show="isSelected(file.path)"
                                     class="absolute top-3 left-3 w-5 h-5 bg-[var(--bl-good)] text-v-surface rounded-v-pill flex items-center justify-center pointer-events-none shadow-v-lift">
                                    <x-admin.icon name="check" class="w-3 h-3" />
                                </div>
                                <button type="button" @click="askDeleteFile(file)" x-bind:aria-label="'Delete ' + file.name + ' from the server'"
                                    class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 w-5 h-5 bg-v-surface border border-v-danger text-v-danger rounded-v-pill flex items-center justify-center hover:bg-[var(--bl-bad-wash)] transition-all shadow-v-lift">
                                    <x-admin.icon name="x" class="w-3 h-3" />
                                </button>
                            </div>
                        </template>
                    </div>

                    <div x-show="!browserLoading && browserFolders.length === 0 && browserFiles.length === 0">
                        <x-admin.empty-state title="Empty folder" />
                    </div>
                </div>

                {{-- Selection Footer --}}
                <div x-show="selectedFiles.length > 0" x-transition
                     class="flex items-center justify-between px-5 py-3 border-t border-v-border bg-v-surface">
                    <div class="flex items-center gap-3">
                        <span class="text-[13px] text-v-secondary">
                            <span class="font-semibold text-v-text" x-text="selectedFiles.length"></span>
                            <span x-text="selectedFiles.length === 1 ? 'image' : 'images'"></span> selected
                        </span>
                        <x-admin.button variant="ghost" size="sm" @click="selectedFiles = []">Clear</x-admin.button>
                    </div>
                    <x-admin.button variant="primary" @click="addSelectedFiles()">
                        <x-admin.icon name="plus" class="w-4 h-4" />
                        <span x-text="'Add ' + selectedFiles.length + (selectedFiles.length === 1 ? ' Image' : ' Images')"></span>
                    </x-admin.button>
                </div>

                {{-- Deleting a catalogue file is destructive and irreversible, so it
                     goes through the admin's own dialog. It is nested inside the
                     browser panel so the panel's click-outside does not read a
                     click on this dialog as a click off the browser. --}}
                <x-admin.modal name="im-delete-file" title="Delete file" maxWidth="sm" class="v-confirm !z-[10000]">
                    <p class="text-[13px] text-v-secondary" x-text="pendingDelete ? 'Delete ' + pendingDelete.name + ' from the server? Any product still using it will lose the image.' : ''"></p>
                    <div class="mt-4 flex justify-end gap-2">
                        <x-admin.button variant="secondary" @click="open = false; pendingDelete = null">Cancel</x-admin.button>
                        <x-admin.button variant="danger" @click="open = false; confirmPendingDelete()">Delete file</x-admin.button>
                    </div>
                </x-admin.modal>
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

        _createPlaceholder() {
            const ph = document.createElement('div');
            ph.className = 'rounded-v-md border-2 border-dashed border-v-mark bg-v-accent-weak aspect-square';
            ph.dataset.placeholder = 'true';
            return ph;
        },

        _getDropIndex(grid, e) {
            const cards = [...grid.querySelectorAll('[data-path]:not([style*="opacity: 0.4"])')];
            for (let i = 0; i < cards.length; i++) {
                const rect = cards[i].getBoundingClientRect();
                const midX = rect.left + rect.width / 2;
                const midY = rect.top + rect.height / 2;
                if (e.clientY < midY || (e.clientY < rect.bottom && e.clientX < midX)) {
                    return parseInt(cards[i].dataset.index);
                }
            }
            return this.items.length - 1;
        },

        renderGrid() {
            const grid = this.$refs.sortableGrid;
            if (!grid) return;
            grid.textContent = '';

            this.items.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'relative group rounded-v-md overflow-hidden bg-v-surface shadow-v-lift border border-v-border cursor-grab';
                card.draggable = true;
                card.dataset.path = item.path;
                card.dataset.index = index;

                const imgWrap = document.createElement('div');
                imgWrap.className = 'aspect-square';
                const img = document.createElement('img');
                img.src = item.url;
                img.alt = 'Product image ' + (index + 1) + (index === 0 ? ' (main)' : '');
                img.className = 'w-full h-full object-cover pointer-events-none';
                img.draggable = false;
                imgWrap.appendChild(img);
                card.appendChild(imgWrap);

                const badge = document.createElement('span');
                badge.className = 'absolute top-1.5 left-1.5 px-2 py-0.5 text-[10px] font-bold rounded-v-sm ' +
                    (index === 0 ? 'bg-v-accent text-v-accent-text' : 'bg-v-text text-v-surface');
                badge.textContent = index === 0 ? 'Main' : '#' + (index + 1);
                card.appendChild(badge);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 w-6 h-6 bg-v-surface border border-v-danger text-v-danger rounded-v-pill flex items-center justify-center hover:bg-[var(--bl-bad-wash)] transition-all shadow-v-lift';
                removeBtn.setAttribute('aria-label', 'Remove image ' + (index + 1) + ' from this product');
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('class', 'w-3 h-3');
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
                    // Remove placeholder
                    if (this._placeholder && this._placeholder.parentNode) {
                        this._placeholder.remove();
                    }
                    // Restore dragged card
                    card.style.display = '';

                    // Calculate drop position from placeholder position
                    if (this._dragFromIndex !== null) {
                        const allCards = [...grid.querySelectorAll('[data-path]')];
                        const visibleCards = allCards.filter(c => c !== this._dragEl);
                        // Placeholder was already removed, so just use _dragFromIndex
                        // The drop event handles the actual reorder
                    }
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

                // Find the card closest to cursor, insert placeholder before or after it
                const cards = [...grid.querySelectorAll('[data-path]')].filter(c => c !== this._dragEl);
                if (!cards.length) return;

                // insert placeholder before the first card whose left edge the cursor has passed
                let inserted = false;
                for (const c of cards) {
                    const rect = c.getBoundingClientRect();
                    // Trigger as soon as cursor enters the card at all
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

                // Figure out where placeholder ended up
                const allChildren = [...grid.children].filter(c => c.dataset.path || c.dataset.placeholder);
                const placeholderPos = allChildren.indexOf(this._placeholder);
                // Count only real cards before placeholder
                let toIndex = 0;
                for (let i = 0; i < allChildren.length; i++) {
                    if (allChildren[i] === this._placeholder) break;
                    if (allChildren[i].dataset.path && allChildren[i] !== this._dragEl) toIndex++;
                }
                // Adjust if dragging down
                if (this._dragFromIndex < toIndex) toIndex--;

                if (toIndex < 0) toIndex = 0;
                if (toIndex >= this.items.length) toIndex = this.items.length - 1;

                if (this._dragFromIndex !== toIndex) {
                    const moved = this.items.splice(this._dragFromIndex, 1)[0];
                    this.items.splice(toIndex, 0, moved);
                }

                // Clean up
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
            this.toast = msg;
            this.toastType = type;
            setTimeout(() => { this.toast = ''; }, 3000);
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.$nextTick(() => this.renderGrid());
        },

        // --- Image Manager Modal ---
        async openBrowser() {
            this.browserOpen = true;
            this.selectedFiles = [];

            let startPath = '';

            if (this.lastBrowsedPath !== null) {
                // User already browsed somewhere this session, return there
                startPath = this.lastBrowsedPath;
            } else if (this.items.length > 0 && this.items[0].path) {
                // First open: navigate to the main image's folder
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
                this.showToast(added + ' image' + (added !== 1 ? 's' : '') + ' added.');
            }
            this.browserOpen = false;
        },

        askDeleteFile(file) {
            this.pendingDelete = file;
            this.$dispatch('open-modal', 'im-delete-file');
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
                this.items = this.items.filter(i => i.path !== file.path);
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
            this.browserUploadStatus = 'Uploading ' + file.name + '...';

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
