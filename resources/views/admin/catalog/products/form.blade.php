@php
    $errorTab = 'general';
    if ($errors->any()) {
        $tabFields = [
            'general' => ['name', 'slug', 'sku', 'brand_id', 'description', 'date_available', 'price', 'quantity', 'weight', 'is_active', 'track_stock', 'minimum_quantity', 'attribute_values'],
            'images' => ['images_json'],
            'pricing' => ['specials'],
            'links' => ['category_ids', 'tag_ids', 'customer_group_ids'],
            'variants' => ['variants', 'variant_types'],
            'seo' => ['meta_title', 'meta_description', 'meta_keywords'],
        ];
        // Matched against the whole key, not just its head. A blank or
        // duplicate variant SKU arrives as `variants.31.sku`, and the old
        // check for `variants` and `variants.*` missed it, so the merchant
        // landed on General with an error about a row they could not see.
        $vKeys = array_keys($errors->messages());
        foreach ($tabFields as $tab => $fields) {
            foreach ($fields as $field) {
                foreach ($vKeys as $key) {
                    if ($key === $field || str_starts_with($key, $field . '.')) {
                        $errorTab = $tab;
                        break 3;
                    }
                }
            }
        }
    }

    // Which variant rows the server refused, so each one can say so where the
    // merchant is looking rather than as a string at the top of another tab.
    $variantErrors = [];
    foreach ($errors->messages() as $key => $messages) {
        if (preg_match('/^variants\.(\d+)\./', $key, $m)) {
            $variantErrors[(int) $m[1]] = $messages[0];
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

    <x-admin.form id="product-form"
                   :action="$product ? route('admin.products.update', $product) : route('admin.products.store')"
                   :method="$product ? 'PUT' : 'POST'" enctype="multipart/form-data" novalidate class="v-settle">
        <input type="hidden" name="store_id" id="store_id" value="1">

        {{-- Teleported into the header, the way the Save it stands beside is.
             It rides this form, so it arms the same dirty state and is written
             by the same Save; nothing here auto-saves.

             form="product-form" is what makes that true and must stay. The
             header is rendered above and outside this form, so a control
             teleported into it belongs to no form and the browser drops it
             from the submission entirely. Without this the save still
             succeeded and reported success, and is_active simply never
             arrived, so the product could not be taken off sale. --}}
        <template x-teleport=".v-head">
            <label class="v-toggle v-head__live">
                <input type="hidden" name="is_active" value="0" form="product-form">
                <input type="checkbox" name="is_active" value="1" form="product-form" @checked(old('is_active', $product?->is_active ?? true))>
                <span class="v-toggle__track" aria-hidden="true"></span>
                <span>{{ $product ? 'Live' : 'Publish' }}</span>
            </label>
        </template>

        <div x-data="productForm()">
            <div x-data="{ rowErrors: @js($variantErrors), tab: @js($errorTab === 'general' ? request('tab', $errorTab) : $errorTab),
                    tabs: ['general', 'images', 'links', 'variants', 'pricing', 'seo'],
                    pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); },
                    /* The strip announced role=tablist and role=tab and then
                       behaved like six ordinary buttons: no arrow keys, no
                       Home or End, and six separate tab stops. Claiming the
                       pattern without implementing it is worse than not
                       claiming it, because a screen reader user is told to
                       expect arrows that do nothing. */
                    step(delta) {
                        const at = this.tabs.indexOf(this.tab);
                        const next = (at + delta + this.tabs.length) % this.tabs.length;
                        this.jump(this.tabs[next]);
                    },
                    jump(t) {
                        this.pick(t);
                        this.$nextTick(() => this.$refs['tab_' + t]?.focus());
                    } }">
                <div class="v-tabs" role="tablist" aria-label="Product sections"
                     @keydown.arrow-right.prevent="step(1)" @keydown.arrow-left.prevent="step(-1)"
                     @keydown.home.prevent="jump(tabs[0])" @keydown.end.prevent="jump(tabs[tabs.length - 1])">
                    <button type="button" class="v-tab" role="tab" id="tab-general" aria-controls="panel-general"
                            x-ref="tab_general" :tabindex="tab === 'general' ? 0 : -1"
                            :aria-selected="tab === 'general' ? 'true' : 'false'" @click="pick('general')">General</button>
                    <button type="button" class="v-tab" role="tab" id="tab-images" aria-controls="panel-images"
                            x-ref="tab_images" :tabindex="tab === 'images' ? 0 : -1"
                            :aria-selected="tab === 'images' ? 'true' : 'false'" @click="pick('images')">Images</button>
                    <button type="button" class="v-tab" role="tab" id="tab-links" aria-controls="panel-links"
                            x-ref="tab_links" :tabindex="tab === 'links' ? 0 : -1"
                            :aria-selected="tab === 'links' ? 'true' : 'false'" @click="pick('links')">Links</button>
                    <button type="button" class="v-tab" role="tab" id="tab-variants" aria-controls="panel-variants"
                            x-ref="tab_variants" :tabindex="tab === 'variants' ? 0 : -1"
                            :aria-selected="tab === 'variants' ? 'true' : 'false'" @click="pick('variants')">
                        Variants <span class="v-tab__count" x-show="variants.length > 0" x-text="variants.length"></span>
                    </button>
                    <button type="button" class="v-tab" role="tab" id="tab-pricing" aria-controls="panel-pricing"
                            x-ref="tab_pricing" :tabindex="tab === 'pricing' ? 0 : -1"
                            :aria-selected="tab === 'pricing' ? 'true' : 'false'" @click="pick('pricing')">
                        Pricing <span class="v-tab__count" x-show="specials.length > 0" x-text="specials.length"></span>
                    </button>
                    <button type="button" class="v-tab" role="tab" id="tab-seo" aria-controls="panel-seo"
                            x-ref="tab_seo" :tabindex="tab === 'seo' ? 0 : -1"
                            :aria-selected="tab === 'seo' ? 'true' : 'false'" @click="pick('seo')">SEO</button>
                </div>

                {{-- ===================== GENERAL ===================== --}}
                <div x-show="tab === 'general'" class="v-settle" role="tabpanel" id="panel-general" aria-labelledby="tab-general" tabindex="0">
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
                            {{-- Active moved to the page header. Whether a product is on
                                 the storefront is a property of the record, not of a tab,
                                 and it was the last control in a panel called "Shipping
                                 and dimensions", which is the last place a merchant looks
                                 for it. Track stock stays: it genuinely is a stock rule. --}}
                            <label class="v-toggle">
                                <input type="hidden" name="track_stock" value="0">
                                <input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product?->track_stock ?? true))>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                <span>Track stock</span>
                            </label>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel title="Attributes"
                        note="Facts about the product, listed under their groups on the product page. Leave a value empty to leave it out.">
                        @if (($attributeGroups ?? collect())->isEmpty())
                            <p class="v-note">No attributes yet. <a href="{{ route('admin.attributes.create') }}" class="v-link">Create one</a> under Catalog, Attributes.</p>
                        @else
                            @foreach ($attributeGroups as $group)
                                <p class="v-field__label">{{ $group->name }}</p>
                                <div class="v-grid v-grid--2">
                                    @foreach ($group->attributes as $attribute)
                                        <x-admin.field :name="'attribute_values.' . $attribute->id" :label="$attribute->name">
                                            <input type="text" name="attribute_values[{{ $attribute->id }}]" maxlength="255" class="v-control"
                                                   value="{{ old('attribute_values.' . $attribute->id, $attributeValues[$attribute->id] ?? '') }}">
                                        </x-admin.field>
                                    @endforeach
                                </div>
                            @endforeach
                        @endif
                    </x-admin.panel>
                </div>

                {{-- ===================== IMAGES ===================== --}}
                <div x-show="tab === 'images'" class="v-settle" role="tabpanel" id="panel-images" aria-labelledby="tab-images" tabindex="0">
                    <x-admin.panel title="Images">
                        @include('admin.catalog.products.partials.image_manager', [
                            'existingImages' => $existingImages,
                            'pimToken' => $pimToken,
                            'defaultFolder' => $product?->brand?->slug ? 'products/' . $product->brand->slug : 'products',
                        ])
                    </x-admin.panel>
                </div>

                {{-- ===================== LINKS ===================== --}}
                <div x-show="tab === 'links'" class="v-settle" role="tabpanel" id="panel-links" aria-labelledby="tab-links" tabindex="0">
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
                <div x-show="tab === 'variants'" class="v-settle" role="tabpanel" id="panel-variants" aria-labelledby="tab-variants" tabindex="0">
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

                        <p class="v-field__error" x-show="comboRefused" x-cloak
                           x-text="comboRefused + ' combinations is more than this table can hold. Reduce the values to ' + maxCombinations + ' or fewer.'"></p>

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
                                        <th scope="col"><span class="v-sr">Photo</span></th>
                                        <th scope="col">Variant</th>
                                        <th scope="col">SKU</th>
                                        <th scope="col">Price modifier</th>
                                        <th scope="col" class="v-cell-num">Stock</th>
                                        <th scope="col" class="v-cell-num">Weight</th>
                                        <th scope="col">For sale</th>
                                        <th scope="col" class="v-cell-act"><span class="v-sr">Remove</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(variant, vIndex) in variants" :key="vIndex">
                                        <tr :class="rowErrors[vIndex] && 'is-refused'">
                                            {{-- The colourway, shown. A merchant scanning nine rows of
                                                 "Aged White / Black / Creme" is scanning words for a
                                                 thing that is a picture. --}}
                                            <td class="v-cell-thumb">
                                                <button type="button" class="v-vthumb"
                                                        :class="variant.image && 'is-set'"
                                                        @click="pickFor(variant, $event)"
                                                        :aria-label="(variant.image ? 'Change' : 'Add') + ' photo for ' + variant.label">
                                                    <template x-if="variant.image">
                                                        <img :src="storageUrl(variant.image)" alt="" aria-hidden="true">
                                                    </template>
                                                    <template x-if="!variant.image">
                                                        <span aria-hidden="true">+</span>
                                                    </template>
                                                </button>
                                                <button type="button" class="v-vthumb__x" x-show="variant.image" x-cloak
                                                        @click="variant.image = ''; touch()"
                                                        :aria-label="'Remove photo from ' + variant.label">&times;</button>
                                                <input type="hidden" :name="'variants['+vIndex+'][image]'" :value="variant.image">
                                            </td>
                                            <td>
                                                <span x-text="variant.label"></span>
                                                <template x-for="(key, kIndex) in variant.value_keys" :key="kIndex">
                                                    <input type="hidden" :name="'variants['+vIndex+'][value_keys]['+kIndex+']'" :value="key">
                                                </template>
                                            </td>
                                            <td>
                                                <input type="text" x-model="variant.sku" :name="'variants['+vIndex+'][sku]'"
                                                       aria-label="Variant SKU"
                                                       :aria-describedby="rowErrors[vIndex] ? 'verr' + vIndex : null"
                                                       :class="(isSkuDuplicate(variant.sku, vIndex) || rowErrors[vIndex]) && 'is-invalid'"
                                                       class="v-control v-control--sm v-mono">
                                                <span class="v-field__error" x-show="rowErrors[vIndex]" x-cloak
                                                      :id="'verr' + vIndex" x-text="rowErrors[vIndex]"></span>
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
                                                {{-- The switch, because this is boolean state and the
                                                     admin has exactly one control for that. It was a bare
                                                     checkbox with no class at all, which is the
                                                     multi-selection control, and which rendered as the
                                                     browser's own blue: the one control on this screen
                                                     that was not wearing the admin. --}}
                                                <label class="v-toggle">
                                                    {{-- The companion always rides the switch: an
                                                         unchecked box posts nothing, and the server reads
                                                         presence, so without this every variant switched
                                                         off would read as absent rather than off. --}}
                                                    <input type="hidden" :name="'variants['+vIndex+'][is_active]'" value="0">
                                                    <input type="checkbox" x-model="variant.is_active"
                                                           :name="'variants['+vIndex+'][is_active]'" value="1">
                                                    <span class="v-toggle__track" aria-hidden="true"></span>
                                                    <span class="v-sr" x-text="'For sale: ' + variant.label"></span>
                                                </label>
                                            </td>
                                            {{-- A combination a merchant does not stock.
                                                 Removing one used to mean deleting the whole
                                                 value from the type above, which took every
                                                 other row that value appears in with it. --}}
                                            <td class="v-cell-act">
                                                <button type="button" class="v-row__x"
                                                        @click="dropCombination(vIndex)"
                                                        :aria-label="'Remove ' + variant.label">&times;</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </x-admin.panel>

                    {{-- The shared browser, aimed at a row.

                         This was hand-rolled this morning and reproduced every
                         flaw of the four copies it was modelled on, plus three
                         of its own: no up-tile, silent catch on both network
                         calls, and folder glyphs rendered at 125px because the
                         tile class was wrong. The diagnosis behind it was
                         right, that including the per-field partial once per
                         row would stack thirty-six modals; the conclusion was
                         wrong. One component, bare, driven by the cell. --}}
                    <x-admin.media-picker name="__variant_image" root="products"
                                          mode="single" can="upload,url" bare
                                          @media:picked="assignVariantImage($event.detail.paths[0] ?? '')"
                                          @media:uploaded="assignVariantImage($event.detail.path)" />
                </div>

                {{-- ===================== PRICING ===================== --}}
                <div x-show="tab === 'pricing'" class="v-settle" role="tabpanel" id="panel-pricing" aria-labelledby="tab-pricing" tabindex="0">
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
                <div x-show="tab === 'seo'" class="v-settle" role="tabpanel" id="panel-seo" aria-labelledby="tab-seo" tabindex="0">
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
            content_style: 'body { font-family: Archivo, ui-sans-serif, system-ui, sans-serif; font-size: 14px; }',
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

                    // Two types with a dozen values each is 144 rows of six
                    // controls: a table nobody fills by hand and a payload
                    // nothing validates usefully. Refused before it is built,
                    // because building it and then explaining it is worse.
                    if (combinations.length > this.maxCombinations) {
                        this.comboRefused = combinations.length;
                        return;
                    }

                    this.comboRefused = 0;

                    // Keyed by the combination's own identity, never by the
                    // words on screen.
                    //
                    // This used to be oldVariants[v.label], and the value
                    // inputs fire on every keystroke, so correcting a typo in
                    // "Black" missed the lookup for one frame and rebuilt the
                    // row empty: SKU gone, stock 0, weight gone, modifier
                    // reset. Typing the letter back did not bring it back,
                    // because the map had already been rebuilt from the blank
                    // rows. The wipe armed the dirty flag, so Save wrote the
                    // zeros. A merchant lost 96 units to a typo.
                    //
                    // value_keys is type-index plus value-index, built four
                    // lines above, and it does not change when the label is
                    // edited. It was already in the payload; it just was not
                    // being used for the one thing it is for.
                    const identity = (v) => (v.value_keys || []).join('|');

                    const oldVariants = {};
                    this.variants.forEach(v => { oldVariants[identity(v)] = v; });

                    this.variants = combinations.map(combo => {
                        const keys = combo.map(c => c.key);
                        const existing = oldVariants[keys.join('|')];
                        return {
                            label: combo.map(c => c.value).join(' / '),
                            value_keys: keys,
                            sku: existing?.sku || '',
                            price: existing?.price || 0,
                            modifier_sign: existing?.modifier_sign || '+',
                            modifier_amount: existing?.modifier_amount ?? '0.00',
                            quantity: existing?.quantity || 0,
                            weight: existing?.weight || '',
                            is_active: existing?.is_active ?? true,
                            // Merchandising, and the thing a rename used to
                            // destroy along with everything else.
                            image: existing?.image || '',
                        };
                    });
                },

                /**
                 * Drop one combination without touching the values above.
                 *
                 * It comes back the next time the values change, because the
                 * matrix is generated from them; that is the honest behaviour
                 * for a generated grid, and it is still better than the only
                 * previous route, which was deleting a value and losing every
                 * other row it appeared in.
                 */
                dropCombination(index) {
                    this.variants.splice(index, 1);
                    this.touch();
                },

                // Two types with a dozen values each is 144 rows of six
                // controls: a table nobody fills by hand.
                maxCombinations: 60,
                comboRefused: 0,

                /* ---- the shared browser, aimed at a row ---- */

                // Which row the panel is filling. The component owns the
                // browsing; this owns only the aim.
                pickingFor: null,

                pickFor(variant, event) {
                    this.pickingFor = variant;
                    // By selector, not by $refs: the picker carries its own
                    // x-data, so a ref on it does not resolve into this scope.
                    this.$root.querySelector('[data-media-picker="__variant_image"]')
                        ?.dispatchEvent(new CustomEvent('media:open', {
                            detail: { from: event.currentTarget },
                        }));
                },

                assignVariantImage(path) {
                    if (!this.pickingFor || !path) return;
                    this.pickingFor.image = path;
                    this.pickingFor = null;
                    this.touch();
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
