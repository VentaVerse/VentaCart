{{-- The product's images, in order: the first is the main image. The
     hidden images_json input carries the ordered list of {path, url}; the
     shared media library adds images to it. --}}
<div x-data="imageManager()" x-init="init()">

    <input type="hidden" name="images_json" :value="JSON.stringify(items)">

    {{-- Adding stages the images, and this page's Save writes them. The
         field dispatches the form's input event itself. --}}
    <x-admin.media-picker name="__product_images" root="products" kind="products" mode="multi" bare
                          @media:picked="addPaths($event.detail.paths, $event.detail.files)" />

    <div class="v-filterbar v-gap-sm">
        <button type="button" class="v-btn v-btn--secondary" @click="openPicker($event)">Image manager</button>
        <span class="v-filterbar__grow"></span>
        <span class="v-cell-quiet v-mono" x-show="items.length > 0" x-text="items.length + ' image' + (items.length !== 1 ? 's' : '')"></span>
    </div>

    <div x-show="items.length === 0" class="v-empty">
        <p class="v-empty__title">No images yet</p>
    </div>

    {{-- Rendered by JS (renderGrid) so the custom drag reorder owns the DOM. --}}
    <div x-show="items.length > 0" x-ref="sortableGrid" class="v-mediagrid"
         role="list" aria-label="Product images, in order. The first is the main image."></div>

</div>

@push('scripts')
<script>
function imageManager() {
    return {
        items: @json($existingImages ?? []),
        pimToken: '{{ $pimToken ?? Str::uuid() }}',
        // Browsing, uploading and deleting belong to the media library.
        // What stays here is the order, and the Main badge it decides.

        _dragFromIndex: null,
        _dragEl: null,
        _placeholder: null,

        init() {
            this.$nextTick(() => this.renderGrid());
        },

        // The hidden images_json input tracks `items` through :value, which
        // fires no input event; every change routes through here so the
        // form's save bar sees it.
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

                // Reachable and movable without a mouse.
                //
                // These were draggable divs with tabIndex -1 and no role, so
                // reordering worked for exactly one input device. Order is not
                // decoration here: position 0 IS the main image, so choosing
                // which photo a shopper sees first was mouse-only.
                card.tabIndex = 0;
                card.setAttribute('role', 'listitem');
                card.setAttribute('aria-label',
                    (index === 0 ? 'Main image' : 'Image ' + (index + 1)) +
                    ' of ' + this.items.length + '. Use Ctrl with the arrow keys to reorder.');
                card.addEventListener('keydown', (e) => {
                    if (!e.ctrlKey && !e.metaKey) return;
                    const delta = e.key === 'ArrowLeft' ? -1 : (e.key === 'ArrowRight' ? 1 : 0);
                    if (!delta) return;
                    e.preventDefault();
                    this.moveItem(index, index + delta);
                });

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
                    // Delay so the browser captures the drag image first.
                    // dragend can land before this frame does on a very short
                    // drag, and it nulls the placeholder, so the frame has to
                    // check it is still wanted rather than insert null.
                    requestAnimationFrame(() => {
                        if (!this._placeholder || this._dragEl !== card) return;
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

                // The swap happens at a card's MIDDLE, not at its far edge.
                //
                // This tested `clientX < rect.right`, which is true across the
                // whole of a card and everything to its left, so the
                // placeholder stayed in front of card 2 until the pointer
                // cleared card 2 entirely. Moving image 1 one place to the
                // right meant dragging it past the end of image 2, which is
                // twice the distance and reads as the drop being refused.
                let before = null;

                for (const c of cards) {
                    const r = c.getBoundingClientRect();

                    // A wrapped grid has rows: anything ending above the
                    // pointer is on an earlier row and cannot be the target.
                    if (e.clientY > r.bottom) continue;

                    const onThisRow = e.clientY >= r.top;
                    const pastMiddle = onThisRow && e.clientX > r.left + (r.width / 2);

                    if (!pastMiddle) {
                        before = c;
                        break;
                    }
                }

                if (before) {
                    if (this._placeholder.nextSibling !== before) {
                        grid.insertBefore(this._placeholder, before);
                    }
                } else if (grid.lastElementChild !== this._placeholder) {
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

                // No off-by-one correction here. The count above already
                // SKIPS the dragged card, so it is the index to splice into
                // after the card is removed. Correcting again cancelled every
                // forward move by one place, which is why dragging an image
                // one step to the right did nothing until you had dragged it
                // clear of the next image entirely.

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

        /**
         * Move one image, keeping focus on it.
         *
         * renderGrid() rebuilds the whole grid, so the card that was focused
         * is a different element afterwards and focus would fall to the body
         * mid-task. It is restored by position.
         */
        moveItem(from, to) {
            if (to < 0 || to >= this.items.length || from === to) return;

            const [moved] = this.items.splice(from, 1);
            this.items.splice(to, 0, moved);

            this.renderGrid();
            this._markDirty();

            this.$nextTick(() => {
                const cards = this.$refs.sortableGrid?.querySelectorAll('.v-imgcard');
                cards?.[to]?.focus();
            });

            this.showToast(to === 0
                ? 'Moved to first. This is now the main image.'
                : 'Moved to position ' + (to + 1) + '.');
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this._markDirty();
            this.$nextTick(() => this.renderGrid());
        },

        /**
         * Opens the media library to add images. What the product already
         * carries shows as added, so the same photo is never offered twice.
         */
        openPicker(event) {
            const el = this.$root.querySelector('[data-media-picker="__product_images"]');
            if (!el) return;

            window.Alpine.$data(el).value = this.items.map((i) => i.path);
            el.dispatchEvent(new CustomEvent('media:open', { detail: { from: event.currentTarget } }));
        },

        /** What came back. Order is the merchant's, so new ones go last. */
        addPaths(paths, files = []) {
            const urls = new Map((files || []).map((f) => [f.path, f.url]));

            (paths || []).forEach((path) => {
                if (this.items.some((i) => i.path === path)) return;
                this.items.push({ path, url: urls.get(path) || '/storage/' + path.split('/').map(encodeURIComponent).join('/') });
            });

            this.renderGrid();
            this._markDirty();
        },
    }
}
</script>
@endpush
