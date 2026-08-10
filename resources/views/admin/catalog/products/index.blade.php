<x-admin.shell title="Products">
    <x-admin.page title="Products">
        <x-slot:actions>
            <x-admin.button type="button" variant="primary" @click="$dispatch('open-product-modal')">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Product
            </x-admin.button>
        </x-slot:actions>

        <div>
            <x-admin.filters :action="route('admin.products.index')">
                <x-admin.field name="search" label="Search" class="v-field--grow">
                    <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search name, SKU..." />
                </x-admin.field>
                <x-admin.field name="category_id" label="Category" class="w-52">
                    <x-admin.select name="category_id">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.field name="status" label="Status" class="w-44">
                    <x-admin.select name="status">
                        <option value="">All Status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </x-admin.select>
                </x-admin.field>
                <x-admin.field name="stock" label="Stock" class="w-44">
                    <x-admin.select name="stock">
                        <option value="">All stock</option>
                        <option value="low" @selected(request('stock') === 'low')>Low stock</option>
                    </x-admin.select>
                </x-admin.field>
                <x-admin.button type="submit" variant="secondary">Filter</x-admin.button>
                @if (request('search') || request('category_id') || request('status') || request('stock'))
                    <a href="{{ route('admin.products.index') }}" class="v-filters__reset">Clear</a>
                @endif
            </x-admin.filters>

            {{-- Products --}}
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Product</x-admin.table.th>
                    <x-admin.table.th>SKU</x-admin.table.th>
                    <x-admin.table.th align="right">Price</x-admin.table.th>
                    <x-admin.table.th align="right">Stock</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($products as $product)
                    <x-admin.table.row href="{{ route('admin.products.edit', $product) }}">
                        <x-admin.table.cell>
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-v-md bg-v-sunken">
                                    @if ($product->image)
                                        <img class="h-10 w-10 object-cover" src="{{ storage_url($product->image) }}" alt="{{ $product->name }}">
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center text-v-muted">
                                            <x-admin.icon name="media" class="w-5 h-5" />
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-v-text">{{ $product->name }}</div>
                                    @if ($product->has_variants)
                                        <div class="text-xs text-v-accent">{{ $product->variants->count() }} variants</div>
                                    @endif
                                </div>
                            </div>
                        </x-admin.table.cell>
                        <x-admin.table.cell mono>{{ $product->sku ?? '-' }}</x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>
                            @if ($product->has_variants)
                                @php
                                    $minPrice = $product->variants->min('price');
                                    $maxPrice = $product->variants->max('price');
                                @endphp
                                @if ($minPrice == $maxPrice)
                                    {{ number_format($minPrice, 2) }}
                                @else
                                    {{ number_format($minPrice, 2) }} - {{ number_format($maxPrice, 2) }}
                                @endif
                            @else
                                {{ number_format($product->price, 2) }}
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>
                            @if ($product->has_variants)
                                {{ $product->variants->sum('quantity') }}
                            @else
                                {{ $product->quantity }}
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$product->is_active ? 'success' : 'neutral'">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.products.edit', $product)">Edit</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-product-{{ $product->id }}')">Delete</x-admin.button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-admin.empty-state title="No products found"
                                :description="(request('search') || request('category_id') || request('status') || request('stock')) ? 'Try adjusting your filters.' : null">
                                <x-admin.button variant="primary" :href="route('admin.products.create')">Create your first product</x-admin.button>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($products->hasPages())
                    <x-slot:footer>
                        {{ $products->links() }}
                    </x-slot:footer>
                @endif
            </x-admin.table>
        </div>

        @foreach ($products as $product)
            <x-admin.confirm-dialog name="delete-product-{{ $product->id }}" title="Delete {{ $product->name }}?"
                message="This permanently removes the product, its images, variants, and pricing. This cannot be undone."
                confirm="Delete" :action="route('admin.products.destroy', $product)" method="DELETE" />
        @endforeach

        {{-- Quick Create Product Modal --}}
        <div x-data="{
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
             x-show="open" x-cloak x-transition.opacity
             class="fixed inset-0 z-[90] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
            <div class="v-modal relative w-full max-w-md rounded-v-lg border border-v-border bg-v-surface p-6"
                 @click.outside="open = false" @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-label="New Product">
                <h3 class="mb-4 text-[15px] font-semibold text-v-text">New Product</h3>
                <div class="space-y-4">
                    <div class="v-field">
                        <label for="quick-product-brand" class="v-field__label v-label">Brand</label>
                        <div class="relative">
                            <input type="text" id="quick-product-brand" x-model="brandSearch" @input.debounce.300ms="searchBrands()" @keydown.enter.prevent
                                class="v-control h-[var(--control-h)] w-full rounded-v-md border border-v-border bg-v-surface px-3 text-[13px] text-v-text placeholder:text-v-muted"
                                placeholder="Search brand...">
                            <div x-show="brandResults.length > 0" x-cloak class="v-menu absolute z-10 mt-1 max-h-40 w-full overflow-y-auto rounded-v-md border border-v-border bg-v-surface">
                                <template x-for="brand in brandResults" :key="brand.id">
                                    <button type="button" @click="selectBrand(brand)" class="block w-full px-3 py-2 text-left text-[13px] text-v-text hover:bg-v-hover" x-text="brand.name"></button>
                                </template>
                            </div>
                        </div>
                        <p x-show="brandId" x-cloak class="v-field__hint">Selected: <span x-text="brandSearch"></span></p>
                    </div>
                    <div class="v-field">
                        <label for="quick-product-name" class="v-field__label v-label">Product Name</label>
                        <input type="text" id="quick-product-name" x-model="name"
                            class="v-control h-[var(--control-h)] w-full rounded-v-md border border-v-border bg-v-surface px-3 text-[13px] text-v-text placeholder:text-v-muted"
                            placeholder="e.g. Boss DS-1 Distortion Pedal">
                    </div>
                    <p x-show="error" x-cloak x-text="error" class="v-field__error"></p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-admin.button type="button" variant="secondary" @click="open = false">Cancel</x-admin.button>
                    <x-admin.button type="button" variant="primary"
                        x-bind:disabled="saving || !name.trim() || !brandId"
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
                        <span x-show="!saving">Create &amp; Edit</span>
                        <span x-show="saving" x-cloak>Creating...</span>
                    </x-admin.button>
                </div>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
