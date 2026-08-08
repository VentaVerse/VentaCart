<x-admin.shell :title="$rule ? 'Edit Rule: ' . $rule->name : 'Create Free Shipping Rule'">
    <x-admin.page :title="$rule ? 'Edit Rule: ' . $rule->name : 'Create Free Shipping Rule'" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')], ['label' => 'Free Shipping Rules', 'url' => route('admin.free-shipping-rules.index')]]">
        <x-slot:actions>
            <x-admin.button variant="ghost" :href="route('admin.free-shipping-rules.index')">Back to Rules</x-admin.button>
        </x-slot:actions>

        @php
            $brandItems = $brands->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values()->all();
            $categoryItems = [];
            foreach ($categories as $cat) {
                $categoryItems[] = ['id' => $cat->id, 'name' => $cat->name];
                foreach ($cat->children as $child) {
                    $categoryItems[] = ['id' => $child->id, 'name' => '- ' . $child->name];
                }
            }
            $groupItems = $customerGroups->map(fn($g) => ['id' => $g->id, 'name' => $g->name])->values()->all();
            $zoneItems = $shippingZones->map(fn($z) => ['id' => $z->id, 'name' => $z->name])->values()->all();
        @endphp

        <form method="POST"
              action="{{ $rule ? route('admin.free-shipping-rules.update', $rule) : route('admin.free-shipping-rules.store') }}"
              data-v-form>
            @csrf
            @if ($rule)
                @method('PUT')
            @endif

            {{-- General --}}
            <x-admin.card title="General">
                <div class="space-y-4">
                    <x-admin.field name="name" label="Rule Name" help="Admin reference">
                        <x-admin.input type="text" name="name" :value="$rule?->name" required placeholder="e.g. Free Shipping over ₱5,000" />
                    </x-admin.field>
                    <x-admin.field name="checkout_name" label="Checkout Name" help="Shown to customer; falls back to Rule Name">
                        <x-admin.input type="text" name="checkout_name" :value="$rule?->checkout_name" placeholder="e.g. Free Shipping" />
                    </x-admin.field>
                    <div class="grid grid-cols-3 gap-4">
                        <x-admin.field name="min_order_total" label="Min Order Total">
                            <x-admin.input type="number" name="min_order_total" :value="$rule?->min_order_total" min="0" step="0.01" placeholder="e.g. 5000" />
                        </x-admin.field>
                        <x-admin.field name="min_quantity" label="Min Quantity">
                            <x-admin.input type="number" name="min_quantity" :value="$rule?->min_quantity" min="1" placeholder="e.g. 3" />
                        </x-admin.field>
                        <x-admin.field name="sort_order" label="Sort Order">
                            <x-admin.input type="number" name="sort_order" :value="$rule?->sort_order ?? 0" min="0" />
                        </x-admin.field>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <x-admin.toggle name="is_active" :checked="$rule?->is_active ?? true" />
                        <span class="text-[13px] text-v-text">Active</span>
                    </label>
                    <div class="border-t border-v-border pt-4 mt-2 space-y-3">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <x-admin.toggle name="apply_whole_cart" :checked="$rule?->apply_whole_cart ?? false" class="mt-0.5" />
                            <div>
                                <span class="text-[13px] text-v-text font-medium">Apply to whole cart</span>
                                <p class="text-xs text-v-muted">If ANY qualifying product is in the cart, the entire cart gets free shipping, even if other products don't match the brand/category conditions.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <x-admin.toggle name="show_badge" :checked="$rule?->show_badge ?? false" class="mt-0.5" />
                            <div>
                                <span class="text-[13px] text-v-text font-medium">Show "Free Shipping" badge</span>
                                <p class="text-xs text-v-muted">Display a "Free Shipping" label on product cards and product pages for items that qualify for this rule.</p>
                            </div>
                        </label>
                    </div>
                </div>
            </x-admin.card>

            {{-- Conditions --}}
            <x-admin.card title="Conditions" description="All conditions must be met. Leave a section empty to skip that condition." class="mt-6">
                <div class="space-y-6">
                    @include('admin.partials.dual-listbox', [
                        'name' => 'brand_ids',
                        'label' => 'Only for these Brands',
                        'hint' => 'Cart must contain ONLY products from selected brands.',
                        'items' => $brandItems,
                        'selected' => old('brand_ids', $rule?->brand_ids ?? []),
                    ])

                    @include('admin.partials.dual-listbox', [
                        'name' => 'category_ids',
                        'label' => 'Only for these Categories',
                        'hint' => 'Cart must contain ONLY products from selected categories.',
                        'items' => $categoryItems,
                        'selected' => old('category_ids', $rule?->category_ids ?? []),
                    ])

                    @include('admin.partials.dual-listbox', [
                        'name' => 'customer_group_ids',
                        'label' => 'Only for these Customer Groups',
                        'hint' => 'Leave empty = all groups.',
                        'items' => $groupItems,
                        'selected' => old('customer_group_ids', $rule?->customer_group_ids ?? []),
                    ])

                    @include('admin.partials.dual-listbox', [
                        'name' => 'shipping_zone_ids',
                        'label' => 'Only for these Shipping Zones',
                        'hint' => 'Leave empty = all zones.',
                        'items' => $zoneItems,
                        'selected' => old('shipping_zone_ids', $rule?->shipping_zone_ids ?? []),
                    ])
                </div>
            </x-admin.card>

            {{-- Exclusions --}}
            <x-admin.card title="Exclusions" description="Free shipping will NOT apply if the cart/customer matches any exclusion." class="mt-6">
                <div class="space-y-6">
                    @include('admin.partials.dual-listbox', [
                        'name' => 'exclude_customer_group_ids',
                        'label' => 'Exclude Customer Groups',
                        'hint' => 'These groups will never get free shipping from this rule.',
                        'items' => $groupItems,
                        'selected' => old('exclude_customer_group_ids', $rule?->exclude_customer_group_ids ?? []),
                    ])

                    @include('admin.partials.dual-listbox', [
                        'name' => 'exclude_brand_ids',
                        'label' => 'Exclude Brands',
                        'hint' => 'If cart contains ANY product from these brands, free shipping won\'t apply.',
                        'items' => $brandItems,
                        'selected' => old('exclude_brand_ids', $rule?->exclude_brand_ids ?? []),
                    ])

                    @include('admin.partials.dual-listbox', [
                        'name' => 'exclude_category_ids',
                        'label' => 'Exclude Categories',
                        'hint' => 'If cart contains ANY product from these categories, free shipping won\'t apply.',
                        'items' => $categoryItems,
                        'selected' => old('exclude_category_ids', $rule?->exclude_category_ids ?? []),
                    ])
                </div>
            </x-admin.card>

            <div class="mt-6 flex justify-end gap-3">
                <x-admin.button variant="secondary" :href="route('admin.free-shipping-rules.index')">Cancel</x-admin.button>
                <x-admin.button type="submit">{{ $rule ? 'Update Rule' : 'Create Rule' }}</x-admin.button>
            </div>
        </form>
    </x-admin.page>
</x-admin.shell>
