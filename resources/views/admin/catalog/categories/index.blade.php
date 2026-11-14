<x-admin.shell title="Categories" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Categories</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.categories.create') }}" class="v-btn v-btn--primary">Add category</a>
        </div>
    </div>

    @php
        $vIds = [];
        $vOrder = [];
        foreach ($rows as $vRow) {
            $vIds[] = (int) $vRow['category']->id;
            $vOrder[] = [(int) $vRow['category']->id, (int) $vRow['depth']];
        }
        // The tree is arranged by hand only when every category is on the page.
        $vArrange = ! $filtered && count($rows) > 1;
    @endphp

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Category name…"
                   aria-label="Search categories" class="v-control v-filterbar__grow">
            <select name="status" aria-label="Filter by status" class="v-control v-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if ($filtered)
                <a href="{{ route('admin.categories.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush x-data="vCategoryList({ ids: {{ Js::from($vIds) }} })">
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

            <div class="v-tablewrap v-catwrap" x-ref="wrap">
                <div class="v-catdrop" x-ref="drop" hidden></div>
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col" class="v-cell-open">
                                <label class="v-check">
                                    <input type="checkbox" aria-label="Select every category on this page"
                                           :checked="ids.length > 0 && selected.length === ids.length"
                                           :indeterminate="selected.length > 0 && selected.length < ids.length"
                                           @change="selected = $event.target.checked ? [...ids] : []">
                                </label>
                            </th>
                            <th>Category</th>
                            <th class="v-cell-num">Products</th>
                            <th class="v-cell-num">Sort</th>
                            <th>Status</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody x-ref="body">
                        @forelse ($rows as $vRow)
                            @php
                                $category = $vRow['category'];
                            @endphp
                            <tr data-id="{{ $category->id }}" data-depth="{{ $vRow['depth'] }}"
                                data-name="{{ Str::limit($category->name, 60) }}" style="--depth: {{ $vRow['depth'] }}">
                                <td class="v-cell-open">
                                    <label class="v-check">
                                        <input type="checkbox" value="{{ $category->id }}" x-model.number="selected"
                                               aria-label="Select {{ Str::limit($category->name, 60) }}">
                                    </label>
                                </td>
                                <td>
                                    {{-- Indented by its level in the tree; a filtered list shows the full path instead. --}}
                                    <span class="v-actrow v-catname">
                                        @if ($vArrange)
                                            <button type="button" class="v-cathandle" data-handle
                                                    aria-label="Move {{ Str::limit($category->name, 60) }}: drag, or use the arrow keys"
                                                    title="Drag to move. Left and right change the level."
                                                    @pointerdown="start($event, $el.closest('tr'))"
                                                    @pointermove="move($event)"
                                                    @pointerup="end()"
                                                    @pointercancel="end()"
                                                    @keydown="key($event, $el.closest('tr'))">
                                                <svg viewBox="0 0 10 16" aria-hidden="true"><circle cx="2.5" cy="3" r="1.5"/><circle cx="7.5" cy="3" r="1.5"/><circle cx="2.5" cy="8" r="1.5"/><circle cx="7.5" cy="8" r="1.5"/><circle cx="2.5" cy="13" r="1.5"/><circle cx="7.5" cy="13" r="1.5"/></svg>
                                            </button>
                                        @endif
                                        @if ($category->image)
                                            <img class="v-thumb" src="{{ storage_url($category->image) }}" alt="">
                                        @endif
                                        <span>
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="v-rowlink">{{ $category->name }}</a>
                                            <span class="v-cell-quiet v-block v-mono">{{ $filtered && $vRow['path'] !== $category->name ? $vRow['path'] : $category->slug }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td class="v-cell-num v-mono">{{ $category->products_count }}</td>
                                <td class="v-cell-num v-mono" data-sort>{{ $category->sort_order }}</td>
                                <td>
                                    <span class="v-badge v-badge--{{ $category->is_active ? 'good' : 'quiet' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-category-{{ $category->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No categories found</p>
                                        @if ($filtered)
                                            <p class="v-empty__line">Try adjusting the filters.</p>
                                        @else
                                            <a href="{{ route('admin.categories.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first category</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($vArrange)
                {{-- The arranged order, saved from the page header like any edit. --}}
                <x-admin.form :action="route('admin.categories.arrange')" :save-only-when-dirty="true">
                    <input type="hidden" name="order" data-order value="{{ json_encode($vOrder) }}">
                </x-admin.form>
                <p class="v-sr" role="status" aria-live="polite" x-text="announcement"></p>
            @endif

            <x-admin.confirm name="bulk-delete-categories" title="Delete the selected categories?"
                message="Their subcategories move up a level. This cannot be undone."
                confirm="Delete" :action="route('admin.categories.bulk-delete')" method="POST">
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            </x-admin.confirm>
        </x-admin.panel>

        @foreach ($rows as $vRow)
            <x-admin.confirm name="delete-category-{{ $vRow['category']->id }}" title="Delete {{ $vRow['category']->name }}?"
                message="Its subcategories move up a level. This cannot be undone."
                confirm="Delete" :action="route('admin.categories.destroy', $vRow['category'])" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
