<x-admin-layout>
    <x-slot name="header">Products</x-slot>
    <x-slot name="headerActions">
        <button type="button" @click="$dispatch('open-product-modal')" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Product
        </button>
    </x-slot>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.products.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, SKU..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <select name="category_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="status" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
                <a href="{{ route('admin.products.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Clear</a>
            </div>
        </form>
    </div>

    {{-- Products Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($products as $product)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    @if ($product->image)
                                        <img class="h-10 w-10 rounded-lg object-cover" src="{{ storage_url($product->image) }}" alt="{{ $product->name }}">
                                    @else
                                        <div class="h-10 w-10 rounded-lg bg-gray-200 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                    @if ($product->has_variants)
                                        <div class="text-xs text-indigo-600">{{ $product->variants->count() }} variants</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $product->sku ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
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
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            @if ($product->has_variants)
                                {{ $product->variants->sum('quantity') }}
                            @else
                                {{ $product->quantity }}
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $product->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right text-sm">
                            <a href="{{ route('admin.products.edit', $product) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            No products found. <a href="{{ route('admin.products.create') }}" class="text-indigo-600 hover:underline">Create your first product</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($products->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $products->links() }}
            </div>
        @endif
    </div>

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
         x-show="open" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" @click.outside="open = false" @keydown.escape.window="open = false">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">New Product</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                    <div class="relative">
                        <input type="text" x-model="brandSearch" @input.debounce.300ms="searchBrands()" @keydown.enter.prevent
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Search brand...">
                        <div x-show="brandResults.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto">
                            <template x-for="brand in brandResults" :key="brand.id">
                                <button type="button" @click="selectBrand(brand)" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50" x-text="brand.name"></button>
                            </template>
                        </div>
                    </div>
                    <p x-show="brandId" class="mt-1 text-xs text-emerald-600">Selected: <span x-text="brandSearch"></span></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                    <input type="text" x-model="name"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Boss DS-1 Distortion Pedal">
                </div>
                <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="button" :disabled="saving || !name.trim() || !brandId"
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
                    "
                    class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    <span x-show="!saving">Create & Edit</span>
                    <span x-show="saving">Creating...</span>
                </button>
            </div>
        </div>
    </div>
</x-admin-layout>
