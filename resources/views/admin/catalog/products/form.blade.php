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
    $vInput = 'v-control w-full h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md placeholder:text-v-muted';
    $vSelect = 'v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md';
    $vInputSm = 'v-control h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md';
    $vTextarea = 'v-control w-full px-3 py-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md placeholder:text-v-muted';
    $vLabel = 'block text-[13px] font-medium text-v-text mb-1';
    $vCheck = 'w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface';
@endphp
<x-admin.shell title="{{ $product ? 'Edit Product' : 'Create Product' }}">
    <x-admin.page :title="$product ? 'Edit Product' : 'Create Product'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.products.index') }}">Back to Products</x-admin.button>
        </x-slot:actions>

        <form method="POST"
              action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}"
              enctype="multipart/form-data"
              x-data="productForm()"
              data-v-form
              novalidate
              class="space-y-6">
            @csrf
            @if ($product)
                @method('PUT')
            @endif

            <x-admin.tabs default="{{ $errorTab }}" param="tab" :names="['general', 'images', 'links', 'variants', 'pricing', 'seo']">
                <x-slot:nav>
                    <x-admin.tabs.tab name="general">General</x-admin.tabs.tab>
                    <x-admin.tabs.tab name="images">Images</x-admin.tabs.tab>
                    <x-admin.tabs.tab name="links">Links</x-admin.tabs.tab>
                    <x-admin.tabs.tab name="variants">Variants <span x-show="variants.length > 0" x-text="'(' + variants.length + ')'"></span></x-admin.tabs.tab>
                    <x-admin.tabs.tab name="pricing">Pricing <span x-show="specials.length > 0" x-text="'(' + specials.length + ')'"></span></x-admin.tabs.tab>
                    <x-admin.tabs.tab name="seo">SEO</x-admin.tabs.tab>
                </x-slot:nav>

                {{-- ===================== GENERAL ===================== --}}
                <x-admin.tabs.panel name="general" class="space-y-6">
                    <x-admin.card>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <input type="hidden" name="store_id" id="store_id" value="1">

                            <div x-data="brandSearch()" x-init="init()">
                                <label for="brand_search" class="{{ $vLabel }}">Brand</label>
                                <div class="relative">
                                    <input type="text" id="brand_search" x-model="search" @input.debounce.300ms="fetchResults()" @focus="open = true" @click.away="open = false"
                                        placeholder="Search brand..." autocomplete="off" class="{{ $vInput }}">
                                    <input type="hidden" name="brand_id" :value="selectedId">
                                    <div x-show="open && results.length > 0" x-cloak class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-v-md border border-v-border bg-v-surface shadow-v-lift-2">
                                        <template x-for="item in results" :key="item.id">
                                            <button type="button" @click="select(item)" class="block w-full px-3 py-2 text-left text-[13px] text-v-text hover:bg-v-hover" x-text="item.name"></button>
                                        </template>
                                    </div>
                                    <div x-show="open && search.length > 0 && results.length === 0 && !loading" x-cloak class="absolute z-50 mt-1 w-full rounded-v-md border border-v-border bg-v-surface px-3 py-2 text-[13px] text-v-muted shadow-v-lift-2">
                                        No brands found for this store.
                                    </div>
                                </div>
                                <div x-show="selectedName" class="mt-1 flex items-center">
                                    <span class="text-xs text-v-accent" x-text="selectedName"></span>
                                    <button type="button" @click="clear()" class="ml-1 text-v-muted hover:text-v-danger" :aria-label="'Clear ' + selectedName">
                                        <x-admin.icon name="x" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                @error('brand_id') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="name" class="{{ $vLabel }}">Product Name <span class="v-req">Required</span></label>
                                <input type="text" name="name" id="name" required value="{{ old('name', $product?->name) }}" class="{{ $vInput }}">
                                @error('name') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="sku" class="{{ $vLabel }}">SKU</label>
                                <input type="text" name="sku" id="sku" value="{{ old('sku', $product?->sku) }}" class="{{ $vInput }}">
                                @error('sku') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="date_available" class="{{ $vLabel }}">Date Available</label>
                                <input type="date" name="date_available" id="date_available" value="{{ old('date_available', $product?->date_available?->format('Y-m-d')) }}" class="{{ $vInput }}">
                                <p class="mt-1 text-xs text-v-muted">Leave blank for immediately available</p>
                                @error('date_available') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="description" class="{{ $vLabel }}">Description</label>
                                <textarea name="description" id="description" rows="12" class="tinymce-editor w-full rounded-v-md border border-v-border text-[13px]">{{ old('description', $product?->description) }}</textarea>
                                @error('description') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Base price --}}
                    <x-admin.card title="Base price">
                        <p class="mb-4 text-xs text-v-muted" x-show="hasVariants">
                            Variants on the next tab inherit from this base via signed modifiers (e.g. <span class="font-mono">+ ₱280</span>). Sales applied to this base ripple through every variant automatically.
                        </p>
                        <p class="mb-4 text-xs text-v-muted" x-show="!hasVariants">
                            The price customers see for this product. Sales applied here override it during the active window.
                        </p>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                            <div>
                                <label for="price" class="{{ $vLabel }}">Price (₱)</label>
                                <input type="number" name="price" id="price" step="0.01" min="0" required x-model.number="basePrice" class="{{ $vInput }}">
                                @error('price') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="cost" class="{{ $vLabel }}">Cost (₱) <span class="font-normal text-v-muted">(optional)</span></label>
                                <input type="number" name="cost" id="cost" step="0.01" min="0" value="{{ old('cost', $product?->cost ?? '0.00') }}" class="{{ $vInput }}">
                            </div>
                            <div>
                                <label for="quantity" class="{{ $vLabel }}">
                                    Quantity
                                    <span x-show="hasVariants && totalVariantQty === 0" class="font-normal text-v-danger">(Out of Stock)</span>
                                </label>
                                <input type="number" name="quantity" id="quantity" min="0"
                                    :value="hasVariants ? totalVariantQty : '{{ old('quantity', $product?->quantity ?? 0) }}'"
                                    :readonly="hasVariants"
                                    :class="hasVariants ? 'bg-v-sunken text-v-muted' : ''"
                                    class="{{ $vInput }}">
                                <p x-show="hasVariants" class="mt-1 text-xs text-v-muted">Sum of variant quantities.</p>
                                @error('quantity') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="minimum_quantity" class="{{ $vLabel }}">Min order qty</label>
                                <input type="number" name="minimum_quantity" id="minimum_quantity" min="1" value="{{ old('minimum_quantity', $product?->minimum_quantity ?? 1) }}" class="{{ $vInput }}">
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Shipping & Dimensions --}}
                    <x-admin.card title="Shipping & Dimensions">
                        <div class="grid grid-cols-2 gap-6 md:grid-cols-5">
                            <div>
                                <label for="weight" class="{{ $vLabel }}">Weight</label>
                                <input type="number" name="weight" id="weight" step="0.001" min="0" value="{{ old('weight', $product?->weight ?? '0.000') }}" class="{{ $vInput }}">
                            </div>
                            <div>
                                <label for="weight_unit" class="{{ $vLabel }}">Unit</label>
                                <select name="weight_unit" id="weight_unit" class="{{ $vSelect }}">
                                    @foreach (['kg' => 'kg', 'g' => 'g', 'lb' => 'lb', 'oz' => 'oz'] as $val => $label)
                                        <option value="{{ $val }}" {{ old('weight_unit', $product?->weight_unit ?? 'kg') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="length" class="{{ $vLabel }}">Length (cm)</label>
                                <input type="number" name="length" id="length" step="0.01" min="0" value="{{ old('length', $product?->length ?? '0.00') }}" class="{{ $vInput }}">
                            </div>
                            <div>
                                <label for="width" class="{{ $vLabel }}">Width (cm)</label>
                                <input type="number" name="width" id="width" step="0.01" min="0" value="{{ old('width', $product?->width ?? '0.00') }}" class="{{ $vInput }}">
                            </div>
                            <div>
                                <label for="height" class="{{ $vLabel }}">Height (cm)</label>
                                <input type="number" name="height" id="height" step="0.01" min="0" value="{{ old('height', $product?->height ?? '0.00') }}" class="{{ $vInput }}">
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Status --}}
                    <x-admin.card>
                        <div class="flex items-center gap-8">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <x-admin.toggle name="is_active" :checked="$product?->is_active ?? true" />
                                <span class="text-[13px] text-v-text">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <x-admin.toggle name="track_stock" :checked="$product?->track_stock ?? true" />
                                <span class="text-[13px] text-v-text">Track Stock</span>
                            </label>
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ===================== IMAGES ===================== --}}
                <x-admin.tabs.panel name="images" class="p-0">
                    @include('admin.catalog.products.partials.image_manager', [
                        'existingImages' => $existingImages,
                        'pimToken' => $pimToken,
                        'defaultFolder' => $product?->brand?->slug ? 'products/' . $product->brand->slug : 'products',
                    ])
                </x-admin.tabs.panel>

                {{-- ===================== LINKS ===================== --}}
                <x-admin.tabs.panel name="links" class="space-y-6">
                    {{-- Categories --}}
                    @php
                        $allCategories = $categories->map(function($c) use ($categories) {
                            $parent = $c->parent_id ? $categories->firstWhere('id', $c->parent_id) : null;
                            return ['id' => $c->id, 'name' => $parent ? $parent->name . ' > ' . $c->name : $c->name];
                        })->sortBy('name')->values()->toArray();
                        $selectedCategoryIds = old('category_ids', $product?->categories->pluck('id')->toArray() ?? []);
                    @endphp
                    <x-admin.card title="Categories">
                        <div x-data="categoryPicker()">
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="category_ids[]" :value="id">
                            </template>

                            <div class="relative" @click.outside="open = false">
                                <input type="text" x-model="search" @focus="open = true" @input="open = true"
                                    placeholder="Search categories..." class="{{ $vInput }} pr-8">
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-v-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>

                                <div x-show="open && filtered.length > 0" x-transition class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-v-md border border-v-border bg-v-surface shadow-v-lift-2">
                                    <template x-for="cat in filtered" :key="cat.id">
                                        <button type="button" @mousedown.prevent="add(cat)" class="block w-full px-3 py-2 text-left text-[13px] text-v-text hover:bg-v-hover" x-text="cat.name"></button>
                                    </template>
                                </div>
                            </div>

                            @error('category_ids') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror

                            <div x-show="selected.length > 0" class="mt-3">
                                <div class="overflow-hidden rounded-v-md border border-v-border">
                                    <template x-for="cat in selectedCategories" :key="cat.id">
                                        <div class="flex items-center justify-between border-b border-v-border px-3 py-2 text-[13px] last:border-b-0 hover:bg-v-hover">
                                            <span class="text-v-text" x-text="cat.name"></span>
                                            <button type="button" @click="remove(cat.id)" class="p-0.5 text-v-muted hover:text-v-danger" title="Remove">
                                                <x-admin.icon name="x" class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <p class="mt-1.5 text-[11px] text-v-muted" x-text="selected.length + ' selected'"></p>
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Tags --}}
                    @php
                        $allTags = ($tags ?? collect())->map(function($t) { return ['id' => $t->id, 'name' => $t->name]; })->values()->toArray();
                        $selectedTagIds = old('tag_ids', $product?->tags->pluck('id')->toArray() ?? []);
                    @endphp
                    <x-admin.card title="Tags">
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
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="tag_ids[]" :value="id">
                            </template>

                            <div class="relative" @click.outside="open = false">
                                <input type="text" x-model="search" @focus="open = true" @input="open = true"
                                    placeholder="Search tags..." class="{{ $vInput }} pr-8">
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-v-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>

                                <div x-show="open && filtered.length > 0" x-transition class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-v-md border border-v-border bg-v-surface shadow-v-lift-2">
                                    <template x-for="tag in filtered" :key="tag.id">
                                        <button type="button" @mousedown.prevent="add(tag)" class="block w-full px-3 py-2 text-left text-[13px] text-v-text hover:bg-v-hover" x-text="tag.name"></button>
                                    </template>
                                </div>
                            </div>

                            <div x-show="selected.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                                <template x-for="tag in selectedTags" :key="tag.id">
                                    <span class="inline-flex items-center gap-1 rounded-full border border-v-border bg-v-accent-weak px-2 py-0.5 text-xs font-medium text-v-accent">
                                        <span x-text="tag.name"></span>
                                        <button type="button" @click="remove(tag.id)" class="text-v-accent hover:opacity-70" :aria-label="'Remove tag ' + tag.name">
                                            <x-admin.icon name="x" class="w-3 h-3" />
                                        </button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Customer Group Visibility --}}
                    <x-admin.card title="Customer Group Visibility">
                        <x-slot name="headerActions">
                            <x-admin.hint label="How group visibility works" class="v-hint--end">Left empty, everyone sees the product. Check a group and only signed-in customers in it can.</x-admin.hint>
                        </x-slot>
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            @foreach ($customerGroups as $group)
                                <label class="flex items-center">
                                    <input type="checkbox" name="customer_group_ids[]" value="{{ $group->id }}"
                                        {{ in_array($group->id, old('customer_group_ids', $product?->customerGroups->pluck('id')->toArray() ?? [])) ? 'checked' : '' }}
                                        class="{{ $vCheck }}">
                                    <span class="ml-2 text-[13px] text-v-text">{{ $group->name }}</span>
                                    @if ($group->is_default)
                                        <span class="ml-1 text-xs text-v-muted">(default)</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ===================== VARIANTS ===================== --}}
                <x-admin.tabs.panel name="variants">
                    <x-admin.card>
                        {{-- Variant Types (horizontal rows with inline value pills) --}}
                        <div class="mb-6 space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="v-label">Variant Types</h3>
                                <button type="button" @click="addVariantType()"
                                    :disabled="variantTypes.length >= 2"
                                    :class="variantTypes.length >= 2 ? 'opacity-40 cursor-not-allowed' : 'hover:opacity-80'"
                                    class="inline-flex items-center gap-1 rounded-v-md bg-v-accent-weak px-2.5 py-1 text-xs font-medium text-v-accent transition-colors">
                                    <x-admin.icon name="plus" class="w-3 h-3" />
                                    Add Type
                                    <span x-show="variantTypes.length > 0" x-text="'(' + variantTypes.length + '/2)'"></span>
                                </button>
                            </div>

                            <template x-for="(type, typeIndex) in variantTypes" :key="typeIndex">
                                <div class="flex items-start gap-3 rounded-v-md border border-v-border bg-v-sunken/60 p-3">
                                    {{-- Type name --}}
                                    <div class="w-36 shrink-0">
                                        <input type="text" x-model="type.name" :name="'variant_types['+typeIndex+'][name]'" placeholder="e.g. Color, Size"
                                            class="{{ $vInputSm }} w-full font-medium" @input="generateCombinations()">
                                    </div>

                                    {{-- Values as pills --}}
                                    <div class="flex flex-1 flex-wrap items-center gap-1.5">
                                        <template x-for="(value, valueIndex) in type.values" :key="valueIndex">
                                            <div class="inline-flex items-center gap-0.5 overflow-hidden rounded-v-md border border-v-border bg-v-surface">
                                                <input type="text" x-model="type.values[valueIndex]" :name="'variant_types['+typeIndex+'][values]['+valueIndex+']'" placeholder="Value"
                                                    class="w-36 border-0 bg-transparent px-2 py-1 text-[13px] text-v-text focus:ring-0" @input="generateCombinations()">
                                                <button type="button" @click="type.values.splice(valueIndex, 1); generateCombinations()" aria-label="Remove variant value"
                                                    class="px-1.5 py-1 text-v-muted transition-colors hover:bg-v-danger/10 hover:text-v-danger">
                                                    <x-admin.icon name="x" class="w-3 h-3" />
                                                </button>
                                            </div>
                                        </template>
                                        <button type="button" @click="type.values.push('')"
                                            class="inline-flex items-center gap-0.5 rounded-v-md px-2 py-1 text-[11px] text-v-accent transition-colors hover:bg-v-accent-weak">
                                            <x-admin.icon name="plus" class="w-3 h-3" />
                                            value
                                        </button>
                                    </div>

                                    {{-- Remove type --}}
                                    <button type="button" @click="removeVariantType(typeIndex)" class="shrink-0 p-1 text-v-muted transition-colors hover:text-v-danger" title="Remove type">
                                        <x-admin.icon name="x" class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </div>

                        {{-- Generated Variant Combinations --}}
                        <div x-show="variants.length > 0">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="v-label">Combinations <span x-text="'(' + variants.length + ')'"></span></h3>
                            </div>
                            <div class="variant-table overflow-hidden rounded-v-md border border-v-border">
                                {{-- Header --}}
                                <div class="flex border-b border-v-border bg-v-sunken">
                                    <div class="w-32 shrink-0 px-3 py-2.5 v-label">Variant</div>
                                    <div class="min-w-[14rem] flex-1 px-3 py-2.5 v-label">SKU</div>
                                    <div class="w-72 shrink-0 px-3 py-2.5 v-label">Price modifier (₱)</div>
                                    <div class="w-24 shrink-0 px-3 py-2.5 v-label">Stock</div>
                                    <div class="w-24 shrink-0 px-3 py-2.5 v-label">Weight</div>
                                    <div class="w-10 shrink-0 px-2 py-2.5 text-center v-label">On</div>
                                </div>

                                {{-- Rows --}}
                                <template x-for="(variant, vIndex) in variants" :key="vIndex">
                                    <div class="border-b border-v-border last:border-b-0">
                                        {{-- Main row --}}
                                        <div class="flex items-center hover:bg-v-hover">
                                            <div class="w-32 shrink-0 px-3 py-2">
                                                <span class="text-[13px] font-medium text-v-text" x-text="variant.label"></span>
                                                <template x-for="(key, kIndex) in variant.value_keys" :key="kIndex">
                                                    <input type="hidden" :name="'variants['+vIndex+'][value_keys]['+kIndex+']'" :value="key">
                                                </template>
                                            </div>
                                            <div class="min-w-[14rem] flex-1 px-2 py-1.5">
                                                <input type="text" x-model="variant.sku" :name="'variants['+vIndex+'][sku]'"
                                                    :class="isSkuDuplicate(variant.sku, vIndex) ? 'ring-1 ring-v-danger border-v-danger' : 'border-v-border'"
                                                    class="{{ $vInputSm }} w-full" placeholder="SKU">
                                            </div>
                                            <div class="w-72 shrink-0 px-2 py-1.5">
                                                <div class="flex items-center gap-2">
                                                    <select x-model="variant.modifier_sign" :name="'variants['+vIndex+'][modifier_sign]'"
                                                        class="{{ $vInputSm }} w-14 shrink-0 px-1">
                                                        <option value="+">+</option>
                                                        <option value="-">−</option>
                                                    </select>
                                                    <input type="number" x-model="variant.modifier_amount" :name="'variants['+vIndex+'][modifier_amount]'" step="0.01" min="0"
                                                        placeholder="0.00"
                                                        class="{{ $vInputSm }} min-w-0 flex-1">
                                                    <span class="shrink-0 text-xs text-v-muted">=</span>
                                                    <span class="v-num shrink-0 text-[13px] font-medium text-v-text" x-text="'₱' + variantTotalPrice(variant).toFixed(2)"></span>
                                                    {{-- Hidden price field — server keeps the absolute value in `price` for API/ERP compat --}}
                                                    <input type="hidden" :name="'variants['+vIndex+'][price]'" :value="variantTotalPrice(variant).toFixed(2)">
                                                </div>
                                            </div>
                                            <div class="w-24 shrink-0 px-2 py-1.5">
                                                <input type="number" x-model.number="variant.quantity" :name="'variants['+vIndex+'][quantity]'" min="0"
                                                    class="{{ $vInputSm }} w-full">
                                            </div>
                                            <div class="w-24 shrink-0 px-2 py-1.5">
                                                <input type="number" x-model="variant.weight" :name="'variants['+vIndex+'][weight]'" step="0.001" min="0"
                                                    class="{{ $vInputSm }} w-full" placeholder="kg">
                                            </div>
                                            <div class="w-10 shrink-0 px-2 py-1.5 text-center">
                                                <input type="checkbox" x-model="variant.is_active" :name="'variants['+vIndex+'][is_active]'"
                                                    class="h-3.5 w-3.5 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                            </div>
                                        </div>

                                    </div>
                                </template>
                            </div>
                        </div>

                        <div x-show="variantTypes.length === 0 && variants.length === 0" class="py-12 text-center">
                            <svg class="mx-auto mb-3 h-10 w-10 text-v-muted/50" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            <p class="text-[13px] text-v-muted">No variants configured</p>
                            <p class="mt-1 text-xs text-v-muted/70">This product uses a single price and stock quantity</p>
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ===================== PRICING ===================== --}}
                <x-admin.tabs.panel name="pricing" class="space-y-6">
                    <x-admin.card title="Special Pricing">
                        <x-slot name="headerActions">
                            <x-admin.hint label="How priority is read" class="v-hint--end">The lowest priority number wins when two rows both qualify.</x-admin.hint>
                        </x-slot>
                        <x-slot:headerActions>
                            <x-admin.button type="button" size="sm" @click="addSpecial()">
                                <x-admin.icon name="plus" class="w-3.5 h-3.5" /> Add Special
                            </x-admin.button>
                        </x-slot:headerActions>

                        <div x-show="specials.length === 0" class="py-4 text-center text-[13px] text-v-muted">
                            No special pricing rules.
                        </div>

                        <div x-show="specials.length > 0" class="overflow-x-auto">
                            <table class="v-table w-full text-[13px]">
                                <thead class="bg-v-sunken">
                                    <tr>
                                        <th class="h-[var(--row-h)] px-3 text-left v-label">Customer Group</th>
                                        <th class="h-[var(--row-h)] px-3 text-left v-label">Price</th>
                                        <th class="h-[var(--row-h)] px-3 text-left v-label">Qty</th>
                                        <th class="h-[var(--row-h)] px-3 text-left v-label">Display</th>
                                        <th class="h-[var(--row-h)] px-3 text-left v-label">Priority</th>
                                        <th class="h-[var(--row-h)] px-3 text-left v-label">Start</th>
                                        <th class="h-[var(--row-h)] px-3 text-left v-label">End</th>
                                        <th class="h-[var(--row-h)] w-10 px-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-v-border">
                                    <template x-for="(special, sIndex) in specials" :key="sIndex">
                                        <tr>
                                            <td class="px-3 py-2">
                                                <select x-model="special.customer_group_id" :name="'specials['+sIndex+'][customer_group_id]'" class="{{ $vInputSm }} w-full">
                                                    <option value="">All Groups</option>
                                                    @foreach ($customerGroups as $group)
                                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" x-model="special.price" :name="'specials['+sIndex+'][price]'" step="0.01" min="0" required
                                                    class="{{ $vInputSm }} w-28">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" x-model="special.quantity" :name="'specials['+sIndex+'][quantity]'" min="1"
                                                    class="{{ $vInputSm }} w-16">
                                            </td>
                                            <td class="px-3 py-2">
                                                <select x-model="special.display_mode" :name="'specials['+sIndex+'][display_mode]'" class="{{ $vInputSm }} w-full">
                                                    <option value="sale">Sale (slashed)</option>
                                                    <option value="override">Override (clean)</option>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" x-model="special.priority" :name="'specials['+sIndex+'][priority]'" min="0"
                                                    class="{{ $vInputSm }} w-16">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="date" x-model="special.date_start" :name="'specials['+sIndex+'][date_start]'"
                                                    class="{{ $vInputSm }} w-36">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="date" x-model="special.date_end" :name="'specials['+sIndex+'][date_end]'"
                                                    class="{{ $vInputSm }} w-36">
                                            </td>
                                            <td class="px-3 py-2">
                                                <button type="button" @click="specials.splice(sIndex, 1)" class="text-v-danger hover:opacity-80" aria-label="Remove price rule">
                                                    <x-admin.icon name="trash" class="w-5 h-5" />
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ===================== SEO ===================== --}}
                <x-admin.tabs.panel name="seo">
                    <x-admin.card>
                        <div class="max-w-2xl space-y-4">
                            <div>
                                <label for="slug" class="{{ $vLabel }}">Slug</label>
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-v-muted">/product/</span>
                                    <input type="text" name="slug" id="slug" value="{{ old('slug', $product?->slug) }}"
                                        class="{{ $vInput }} flex-1 font-mono"
                                        placeholder="auto-generated-from-name">
                                </div>
                                <p class="mt-1 text-[11px] text-v-muted">Leave blank to auto-generate from name</p>
                                @error('slug') <p class="mt-1 text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="meta_title" class="{{ $vLabel }}">Meta Title</label>
                                <input type="text" name="meta_title" id="meta_title" value="{{ old('meta_title', $product?->meta_title) }}" maxlength="255"
                                    class="{{ $vInput }}">
                            </div>
                            <div>
                                <label for="meta_description" class="{{ $vLabel }}">Meta Description</label>
                                <textarea name="meta_description" id="meta_description" rows="3" maxlength="500"
                                    class="{{ $vTextarea }}">{{ old('meta_description', $product?->meta_description) }}</textarea>
                            </div>
                            <div>
                                <label for="meta_keywords" class="{{ $vLabel }}">Meta Keywords</label>
                                <input type="text" name="meta_keywords" id="meta_keywords" value="{{ old('meta_keywords', $product?->meta_keywords) }}" maxlength="255"
                                    class="{{ $vInput }}">
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
                            }" class="mt-4 border-t border-v-border pt-4">
                                @if ($product ?? false)
                                    <x-admin.button type="button" variant="primary" @click="optimize()" x-bind:disabled="loading">
                                        <template x-if="!loading">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                                        </template>
                                        <template x-if="loading">
                                            <x-admin.icon name="spinner" class="w-4 h-4 animate-spin" />
                                        </template>
                                        <span x-text="loading ? 'Optimizing...' : 'Optimize with AI'"></span>
                                    </x-admin.button>
                                @else
                                    <p class="text-xs text-v-muted">Save the product first to use AI optimization.</p>
                                @endif

                                <div x-show="error" x-cloak class="mt-3 rounded-v-md border border-v-danger bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] p-3 text-[13px] text-v-danger" x-text="error"></div>

                                <div x-show="suggestion" x-cloak class="mt-3 rounded-v-md border border-v-border bg-v-accent-weak p-4">
                                    <h4 class="mb-3 text-[13px] font-semibold text-v-text">AI Suggestion Preview</h4>
                                    <div class="space-y-3">
                                        <template x-for="field in ['meta_title', 'meta_description', 'og_title', 'og_description']" :key="field">
                                            <div>
                                                <label class="v-label" x-text="field.replace(/_/g, ' ')"></label>
                                                <div class="mt-1 grid grid-cols-2 gap-2">
                                                    <div class="rounded-v-sm bg-v-surface p-2 text-xs">
                                                        <span class="mb-0.5 block text-v-muted">Current:</span>
                                                        <span x-text="suggestion?.['current_' + field] || '(empty)'"></span>
                                                    </div>
                                                    <div class="rounded-v-sm border-l-2 border-v-accent bg-v-surface p-2 text-xs">
                                                        <span class="mb-0.5 block text-v-accent">Suggested:</span>
                                                        <span x-text="suggestion?.['suggested_' + field] || '(empty)'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="mt-4 flex gap-2">
                                        <x-admin.button type="button" variant="primary" size="sm" @click="apply()">Apply to Form</x-admin.button>
                                        <x-admin.button type="button" variant="secondary" size="sm" @click="suggestion = null">Dismiss</x-admin.button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>
                </x-admin.tabs.panel>
            </x-admin.tabs>

            {{-- Submit --}}
            <div class="flex justify-end gap-3">
                <x-admin.button variant="secondary" href="{{ route('admin.products.index') }}">Cancel</x-admin.button>
                <x-admin.button type="submit">{{ $product ? 'Update Product' : 'Create Product' }}</x-admin.button>
            </div>
        </form>
    </x-admin.page>

    @push('scripts')
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
                            modifier_sign: existing?.modifier_sign || '+',
                            modifier_amount: existing?.modifier_amount ?? '0.00',
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
</x-admin.shell>
