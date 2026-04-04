<x-admin-layout>
    <x-slot name="header">Categories</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Category
        </a>
    </x-slot>

    @php
        $parents = $categories->whereNull('parent_id')->sortBy('sort_order');
        $children = $categories->whereNotNull('parent_id')->groupBy('parent_id');
    @endphp

    {{-- Parent categories --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <p class="text-xs text-gray-500">Drag the handle to reorder. Subcategories are sorted within their parent.</p>
        </div>

        <div x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
            @forelse ($parents as $parent)
                <div data-id="{{ $parent->id }}" draggable="true" class="border-b border-gray-100 last:border-b-0">
                    {{-- Parent row --}}
                    <div class="flex items-center hover:bg-gray-50 px-6 py-3 gap-3">
                        <svg class="w-5 h-5 text-gray-400 cursor-grab flex-shrink-0 drag-handle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                        @if ($parent->image)
                            <img class="h-8 w-8 rounded object-cover flex-shrink-0" src="{{ storage_url($parent->image) }}" alt="">
                        @endif
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-semibold text-gray-900">{{ $parent->name }}</span>
                            <span class="text-xs text-gray-400 ml-2">/{{ $parent->slug }}</span>
                        </div>
                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ $parent->products_count }} products</span>
                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $parent->is_active ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                        <a href="{{ route('admin.categories.edit', $parent) }}" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium whitespace-nowrap">Edit</a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $parent) }}" class="inline" onsubmit="return confirm('Delete? Children move up.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900 text-xs font-medium whitespace-nowrap">Delete</button>
                        </form>
                    </div>

                    {{-- Children --}}
                    @if (isset($children[$parent->id]) && $children[$parent->id]->isNotEmpty())
                        <div class="bg-gray-50/50" x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                            @foreach ($children[$parent->id]->sortBy('sort_order') as $child)
                                <div data-id="{{ $child->id }}" draggable="true" class="flex items-center hover:bg-gray-100 px-6 py-2.5 border-t border-gray-100 gap-3">
                                    <div class="w-8 flex-shrink-0"></div>
                                    <svg class="w-4 h-4 text-gray-300 cursor-grab flex-shrink-0 drag-handle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                    @if ($child->image)
                                        <img class="h-6 w-6 rounded object-cover flex-shrink-0" src="{{ storage_url($child->image) }}" alt="">
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm text-gray-700">{{ $child->name }}</span>
                                        <span class="text-xs text-gray-400 ml-2">/{{ $child->slug }}</span>
                                    </div>
                                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ $child->products_count }}</span>
                                    <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $child->is_active ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                                    <a href="{{ route('admin.categories.edit', $child) }}" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium whitespace-nowrap">Edit</a>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $child) }}" class="inline" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 text-xs font-medium whitespace-nowrap">Delete</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-6 py-12 text-center text-gray-500">
                    No categories found. <a href="{{ route('admin.categories.create') }}" class="text-indigo-600 hover:underline">Create your first category</a>.
                </div>
            @endforelse
        </div>
    </div>

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
</x-admin-layout>
