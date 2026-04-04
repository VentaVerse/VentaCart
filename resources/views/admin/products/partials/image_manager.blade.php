{{-- Image Manager Partial --}}
{{-- Usage: @include('admin.products.partials.image_manager', ['existingImages' => $existingImages ?? []]) --}}

<div x-data="imageManager()" x-init="init()" class="p-6">

    {{-- Hidden JSON field --}}
    <input type="hidden" name="images_json" :value="JSON.stringify(items)">

    {{-- Toolbar --}}
    <div class="flex items-center gap-2 mb-5">
        <button type="button" @click="openBrowser()"
            class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            Image Manager
        </button>
        <span class="ml-auto text-xs text-gray-400" x-show="items.length > 0" x-text="items.length + ' image' + (items.length !== 1 ? 's' : '')"></span>
    </div>

    {{-- Thumbnail Grid --}}
    <div class="rounded-xl border-2 border-dashed p-4 min-h-[10rem] bg-gray-50 border-gray-300">
        {{-- Empty State --}}
        <div x-show="items.length === 0" class="text-center py-10 text-gray-400">
            <svg class="w-14 h-14 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm font-medium text-gray-500">No images yet</p>
            <p class="text-xs mt-1">Click Image Manager to browse, upload, or import images</p>
        </div>

        {{-- Grid (no x-for — rendered via JS to avoid SortableJS conflict) --}}
        <div x-show="items.length > 0" x-ref="sortableGrid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3"></div>
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-transition.opacity class="fixed bottom-6 right-6 z-[60] px-4 py-3 rounded-lg shadow-lg text-sm text-white"
         :class="toastType === 'error' ? 'bg-red-600' : 'bg-emerald-600'">
        <span x-text="toast"></span>
    </div>

    {{-- Image Manager Modal --}}
    <div x-show="browserOpen" x-transition.opacity.duration.200ms
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.45); backdrop-filter: blur(3px);"
         @keydown.escape.window="browserOpen = false">

        <div class="w-full max-w-5xl max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
             @click.outside="browserOpen = false">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <h3 class="text-sm font-semibold text-gray-900">Image Manager</h3>
                        </div>
                        <span class="text-gray-300">|</span>
                        {{-- Breadcrumb --}}
                        <nav class="flex items-center gap-0.5 text-sm min-w-0 overflow-x-auto">
                            <button type="button" @click="browseDir('')"
                                class="text-indigo-600 hover:text-indigo-800 transition-colors shrink-0 font-medium flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"/></svg>
                                Files
                            </button>
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

                {{-- Modal Toolbar: Search + URL + Sort + Upload Here --}}
                <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 bg-white">
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="browserSearch" placeholder="Search..."
                            @keydown.enter.prevent
                            class="w-44 pl-9 pr-3 py-1.5 rounded-lg bg-white border-gray-300 text-gray-900 text-sm placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="button" @click="showUrlInput = !showUrlInput"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-sm font-medium rounded-lg border transition-colors"
                        :class="showUrlInput ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        URL
                    </button>
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

                {{-- URL Import Bar --}}
                <div x-show="showUrlInput" x-transition class="px-5 py-2.5 border-b border-gray-100 bg-gray-50/50">
                    <div class="flex gap-2">
                        <input type="text" x-model="urlInput" placeholder="https://example.com/image.jpg"
                            class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                            @keydown.enter.prevent="importFromUrl()">
                        <button type="button" @click="importFromUrl()" :disabled="importingUrl"
                            class="px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                            <span x-show="!importingUrl">Import</span>
                            <span x-show="importingUrl">Importing...</span>
                        </button>
                    </div>
                </div>

                {{-- Modal Content with drag & drop --}}
                <div class="flex-1 overflow-y-auto p-5 bg-gray-50/50 relative"
                     @dragover.prevent="browserDragover = true"
                     @dragleave.prevent="browserDragover = false"
                     @drop.prevent="browserDragover = false; handleBrowserDrop($event)">

                    {{-- Drag overlay --}}
                    <div x-show="browserDragover" class="absolute inset-0 z-10 flex items-center justify-center bg-indigo-50/90 border-2 border-dashed border-indigo-400 rounded-xl m-2 pointer-events-none">
                        <div class="text-center">
                            <svg class="w-10 h-10 mx-auto mb-2 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <p class="text-sm font-medium text-indigo-700">Drop to upload here</p>
                        </div>
                    </div>

                    {{-- Upload progress --}}
                    <div x-show="browserUploading" class="mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span class="text-xs text-gray-500" x-text="browserUploadStatus"></span>
                    </div>

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
                        <template x-for="folder in filteredBrowserFolders" :key="folder.path">
                            <button type="button" @click="browseDir(folder.path)"
                                class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                                <svg class="w-12 h-12 text-amber-500 group-hover:text-amber-600 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                <span class="text-xs text-gray-600 mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                            </button>
                        </template>

                        {{-- Files --}}
                        <template x-for="file in filteredBrowserFiles" :key="file.path">
                            <div class="relative group">
                                <button type="button" @click="toggleFileSelection(file)"
                                    class="flex flex-col items-center p-2 rounded-xl transition-colors w-full"
                                    :class="isAlreadyAdded(file.path)
                                        ? 'ring-2 ring-indigo-500 bg-indigo-50 opacity-60 cursor-default'
                                        : isSelected(file.path)
                                            ? 'ring-2 ring-emerald-500 bg-emerald-50'
                                            : 'hover:bg-gray-100'">
                                    <div class="w-full aspect-square rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                                        <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                                    </div>
                                    <span class="text-[11px] text-gray-500 mt-1.5 truncate w-full text-center leading-tight" x-text="file.name"></span>
                                </button>
                                {{-- Already-added indicator --}}
                                <div x-show="isAlreadyAdded(file.path)"
                                     class="absolute top-3 left-3 w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center pointer-events-none shadow-sm">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                {{-- Selection indicator --}}
                                <div x-show="isSelected(file.path)"
                                     class="absolute top-3 left-3 w-5 h-5 bg-emerald-500 rounded-full flex items-center justify-center pointer-events-none shadow-sm">
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
                        <p class="text-xs text-gray-400 mt-1">Drop files here or use Upload Here above</p>
                    </div>
                </div>

                {{-- Selection Footer --}}
                <div x-show="selectedFiles.length > 0" x-transition
                     class="flex items-center justify-between px-5 py-3 border-t border-gray-200 bg-white">
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-600">
                            <span class="font-semibold text-emerald-600" x-text="selectedFiles.length"></span>
                            <span x-text="selectedFiles.length === 1 ? 'image' : 'images'"></span> selected
                        </span>
                        <button type="button" @click="selectedFiles = []"
                            class="text-xs text-gray-400 hover:text-gray-600 underline transition-colors">
                            Clear
                        </button>
                    </div>
                    <button type="button" @click="addSelectedFiles()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span x-text="'Add ' + selectedFiles.length + (selectedFiles.length === 1 ? ' Image' : ' Images')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function imageManager() {
    return {
        items: @json($existingImages ?? []),
        pimToken: '{{ $pimToken ?? Str::uuid() }}',
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
            ph.className = 'rounded-lg border-2 border-dashed border-indigo-400 bg-indigo-50 aspect-square';
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
                card.className = 'relative group rounded-lg overflow-hidden bg-white shadow-sm border border-gray-200 cursor-grab hover:shadow-md';
                card.draggable = true;
                card.dataset.path = item.path;
                card.dataset.index = index;

                const imgWrap = document.createElement('div');
                imgWrap.className = 'aspect-square';
                const img = document.createElement('img');
                img.src = item.url;
                img.alt = 'Image ' + (index + 1);
                img.className = 'w-full h-full object-cover pointer-events-none';
                img.draggable = false;
                imgWrap.appendChild(img);
                card.appendChild(imgWrap);

                const badge = document.createElement('span');
                badge.className = 'absolute top-1.5 left-1.5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded shadow-sm ' +
                    (index === 0 ? 'bg-indigo-600 text-white' : 'bg-black/50 text-white backdrop-blur-sm');
                badge.textContent = index === 0 ? 'Main' : '#' + (index + 1);
                card.appendChild(badge);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-all shadow-sm';
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('class', 'w-3 h-3');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '2.5');
                svg.setAttribute('viewBox', '0 0 24 24');
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
                // User already browsed somewhere this session — return there
                startPath = this.lastBrowsedPath;
            } else if (this.items.length > 0 && this.items[0].path) {
                // First open: navigate to the main image's folder
                const mainPath = this.items[0].path;
                const lastSlash = mainPath.lastIndexOf('/');
                if (lastSlash > 0) {
                    startPath = mainPath.substring(0, lastSlash);
                }
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

        async deleteServerFile(file) {
            if (!confirm(`Delete "${file.name}" from server?`)) return;

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
            formData.append('catalog_path', this.browserCurrentPath);

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
                    body: JSON.stringify({ url: this.urlInput.trim(), store_id: this.getStoreId(), catalog_path: this.browserCurrentPath }),
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
