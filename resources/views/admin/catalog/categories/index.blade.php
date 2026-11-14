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
        foreach ($rows as $vRow) {
            $vIds[] = (int) $vRow['category']->id;
        }
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

        <x-admin.panel flush x-data="{ selected: [], ids: {{ Js::from($vIds) }} }">
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

            <div class="v-tablewrap">
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
                    <tbody>
                        @forelse ($rows as $vRow)
                            @php
                                $category = $vRow['category'];
                            @endphp
                            <tr>
                                <td class="v-cell-open">
                                    <label class="v-check">
                                        <input type="checkbox" value="{{ $category->id }}" x-model.number="selected"
                                               aria-label="Select {{ Str::limit($category->name, 60) }}">
                                    </label>
                                </td>
                                <td>
                                    {{-- Indented by its level in the tree; a filtered list shows the full path instead. --}}
                                    <span class="v-actrow v-catname" style="--depth: {{ $vRow['depth'] }}">
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
                                <td class="v-cell-num v-mono">{{ $category->sort_order }}</td>
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
