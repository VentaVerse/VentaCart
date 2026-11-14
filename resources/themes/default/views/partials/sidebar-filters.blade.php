@php
    $filterSettings = $theme->getGroup('filters');
@endphp

<form method="GET" action="{{ url()->current() }}">
    {{-- Preserve sort --}}
    @if (request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    {{-- Price Range --}}
    @if ($filterSettings['price_enabled'] ?? true)
        <div class="border-b border-[var(--color-border)] pb-4">
            <h3 class="text-sm font-semibold mb-3" class="font-heading">Price</h3>
            <div class="flex items-center gap-2">
                <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="{{ number_format($priceRange->min_price ?? 0, 0) }}" min="0" class="w-full text-xs border-[var(--color-input-border)] rounded px-2 py-1.5">
                <span class="text-[var(--color-text-muted)]">-</span>
                <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="{{ number_format($priceRange->max_price ?? 0, 0) }}" min="0" class="w-full text-xs border-[var(--color-input-border)] rounded px-2 py-1.5">
            </div>
        </div>
    @endif

    {{-- Brands --}}
    @if (($filterSettings['brand_enabled'] ?? true) && $availableBrands->isNotEmpty())
        <div class="border-b border-[var(--color-border)] pb-4">
            <h3 class="text-sm font-semibold mb-3" class="font-heading">Brand</h3>
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach ($availableBrands as $brand)
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="brand[]" value="{{ $brand->id }}" {{ in_array($brand->id, (array) request('brand', [])) ? 'checked' : '' }} class="rounded border-[var(--color-input-border)] text-[var(--color-primary)]">
                        {{ $brand->name }}
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Availability --}}
    @if ($filterSettings['availability_enabled'] ?? true)
        <div class="border-b border-[var(--color-border)] pb-4">
            <h3 class="text-sm font-semibold mb-3" class="font-heading">Availability</h3>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="in_stock" value="1" {{ request('in_stock') ? 'checked' : '' }} class="rounded border-[var(--color-input-border)] text-[var(--color-primary)]">
                In Stock
            </label>
        </div>
    @endif

    {{-- On Sale --}}
    @if ($filterSettings['on_sale_enabled'] ?? true)
        <div class="border-b border-[var(--color-border)] pb-4">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="on_sale" value="1" {{ request('on_sale') ? 'checked' : '' }} class="rounded border-[var(--color-input-border)] text-[var(--color-primary)]">
                <span class="font-semibold" class="font-heading">On Sale</span>
            </label>
        </div>
    @endif

    {{-- Attributes the store marked as filterable --}}
    @foreach (($availableAttributes ?? []) as $vAttr)
        <div class="border-b border-[var(--color-border)] pb-4">
            <h3 class="text-sm font-semibold mb-3 font-heading">{{ $vAttr['name'] }}</h3>
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach ($vAttr['values'] as $vVal)
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="attr[{{ $vAttr['slug'] }}][]" value="{{ $vVal['value'] }}" @checked(in_array($vVal['value'], (array) data_get(request('attr'), $vAttr['slug'], []), true)) class="rounded border-[var(--color-input-border)] text-[var(--color-primary)]">
                        {{ $vVal['value'] }} <span class="text-[var(--color-text-muted)]">({{ $vVal['count'] }})</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Tags --}}
    @if (($filterSettings['tags_enabled'] ?? true) && $availableTags->isNotEmpty())
        <div class="border-b border-[var(--color-border)] pb-4">
            <h3 class="text-sm font-semibold mb-3" class="font-heading">Tags</h3>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($availableTags as $tag)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="tag[]" value="{{ $tag->id }}" {{ in_array($tag->id, (array) request('tag', [])) ? 'checked' : '' }} class="hidden peer">
                        <span class="inline-block px-2 py-1 text-xs border border-[var(--color-input-border)] rounded-full peer-checked:bg-[var(--color-primary)] peer-checked:text-[var(--color-btn-text)] peer-checked:border-[var(--color-primary)] transition-colors">
                            {{ $tag->name }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    <button type="submit" class="w-full py-2 text-sm font-medium text-[var(--color-btn-text)] rounded" style="background-color: var(--color-primary);">
        Apply Filters
    </button>

    @if (request()->hasAny(['min_price', 'max_price', 'brand', 'in_stock', 'on_sale', 'tag', 'attr']))
        <a href="{{ url()->current() }}{{ request('sort') ? '?sort=' . request('sort') : '' }}" class="block text-center text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text)] mt-2">
            Clear Filters
        </a>
    @endif
</form>
