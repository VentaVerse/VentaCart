<x-admin3.shell title="Categories" active="catalog">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">
                Categories
                <x-admin3.help text="Drag rows to reorder. Subcategories are sorted within their parent." />
            </h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.categories.create') }}" class="v3-btn v3-btn--primary">Add category</a>
        </div>
    </div>

    @php
        $parents = $categories->whereNull('parent_id')->sortBy('sort_order');
        $children = $categories->whereNotNull('parent_id')->groupBy('parent_id');
    @endphp

    <div class="v3-settle">
        <x-admin3.panel>
            <div x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                @forelse ($parents as $parent)
                    <div data-id="{{ $parent->id }}" draggable="true">
                        <div class="v3-dragrow">
                            <span class="v3-dragrow__grip drag-handle" aria-hidden="true"><x-admin3.icon name="menu" /></span>
                            @if ($parent->image)
                                <img class="v3-thumb" src="{{ storage_url($parent->image) }}" alt="">
                            @endif
                            <span>
                                <a href="{{ route('admin.categories.edit', $parent) }}" class="v3-rowlink">{{ $parent->name }}</a>
                                <span class="v3-cell-quiet v3-block v3-mono">/{{ $parent->slug }}</span>
                            </span>
                            <span class="v3-dragrow__end">
                                <span class="v3-cell-quiet v3-mono">{{ $parent->products_count }} products</span>
                                <span class="v3-badge v3-badge--{{ $parent->is_active ? 'good' : 'quiet' }}">{{ $parent->is_active ? 'Active' : 'Inactive' }}</span>
                                <a href="{{ route('admin.categories.edit', $parent) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                        @click="$dispatch('v3-confirm', 'delete-category-{{ $parent->id }}')">Delete</button>
                            </span>
                        </div>

                        @if (isset($children[$parent->id]) && $children[$parent->id]->isNotEmpty())
                            <div class="v3-dragnest" x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                                @foreach ($children[$parent->id]->sortBy('sort_order') as $child)
                                    <div data-id="{{ $child->id }}" draggable="true" class="v3-dragrow">
                                        <span class="v3-dragrow__grip drag-handle" aria-hidden="true"><x-admin3.icon name="menu" /></span>
                                        @if ($child->image)
                                            <img class="v3-thumb" src="{{ storage_url($child->image) }}" alt="">
                                        @endif
                                        <span>
                                            <a href="{{ route('admin.categories.edit', $child) }}" class="v3-rowlink">{{ $child->name }}</a>
                                            <span class="v3-cell-quiet v3-block v3-mono">/{{ $child->slug }}</span>
                                        </span>
                                        <span class="v3-dragrow__end">
                                            <span class="v3-cell-quiet v3-mono">{{ $child->products_count }}</span>
                                            <span class="v3-badge v3-badge--{{ $child->is_active ? 'good' : 'quiet' }}">{{ $child->is_active ? 'Active' : 'Inactive' }}</span>
                                            <a href="{{ route('admin.categories.edit', $child) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                            <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                                    @click="$dispatch('v3-confirm', 'delete-category-{{ $child->id }}')">Delete</button>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="v3-empty">
                        <p class="v3-empty__title">No categories found</p>
                        <a href="{{ route('admin.categories.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create your first category</a>
                    </div>
                @endforelse
            </div>
        </x-admin3.panel>

        @foreach ($categories as $category)
            <x-admin3.confirm name="delete-category-{{ $category->id }}" title="Delete {{ $category->name }}?"
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
</x-admin3.shell>
