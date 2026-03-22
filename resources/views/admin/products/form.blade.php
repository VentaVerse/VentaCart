<x-admin-layout>
    <x-slot name="header">{{ $product ? 'Edit Product' : 'Create Product' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.products.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Products
        </a>
    </x-slot>

    <form method="POST"
          action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}"
          enctype="multipart/form-data"
          x-data="productForm()"
          class="space-y-6">
        @csrf
        @if ($product)
            @method('PUT')
        @endif

        {{-- Tab Navigation --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                <template x-for="t in tabs" :key="t.id">
                    <button type="button" @click="activeTab = t.id"
                        :class="activeTab === t.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors"
                        x-text="t.label"></button>
                </template>
            </nav>

            {{-- ===================== GENERAL ===================== --}}
            <div x-show="activeTab === 'general'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="store_id" id="store_id" value="1">

                    <div x-data="brandSearch()" x-init="init()">
                        <label for="brand_search" class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                        <div class="relative">
                            <input type="text" id="brand_search" x-model="search" @input.debounce.300ms="fetchResults()" @focus="open = true" @click.away="open = false"
                                placeholder="Search brand..." autocomplete="off"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <input type="hidden" name="brand_id" :value="selectedId">
                            <div x-show="open && results.length > 0" x-cloak class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                <template x-for="item in results" :key="item.id">
                                    <button type="button" @click="select(item)" class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors" x-text="item.name"></button>
                                </template>
                            </div>
                            <div x-show="open && search.length > 0 && results.length === 0 && !loading" x-cloak class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg px-3 py-2 text-sm text-gray-400">
                                No brands found for this store.
                            </div>
                        </div>
                        <div x-show="selectedName" class="mt-1 flex items-center">
                            <span class="text-xs text-indigo-600" x-text="selectedName"></span>
                            <button type="button" @click="clear()" class="ml-1 text-gray-400 hover:text-red-500">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Images are organized by brand folder.</p>
                        @error('brand_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                        <input type="text" name="name" id="name" required value="{{ old('name', $product?->name) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="sku" class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                        <input type="text" name="sku" id="sku" value="{{ old('sku', $product?->sku) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('sku') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="model" class="block text-sm font-medium text-gray-700 mb-1">Model</label>
                        <input type="text" name="model" id="model" value="{{ old('model', $product?->model) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="date_available" class="block text-sm font-medium text-gray-700 mb-1">Date Available</label>
                        <input type="date" name="date_available" id="date_available" value="{{ old('date_available', $product?->date_available?->format('Y-m-d')) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-400">Leave blank for immediately available</p>
                        @error('date_available') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="description" rows="12"
                            class="tinymce-editor w-full rounded-lg border-gray-300 text-sm">{{ old('description', $product?->description) }}</textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Shipping & Dimensions --}}
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Shipping & Dimensions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                        <div>
                            <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Weight</label>
                            <input type="number" name="weight" id="weight" step="0.001" min="0" value="{{ old('weight', $product?->weight ?? '0.000') }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="weight_unit" class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                            <select name="weight_unit" id="weight_unit" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (['kg' => 'kg', 'g' => 'g', 'lb' => 'lb', 'oz' => 'oz'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('weight_unit', $product?->weight_unit ?? 'kg') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="length" class="block text-sm font-medium text-gray-700 mb-1">Length (cm)</label>
                            <input type="number" name="length" id="length" step="0.01" min="0" value="{{ old('length', $product?->length ?? '0.00') }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="width" class="block text-sm font-medium text-gray-700 mb-1">Width (cm)</label>
                            <input type="number" name="width" id="width" step="0.01" min="0" value="{{ old('width', $product?->width ?? '0.00') }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="height" class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
                            <input type="number" name="height" id="height" step="0.01" min="0" value="{{ old('height', $product?->height ?? '0.00') }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Status --}}
                <div class="border-t border-gray-200 pt-6">
                    <div class="flex items-center space-x-8">
                        <label class="flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product?->is_active ?? true) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                        <label class="flex items-center">
                            <input type="hidden" name="track_stock" value="0">
                            <input type="checkbox" name="track_stock" value="1" {{ old('track_stock', $product?->track_stock ?? true) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Track Stock</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ===================== IMAGES ===================== --}}
            <div x-show="activeTab === 'images'" class="p-0">
                @include('admin.products.partials.image_manager', [
                    'existingImages' => $existingImages,
                    'pimToken' => $pimToken,
                    'brandFolder' => $brandFolder ?? '',
                ])
            </div>

            {{-- ===================== PRICING ===================== --}}
            <div x-show="activeTab === 'pricing'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div x-show="!hasVariants">
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                        <input type="number" name="price" id="price" step="0.01" min="0" value="{{ old('price', $product?->price ?? '0.00') }}"
                            :required="!hasVariants"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">
                            Quantity
                            <span x-show="hasVariants && totalVariantQty === 0" class="text-red-500 font-normal">(Out of Stock)</span>
                        </label>
                        <input type="number" name="quantity" id="quantity" min="0"
                            :value="hasVariants ? totalVariantQty : '{{ old('quantity', $product?->quantity ?? 0) }}'"
                            :readonly="hasVariants"
                            :class="hasVariants ? 'bg-gray-100 text-gray-500' : ''"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p x-show="hasVariants" class="mt-1 text-xs text-gray-400">Computed from variant quantities</p>
                        @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="minimum_quantity" class="block text-sm font-medium text-gray-700 mb-1">Min Order Qty</label>
                        <input type="number" name="minimum_quantity" id="minimum_quantity" min="1" value="{{ old('minimum_quantity', $product?->minimum_quantity ?? 1) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Special Pricing --}}
                <div class="border-t border-gray-200 pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Special Pricing</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Lower priority number = higher precedence.</p>
                        </div>
                        <button type="button" @click="addSpecial()" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Special
                        </button>
                    </div>

                    <div x-show="specials.length === 0" class="text-sm text-gray-400 py-4 text-center">
                        No special pricing rules.
                    </div>

                    <div x-show="specials.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer Group</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Display</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Start</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">End</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(special, sIndex) in specials" :key="sIndex">
                                    <tr>
                                        <td class="px-3 py-2">
                                            <select x-model="special.customer_group_id" :name="'specials['+sIndex+'][customer_group_id]'"
                                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">All Groups</option>
                                                @foreach ($customerGroups as $group)
                                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" x-model="special.price" :name="'specials['+sIndex+'][price]'" step="0.01" min="0" required
                                                class="w-28 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" x-model="special.quantity" :name="'specials['+sIndex+'][quantity]'" min="1"
                                                class="w-16 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <select x-model="special.display_mode" :name="'specials['+sIndex+'][display_mode]'"
                                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="sale">Sale (slashed)</option>
                                                <option value="override">Override (clean)</option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" x-model="special.priority" :name="'specials['+sIndex+'][priority]'" min="0"
                                                class="w-16 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="date" x-model="special.date_start" :name="'specials['+sIndex+'][date_start]'"
                                                class="w-36 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="date" x-model="special.date_end" :name="'specials['+sIndex+'][date_end]'"
                                                class="w-36 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <button type="button" @click="specials.splice(sIndex, 1)" class="text-red-500 hover:text-red-700">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ===================== LINKS ===================== --}}
            <div x-show="activeTab === 'links'" class="p-6 space-y-6">
                {{-- Categories --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Categories</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        @foreach ($categories as $category)
                            <label class="flex items-center">
                                <input type="checkbox" name="category_ids[]" value="{{ $category->id }}"
                                    {{ in_array($category->id, old('category_ids', $product?->categories->pluck('id')->toArray() ?? [])) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">{{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('category_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Customer Group Visibility --}}
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Customer Group Visibility</h3>
                    <p class="text-xs text-gray-400 mb-3">If none are checked, the product is visible to everyone. If checked, only logged-in customers in those groups can see it.</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        @foreach ($customerGroups as $group)
                            <label class="flex items-center">
                                <input type="checkbox" name="customer_group_ids[]" value="{{ $group->id }}"
                                    {{ in_array($group->id, old('customer_group_ids', $product?->customerGroups->pluck('id')->toArray() ?? [])) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">{{ $group->name }}</span>
                                @if ($group->is_default)
                                    <span class="ml-1 text-xs text-gray-400">(default)</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ===================== VARIANTS ===================== --}}
            <div x-show="activeTab === 'variants'" class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-gray-500">Add variant types (e.g. Color, Size) and their values to generate combinations.</p>
                    <button type="button" @click="addVariantType()" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Variant Type
                    </button>
                </div>

                {{-- Variant Types --}}
                <template x-for="(type, typeIndex) in variantTypes" :key="typeIndex">
                    <div class="border border-gray-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex-1 mr-4">
                                <input type="text" x-model="type.name" :name="'variant_types['+typeIndex+'][name]'" placeholder="Type name (e.g., Color, Size)"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" @input="generateCombinations()">
                            </div>
                            <button type="button" @click="removeVariantType(typeIndex)" class="text-red-500 hover:text-red-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="(value, valueIndex) in type.values" :key="valueIndex">
                                <div class="flex items-center">
                                    <input type="text" x-model="type.values[valueIndex]" :name="'variant_types['+typeIndex+'][values]['+valueIndex+']'" placeholder="Value"
                                        class="w-32 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" @input="generateCombinations()">
                                    <button type="button" @click="type.values.splice(valueIndex, 1); generateCombinations()" class="ml-1 text-red-400 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="type.values.push('')" class="inline-flex items-center px-3 py-1.5 border border-dashed border-gray-300 text-sm text-gray-500 rounded-lg hover:border-indigo-500 hover:text-indigo-500">
                                + Add Value
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Generated Variant Combinations --}}
                <div x-show="variants.length > 0" class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Variant Combinations (<span x-text="variants.length"></span>)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Combination</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Weight (kg)</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(variant, vIndex) in variants" :key="vIndex">
                                    <tr>
                                        <td class="px-3 py-2 text-sm text-gray-900" x-text="variant.label"></td>
                                        <td class="px-3 py-2">
                                            <input type="text" x-model="variant.sku" :name="'variants['+vIndex+'][sku]'"
                                                :class="isSkuDuplicate(variant.sku, vIndex) ? 'ring-2 ring-red-500 border-red-500' : 'border-gray-300'"
                                                class="w-40 rounded-lg text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <template x-for="(key, kIndex) in variant.value_keys" :key="kIndex">
                                                <input type="hidden" :name="'variants['+vIndex+'][value_keys]['+kIndex+']'" :value="key">
                                            </template>
                                            <p x-show="isSkuDuplicate(variant.sku, vIndex)" class="text-xs text-red-600 mt-0.5">Duplicate SKU</p>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" x-model="variant.price" :name="'variants['+vIndex+'][price]'" step="0.01" min="0"
                                                class="w-28 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" x-model.number="variant.quantity" :name="'variants['+vIndex+'][quantity]'" min="0"
                                                class="w-20 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" x-model="variant.weight" :name="'variants['+vIndex+'][weight]'" step="0.001" min="0"
                                                class="w-24 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="0.000">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="checkbox" x-model="variant.is_active" :name="'variants['+vIndex+'][is_active]'"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div x-show="variantTypes.length === 0 && variants.length === 0" class="text-sm text-gray-400 py-8 text-center">
                    No variants. This product will use a single price and stock quantity.
                </div>
            </div>

            {{-- ===================== SEO ===================== --}}
            <div x-show="activeTab === 'seo'" class="p-6">
                <div class="space-y-4 max-w-2xl">
                    <div>
                        <label for="meta_title" class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label>
                        <input type="text" name="meta_title" id="meta_title" value="{{ old('meta_title', $product?->meta_title) }}" maxlength="255"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="meta_description" class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label>
                        <textarea name="meta_description" id="meta_description" rows="3" maxlength="500"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('meta_description', $product?->meta_description) }}</textarea>
                    </div>
                    <div>
                        <label for="meta_keywords" class="block text-sm font-medium text-gray-700 mb-1">Meta Keywords</label>
                        <input type="text" name="meta_keywords" id="meta_keywords" value="{{ old('meta_keywords', $product?->meta_keywords) }}" maxlength="255"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end space-x-3">
            <a href="{{ route('admin.products.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                {{ $product ? 'Update Product' : 'Create Product' }}
            </button>
        </div>
    </form>

    @push('scripts')
    {{-- TinyMCE dialog input borders — must load after TinyMCE's skin --}}
    <style>
        .tox .tox-dialog .tox-textfield,
        .tox .tox-dialog .tox-textarea,
        .tox .tox-dialog .tox-selectfield select,
        .tox.tox-tinymce-aux .tox-textfield,
        .tox.tox-tinymce-aux .tox-textarea {
            border: 1px solid #999 !important;
            border-radius: 4px !important;
        }
        .tox .tox-dialog .tox-textfield:focus,
        .tox .tox-dialog .tox-textarea:focus,
        .tox.tox-tinymce-aux .tox-textfield:focus,
        .tox.tox-tinymce-aux .tox-textarea:focus {
            border-color: #4f46e5 !important;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15) !important;
        }
    </style>
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '.tinymce-editor',
            height: 350,
            menubar: false,
            plugins: 'lists link autolink code table media',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link table media | removeformat code',
            media_live_embeds: true,
            media_alt_source: false,
            media_poster: false,
            content_style: 'body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 14px; }',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            relative_urls: false,
            remove_script_host: true,
        });

        function brandSearch() {
            return {
                search: '',
                selectedId: '{{ old('brand_id', $product?->brand_id ?? '') }}',
                selectedName: '{{ old('brand_id', $product?->brand_id) ? ($product?->brand?->name ?? '') : '' }}',
                results: [],
                open: false,
                loading: false,

                init() {
                    if (this.selectedName) {
                        this.search = this.selectedName;
                    }
                },

                async fetchResults() {
                    if (this.search.length < 1) {
                        this.results = [];
                        return;
                    }

                    this.loading = true;
                    try {
                        const response = await fetch(`{{ route('admin.brands.search') }}?store_id=1&q=${encodeURIComponent(this.search)}`);
                        this.results = await response.json();
                    } catch (e) {
                        this.results = [];
                    }
                    this.loading = false;
                    this.open = true;
                },

                select(item) {
                    this.selectedId = item.id;
                    this.selectedName = item.name;
                    this.search = item.name;
                    this.open = false;
                },

                clear() {
                    this.selectedId = '';
                    this.selectedName = '';
                    this.search = '';
                    this.results = [];
                }
            };
        }

        function productForm() {
            const existingTypes = @json($existingTypes);
            const existingVariants = @json($existingVariants);
            const existingSpecials = @json($existingSpecials);

            return {
                activeTab: 'general',
                tabs: [
                    { id: 'general', label: 'General' },
                    { id: 'images', label: 'Images' },
                    { id: 'pricing', label: 'Pricing' },
                    { id: 'links', label: 'Links' },
                    { id: 'variants', label: 'Variants' },
                    { id: 'seo', label: 'SEO' },
                ],
                variantTypes: existingTypes,
                variants: existingVariants,
                specials: existingSpecials,

                get hasVariants() {
                    return this.variants.length > 0;
                },

                get totalVariantQty() {
                    return this.variants.reduce((sum, v) => sum + (parseInt(v.quantity) || 0), 0);
                },

                addSpecial() {
                    this.specials.push({
                        customer_group_id: '',
                        price: '',
                        quantity: 1,
                        display_mode: 'sale',
                        priority: this.specials.length,
                        date_start: '',
                        date_end: '',
                    });
                },

                addVariantType() {
                    this.variantTypes.push({ name: '', values: [''] });
                },

                removeVariantType(index) {
                    this.variantTypes.splice(index, 1);
                    this.generateCombinations();
                },

                generateCombinations() {
                    const types = this.variantTypes.filter(t => t.name && t.values.some(v => v));

                    if (types.length === 0) {
                        this.variants = [];
                        return;
                    }

                    const valueSets = types.map((t, ti) =>
                        t.values
                            .map((v, vi) => ({ value: v, key: ti + '_' + vi }))
                            .filter(v => v.value)
                    );

                    const combinations = this.cartesian(valueSets);

                    const oldVariants = {};
                    this.variants.forEach(v => { oldVariants[v.label] = v; });

                    this.variants = combinations.map(combo => {
                        const label = combo.map(c => c.value).join(' / ');
                        const existing = oldVariants[label];
                        return {
                            label: label,
                            value_keys: combo.map(c => c.key),
                            sku: existing?.sku || '',
                            price: existing?.price || 0,
                            quantity: existing?.quantity || 0,
                            weight: existing?.weight || '',
                            is_active: existing?.is_active ?? true,
                        };
                    });
                },

                cartesian(arrays) {
                    if (arrays.length === 0) return [];
                    return arrays.reduce((acc, curr) => {
                        const result = [];
                        acc.forEach(a => {
                            curr.forEach(b => {
                                result.push([...a, b]);
                            });
                        });
                        return result;
                    }, [[]]);
                },

                isSkuDuplicate(sku, currentIndex) {
                    if (!sku) return false;
                    const parentSku = document.getElementById('sku')?.value || '';
                    if (parentSku && sku === parentSku) return true;
                    return this.variants.some((v, i) => i !== currentIndex && v.sku && v.sku === sku);
                },

                init() {
                    if (this.variantTypes.length > 0) {
                        this.generateCombinations();
                    }
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>
