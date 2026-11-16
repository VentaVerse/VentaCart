<x-admin.settings-shell title="Catalog" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Catalog</h1>
            <p class="v-head__standing">How products are listed, sorted and badged.</p>
        </div>
    </div>

    <x-admin.form :action="route('admin.settings.update')" class="v-settle">
        <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'catalog') }}">

        <x-admin.panel title="Product listings">
            <div class="v-narrow">
                <x-admin.field name="groups.category_page.products_per_page" label="Products per page">
                    <input type="number" name="groups[category_page][products_per_page]" min="{{ \App\Support\ProductListing::PER_PAGE_MIN }}" max="{{ \App\Support\ProductListing::PER_PAGE_MAX }}" class="v-control v-mono v-w-rate" value="{{ old('groups.category_page.products_per_page', $settings['category_page']['products_per_page'] ?? \App\Support\ProductListing::PER_PAGE_DEFAULT) }}">
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="groups.category_page.default_sort" label="Default sort">
                        <select name="groups[category_page][default_sort]" class="v-control">
                            @foreach (\App\Support\ProductListing::sorts() as $value => $label)
                                <option value="{{ $value }}" @selected(old('groups.category_page.default_sort', $settings['category_page']['default_sort'] ?? \App\Support\ProductListing::DEFAULT_SORT) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Sidebar filters">
            @foreach (['price_enabled' => 'Price range', 'availability_enabled' => 'Availability', 'brand_enabled' => 'Brand', 'rating_enabled' => 'Rating', 'on_sale_enabled' => 'On sale', 'tags_enabled' => 'Tags'] as $key => $label)
                <div class="{{ $loop->first ? '' : 'v-gap' }}">
                    <label class="v-toggle">
                        <input type="hidden" name="groups[filters][{{ $key }}]" value="0">
                        <input type="checkbox" name="groups[filters][{{ $key }}]" value="1"
                               @checked(($settings['filters'][$key] ?? '0') === '1')>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </x-admin.panel>

        <x-admin.panel title="Badges">
            <div class="v-narrow">
                <x-admin.field name="groups.badges.new_count" label="NEW badge: last X products" hint="Products added most recently. 0 turns it off.">
                    <input type="number" name="groups[badges][new_count]" min="0" max="100" class="v-control v-mono v-w-rate" value="{{ $settings['badges']['new_count'] ?? '10' }}">
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="groups.badges.hot_count" label="HOT badge: top X best sellers" hint="Products with the most orders. 0 turns it off.">
                        <input type="number" name="groups[badges][hot_count]" min="0" max="100" class="v-control v-mono v-w-rate" value="{{ $settings['badges']['hot_count'] ?? '5' }}">
                    </x-admin.field>
                </div>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.settings-shell>
