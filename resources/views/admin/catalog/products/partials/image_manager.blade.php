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
                          can="upload,url,delete,folder" bare
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
    <div x-show="items.length > 0" x-ref="sortableGrid" class="v-mediagrid"
         role="list" aria-label="Product images, in order. The first is the main image."></div>

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
