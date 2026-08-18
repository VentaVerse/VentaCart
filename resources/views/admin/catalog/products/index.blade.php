<x-admin.shell title="Products" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Products</h1>
        </div>
        <div class="v-head__actions">
            <button type="button" class="v-btn v-btn--primary" @click="$dispatch('open-product-modal')">Add product</button>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.products.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Name or SKU…"
                   aria-label="Search products" class="v-control v-filterbar__grow">
            <select name="category_id" aria-label="Filter by category" class="v-control v-w-name">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="v-control v-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <select name="stock" aria-label="Filter by stock" class="v-control v-w-name">
                <option value="">All stock</option>
                <option value="low" @selected(request('stock') === 'low')>Low stock</option>
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request('search') || request('category_id') || request('status') || request('stock'))
                <a href="{{ route('admin.products.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="v-cell-num">Price</th>
                            <th class="v-cell-num">Stock</th>
                            <th>Status</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <span class="v-actrow">
                                        @if ($product->image)
                                            <img class="v-thumb" src="{{ storage_url($product->image) }}" alt="">
                                        @else
                                            <span class="v-thumb v-thumb--blank"><x-admin.icon name="image" /></span>
                                        @endif
                                        <span>
                                            <a href="{{ route('admin.products.edit', $product) }}" class="v-rowlink">{{ $product->name }}</a>
                                            @if ($product->has_variants)
                                                <span class="v-cell-quiet v-block">{{ $product->variants->count() }} variants</span>
                                            @endif
                                        </span>
                                    </span>
                                </td>
                                <td class="v-mono v-cell-quiet">{{ $product->sku ?? '-' }}</td>
                                <td class="v-cell-num v-mono">
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
                                <td class="v-cell-num v-mono">{{ $product->has_variants ? $product->variants->sum('quantity') : $product->quantity }}</td>
                                <td>
                                    <span class="v-badge v-badge--{{ $product->is_active ? 'good' : 'quiet' }}">
                                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-product-{{ $product->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No products found</p>
                                        <p class="v-empty__line">{{ (request('search') || request('category_id') || request('status') || request('stock')) ? 'Try adjusting the filters.' : 'The catalog starts here.' }}</p>
                                        @unless (request('search') || request('category_id') || request('status') || request('stock'))
                                            <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="$dispatch('open-product-modal')">Create your first product</button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$products" />

        @foreach ($products as $product)
            <x-admin.confirm name="delete-product-{{ $product->id }}" title="Delete {{ $product->name }}?"
                message="This permanently removes the product, its images, variants, and pricing. This cannot be undone."
                confirm="Delete" :action="route('admin.products.destroy', $product)" method="DELETE" />
        @endforeach

        {{-- Quick create: brand + name, then straight into the edit page. --}}
        <div class="v-passthru" x-data="{
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
                <div class="v-overlay" @click.self="open = false">
                    <div class="v-modal" role="dialog" aria-modal="true" aria-label="New product">
                        <h2 class="v-modal__title">New product</h2>
                        <label class="v-field v-gap-sm">
                            <span class="v-field__label">Brand</span>
                            <span class="v-combo">
                                <input type="text" x-model="brandSearch" @input.debounce.300ms="searchBrands()" @keydown.enter.prevent
                                       class="v-control" aria-label="Search brand">
                                <span x-show="brandResults.length > 0" x-cloak class="v-combo__menu">
                                    <template x-for="brand in brandResults" :key="brand.id">
                                        <button type="button" class="v-combo__opt" @click="selectBrand(brand)" x-text="brand.name"></button>
                                    </template>
                                </span>
                            </span>
                        </label>
                        <label class="v-field v-gap-sm">
                            <span class="v-field__label">Product name</span>
                            <input type="text" x-model="name" class="v-control">
                        </label>
                        <p x-show="error" x-cloak x-text="error" class="v-field__error"></p>
                        <div class="v-modal__acts">
                            <button type="button" class="v-btn v-btn--ghost" @click="open = false">Cancel</button>
                            <button type="button" class="v-btn v-btn--primary"
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
</x-admin.shell>
