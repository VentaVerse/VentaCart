<x-admin3.shell title="Brands" active="catalog">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Brands</h1>
        </div>
        <div class="v3-head__actions">
            <button type="button" class="v3-btn v3-btn--primary" @click="$dispatch('open-brand-modal')">Add brand</button>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.brands.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Brand name…"
                   aria-label="Search brands" class="v3-control v3-filterbar__grow">
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request('search'))
                <a href="{{ route('admin.brands.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Brand</th>
                            <th class="v3-cell-num">Products</th>
                            <th class="v3-cell-num">Sort</th>
                            <th>Status</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($brands as $brand)
                            <tr>
                                <td>
                                    <span class="v3-actrow">
                                        @if ($brand->image)
                                            <img class="v3-thumb" src="{{ storage_url($brand->image) }}" alt="">
                                        @endif
                                        <span>
                                            <a href="{{ route('admin.brands.edit', $brand) }}" class="v3-rowlink">{{ $brand->name }}</a>
                                            <span class="v3-cell-quiet v3-block v3-mono">{{ $brand->slug }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td class="v3-cell-num v3-mono">{{ $brand->products_count }}</td>
                                <td class="v3-cell-num v3-mono">{{ $brand->sort_order }}</td>
                                <td>
                                    <span class="v3-badge v3-badge--{{ $brand->is_active ? 'good' : 'quiet' }}">
                                        {{ $brand->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.brands.edit', $brand) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-brand-{{ $brand->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No brands found</p>
                                        <p class="v3-empty__line">{{ request('search') ? 'Try adjusting the search.' : 'Brands group the catalog by maker.' }}</p>
                                        @unless (request('search'))
                                            <button type="button" class="v3-btn v3-btn--primary v3-btn--sm" @click="$dispatch('open-brand-modal')">Create your first brand</button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$brands" />

        @foreach ($brands as $brand)
            <x-admin3.confirm name="delete-brand-{{ $brand->id }}" title="Delete {{ $brand->name }}?"
                message="Products will keep their data but lose the brand link. This cannot be undone."
                confirm="Delete" :action="route('admin.brands.destroy', $brand)" method="DELETE" />
        @endforeach

        {{-- Quick create: name only, then straight into the edit page. --}}
        <div class="v3-passthru" x-data="{ open: false, name: '', saving: false, error: '' }"
             @open-brand-modal.window="open = true; name = ''; error = ''; $nextTick(() => $refs.name.focus())"
             @keydown.escape.window="open = false">
            <template x-if="open">
                <div class="v3-overlay" @click.self="open = false">
                    <div class="v3-modal" role="dialog" aria-modal="true" aria-label="New brand">
                        <h2 class="v3-modal__title">New brand</h2>
                        <label class="v3-field v3-gap-sm">
                            <span class="v3-field__label">Brand name</span>
                            <input type="text" x-model="name" x-ref="name" class="v3-control"
                                   @keydown.enter.prevent="$refs.saveBtn.click()">
                        </label>
                        <p x-show="error" x-cloak x-text="error" class="v3-field__error"></p>
                        <div class="v3-modal__acts">
                            <button type="button" class="v3-btn v3-btn--ghost" @click="open = false">Cancel</button>
                            <button type="button" class="v3-btn v3-btn--primary" x-ref="saveBtn"
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
</x-admin3.shell>
