{{-- One level of the tree; each row draws its own subcategories below it. --}}
@foreach ($nodes as $node)
    <div data-id="{{ $node->id }}" draggable="true">
        <div class="v-dragrow">
            <label class="v-check">
                <input type="checkbox" value="{{ $node->id }}" x-model.number="selected"
                       aria-label="Select {{ \Illuminate\Support\Str::limit($node->name, 60) }}">
            </label>
            <span class="v-dragrow__grip drag-handle" aria-hidden="true"><x-admin.icon name="menu" /></span>
            @if ($node->image)
                <img class="v-thumb" src="{{ storage_url($node->image) }}" alt="">
            @endif
            <span>
                <a href="{{ route('admin.categories.edit', $node) }}" class="v-rowlink">{{ $node->name }}</a>
                <span class="v-cell-quiet v-block v-mono">/{{ $node->slug }}</span>
            </span>
            <span class="v-dragrow__end">
                <span class="v-cell-quiet v-mono">{{ $node->products_count }} {{ \Illuminate\Support\Str::plural('product', $node->products_count) }}</span>
                <span class="v-badge v-badge--{{ $node->is_active ? 'good' : 'quiet' }}">{{ $node->is_active ? 'Active' : 'Inactive' }}</span>
                <a href="{{ route('admin.categories.edit', $node) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                <button type="button" class="v-btn v-btn--danger v-btn--sm"
                        @click="$dispatch('v-confirm', 'delete-category-{{ $node->id }}')">Delete</button>
            </span>
        </div>

        @if (($byParent[$node->id] ?? collect())->isNotEmpty())
            <div class="v-dragnest" x-init="initSortable($el, '{{ route('admin.categories.reorder') }}')">
                @include('admin.catalog.categories.partials.branch', ['nodes' => $byParent[$node->id]])
            </div>
        @endif
    </div>
@endforeach
