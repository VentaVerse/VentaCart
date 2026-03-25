@php
    $mainImage = $product->images->sortBy('sort_order')->first();
    $imagePath = $mainImage?->path;

    $price = $product->price;
    $salePrice = null;
    $overridePrice = null;
    $customerGroupId = Auth::guard('customer')->user()?->customer_group_id;

    // Check for sale special (shows strikethrough)
    $activeSpecial = $product->specials
        ->where('display_mode', 'sale')
        ->filter(function ($s) use ($customerGroupId) {
            if ($s->date_start && $s->date_start > now()) return false;
            if ($s->date_end && $s->date_end < now()) return false;
            if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
            if ($s->quantity > 1) return false;
            return true;
        })
        ->sortBy('priority')
        ->first();

    if ($activeSpecial) {
        $salePrice = $activeSpecial->price;
    }

    // Check for override special (clean price replacement, no strikethrough)
    if (!$salePrice) {
        $overrideSpecial = $product->specials
            ->where('display_mode', 'override')
            ->filter(function ($s) use ($customerGroupId) {
                if ($s->date_start && $s->date_start > now()) return false;
                if ($s->date_end && $s->date_end < now()) return false;
                if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
                if ($s->quantity > 1) return false;
                return true;
            })
            ->sortBy('priority')
            ->first();

        if ($overrideSpecial) {
            $overridePrice = $overrideSpecial->price;
        }
    }

    $currency = $defaultCurrency ?? (object)['symbol' => '₱'];
    $badges = isset($badgeService) ? $badgeService->getBadgesForProduct($product) : [];
@endphp

<div class="product-card" x-data="{ imgLoaded: false }" x-init="$nextTick(() => { const img = $el.querySelector('img'); if (img && img.complete) imgLoaded = true })">
    <div class="product-image-wrap">
        {{-- Wishlist Heart --}}
        <button
            class="wishlist-btn"
            x-data="wishlistBtn({{ $product->id }})"
            @click.prevent="toggle()"
            :class="{ 'active': wishlisted }"
            title="Add to Wishlist"
        >
            <svg class="w-5 h-5" :fill="wishlisted ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
        </button>

        {{-- Badges --}}
        @php $badgeOffset = 0; @endphp
        @foreach ($badges as $badge)
            @if ($badge['type'] === 'new')
                <span class="badge badge-new" style="top: {{ 12 + $badgeOffset }}px;">{{ $badge['label'] }}</span>
                @php $badgeOffset += 28; @endphp
            @elseif ($badge['type'] === 'hot')
                <span class="badge badge-hot" style="top: {{ 12 + $badgeOffset }}px;">{{ $badge['label'] }}</span>
                @php $badgeOffset += 28; @endphp
            @elseif ($badge['type'] === 'sale')
                <span class="badge badge-sale">{{ $badge['label'] }}</span>
            @endif
        @endforeach

        {{-- Skeleton shimmer (shown while image loads) --}}
        <div class="skeleton-shimmer" x-show="!imgLoaded" x-cloak></div>

        <a href="{{ route('product.show', $product->slug) }}">
            @if ($imagePath)
                <img
                    src="{{ storage_url($imagePath) }}"
                    alt="{{ $product->name }}"
                    loading="lazy"
                    x-on:load="imgLoaded = true"
                    :class="{ 'opacity-0': !imgLoaded }"
                >
            @else
                <div class="w-full h-full flex items-center justify-center text-[#333]" x-init="imgLoaded = true">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            @endif
        </a>
    </div>

    <div class="product-info">
        <div class="product-name">
            <a href="{{ route('product.show', $product->slug) }}">{{ $product->name }}</a>
        </div>

        @if (($product->reviews_count ?? 0) > 0)
            <div class="product-rating">
                @php $avg = round($product->reviews_avg_rating ?? 0, 1); @endphp
                @for ($i = 1; $i <= 5; $i++)
                    @if ($i <= floor($avg))
                        <svg class="star-icon star-full" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.37-2.448a1 1 0 00-1.176 0l-3.37 2.448c-.784.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.065 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/></svg>
                    @elseif ($i - $avg < 1 && $i - $avg > 0)
                        <svg class="star-icon star-half" viewBox="0 0 20 20"><defs><linearGradient id="half-{{ $product->id }}"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="#444" stop-opacity="0.3"/></linearGradient></defs><path fill="url(#half-{{ $product->id }})" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.37-2.448a1 1 0 00-1.176 0l-3.37 2.448c-.784.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.065 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/></svg>
                    @else
                        <svg class="star-icon star-empty" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.37-2.448a1 1 0 00-1.176 0l-3.37 2.448c-.784.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.065 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/></svg>
                    @endif
                @endfor
                <span class="review-count">({{ $product->reviews_count }})</span>
            </div>
        @endif

        <div class="product-price">
            @if ($salePrice)
                <span class="product-price-old">{{ $currency->symbol }}{{ number_format($price, 2) }}</span>
                <span class="product-price-sale">{{ $currency->symbol }}{{ number_format($salePrice, 2) }}</span>
            @elseif ($overridePrice)
                {{ $currency->symbol }}{{ number_format($overridePrice, 2) }}
            @else
                {{ $currency->symbol }}{{ number_format($price, 2) }}
            @endif
        </div>
    </div>
</div>
