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
        // Any depth: each category is drawn under its parent. One whose
        // parent is missing is drawn at the top rather than lost.
        $known = $categories->pluck('id')->flip();
        $byParent = $categories->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->groupBy(fn ($c) => $c->parent_id && $known->has($c->parent_id) ? (int) $c->parent_id : 0);
        $hasChildren = $categories->pluck('parent_id')->filter()->flip();
        $vAllIds = [];
        foreach ($categories as $vCat) {
            $vAllIds[] = (int) $vCat->id;
        }
    @endphp

    <div class="v-settle">
        <x-admin.panel flush x-data="{ selected: [], ids: {{ Js::from($vAllIds) }} }">
            {{-- Ticked rows, and what to do with all of them at once. --}}
            <div class="v-bulkbar" x-show="selected.length > 0" x-cloak role="region" aria-label="Actions for the selected categories">
                <span><strong x-text="selected.length"></strong> selected</span>
                <span class="v-bulkbar__acts">
                    @foreach ([0 => 'Disable', 1 => 'Enable'] as $vActive => $vLabel)
                        <form method="POST" action="{{ route('admin.categories.bulk-status') }}">
                            @csrf
                            <input type="hidden" name="active" value="{{ $vActive }}">
                            <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                            <button type="submit" class="v-btn v-btn--secondary v-btn--sm">{{ $vLabel }}</button>
                        </form>
                    @endforeach
                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'bulk-delete-categories')">Delete</button>
                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="selected = []">Clear</button>
                </span>
            </div>

            @if (($byParent[0] ?? collect())->isNotEmpty())
                <div class="v-cathead">
                    <label class="v-check">
                        <input type="checkbox" aria-label="Select every category"
                               :checked="ids.length > 0 && selected.length === ids.length"
                               :indeterminate="selected.length > 0 && selected.length < ids.length"
                               @change="selected = $event.target.checked ? [...ids] : []">
                    </label>
                    <span>Category</span>
                </div>
            @endif

            <div class="v-cattree" x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                @if (($byParent[0] ?? collect())->isNotEmpty())
                    @include('admin.catalog.categories.partials.branch', ['nodes' => $byParent[0]])
                @else
                    <div class="v-empty">
                        <p class="v-empty__title">No categories found</p>
                        <a href="{{ route('admin.categories.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first category</a>
                    </div>
                @endif
            </div>

            <x-admin.confirm name="bulk-delete-categories" title="Delete the selected categories?"
                message="Their subcategories move up a level. This cannot be undone."
                confirm="Delete" :action="route('admin.categories.bulk-delete')" method="POST">
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            </x-admin.confirm>
        </x-admin.panel>

        @foreach ($categories as $category)
            <x-admin.confirm name="delete-category-{{ $category->id }}" title="Delete {{ $category->name }}?"
                :message="$hasChildren->has($category->id) ? 'Its subcategories move up a level. This cannot be undone.' : 'This cannot be undone.'"
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
