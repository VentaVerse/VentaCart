<x-admin.shell title="Categories" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">
                Categories
                <x-admin.help text="Drag rows to reorder. Subcategories are sorted within their parent." />
            </h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.categories.create') }}" class="v-btn v-btn--primary">Add category</a>
        </div>
    </div>

    @php
        $parents = $categories->whereNull('parent_id')->sortBy('sort_order');
        $children = $categories->whereNotNull('parent_id')->groupBy('parent_id');
    @endphp

    <div class="v-settle">
        <x-admin.panel>
            <div x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                @forelse ($parents as $parent)
                    <div data-id="{{ $parent->id }}" draggable="true">
                        <div class="v-dragrow">
                            <span class="v-dragrow__grip drag-handle" aria-hidden="true"><x-admin.icon name="menu" /></span>
                            @if ($parent->image)
                                <img class="v-thumb" src="{{ storage_url($parent->image) }}" alt="">
                            @endif
                            <span>
                                <a href="{{ route('admin.categories.edit', $parent) }}" class="v-rowlink">{{ $parent->name }}</a>
                                <span class="v-cell-quiet v-block v-mono">/{{ $parent->slug }}</span>
                            </span>
                            <span class="v-dragrow__end">
                                <span class="v-cell-quiet v-mono">{{ $parent->products_count }} products</span>
                                <span class="v-badge v-badge--{{ $parent->is_active ? 'good' : 'quiet' }}">{{ $parent->is_active ? 'Active' : 'Inactive' }}</span>
                                <a href="{{ route('admin.categories.edit', $parent) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                        @click="$dispatch('v-confirm', 'delete-category-{{ $parent->id }}')">Delete</button>
                            </span>
                        </div>

                        @if (isset($children[$parent->id]) && $children[$parent->id]->isNotEmpty())
                            <div class="v-dragnest" x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                                @foreach ($children[$parent->id]->sortBy('sort_order') as $child)
                                    <div data-id="{{ $child->id }}" draggable="true" class="v-dragrow">
                                        <span class="v-dragrow__grip drag-handle" aria-hidden="true"><x-admin.icon name="menu" /></span>
                                        @if ($child->image)
                                            <img class="v-thumb" src="{{ storage_url($child->image) }}" alt="">
                                        @endif
                                        <span>
                                            <a href="{{ route('admin.categories.edit', $child) }}" class="v-rowlink">{{ $child->name }}</a>
                                            <span class="v-cell-quiet v-block v-mono">/{{ $child->slug }}</span>
                                        </span>
                                        <span class="v-dragrow__end">
                                            <span class="v-cell-quiet v-mono">{{ $child->products_count }}</span>
                                            <span class="v-badge v-badge--{{ $child->is_active ? 'good' : 'quiet' }}">{{ $child->is_active ? 'Active' : 'Inactive' }}</span>
                                            <a href="{{ route('admin.categories.edit', $child) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                            <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                                    @click="$dispatch('v-confirm', 'delete-category-{{ $child->id }}')">Delete</button>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="v-empty">
                        <p class="v-empty__title">No categories found</p>
                        <a href="{{ route('admin.categories.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first category</a>
                    </div>
                @endforelse
            </div>
        </x-admin.panel>

        @foreach ($categories as $category)
            <x-admin.confirm name="delete-category-{{ $category->id }}" title="Delete {{ $category->name }}?"
                :message="isset($children[$category->id]) && $children[$category->id]->isNotEmpty() ? 'Subcategories will move up to top-level. This cannot be undone.' : 'This cannot be undone.'"
                confirm="Delete" :action="route('admin.categories.destroy', $category)" method="DELETE" />
        @endforeach
    </div>

    @push('scripts')
    <script>
        function initSortable(container, reorderUrl) {
            let dragEl = null;

            container.addEventListener('dragstart', function(e) {
                const item = e.target.closest('[data-id]');
                if (!item || item.parentElement !== container) return;
                dragEl = item;
                item.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.dataset.id);
            });

            container.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!dragEl) return;
                const target = e.target.closest('[data-id]');
                if (!target || target === dragEl || target.parentElement !== container) return;
                const rect = target.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    target.before(dragEl);
                } else {
                    target.after(dragEl);
                }
            });

            container.addEventListener('drop', function(e) {
                e.preventDefault();
                if (!dragEl) return;
                const items = [...container.querySelectorAll(':scope > [data-id]')];
                const order = items.map(function(el, idx) {
                    return { id: parseInt(el.dataset.id), sort_order: idx };
                });

                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ order: order })
                }).then(function (r) {
                    Alpine.store('toasts').push(r.ok ? 'Order saved.' : 'Reorder failed.', r.ok ? 'success' : 'danger');
                }).catch(function () {
                    Alpine.store('toasts').push('Reorder failed.', 'danger');
                });
            });

            container.addEventListener('dragend', function() {
                if (dragEl) dragEl.classList.remove('is-dragging');
                dragEl = null;
            });
        }
    </script>
    @endpush
</x-admin.shell>
