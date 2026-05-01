@php
    $errorTab = 'general';
    if ($errors->any()) {
        $tabFields = [
            'general' => ['name', 'slug', 'sku', 'brand_id', 'description', 'date_available', 'price', 'quantity', 'weight', 'is_active', 'track_stock', 'minimum_quantity'],
            'images' => ['images_json'],
            'pricing' => ['specials'],
            'links' => ['category_ids', 'tag_ids', 'customer_group_ids'],
            'variants' => ['variants', 'variant_types'],
            'seo' => ['meta_title', 'meta_description', 'meta_keywords'],
        ];
        foreach ($tabFields as $tab => $fields) {
            foreach ($fields as $field) {
                if ($errors->has($field) || $errors->has($field . '.*')) {
                    $errorTab = $tab;
                    break 2;
                }
            }
        }
    }
@endphp
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
          novalidate
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
                        @error('brand_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
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

                {{-- Base price --}}
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Base price</h3>
                    <p class="text-xs text-gray-400 mb-4" x-show="hasVariants">
                        Variants on the next tab inherit from this base via signed modifiers (e.g. <span class="font-mono">+ ₱280</span>). Sales applied to this base ripple through every variant automatically.
                    </p>
                    <p class="text-xs text-gray-400 mb-4" x-show="!hasVariants">
                        The price customers see for this product. Sales applied here override it during the active window.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price (₱)</label>
                            <input type="number" name="price" id="price" step="0.01" min="0" required
                                x-model.number="basePrice"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="cost" class="block text-sm font-medium text-gray-700 mb-1">Cost (₱) <span class="text-gray-400 font-normal">— optional</span></label>
                            <input type="number" name="cost" id="cost" step="0.01" min="0"
                                value="{{ old('cost', $product?->cost ?? '0.00') }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-400">Internal cost for margin reporting. Not shown on storefront.</p>
                        </div>
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
                ])
            </div>

            {{-- ===================== PRICING ===================== --}}
            <div x-show="activeTab === 'pricing'" class="p-6 space-y-6">
                {{-- Quick reference back to General-tab base + variants --}}
                <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                    <svg class="w-5 h-5 text-slate-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                    </svg>
                    <div class="flex-1 text-sm">
                        <p class="text-slate-700"><span class="font-medium">Base price</span> lives on the <button type="button" @click="activeTab = 'general'" class="text-indigo-600 hover:text-indigo-800 underline">General tab</button>.
                        <span x-show="hasVariants">Per-variant modifiers live on the <button type="button" @click="activeTab = 'variants'" class="text-indigo-600 hover:text-indigo-800 underline">Variants tab</button>.</span></p>
                        <p class="text-xs text-slate-500 mt-0.5">Specials below apply to the base price; variant prices ripple via their modifiers.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
                @php
                    $allCategories = $categories->map(function($c) use ($categories) {
                        $parent = $c->parent_id ? $categories->firstWhere('id', $c->parent_id) : null;
                        return ['id' => $c->id, 'name' => $parent ? $parent->name . ' > ' . $c->name : $c->name];
                    })->sortBy('name')->values()->toArray();
                    $selectedCategoryIds = old('category_ids', $product?->categories->pluck('id')->toArray() ?? []);
                @endphp
                <div x-data="categoryPicker()">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Categories</h3>

                    {{-- Hidden inputs for form submission --}}
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="category_ids[]" :value="id">
                    </template>

                    {{-- Search input + dropdown --}}
                    <div class="relative" @click.outside="open = false">
                        <input type="text" x-model="search" @focus="open = true" @input="open = true"
                            placeholder="Search categories..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 pr-8">
                        <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>

                        {{-- Dropdown --}}
                        <div x-show="open && filtered.length > 0" x-transition
                            class="absolute z-20 mt-1 w-full max-h-48 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200">
                            <template x-for="cat in filtered" :key="cat.id">
                                <button type="button" @mousedown.prevent="add(cat)"
                                    class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                                    x-text="cat.name"></button>
                            </template>
                        </div>
                    </div>

                    @error('category_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    {{-- Selected categories list --}}
                    <div x-show="selected.length > 0" class="mt-3">
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <template x-for="cat in selectedCategories" :key="cat.id">
                                <div class="flex items-center justify-between px-3 py-2 text-sm border-b border-gray-100 last:border-b-0 hover:bg-gray-50">
                                    <span class="text-gray-700" x-text="cat.name"></span>
                                    <button type="button" @click="remove(cat.id)" class="text-gray-400 hover:text-red-500 transition-colors p-0.5" title="Remove">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1.5" x-text="selected.length + ' selected'"></p>
                    </div>
                </div>

                {{-- Tags --}}
                @php
                    $allTags = ($tags ?? collect())->map(function($t) { return ['id' => $t->id, 'name' => $t->name]; })->values()->toArray();
                    $selectedTagIds = old('tag_ids', $product?->tags->pluck('id')->toArray() ?? []);
                @endphp
                <div x-data="{
                    allItems: {{ json_encode($allTags) }},
                    selected: {{ json_encode(array_map('intval', $selectedTagIds)) }},
                    search: '',
                    open: false,
                    get filtered() {
                        return this.allItems.filter(t => !this.selected.includes(t.id) && (!this.search || t.name.toLowerCase().includes(this.search.toLowerCase())));
                    },
                    get selectedTags() {
                        return this.allItems.filter(t => this.selected.includes(t.id));
                    },
                    add(tag) { this.selected.push(tag.id); this.search = ''; this.open = false; },
                    remove(id) { this.selected = this.selected.filter(i => i !== id); }
                }">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Tags</h3>

                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="tag_ids[]" :value="id">
                    </template>

                    <div class="relative" @click.outside="open = false">
                        <input type="text" x-model="search" @focus="open = true" @input="open = true"
                            placeholder="Search tags..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 pr-8">
                        <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>

                        <div x-show="open && filtered.length > 0" x-transition
                            class="absolute z-20 mt-1 w-full max-h-48 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200">
                            <template x-for="tag in filtered" :key="tag.id">
                                <button type="button" @mousedown.prevent="add(tag)"
                                    class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                                    x-text="tag.name"></button>
                            </template>
                        </div>
                    </div>

                    <div x-show="selected.length > 0" class="flex flex-wrap gap-1.5 mt-2">
                        <template x-for="tag in selectedTags" :key="tag.id">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-full border border-indigo-200">
                                <span x-text="tag.name"></span>
                                <button type="button" @click="remove(tag.id)" class="text-indigo-400 hover:text-indigo-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </span>
                        </template>
                    </div>
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
                {{-- Variant Types (horizontal rows with inline value pills) --}}
                <div class="space-y-3 mb-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Variant Types</h3>
                        <button type="button" @click="addVariantType()"
                            :disabled="variantTypes.length >= 2"
                            :class="variantTypes.length >= 2 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-100'"
                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs bg-indigo-50 text-indigo-600 rounded-md font-medium transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add Type
                            <span x-show="variantTypes.length > 0" class="text-indigo-400" x-text="'(' + variantTypes.length + '/2)'"></span>
                        </button>
                    </div>

                    <template x-for="(type, typeIndex) in variantTypes" :key="typeIndex">
                        <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50/50">
                            {{-- Type name --}}
                            <div class="shrink-0 w-36">
                                <input type="text" x-model="type.name" :name="'variant_types['+typeIndex+'][name]'" placeholder="e.g. Color, Size"
                                    class="w-full rounded-md border-gray-300 text-sm py-1.5 font-medium focus:border-indigo-500 focus:ring-indigo-500" @input="generateCombinations()">
                            </div>

                            {{-- Values as pills --}}
                            <div class="flex-1 flex flex-wrap items-center gap-1.5">
                                <template x-for="(value, valueIndex) in type.values" :key="valueIndex">
                                    <div class="inline-flex items-center gap-0.5 bg-white border border-gray-300 rounded-md overflow-hidden">
                                        <input type="text" x-model="type.values[valueIndex]" :name="'variant_types['+typeIndex+'][values]['+valueIndex+']'" placeholder="Value"
                                            class="w-36 border-0 text-sm py-1 px-2 focus:ring-0" @input="generateCombinations()">
                                        <button type="button" @click="type.values.splice(valueIndex, 1); generateCombinations()"
                                            class="px-1.5 py-1 text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="type.values.push('')"
                                    class="inline-flex items-center gap-0.5 px-2 py-1 text-[11px] text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-md transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    value
                                </button>
                            </div>

                            {{-- Remove type --}}
                            <button type="button" @click="removeVariantType(typeIndex)" class="shrink-0 p-1 text-gray-400 hover:text-red-500 transition-colors" title="Remove type">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Generated Variant Combinations --}}
                <div x-show="variants.length > 0">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Combinations <span class="text-gray-300" x-text="'(' + variants.length + ')'"></span></h3>
                    </div>
                    <div class="rounded-lg border border-gray-200 overflow-hidden variant-table">
                        {{-- Header --}}
                        <div class="flex bg-gray-50 border-b border-gray-200">
                            <div class="w-32 shrink-0 px-3 py-2.5 text-xs font-medium text-gray-500 uppercase">Variant</div>
                            <div class="flex-1 px-3 py-2.5 text-xs font-medium text-gray-500 uppercase">SKU</div>
                            <div class="w-56 shrink-0 px-3 py-2.5 text-xs font-medium text-gray-500 uppercase">Price modifier (₱)</div>
                            <div class="w-20 shrink-0 px-3 py-2.5 text-xs font-medium text-gray-500 uppercase">Stock</div>
                            <div class="w-20 shrink-0 px-3 py-2.5 text-xs font-medium text-gray-500 uppercase">Weight</div>
                            <div class="w-10 shrink-0 px-2 py-2.5 text-xs font-medium text-gray-500 uppercase text-center">On</div>
                        </div>

                        {{-- Rows --}}
                        <template x-for="(variant, vIndex) in variants" :key="vIndex">
                            <div class="border-b border-gray-100 last:border-b-0">
                                {{-- Main row --}}
                                <div class="flex items-center hover:bg-gray-50">
                                    <div class="w-32 shrink-0 px-3 py-2">
                                        <span class="text-sm font-medium text-gray-900" x-text="variant.label"></span>
                                        <template x-for="(key, kIndex) in variant.value_keys" :key="kIndex">
                                            <input type="hidden" :name="'variants['+vIndex+'][value_keys]['+kIndex+']'" :value="key">
                                        </template>
                                    </div>
                                    <div class="flex-1 px-2 py-1.5">
                                        <input type="text" x-model="variant.sku" :name="'variants['+vIndex+'][sku]'"
                                            :class="isSkuDuplicate(variant.sku, vIndex) ? 'ring-1 ring-red-500 border-red-400' : 'border-gray-300'"
                                            class="w-full rounded-md text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500" placeholder="SKU">
                                    </div>
                                    <div class="w-56 shrink-0 px-2 py-1.5">
                                        <div class="flex items-center gap-1">
                                            <select x-model="variant.modifier_sign" :name="'variants['+vIndex+'][modifier_sign]'"
                                                class="w-12 rounded-md border-gray-300 text-sm py-1 px-1 focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="+">+</option>
                                                <option value="-">−</option>
                                            </select>
                                            <input type="number" x-model="variant.modifier_amount" :name="'variants['+vIndex+'][modifier_amount]'" step="0.01" min="0"
                                                placeholder="0.00"
                                                class="w-20 rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                            <span class="text-xs text-gray-400">=</span>
                                            <span class="text-sm font-medium text-gray-700 tabular-nums" x-text="'₱' + variantTotalPrice(variant).toFixed(2)"></span>
                                            {{-- Hidden price field — server keeps the absolute value in `price` for API/ERP compat --}}
                                            <input type="hidden" :name="'variants['+vIndex+'][price]'" :value="variantTotalPrice(variant).toFixed(2)">
                                        </div>
                                    </div>
                                    <div class="w-20 shrink-0 px-2 py-1.5">
                                        <input type="number" x-model.number="variant.quantity" :name="'variants['+vIndex+'][quantity]'" min="0"
                                            class="w-full rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div class="w-20 shrink-0 px-2 py-1.5">
                                        <input type="number" x-model="variant.weight" :name="'variants['+vIndex+'][weight]'" step="0.001" min="0"
                                            class="w-full rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500" placeholder="kg">
                                    </div>
                                    <div class="w-10 shrink-0 px-2 py-1.5 text-center">
                                        <input type="checkbox" x-model="variant.is_active" :name="'variants['+vIndex+'][is_active]'"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5">
                                    </div>
                                </div>

                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="variantTypes.length === 0 && variants.length === 0" class="text-center py-12">
                    <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <p class="text-sm text-gray-400">No variants configured</p>
                    <p class="text-xs text-gray-300 mt-1">This product uses a single price and stock quantity</p>
                </div>
            </div>

            {{-- ===================== SEO ===================== --}}
            <div x-show="activeTab === 'seo'" class="p-6">
                <div class="space-y-4 max-w-2xl">
                    <div>
                        <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-gray-400">/product/</span>
                            <input type="text" name="slug" id="slug" value="{{ old('slug', $product?->slug) }}"
                                class="flex-1 rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="auto-generated-from-name">
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Leave blank to auto-generate from name</p>
                        @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
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

                    {{-- AI SEO Optimizer --}}
                    <div x-data="{
                        loading: false,
                        suggestion: null,
                        error: null,
                        async optimize() {
                            this.loading = true;
                            this.error = null;
                            this.suggestion = null;
                            try {
                                const res = await fetch('{{ route("admin.extensions.seo.optimize") }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                    body: JSON.stringify({ entity_type: 'product', entity_id: {{ $product->id ?? 0 }} })
                                });
                                const data = await res.json();
                                if (data.ok) {
                                    this.suggestion = data.suggestion;
                                } else {
                                    this.error = data.message || 'Failed to generate suggestion.';
                                }
                            } catch (e) {
                                this.error = 'Request failed. Check your Claude API key in Extensions > SEO > Settings.';
                            }
                            this.loading = false;
                        },
                        apply() {
                            if (!this.suggestion) return;
                            const s = this.suggestion;
                            const mt = document.querySelector('[name=meta_title]');
                            const md = document.querySelector('[name=meta_description]');
                            if (mt && s.suggested_meta_title) mt.value = s.suggested_meta_title;
                            if (md && s.suggested_meta_description) md.value = s.suggested_meta_description;
                            this.suggestion = null;
                        }
                    }" class="mt-4 pt-4 border-t border-gray-100">
                        @if ($product ?? false)
                            <button type="button" @click="optimize()" :disabled="loading"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 disabled:opacity-50 transition-colors">
                                <template x-if="!loading">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                                </template>
                                <template x-if="loading">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                </template>
                                <span x-text="loading ? 'Optimizing...' : 'Optimize with AI'"></span>
                            </button>
                        @else
                            <p class="text-xs text-gray-400">Save the product first to use AI optimization.</p>
                        @endif

                        <div x-show="error" x-cloak class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700" x-text="error"></div>

                        <div x-show="suggestion" x-cloak class="mt-3 bg-purple-50 border border-purple-200 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-purple-800 mb-3">AI Suggestion Preview</h4>
                            <div class="space-y-3">
                                <template x-for="field in ['meta_title', 'meta_description', 'og_title', 'og_description']" :key="field">
                                    <div>
                                        <label class="text-xs font-medium text-gray-500 uppercase" x-text="field.replace(/_/g, ' ')"></label>
                                        <div class="grid grid-cols-2 gap-2 mt-1">
                                            <div class="bg-white rounded p-2 text-xs">
                                                <span class="text-gray-400 block mb-0.5">Current:</span>
                                                <span x-text="suggestion['current_' + field] || '(empty)'"></span>
                                            </div>
                                            <div class="bg-white rounded p-2 text-xs border-l-2 border-purple-400">
                                                <span class="text-purple-600 block mb-0.5">Suggested:</span>
                                                <span x-text="suggestion['suggested_' + field] || '(empty)'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="flex gap-2 mt-4">
                                <button type="button" @click="apply()" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">Apply to Form</button>
                                <button type="button" @click="suggestion = null" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300">Dismiss</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="border-t border-gray-200 px-6 py-3 flex justify-end space-x-3">
                <a href="{{ route('admin.products.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $product ? 'Update Product' : 'Create Product' }}
                </button>
            </div>
        </div>
    </form>

    @push('scripts')
    <style>
        /* Hide number input spinners in variant table */
        .variant-table input[type="number"]::-webkit-inner-spin-button,
        .variant-table input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .variant-table input[type="number"] { -moz-appearance: textfield; }
    </style>
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

        window.__allCategories = @json($allCategories);
        window.__selectedCategoryIds = @json(array_map('intval', $selectedCategoryIds));

        function categoryPicker() {
            return {
                search: '',
                open: false,
                allCategories: window.__allCategories,
                selected: [...window.__selectedCategoryIds],
                get filtered() {
                    const sel = this.selected;
                    if (!this.search) return this.allCategories.filter(function(c) { return sel.indexOf(c.id) === -1; });
                    const q = this.search.toLowerCase();
                    return this.allCategories.filter(function(c) { return sel.indexOf(c.id) === -1 && c.name.toLowerCase().indexOf(q) !== -1; });
                },
                get selectedCategories() {
                    const sel = this.selected;
                    return this.allCategories.filter(function(c) { return sel.indexOf(c.id) !== -1; });
                },
                add(cat) { this.selected.push(cat.id); this.search = ''; this.open = false; },
                remove(id) { this.selected = this.selected.filter(function(i) { return i !== id; }); }
            };
        }

        function productForm() {
            const existingTypes = @json($existingTypes);
            const existingVariants = @json($existingVariants);
            const existingSpecials = @json($existingSpecials);

            return {
                activeTab: '{{ $errorTab ?? 'general' }}',
                tabs: [
                    { id: 'general', label: 'General' },
                    { id: 'images', label: 'Images' },
                    { id: 'links', label: 'Links' },
                    { id: 'variants', label: 'Variants' },
                    { id: 'pricing', label: 'Pricing' },
                    { id: 'seo', label: 'SEO' },
                ],
                variantTypes: existingTypes,
                variants: existingVariants,
                specials: existingSpecials,
                basePrice: parseFloat(@json(old('price', $product?->price ?? 0))) || 0,

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
                    if (this.variantTypes.length >= 2) return;
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
                            group_prices: existing?.group_prices || {},
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

                // Variant price = base + signed modifier. basePrice is reactive
                // (x-model.number on the General-tab Price input), so the read-only
                // totals on the Variants tab tick live as the admin types.
                variantTotalPrice(variant) {
                    const sign = variant.modifier_sign === '-' ? -1 : 1;
                    const amount = parseFloat(variant.modifier_amount) || 0;
                    const total = (this.basePrice || 0) + (sign * amount);
                    return Math.max(0, total);
                },

                init() {
                    if (this.variantTypes.length > 0) {
                        this.generateCombinations();
                    }
                    // Default sign/amount on any variant rows that lack them
                    // (newly-generated variants from the matrix builder).
                    this.variants.forEach(v => {
                        if (v.modifier_sign === undefined) v.modifier_sign = '+';
                        if (v.modifier_amount === undefined) v.modifier_amount = '0.00';
                    });
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>
