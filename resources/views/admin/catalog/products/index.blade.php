<x-admin3.shell title="Products" active="catalog">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Products</h1>
        </div>
        <div class="v3-head__actions">
            <button type="button" class="v3-btn v3-btn--primary" @click="$dispatch('open-product-modal')">Add product</button>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.products.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Name or SKU…"
                   aria-label="Search products" class="v3-control v3-filterbar__grow">
            <select name="category_id" aria-label="Filter by category" class="v3-control v3-w-name">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="v3-control v3-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <select name="stock" aria-label="Filter by stock" class="v3-control v3-w-name">
                <option value="">All stock</option>
                <option value="low" @selected(request('stock') === 'low')>Low stock</option>
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request('search') || request('category_id') || request('status') || request('stock'))
                <a href="{{ route('admin.products.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="v3-cell-num">Price</th>
                            <th class="v3-cell-num">Stock</th>
                            <th>Status</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <span class="v3-actrow">
                                        @if ($product->image)
                                            <img class="v3-thumb" src="{{ storage_url($product->image) }}" alt="">
                                        @else
                                            <span class="v3-thumb v3-thumb--blank"><x-admin3.icon name="image" /></span>
                                        @endif
                                        <span>
                                            <a href="{{ route('admin.products.edit', $product) }}" class="v3-rowlink">{{ $product->name }}</a>
                                            @if ($product->has_variants)
                                                <span class="v3-cell-quiet v3-block">{{ $product->variants->count() }} variants</span>
                                            @endif
                                        </span>
                                    </span>
                                </td>
                                <td class="v3-mono v3-cell-quiet">{{ $product->sku ?? '-' }}</td>
                                <td class="v3-cell-num v3-mono">
                                    @if ($product->has_variants)
                                        @php
                                            $minPrice = $product->variants->min('price');
                                            $maxPrice = $product->variants->max('price');
                                        @endphp
                                        @if ($minPrice == $maxPrice)
                                            {{ number_format($minPrice, 2) }}
                                        @else
                                            {{ number_format($minPrice, 2) }} to {{ number_format($maxPrice, 2) }}
                                        @endif
                                    @else
                                        {{ number_format($product->price, 2) }}
                                    @endif
                                </td>
                                <td class="v3-cell-num v3-mono">{{ $product->has_variants ? $product->variants->sum('quantity') : $product->quantity }}</td>
                                <td>
                                    <span class="v3-badge v3-badge--{{ $product->is_active ? 'good' : 'quiet' }}">
                                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-product-{{ $product->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No products found</p>
                                        <p class="v3-empty__line">{{ (request('search') || request('category_id') || request('status') || request('stock')) ? 'Try adjusting the filters.' : 'The catalog starts here.' }}</p>
                                        @unless (request('search') || request('category_id') || request('status') || request('stock'))
                                            <button type="button" class="v3-btn v3-btn--primary v3-btn--sm" @click="$dispatch('open-product-modal')">Create your first product</button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$products" />

        @foreach ($products as $product)
            <x-admin3.confirm name="delete-product-{{ $product->id }}" title="Delete {{ $product->name }}?"
                message="This permanently removes the product, its images, variants, and pricing. This cannot be undone."
                confirm="Delete" :action="route('admin.products.destroy', $product)" method="DELETE" />
        @endforeach

        {{-- Quick create: brand + name, then straight into the edit page. --}}
        <div class="v3-passthru" x-data="{
                open: false, name: '', brandId: '', brandSearch: '', brandResults: [], saving: false, error: '',
                async searchBrands() {
                    if (this.brandSearch.length < 1) { this.brandResults = []; return; }
                    const res = await fetch('{{ route('admin.brands.search') }}?store_id={{ config('app.store_id', 1) }}&q=' + encodeURIComponent(this.brandSearch));
                    this.brandResults = await res.json();
                },
                selectBrand(brand) {
                    this.brandId = brand.id;
                    this.brandSearch = brand.name;
                    this.brandResults = [];
                }
             }"
             @open-product-modal.window="open = true; name = ''; brandId = ''; brandSearch = ''; error = ''"
             @keydown.escape.window="open = false">
            <template x-if="open">
                <div class="v3-overlay" @click.self="open = false">
                    <div class="v3-modal" role="dialog" aria-modal="true" aria-label="New product">
                        <h2 class="v3-modal__title">New product</h2>
                        <label class="v3-field v3-gap-sm">
                            <span class="v3-field__label">Brand</span>
                            <span class="v3-combo">
                                <input type="text" x-model="brandSearch" @input.debounce.300ms="searchBrands()" @keydown.enter.prevent
                                       class="v3-control" aria-label="Search brand">
                                <span x-show="brandResults.length > 0" x-cloak class="v3-combo__menu">
                                    <template x-for="brand in brandResults" :key="brand.id">
                                        <button type="button" class="v3-combo__opt" @click="selectBrand(brand)" x-text="brand.name"></button>
                                    </template>
                                </span>
                            </span>
                        </label>
                        <label class="v3-field v3-gap-sm">
                            <span class="v3-field__label">Product name</span>
                            <input type="text" x-model="name" class="v3-control">
                        </label>
                        <p x-show="error" x-cloak x-text="error" class="v3-field__error"></p>
                        <div class="v3-modal__acts">
                            <button type="button" class="v3-btn v3-btn--ghost" @click="open = false">Cancel</button>
                            <button type="button" class="v3-btn v3-btn--primary"
                                :disabled="saving || !name.trim() || !brandId"
                                @click="
                                    saving = true; error = '';
                                    fetch('{{ route('admin.products.quick-create') }}', {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                        body: JSON.stringify({ name: name.trim(), brand_id: brandId })
                                    }).then(function(r) { return r.json(); }).then(function(d) {
                                        if (d.success) { window.location.href = d.redirect; }
                                        else { error = d.message || 'Failed to create product.'; saving = false; }
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
