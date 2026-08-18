<x-admin.shell title="Brands" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Brands</h1>
        </div>
        <div class="v-head__actions">
            <button type="button" class="v-btn v-btn--primary" @click="$dispatch('open-brand-modal')">Add brand</button>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.brands.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Brand name…"
                   aria-label="Search brands" class="v-control v-filterbar__grow">
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request('search'))
                <a href="{{ route('admin.brands.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Brand</th>
                            <th class="v-cell-num">Products</th>
                            <th class="v-cell-num">Sort</th>
                            <th>Status</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($brands as $brand)
                            <tr>
                                <td>
                                    <span class="v-actrow">
                                        @if ($brand->image)
                                            <img class="v-thumb" src="{{ storage_url($brand->image) }}" alt="">
                                        @endif
                                        <span>
                                            <a href="{{ route('admin.brands.edit', $brand) }}" class="v-rowlink">{{ $brand->name }}</a>
                                            <span class="v-cell-quiet v-block v-mono">{{ $brand->slug }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td class="v-cell-num v-mono">{{ $brand->products_count }}</td>
                                <td class="v-cell-num v-mono">{{ $brand->sort_order }}</td>
                                <td>
                                    <span class="v-badge v-badge--{{ $brand->is_active ? 'good' : 'quiet' }}">
                                        {{ $brand->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.brands.edit', $brand) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-brand-{{ $brand->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No brands found</p>
                                        <p class="v-empty__line">{{ request('search') ? 'Try adjusting the search.' : 'Brands group the catalog by maker.' }}</p>
                                        @unless (request('search'))
                                            <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="$dispatch('open-brand-modal')">Create your first brand</button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$brands" />

        @foreach ($brands as $brand)
            <x-admin.confirm name="delete-brand-{{ $brand->id }}" title="Delete {{ $brand->name }}?"
                message="Products will keep their data but lose the brand link. This cannot be undone."
                confirm="Delete" :action="route('admin.brands.destroy', $brand)" method="DELETE" />
        @endforeach

        {{-- Quick create: name only, then straight into the edit page. --}}
        <div class="v-passthru" x-data="{ open: false, name: '', saving: false, error: '' }"
             @open-brand-modal.window="open = true; name = ''; error = ''; $nextTick(() => $refs.name.focus())"
             @keydown.escape.window="open = false">
            <template x-if="open">
                <div class="v-overlay" @click.self="open = false">
                    <div class="v-modal" role="dialog" aria-modal="true" aria-label="New brand">
                        <h2 class="v-modal__title">New brand</h2>
                        <label class="v-field v-gap-sm">
                            <span class="v-field__label">Brand name</span>
                            <input type="text" x-model="name" x-ref="name" class="v-control"
                                   @keydown.enter.prevent="$refs.saveBtn.click()">
                        </label>
                        <p x-show="error" x-cloak x-text="error" class="v-field__error"></p>
                        <div class="v-modal__acts">
                            <button type="button" class="v-btn v-btn--ghost" @click="open = false">Cancel</button>
                            <button type="button" class="v-btn v-btn--primary" x-ref="saveBtn"
                                :disabled="saving || !name.trim()"
                                @click="
                                    saving = true; error = '';
                                    fetch('{{ route('admin.brands.quick-create') }}', {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                        body: JSON.stringify({ name: name.trim() })
                                    }).then(function(r) { return r.json(); }).then(function(d) {
                                        if (d.success) { window.location.href = d.redirect; }
                                        else { error = d.message || 'Failed to create brand.'; saving = false; }
                                    }).catch(function() { error = 'Network error.'; saving = false; });
                                ">
                                <span x-text="saving ? 'Creating…' : 'Create and edit'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-admin.shell>
