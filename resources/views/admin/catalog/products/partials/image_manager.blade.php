{{-- Image manager, v dress. Same contract as the v2 partial: the
     hidden images_json input carries the ordered list; the modal
     browses, multi-selects, uploads, imports, and deletes through the
     products image endpoints. Every mutation calls _markDirty() so the
     save doctrine sees edits that never fire an input event. --}}
<div x-data="imageManager()" x-init="init()">

    <input type="hidden" name="images_json" :value="JSON.stringify(items)">

    {{-- The shared browser, in multi mode. Selection still STAGES and arms
         this page's Save, which is what it always did and what the doctrine
         asks for; the component dispatches the input event itself, so the
         _markDirty() dance every copy had to remember is gone. --}}
    <x-admin.media-picker name="__product_images" root="products" mode="multi"
                          can="upload,url,delete" bare
                          @media:picked="addPaths($event.detail.paths)" />

    <div class="v-filterbar v-gap-sm">
        <button type="button" class="v-btn v-btn--secondary" @click="openPicker($event)">Image manager</button>
        <span class="v-filterbar__grow"></span>
        <span class="v-cell-quiet v-mono" x-show="items.length > 0" x-text="items.length + ' image' + (items.length !== 1 ? 's' : '')"></span>
    </div>

    <div x-show="items.length === 0" class="v-empty">
        <p class="v-empty__title">No images yet</p>
    </div>

    {{-- Rendered by JS (renderGrid) so the custom drag reorder owns the DOM. --}}
    <div x-show="items.length > 0" x-ref="sortableGrid" class="v-mediagrid"></div>

</div>

@push('scripts')
<script>
function imageManager() {
    return {
        items: @json($existingImages ?? []),
        pimToken: '{{ $pimToken ?? Str::uuid() }}',
        root: 'products',
        // Browsing, selecting, uploading, importing and deleting all left
        // with the modal: eighteen keys and fifteen methods, now the shared
        // component's. What stays is this grid, which is the one thing here
        // no picker does: order, and the Main badge that order decides.

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
        /** Aim the shared panel, seeded with what is already attached. */
        openPicker(event) {
            const el = this.$root.querySelector('[data-media-picker="__product_images"]');
            if (!el) return;

            // The panel marks what this product already carries, so a
            // merchant is never offered the same photo twice.
            window.Alpine.$data(el).value = this.items.map((i) => i.path);
            el.dispatchEvent(new CustomEvent('media:open', { detail: { from: event.currentTarget } }));
        },

        /** What came back. Order is the merchant's, so new ones go last. */
        addPaths(paths) {
            (paths || []).forEach((path) => {
                if (this.items.some((i) => i.path === path)) return;
                this.items.push({ path, url: '/storage/' + path.split('/').map(encodeURIComponent).join('/') });
            });

            this.renderGrid();
            this._markDirty();
        },
    }
}
</script>
@endpush
