<x-admin.shell title="Categories">
    <x-admin.page title="Categories">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.categories.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Category
            </x-admin.button>
        </x-slot:actions>

        @php
            $parents = $categories->whereNull('parent_id')->sortBy('sort_order');
            $children = $categories->whereNotNull('parent_id')->groupBy('parent_id');
        @endphp

        {{-- Parent categories --}}
        <div class="bg-v-surface border border-v-border rounded-v-lg overflow-hidden">
            <div class="px-5 py-2.5 bg-v-sunken border-b border-v-border">
                <p class="text-xs text-v-muted">Drag the handle to reorder. Subcategories are sorted within their parent.</p>
            </div>

            <div x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                @forelse ($parents as $parent)
                    <div data-id="{{ $parent->id }}" draggable="true" class="border-b border-v-border last:border-b-0">
                        {{-- Parent row --}}
                        <div class="flex items-center hover:bg-v-hover px-5 py-2.5 gap-3">
                            <svg class="w-5 h-5 text-v-muted cursor-grab shrink-0 drag-handle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                            @if ($parent->image)
                                <img class="h-8 w-8 rounded-v-sm object-cover shrink-0" src="{{ storage_url($parent->image) }}" alt="">
                            @endif
                            <div class="flex-1 min-w-0">
                                <span class="text-[13px] font-semibold text-v-text">{{ $parent->name }}</span>
                                <span class="text-xs text-v-muted ml-2">/{{ $parent->slug }}</span>
                            </div>
                            <span class="text-xs text-v-muted whitespace-nowrap">{{ $parent->products_count }} products</span>
                            <x-admin.badge :tone="$parent->is_active ? 'success' : 'neutral'">{{ $parent->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                            <a href="{{ route('admin.categories.edit', $parent) }}" class="text-[13px] text-v-accent hover:opacity-80 font-medium whitespace-nowrap">Edit</a>
                            <button type="button" class="text-[13px] text-v-danger hover:opacity-80 font-medium whitespace-nowrap" @click="$dispatch('open-modal', 'delete-category-{{ $parent->id }}')">Delete</button>
                        </div>

                        {{-- Children --}}
                        @if (isset($children[$parent->id]) && $children[$parent->id]->isNotEmpty())
                            <div class="bg-v-sunken/50" x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                                @foreach ($children[$parent->id]->sortBy('sort_order') as $child)
                                    <div data-id="{{ $child->id }}" draggable="true" class="flex items-center hover:bg-v-hover px-5 py-2 border-t border-v-border gap-3">
                                        <div class="w-8 shrink-0"></div>
                                        <svg class="w-4 h-4 text-v-muted/70 cursor-grab shrink-0 drag-handle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                        @if ($child->image)
                                            <img class="h-6 w-6 rounded-v-sm object-cover shrink-0" src="{{ storage_url($child->image) }}" alt="">
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <span class="text-[13px] text-v-secondary">{{ $child->name }}</span>
                                            <span class="text-xs text-v-muted ml-2">/{{ $child->slug }}</span>
                                        </div>
                                        <span class="text-xs text-v-muted whitespace-nowrap">{{ $child->products_count }}</span>
                                        <x-admin.badge :tone="$child->is_active ? 'success' : 'neutral'">{{ $child->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                                        <a href="{{ route('admin.categories.edit', $child) }}" class="text-[13px] text-v-accent hover:opacity-80 font-medium whitespace-nowrap">Edit</a>
                                        <button type="button" class="text-[13px] text-v-danger hover:opacity-80 font-medium whitespace-nowrap" @click="$dispatch('open-modal', 'delete-category-{{ $child->id }}')">Delete</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <x-admin.empty-state icon="categories" title="No categories found">
                        <a href="{{ route('admin.categories.create') }}" class="text-[13px] text-v-accent hover:underline">Create your first category</a>
                    </x-admin.empty-state>
                @endforelse
            </div>
        </div>

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
