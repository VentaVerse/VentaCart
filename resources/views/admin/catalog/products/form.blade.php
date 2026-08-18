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
<x-admin.shell :title="$product ? 'Edit Product' : 'Create Product'" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.products.index') }}">Products</a>
                <span aria-hidden="true">/</span>
                <span>{{ $product->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $product ? $product->name : 'Create product' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$product ? route('admin.products.update', $product) : route('admin.products.store')"
                   :method="$product ? 'PUT' : 'POST'" enctype="multipart/form-data" novalidate class="v-settle">
        <input type="hidden" name="store_id" id="store_id" value="1">

        <div x-data="productForm()">
            <div x-data="{ tab: @js($errorTab === 'general' ? request('tab', $errorTab) : $errorTab),
                    pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
                <div class="v-tabs" role="tablist">
                    <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'general' ? 'true' : 'false'" @click="pick('general')">General</button>
                    <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'images' ? 'true' : 'false'" @click="pick('images')">Images</button>
                    <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'links' ? 'true' : 'false'" @click="pick('links')">Links</button>
                    <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'variants' ? 'true' : 'false'" @click="pick('variants')">
                        Variants <span class="v-tab__count" x-show="variants.length > 0" x-text="variants.length"></span>
                    </button>
                    <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'pricing' ? 'true' : 'false'" @click="pick('pricing')">
                        Pricing <span class="v-tab__count" x-show="specials.length > 0" x-text="specials.length"></span>
                    </button>
                    <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'seo' ? 'true' : 'false'" @click="pick('seo')">SEO</button>
                </div>

                {{-- ===================== GENERAL ===================== --}}
                <div x-show="tab === 'general'" class="v-settle">
                    <x-admin.panel title="Product">
                        <div class="v-grid v-grid--2">
                            <div x-data="brandSearch()" x-init="init()">
                                <label class="v-field">
                                    <span class="v-field__label">Brand</span>
                                    <span class="v-combo">
                                        <input type="text" x-model="search" @input.debounce.300ms="fetchResults()" @focus="open = true" @click.away="open = false"
                                               autocomplete="off" class="v-control" aria-label="Search brand">
                                        <input type="hidden" name="brand_id" :value="selectedId">
                                        <span x-show="open && results.length > 0" x-cloak class="v-combo__menu">
                                            <template x-for="item in results" :key="item.id">
                                                <button type="button" class="v-combo__opt" @click="select(item); touch()" x-text="item.name"></button>
                                            </template>
                                        </span>
                                        <span x-show="open && search.length > 0 && results.length === 0 && !loading" x-cloak class="v-combo__menu">
                                            <span class="v-combo__opt">No brands found for this store.</span>
                                        </span>
                                    </span>
                                </label>
                                <span x-show="selectedName" class="v-actrow v-gap-sm">
                                    <span class="v-tone-act" x-text="selectedName"></span>
                                    <button type="button" @click="clear(); touch()" class="v-row__x" :aria-label="'Clear ' + selectedName"><x-admin.icon name="x" /></button>
                                </span>
                                @error('brand_id') <span class="v-field__error">{{ $message }}</span> @enderror
                            </div>
                            <x-admin.field name="name" label="Product name" required>
                                <input type="text" name="name" id="name" required class="v-control" value="{{ old('name', $product?->name) }}">
                            </x-admin.field>
                            <x-admin.field name="sku" label="SKU">
                                <input type="text" name="sku" id="sku" class="v-control v-mono" value="{{ old('sku', $product?->sku) }}">
                            </x-admin.field>
                            <x-admin.field name="date_available" label="Date available" hint="Leave blank for immediately available.">
                                <input type="date" name="date_available" class="v-control v-mono" value="{{ old('date_available', $product?->date_available?->format('Y-m-d')) }}">
                            </x-admin.field>
                        </div>
                        <div class="v-gap">
                            <x-admin.field name="description" label="Description">
                                <textarea name="description" id="v-product-description" rows="12" class="v-control">{{ old('description', $product?->description) }}</textarea>
                            </x-admin.field>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel title="Base price"
                        note="Variants inherit this base through signed modifiers, and a sale applied here ripples through every variant. Without variants, a sale here overrides the price for its window.">
                        <div class="v-grid v-grid--2">
                            <x-admin.field name="price" label="Price" required>
                                <input type="number" name="price" id="price" step="0.01" min="0" required x-model.number="basePrice" class="v-control v-mono">
                            </x-admin.field>
                            <x-admin.field name="cost" label="Cost">
                                <input type="number" name="cost" step="0.01" min="0" class="v-control v-mono" value="{{ old('cost', $product?->cost ?? '0.00') }}">
                            </x-admin.field>
                            <x-admin.field name="quantity" label="Quantity" hint="With variants, this is the read-only sum of variant quantities.">
                                <input type="number" name="quantity" min="0"
                                       :value="hasVariants ? totalVariantQty : '{{ old('quantity', $product?->quantity ?? 0) }}'"
                                       :readonly="hasVariants" class="v-control v-mono">
                            </x-admin.field>
                            <x-admin.field name="minimum_quantity" label="Min order qty">
                                <input type="number" name="minimum_quantity" min="1" class="v-control v-mono" value="{{ old('minimum_quantity', $product?->minimum_quantity ?? 1) }}">
                            </x-admin.field>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel title="Shipping and dimensions">
                        <div class="v-grid v-grid--3">
                            <x-admin.field name="weight" label="Weight">
                                <input type="number" name="weight" step="0.001" min="0" class="v-control v-mono" value="{{ old('weight', $product?->weight ?? '0.000') }}">
                            </x-admin.field>
                            <x-admin.field name="weight_unit" label="Unit">
                                <select name="weight_unit" class="v-control">
                                    @foreach (['kg' => 'kg', 'g' => 'g', 'lb' => 'lb', 'oz' => 'oz'] as $val => $label)
                                        <option value="{{ $val }}" @selected(old('weight_unit', $product?->weight_unit ?? 'kg') === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-admin.field>
                            <x-admin.field name="length" label="Length (cm)">
                                <input type="number" name="length" step="0.01" min="0" class="v-control v-mono" value="{{ old('length', $product?->length ?? '0.00') }}">
                            </x-admin.field>
                            <x-admin.field name="width" label="Width (cm)">
                                <input type="number" name="width" step="0.01" min="0" class="v-control v-mono" value="{{ old('width', $product?->width ?? '0.00') }}">
                            </x-admin.field>
                            <x-admin.field name="height" label="Height (cm)">
                                <input type="number" name="height" step="0.01" min="0" class="v-control v-mono" value="{{ old('height', $product?->height ?? '0.00') }}">
                            </x-admin.field>
                        </div>
                        <div class="v-gap v-actrow">
                            <label class="v-toggle">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true))>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                <span>Active</span>
                            </label>
                            <label class="v-toggle">
                                <input type="hidden" name="track_stock" value="0">
                                <input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product?->track_stock ?? true))>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                <span>Track stock</span>
                            </label>
                        </div>
                    </x-admin.panel>
                </div>

                {{-- ===================== IMAGES ===================== --}}
                <div x-show="tab === 'images'" class="v-settle">
                    <x-admin.panel title="Images">
                        @include('admin.catalog.products.partials.image_manager', [
                            'existingImages' => $existingImages,
                            'pimToken' => $pimToken,
                            'defaultFolder' => $product?->brand?->slug ? 'products/' . $product->brand->slug : 'products',
                        ])
                    </x-admin.panel>
                </div>

                {{-- ===================== LINKS ===================== --}}
                <div x-show="tab === 'links'" class="v-settle">
                    @php
                        $allCategories = $categories->map(function($c) use ($categories) {
                            $parent = $c->parent_id ? $categories->firstWhere('id', $c->parent_id) : null;
                            return ['id' => $c->id, 'name' => $parent ? $parent->name . ' > ' . $c->name : $c->name];
                        })->sortBy('name')->values()->toArray();
                        $selectedCategoryIds = old('category_ids', $product?->categories->pluck('id')->toArray() ?? []);
                        $allTags = ($tags ?? collect())->map(function($t) { return ['id' => $t->id, 'name' => $t->name]; })->values()->toArray();
                        $selectedTagIds = old('tag_ids', $product?->tags->pluck('id')->toArray() ?? []);
                    @endphp

                    <x-admin.panel title="Categories">
                        <div x-data="categoryPicker()">
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="category_ids[]" :value="id">
                            </template>

                            <label class="v-field">
                                <span class="v-field__label">Search categories</span>
                                <span class="v-combo" @click.outside="open = false">
                                    <input type="text" x-model="search" @focus="open = true" @input="open = true" class="v-control">
                                    <span x-show="open && filtered.length > 0" x-cloak class="v-combo__menu">
                                        <template x-for="cat in filtered" :key="cat.id">
                                            <button type="button" class="v-combo__opt" @mousedown.prevent="add(cat); touch()" x-text="cat.name"></button>
                                        </template>
                                    </span>
                                </span>
                            </label>
                            @error('category_ids') <span class="v-field__error">{{ $message }}</span> @enderror

                            <template x-for="cat in selectedCategories" :key="cat.id">
                                <span class="v-pickband v-gap-sm">
                                    <span class="v-pickband__who"><strong x-text="cat.name"></strong></span>
                                    <button type="button" class="v-row__x" @click="remove(cat.id); touch()" :aria-label="'Remove ' + cat.name">
                                        <x-admin.icon name="x" />
                                    </button>
                                </span>
                            </template>
                            <p class="v-cell-quiet v-gap-sm" x-show="selected.length > 0" x-text="selected.length + ' selected'"></p>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel title="Tags">
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

                            <label class="v-field">
                                <span class="v-field__label">Search tags</span>
                                <span class="v-combo" @click.outside="open = false">
                                    <input type="text" x-model="search" @focus="open = true" @input="open = true" class="v-control">
                                    <span x-show="open && filtered.length > 0" x-cloak class="v-combo__menu">
                                        <template x-for="tag in filtered" :key="tag.id">
                                            <button type="button" class="v-combo__opt" @mousedown.prevent="add(tag); touch()" x-text="tag.name"></button>
                                        </template>
                                    </span>
                                </span>
                            </label>

                            <span class="v-gap-sm" x-show="selected.length > 0">
                                <template x-for="tag in selectedTags" :key="tag.id">
                                    <span class="v-badge v-badge--info v-chip--inline">
                                        <span x-text="tag.name"></span>
                                        <button type="button" class="v-row__x" @click="remove(tag.id); touch()" :aria-label="'Remove tag ' + tag.name">
                                            <x-admin.icon name="x" />
                                        </button>
                                    </span>
                                </template>
                            </span>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel title="Customer group visibility"
                        note="Left empty, everyone sees the product. Check a group and only signed-in customers in it can.">
                        <div class="v-grid v-grid--3">
                            @foreach ($customerGroups as $group)
                                <label class="v-check">
                                    <input type="checkbox" name="customer_group_ids[]" value="{{ $group->id }}"
                                        @checked(in_array($group->id, old('customer_group_ids', $product?->customerGroups->pluck('id')->toArray() ?? [])))>
                                    <span>{{ $group->name }}@if ($group->is_default) (default)@endif</span>
                                </label>
                            @endforeach
                        </div>
                    </x-admin.panel>
                </div>

                {{-- ===================== VARIANTS ===================== --}}
                <div x-show="tab === 'variants'" class="v-settle">
                    <x-admin.panel title="Variant types">
                        <x-slot:acts>
                            <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="addVariantType(); touch()"
                                    :disabled="variantTypes.length >= 2">
                                Add type <span class="v-mono" x-show="variantTypes.length > 0" x-text="'(' + variantTypes.length + '/2)'"></span>
                            </button>
                        </x-slot:acts>

                        <template x-for="(type, typeIndex) in variantTypes" :key="typeIndex">
                            <div class="v-well">
                                <div class="v-filterbar">
                                    <input type="text" x-model="type.name" :name="'variant_types['+typeIndex+'][name]'"
                                           aria-label="Variant type name" class="v-control v-w-name" @input="generateCombinations()">
                                    <template x-for="(value, valueIndex) in type.values" :key="valueIndex">
                                        <span class="v-namerow">
                                            <input type="text" x-model="type.values[valueIndex]" :name="'variant_types['+typeIndex+'][values]['+valueIndex+']'"
                                                   aria-label="Variant value" class="v-control v-control--sm v-w-name" @input="generateCombinations()">
                                            <button type="button" class="v-row__x" @click="type.values.splice(valueIndex, 1); generateCombinations(); touch()" aria-label="Remove variant value">
                                                <x-admin.icon name="x" />
                                            </button>
                                        </span>
                                    </template>
                                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="type.values.push(''); touch()">Add value</button>
                                    <span class="v-filterbar__grow"></span>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="removeVariantType(typeIndex); touch()">Remove type</button>
                                </div>
                            </div>
                        </template>

                        <div x-show="variantTypes.length === 0 && variants.length === 0" class="v-empty">
                            <p class="v-empty__title">No variants configured</p>
                            <p class="v-empty__line">This product uses a single price and stock quantity.</p>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel title="Combinations" x-show="variants.length > 0">
                        <div class="v-tablewrap">
                            <table class="v-table">
                                <thead>
                                    <tr>
                                        <th>Variant</th>
                                        <th>SKU</th>
                                        <th>Price modifier</th>
                                        <th class="v-cell-num">Stock</th>
                                        <th class="v-cell-num">Weight</th>
                                        <th>On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(variant, vIndex) in variants" :key="vIndex">
                                        <tr>
                                            <td>
                                                <span x-text="variant.label"></span>
                                                <template x-for="(key, kIndex) in variant.value_keys" :key="kIndex">
                                                    <input type="hidden" :name="'variants['+vIndex+'][value_keys]['+kIndex+']'" :value="key">
                                                </template>
                                            </td>
                                            <td>
                                                <input type="text" x-model="variant.sku" :name="'variants['+vIndex+'][sku]'"
                                                       aria-label="Variant SKU"
                                                       :class="isSkuDuplicate(variant.sku, vIndex) && 'is-invalid'"
                                                       class="v-control v-control--sm v-mono">
                                            </td>
                                            <td>
                                                <span class="v-actrow">
                                                    <select x-model="variant.modifier_sign" :name="'variants['+vIndex+'][modifier_sign]'"
                                                            aria-label="Modifier sign" class="v-control v-control--sm v-w-sym">
                                                        <option value="+">+</option>
                                                        <option value="-">-</option>
                                                    </select>
                                                    <input type="number" x-model="variant.modifier_amount" :name="'variants['+vIndex+'][modifier_amount]'" step="0.01" min="0"
                                                           aria-label="Modifier amount" class="v-control v-control--sm v-mono v-w-sort">
                                                    <span class="v-cell-quiet">=</span>
                                                    <span class="v-mono" x-text="variantTotalPrice(variant).toFixed(2)"></span>
                                                    {{-- Server keeps the absolute value in `price` for API/ERP compat --}}
                                                    <input type="hidden" :name="'variants['+vIndex+'][price]'" :value="variantTotalPrice(variant).toFixed(2)">
                                                </span>
                                            </td>
                                            <td class="v-cell-num">
                                                <input type="number" x-model.number="variant.quantity" :name="'variants['+vIndex+'][quantity]'" min="0"
                                                       aria-label="Variant stock" class="v-control v-control--sm v-mono v-w-sort">
                                            </td>
                                            <td class="v-cell-num">
                                                <input type="number" x-model="variant.weight" :name="'variants['+vIndex+'][weight]'" step="0.001" min="0"
                                                       aria-label="Variant weight" class="v-control v-control--sm v-mono v-w-sort">
                                            </td>
                                            <td>
                                                <label class="v-check">
                                                    <input type="checkbox" x-model="variant.is_active" :name="'variants['+vIndex+'][is_active]'">
                                                    <span class="v-sr">Variant active</span>
                                                </label>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </x-admin.panel>
                </div>

                {{-- ===================== PRICING ===================== --}}
                <div x-show="tab === 'pricing'" class="v-settle">
                    <x-admin.panel title="Special pricing"
                        note="The lowest priority number wins when two rows both qualify.">
                        <x-slot:acts>
                            <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="addSpecial(); touch()">Add special</button>
                        </x-slot:acts>

                        <div x-show="specials.length === 0" class="v-empty">
                            <p class="v-empty__title">No special pricing rules</p>
                        </div>

                        <div x-show="specials.length > 0" class="v-tablewrap">
                            <table class="v-table">
                                <thead>
                                    <tr>
                                        <th>Customer group</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>Display</th>
                                        <th>Priority</th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(special, sIndex) in specials" :key="sIndex">
                                        <tr>
                                            <td>
                                                <select x-model="special.customer_group_id" :name="'specials['+sIndex+'][customer_group_id]'"
                                                        aria-label="Customer group" class="v-control v-control--sm">
                                                    <option value="">All groups</option>
                                                    @foreach ($customerGroups as $group)
                                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input type="number" x-model="special.price" :name="'specials['+sIndex+'][price]'" step="0.01" min="0" required aria-label="Special price" class="v-control v-control--sm v-mono v-w-name"></td>
                                            <td><input type="number" x-model="special.quantity" :name="'specials['+sIndex+'][quantity]'" min="1" aria-label="Minimum quantity" class="v-control v-control--sm v-mono v-w-sort"></td>
                                            <td>
                                                <select x-model="special.display_mode" :name="'specials['+sIndex+'][display_mode]'" aria-label="Display mode" class="v-control v-control--sm">
                                                    <option value="sale">Sale (slashed)</option>
                                                    <option value="override">Override (clean)</option>
                                                </select>
                                            </td>
                                            <td><input type="number" x-model="special.priority" :name="'specials['+sIndex+'][priority]'" min="0" aria-label="Priority" class="v-control v-control--sm v-mono v-w-sort"></td>
                                            <td><input type="date" x-model="special.date_start" :name="'specials['+sIndex+'][date_start]'" aria-label="Start date" class="v-control v-control--sm v-mono"></td>
                                            <td><input type="date" x-model="special.date_end" :name="'specials['+sIndex+'][date_end]'" aria-label="End date" class="v-control v-control--sm v-mono"></td>
                                            <td class="v-cell-act">
                                                <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="specials.splice(sIndex, 1); touch()" aria-label="Remove price rule">Remove</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </x-admin.panel>
                </div>

                {{-- ===================== SEO ===================== --}}
                <div x-show="tab === 'seo'" class="v-settle">
                    <x-admin.panel title="SEO">
                        <x-admin.field name="slug" label="Slug" hint="Leave blank to auto-generate from the name. Public URL: /product/{slug}">
                            <input type="text" name="slug" class="v-control v-mono" value="{{ old('slug', $product?->slug) }}">
                        </x-admin.field>
                        <div class="v-gap v-grid v-grid--2">
                            <x-admin.field name="meta_title" label="Meta title">
                                <input type="text" name="meta_title" maxlength="255" class="v-control" value="{{ old('meta_title', $product?->meta_title) }}">
                            </x-admin.field>
                            <x-admin.field name="meta_keywords" label="Meta keywords">
                                <input type="text" name="meta_keywords" maxlength="255" class="v-control" value="{{ old('meta_keywords', $product?->meta_keywords) }}">
                            </x-admin.field>
                        </div>
                        <div class="v-gap">
                            <x-admin.field name="meta_description" label="Meta description">
                                <textarea name="meta_description" rows="3" maxlength="500" class="v-control">{{ old('meta_description', $product?->meta_description) }}</textarea>
                            </x-admin.field>
                        </div>
                        @include('admin.partials.seo_optimizer', ['entityType' => 'product', 'entityId' => $product?->id])
                    </x-admin.panel>
                </div>
            </div>
        </div>
    </x-admin.form>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v-product-description',
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
            setup: function (editor) {
                // The editor lives outside the form's input events; sync it
                // back to the textarea and tell the save doctrine on every
                // edit, so the save bar and leave guard see TinyMCE changes.
                editor.on('input change undo redo', function () {
                    editor.save();
                    var form = editor.getElement()?.closest('form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
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
                    // The combination rebuild above mutates the serialized
                    // fields after the doctrine's first snapshot; hand it the
                    // settled state as the clean baseline.
                    this.$nextTick(() => this.$el.closest('form')?.dispatchEvent(new Event('v-rebaseline')));
                }
            }
        }
    </script>
    @endpush
</x-admin.shell>
