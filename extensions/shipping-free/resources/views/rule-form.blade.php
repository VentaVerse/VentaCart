<x-admin.shell :title="$rule ? $rule->name : 'Add free shipping rule'">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.free-shipping-rules.index') }}">Free Shipping</a>
                <span aria-hidden="true">/</span>
                <span>{{ $rule?->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $rule ? $rule->name : 'Add free shipping rule' }}</h1>
        </div>
    </div>

    @php
        $brandItems = $brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()->all();
        $categoryItems = [];
        foreach ($categories as $cat) {
            $categoryItems[] = ['id' => $cat->id, 'name' => $cat->name];
            foreach ($cat->children as $child) {
                $categoryItems[] = ['id' => $child->id, 'name' => '- ' . $child->name];
            }
        }
        $groupItems = $customerGroups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values()->all();
        $zoneItems = $shippingZones->map(fn ($z) => ['id' => $z->id, 'name' => $z->name])->values()->all();
    @endphp

    <x-admin.form :action="$rule ? route('admin.free-shipping-rules.update', $rule) : route('admin.free-shipping-rules.store')"
                   :method="$rule ? 'PUT' : 'POST'" class="v-settle">

        <x-admin.panel title="General">
            <div class="v-grid v-grid--2">
                <x-admin.field name="name" label="Rule name" hint="Admin reference." required>
                    <input type="text" name="name" required class="v-control"
                           value="{{ old('name', $rule?->name) }}">
                </x-admin.field>
                <x-admin.field name="checkout_name" label="Checkout name" hint="Shown to the customer; blank falls back to the rule name.">
                    <input type="text" name="checkout_name" class="v-control"
                           value="{{ old('checkout_name', $rule?->checkout_name) }}">
                </x-admin.field>
            </div>
            <div class="v-grid v-grid--3 v-gap">
                <x-admin.field name="min_order_total" label="Minimum order total">
                    <input type="number" name="min_order_total" min="0" step="0.01" class="v-control v-mono"
                           value="{{ old('min_order_total', $rule?->min_order_total) }}">
                </x-admin.field>
                <x-admin.field name="min_quantity" label="Minimum quantity">
                    <input type="number" name="min_quantity" min="1" class="v-control v-mono"
                           value="{{ old('min_quantity', $rule?->min_quantity) }}">
                </x-admin.field>
                <x-admin.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v-control v-mono"
                           value="{{ old('sort_order', $rule?->sort_order ?? 0) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule?->is_active ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
            <div class="v-gap">
                <x-admin.field name="apply_whole_cart">
                    <label class="v-toggle">
                        <input type="hidden" name="apply_whole_cart" value="0">
                        <input type="checkbox" name="apply_whole_cart" value="1" @checked(old('apply_whole_cart', $rule?->apply_whole_cart ?? false))>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Apply to the whole cart
                        <x-admin.help text="One qualifying product frees the whole cart, including products that fail the brand and category conditions." />
                    </label>
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="show_badge">
                    <label class="v-toggle">
                        <input type="hidden" name="show_badge" value="0">
                        <input type="checkbox" name="show_badge" value="1" @checked(old('show_badge', $rule?->show_badge ?? false))>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Show the Free Shipping badge
                        <x-admin.help text="The label appears on product cards and product pages, on the storefront." />
                    </label>
                </x-admin.field>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Conditions" note="A section left empty is skipped; every section that has entries must match.">
            <x-admin.picklist name="brand_ids" label="Only for these brands"
                hint="The cart must contain ONLY products from the picked brands."
                :items="$brandItems" :selected="old('brand_ids', $rule?->brand_ids ?? [])" />
            <div class="v-gap">
                <x-admin.picklist name="category_ids" label="Only for these categories"
                    hint="The cart must contain ONLY products from the picked categories."
                    :items="$categoryItems" :selected="old('category_ids', $rule?->category_ids ?? [])" />
            </div>
            <div class="v-gap">
                <x-admin.picklist name="customer_group_ids" label="Only for these customer groups"
                    hint="Empty means every group."
                    :items="$groupItems" :selected="old('customer_group_ids', $rule?->customer_group_ids ?? [])" />
            </div>
            <div class="v-gap">
                <x-admin.picklist name="shipping_zone_ids" label="Only for these shipping zones"
                    hint="Empty means every zone."
                    :items="$zoneItems" :selected="old('shipping_zone_ids', $rule?->shipping_zone_ids ?? [])" />
            </div>
        </x-admin.panel>

        <x-admin.panel title="Exclusions" note="One match anywhere below is enough to withhold free shipping.">
            <x-admin.picklist name="exclude_customer_group_ids" label="Exclude customer groups"
                hint="These groups never get free shipping from this rule."
                :items="$groupItems" :selected="old('exclude_customer_group_ids', $rule?->exclude_customer_group_ids ?? [])" />
            <div class="v-gap">
                <x-admin.picklist name="exclude_brand_ids" label="Exclude brands"
                    hint="Any product from these brands in the cart withholds free shipping."
                    :items="$brandItems" :selected="old('exclude_brand_ids', $rule?->exclude_brand_ids ?? [])" />
            </div>
            <div class="v-gap">
                <x-admin.picklist name="exclude_category_ids" label="Exclude categories"
                    hint="Any product from these categories in the cart withholds free shipping."
                    :items="$categoryItems" :selected="old('exclude_category_ids', $rule?->exclude_category_ids ?? [])" />
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
