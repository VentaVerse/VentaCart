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
        <div class="border-b border-[#3A4250] pb-4">
            <h3 class="text-sm font-semibold mb-3 text-[#B0B8C4]" class="font-heading">Price</h3>
            <div class="flex items-center gap-2">
                <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="{{ number_format($priceRange->min_price ?? 0, 0) }}" min="0" class="w-full text-xs bg-[#141414] border-[#3A4250] text-[#B0B8C4] rounded px-2 py-1.5 placeholder-[#555]">
                <span class="text-[#4F6070]">-</span>
                <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="{{ number_format($priceRange->max_price ?? 0, 0) }}" min="0" class="w-full text-xs bg-[#141414] border-[#3A4250] text-[#B0B8C4] rounded px-2 py-1.5 placeholder-[#555]">
            </div>
        </div>
    @endif

    {{-- Brands --}}
    @if (($filterSettings['brand_enabled'] ?? true) && $availableBrands->isNotEmpty())
        <div class="border-b border-[#3A4250] pb-4">
            <h3 class="text-sm font-semibold mb-3 text-[#B0B8C4]" class="font-heading">Brand</h3>
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach ($availableBrands as $brand)
                    <label class="flex items-center gap-2 text-sm text-[#8899AA] cursor-pointer hover:text-[#B0B8C4]">
                        <input type="checkbox" name="brand[]" value="{{ $brand->id }}" {{ in_array($brand->id, (array) request('brand', [])) ? 'checked' : '' }} class="rounded border-[#444] bg-[#141414] text-[var(--color-primary)]">
                        {{ $brand->name }}
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Availability --}}
    @if ($filterSettings['availability_enabled'] ?? true)
        <div class="border-b border-[#3A4250] pb-4">
            <h3 class="text-sm font-semibold mb-3 text-[#B0B8C4]" class="font-heading">Availability</h3>
            <label class="flex items-center gap-2 text-sm text-[#8899AA] cursor-pointer hover:text-[#B0B8C4]">
                <input type="checkbox" name="in_stock" value="1" {{ request('in_stock') ? 'checked' : '' }} class="rounded border-[#444] bg-[#141414] text-[var(--color-primary)]">
                In Stock
            </label>
        </div>
    @endif

    {{-- On Sale --}}
    @if ($filterSettings['on_sale_enabled'] ?? true)
        <div class="border-b border-[#3A4250] pb-4">
            <label class="flex items-center gap-2 text-sm text-[#8899AA] cursor-pointer hover:text-[#B0B8C4]">
                <input type="checkbox" name="on_sale" value="1" {{ request('on_sale') ? 'checked' : '' }} class="rounded border-[#444] bg-[#141414] text-[var(--color-primary)]">
                <span class="font-semibold text-[#B0B8C4]" class="font-heading">On Sale</span>
            </label>
        </div>
    @endif

    {{-- Tags --}}
    @if (($filterSettings['tags_enabled'] ?? true) && $availableTags->isNotEmpty())
        <div class="border-b border-[#3A4250] pb-4">
            <h3 class="text-sm font-semibold mb-3 text-[#B0B8C4]" class="font-heading">Tags</h3>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($availableTags as $tag)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="tag[]" value="{{ $tag->id }}" {{ in_array($tag->id, (array) request('tag', [])) ? 'checked' : '' }} class="hidden peer">
                        <span class="inline-block px-2 py-1 text-xs border border-[#333] text-[#888] rounded-full peer-checked:bg-[var(--color-primary)] peer-checked:text-[#0D0D0D] peer-checked:border-[var(--color-primary)] hover:border-[#555] hover:text-[#B0B8C4] transition-colors">
                            {{ $tag->name }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    <button type="submit" class="w-full py-2 text-sm font-medium rounded transition-colors" style="background-color: var(--color-primary); color: #0D0D0D;">
        Apply Filters
    </button>

    @if (request()->hasAny(['min_price', 'max_price', 'brand', 'in_stock', 'on_sale', 'tag']))
        <a href="{{ url()->current() }}{{ request('sort') ? '?sort=' . request('sort') : '' }}" class="block text-center text-xs text-[#666] hover:text-[var(--color-primary)] mt-2">
            Clear Filters
        </a>
    @endif
</form>
