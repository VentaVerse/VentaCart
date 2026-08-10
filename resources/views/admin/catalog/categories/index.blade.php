<x-admin.shell title="Categories">
    <x-admin.page title="Categories">
        <x-slot:actions>
            <x-admin.hint label="How ordering works" class="v-hint--end">Subcategories are sorted within their parent.</x-admin.hint>
            <x-admin.button variant="primary" href="{{ route('admin.categories.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Category
            </x-admin.button>
        </x-slot:actions>

        @php
            $parents = $categories->whereNull('parent_id')->sortBy('sort_order');
            $children = $categories->whereNotNull('parent_id')->groupBy('parent_id');
        @endphp

        <x-admin.panel flush>
            <div class="v-sortlist" x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                @forelse ($parents as $parent)
                    <div data-id="{{ $parent->id }}" draggable="true">
                        {{-- Parent row --}}
                        <div class="v-sortrow">
                            <span class="v-sortrow__grip drag-handle" aria-hidden="true"><x-admin.icon name="drag" class="w-4 h-4" /></span>
                            @if ($parent->image)
                                <img class="v-sortrow__art" src="{{ storage_url($parent->image) }}" alt="">
                            @endif
                            <div class="v-sortrow__body">
                                <span class="v-sortrow__name">{{ $parent->name }}</span>
                                <span class="v-sortrow__slug">/{{ $parent->slug }}</span>
                            </div>
                            <span class="v-sortrow__fact">{{ $parent->products_count }} products</span>
                            <x-admin.badge :tone="$parent->is_active ? 'success' : 'neutral'">{{ $parent->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                            <div class="v-rowactions">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.categories.edit', $parent)">Edit</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-category-{{ $parent->id }}')">Delete</x-admin.button>
                            </div>
                        </div>

                        {{-- Children --}}
                        @if (isset($children[$parent->id]) && $children[$parent->id]->isNotEmpty())
                            <div class="v-sortnest v-sortlist" x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                                @foreach ($children[$parent->id]->sortBy('sort_order') as $child)
                                    <div data-id="{{ $child->id }}" draggable="true" class="v-sortrow">
                                        <span class="v-sortrow__grip drag-handle" aria-hidden="true"><x-admin.icon name="drag" class="w-4 h-4" /></span>
                                        @if ($child->image)
                                            <img class="v-sortrow__art" src="{{ storage_url($child->image) }}" alt="">
                                        @endif
                                        <div class="v-sortrow__body">
                                            <span class="v-sortrow__name">{{ $child->name }}</span>
                                            <span class="v-sortrow__slug">/{{ $child->slug }}</span>
                                        </div>
                                        <span class="v-sortrow__fact">{{ $child->products_count }}</span>
                                        <x-admin.badge :tone="$child->is_active ? 'success' : 'neutral'">{{ $child->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                                        <div class="v-rowactions">
                                            <x-admin.button size="sm" variant="ghost" :href="route('admin.categories.edit', $child)">Edit</x-admin.button>
                                            <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-category-{{ $child->id }}')">Delete</x-admin.button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <x-admin.empty-state title="No categories found">
                        <x-admin.button variant="primary" :href="route('admin.categories.create')">Create your first category</x-admin.button>
                    </x-admin.empty-state>
                @endforelse
            </div>
        </x-admin.panel>

        @foreach ($categories as $category)
            <x-admin.confirm-dialog name="delete-category-{{ $category->id }}" title="Delete {{ $category->name }}?"
                :message="isset($children[$category->id]) && $children[$category->id]->isNotEmpty() ? 'Subcategories will move up to top-level. This cannot be undone.' : 'This cannot be undone.'"
                confirm="Delete" :action="route('admin.categories.destroy', $category)" method="DELETE" />
        @endforeach

        @push('scripts')
        <script>
            function initSortable(container, reorderUrl) {
                let dragEl = null;

                container.addEventListener('dragstart', function(e) {
                    const item = e.target.closest('[data-id]');
                    if (!item || item.parentElement !== container) return;
                    dragEl = item;
                    item.style.opacity = '0.4';
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
                    });
                });

                container.addEventListener('dragend', function() {
                    if (dragEl) dragEl.style.opacity = '1';
                    dragEl = null;
                });
            }
        </script>
        @endpush
    </x-admin.page>
</x-admin.shell>
