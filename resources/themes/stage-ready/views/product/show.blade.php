<x-store-layout :title="$product->meta_title ?: $product->name" :meta-description="$product->meta_description">

    @section('og_title', $product->meta_title ?: $product->name)
    @section('og_description', $product->meta_description ?: Str::limit(strip_tags($product->description), 200))
    @if ($product->images->first())
        @section('og_image', storage_url($product->images->first()->path))
    @endif

    @push('head')
        <meta name="product-id" content="{{ $product->id }}">
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "Product",
            "name": "{{ e($product->name) }}",
            "description": "{{ e(Str::limit(strip_tags($product->description), 200)) }}",
            "sku": "{{ e($product->sku) }}",
            "brand": {
                "@@type": "Brand",
                "name": "{{ e($product->brand?->name ?? '') }}"
            },
            "offers": {
                "@@type": "Offer",
                "price": "{{ $effectivePrice }}",
                "priceCurrency": "PHP",
                "availability": "{{ $product->quantity > 0 || !$product->track_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}"
            }
            @if ($reviewCount > 0)
            ,"aggregateRating": {
                "@@type": "AggregateRating",
                "ratingValue": "{{ number_format($averageRating, 1) }}",
                "reviewCount": "{{ $reviewCount }}"
            }
            @endif
        }
        </script>
    @endpush

    {{-- Breadcrumbs --}}
    <div class="product-page-breadcrumbs">
        <div class="container-store py-3">
            <nav>
                <a href="{{ route('home') }}">Home</a>
                @foreach ($breadcrumbs as $crumb)
                    <svg class="w-3 h-3 text-[#333]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <a href="{{ route('category.show', $crumb->slug) }}">{{ $crumb->name }}</a>
                @endforeach
                <svg class="w-3 h-3 text-[#333]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-[#999] truncate">{{ $product->name }}</span>
            </nav>
        </div>
    </div>

    @php
        $images = $product->images->sortBy('sort_order')->values();
        // Ensure main image is first and included
        if ($product->image) {
            $mainInGallery = $images->firstWhere('path', $product->image);
            if (!$mainInGallery) {
                $images->prepend((object)['path' => $product->image, 'sort_order' => -1]);
            } elseif ($images->search(fn($img) => $img->path === $product->image) !== 0) {
                $images = $images->reject(fn($img) => $img->path === $product->image)->prepend($mainInGallery)->values();
            }
        }
        $thumbPosition = $settings['thumbnail_position'] ?? 'left';
        $currencySymbol = $defaultCurrency->symbol ?? '₱';
    @endphp

    <div class="container-store py-8">
        <div class="flex flex-col lg:flex-row gap-10"
             x-data="addToCart({
                 productId: {{ $product->id }},
                 hasVariants: {{ $product->has_variants ? 'true' : 'false' }},
                 variantMatrix: {{ json_encode($variantMatrix) }},
                 variantTypes: {{ json_encode($variantTypes) }},
                 basePrice: {{ $effectivePrice }},
                 priceTiers: {{ json_encode($priceTiers) }},
                 productPrice: {{ $product->price }},
                 trackStock: {{ $product->track_stock ? 'true' : 'false' }},
                 productQuantity: {{ $product->quantity }},
                 customerName: '{{ addslashes(auth('customer')->user()?->first_name ?? '') }}',
                 customerEmail: '{{ addslashes(auth('customer')->user()?->email ?? '') }}'
             })">

            {{-- Image Gallery --}}
            <div class="lg:w-1/2" x-data="{
                    activeImage: 0, zooming: false, zoomX: 50, zoomY: 50,
                    lightbox: false, dragStartX: 0, dragDelta: 0, dragging: false,
                    imageCount: {{ $images->count() }},
                    slideWidth: 0,
                    init() {
                        this.$watch('lightbox', (open) => {
                            if (!open) { this.dragging = false; this.dragDelta = 0; }
                            else { this.$nextTick(() => { this.slideWidth = this.$refs.lbStrip ? this.$refs.lbStrip.parentElement.offsetWidth : window.innerWidth; }); }
                        });
                    },
                    startDrag(x) {
                        this.dragging = true;
                        this.dragStartX = x;
                        this.dragDelta = 0;
                    },
                    onDrag(x) {
                        if (!this.dragging || !this.lightbox) return;
                        this.dragDelta = x - this.dragStartX;
                    },
                    endDrag() {
                        if (!this.dragging) return;
                        this.dragging = false;
                        const pct = Math.abs(this.dragDelta) / this.slideWidth * 100;
                        if (pct > 10 && this.imageCount > 1) {
                            const direction = this.dragDelta < 0 ? 1 : -1;
                            const next = this.activeImage + direction;
                            this.activeImage = Math.max(0, Math.min(next, this.imageCount - 1));
                        }
                        this.dragDelta = 0;
                    },
                    goTo(index) {
                        this.activeImage = Math.max(0, Math.min(index, this.imageCount - 1));
                        this.dragDelta = 0;
                    },
                    get stripOffset() {
                        return -(this.activeImage * this.slideWidth) + this.dragDelta;
                    }
                 }"
                 @keydown.escape.window="lightbox = false"
                 @keydown.left.window="if (lightbox) goTo(activeImage - 1)"
                 @keydown.right.window="if (lightbox) goTo(activeImage + 1)">
                <div class="flex {{ $thumbPosition === 'bottom' ? 'flex-col' : 'flex-row' }} gap-3">
                    {{-- Thumbnails --}}
                    @if ($images->count() > 1)
                        <div class="{{ $thumbPosition === 'bottom' ? 'order-2' : 'order-1 w-16 flex-shrink-0' }} relative" x-data="{ thumbContainer: null }" x-init="thumbContainer = $refs.thumbScroll">
                            @if ($thumbPosition !== 'bottom' && $images->count() > 6)
                                <button @click="thumbContainer.scrollBy({ top: -200, behavior: 'smooth' })"
                                    class="absolute -top-1 left-0 w-full h-6 flex items-center justify-center bg-gradient-to-b from-[#111] to-transparent z-10 text-[#999] hover:text-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                </button>
                            @endif
                            <div x-ref="thumbScroll" class="{{ $thumbPosition === 'bottom' ? 'flex gap-2 overflow-x-auto' : 'flex flex-col gap-2 overflow-y-auto' }}"
                                 style="{{ $thumbPosition !== 'bottom' ? 'max-height: 420px;' : '' }} scrollbar-width: none;">
                                @foreach ($images as $i => $img)
                                    <button @click="activeImage = {{ $i }}"
                                        :class="activeImage === {{ $i }} ? 'active' : ''"
                                        class="product-gallery-thumb w-16 h-16 flex-shrink-0">
                                        <img src="{{ storage_url($img->path) }}" alt="" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                            @if ($thumbPosition !== 'bottom' && $images->count() > 6)
                                <button @click="thumbContainer.scrollBy({ top: 200, behavior: 'smooth' })"
                                    class="absolute -bottom-1 left-0 w-full h-6 flex items-center justify-center bg-gradient-to-t from-[#111] to-transparent z-10 text-[#999] hover:text-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            @endif
                        </div>
                    @endif

                    {{-- Main Image --}}
                    <div class="flex-1 {{ $thumbPosition === 'bottom' ? 'order-1' : 'order-2' }} product-gallery-main relative"
                         @mouseenter="zooming = true"
                         @mouseleave="zooming = false"
                         @mousemove="const r = $el.getBoundingClientRect(); zoomX = ((event.clientX - r.left) / r.width) * 100; zoomY = ((event.clientY - r.top) / r.height) * 100"
                         @click="lightbox = true; zooming = false">
                        <div class="aspect-square"></div>
                        @foreach ($images as $i => $img)
                            <div x-show="activeImage === {{ $i }}" class="absolute inset-0 flex items-center justify-center product-gallery-zoom"
                                 :style="zooming && activeImage === {{ $i }} ? { transform: 'scale(2)', transformOrigin: zoomX + '% ' + zoomY + '%' } : {}">
                                <img src="{{ storage_url($img->path) }}" alt="{{ $product->name }}" class="max-w-full max-h-full object-contain p-4">
                            </div>
                        @endforeach
                        @if ($images->isEmpty())
                            <div class="aspect-square flex items-center justify-center text-[#333]">
                                <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Lightbox Modal --}}
                <div x-show="lightbox" x-transition.opacity.duration.200ms
                     class="fixed inset-0 z-[9999] flex flex-col bg-white">

                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 shrink-0">
                        @if ($images->count() > 1)
                            <span class="text-sm text-gray-500" x-text="(activeImage + 1) + ' / {{ $images->count() }}'"></span>
                        @else
                            <span></span>
                        @endif
                        <button @click="lightbox = false"
                            class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-800 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="flex-1 relative overflow-hidden"
                         @mousedown.self="lightbox = false">
                        @if ($images->count() > 1)
                            <button @click.stop="goTo(activeImage - 1)"
                                class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 z-10 w-11 h-11 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 transition-colors shadow-sm"
                                :class="activeImage === 0 ? 'opacity-30 pointer-events-none' : ''">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button @click.stop="goTo(activeImage + 1)"
                                class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 z-10 w-11 h-11 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 transition-colors shadow-sm"
                                :class="activeImage === imageCount - 1 ? 'opacity-30 pointer-events-none' : ''">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @endif

                        <div class="w-full h-full flex items-center"
                             @mousedown.prevent="startDrag(event.clientX)"
                             @mousemove.window="onDrag(event.clientX)"
                             @mouseup.window="endDrag()"
                             @touchstart="startDrag(event.touches[0].clientX)"
                             @touchmove.prevent="onDrag(event.touches[0].clientX)"
                             @touchend="endDrag()"
                             :class="dragging ? 'cursor-grabbing' : 'cursor-grab'">
                            <div x-ref="lbStrip" class="flex h-full select-none"
                                 :style="{ transform: 'translateX(' + stripOffset + 'px)', transition: dragging ? 'none' : 'transform 0.4s ease' }">
                                @foreach ($images as $i => $img)
                                    <div class="flex items-center justify-center shrink-0 p-6 sm:p-12" :style="{ width: slideWidth + 'px' }">
                                        <img src="{{ storage_url($img->path) }}" alt="{{ $product->name }}"
                                             class="max-w-full max-h-full object-contain pointer-events-none">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if ($images->count() > 1)
                        <div class="shrink-0 border-t border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="flex items-center justify-center gap-2 overflow-x-auto" style="scrollbar-width: none;">
                                @foreach ($images as $i => $img)
                                    <button @click="goTo({{ $i }})" class="shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition-colors"
                                            :class="activeImage === {{ $i }} ? 'border-gray-800' : 'border-transparent hover:border-gray-300'">
                                        <img src="{{ storage_url($img->path) }}" alt="" class="w-full h-full object-contain p-1" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Product Info --}}
            <div class="lg:w-1/2">
                <h1 class="text-3xl mb-2" class="font-heading-wide">{{ $product->name }}</h1>

                {{-- Average Rating --}}
                @if ($reviewCount > 0)
                    <div class="flex items-center gap-2 mb-2">
                        <div class="star-rating">
                            @for ($i = 1; $i <= 5; $i++)
                                @if ($i <= round($averageRating))
                                    <span>&#9733;</span>
                                @else
                                    <span class="star-empty">&#9733;</span>
                                @endif
                            @endfor
                        </div>
                        <a href="#reviews" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">({{ $reviewCount }} {{ Str::plural('review', $reviewCount) }})</a>
                    </div>
                @endif

                {{-- Brand --}}
                @if (($settings['show_brand'] ?? true) && $product->brand)
                    <p class="text-sm text-[#666] mb-4">
                        Brand: <a href="{{ route('brand.show', $product->brand) }}" class="text-[#999] hover:text-[var(--color-primary)] transition-colors">{{ $product->brand->name }}</a>
                    </p>
                @endif

                {{-- Price --}}
                <div class="mb-5">
                    @if ($activeSpecial)
                    {{-- Variant + sale: show slashed regular variant price --}}
                    <div class="flex items-baseline gap-3"
                         x-show="hasVariants && activeVariant && activeVariant.regular_price > activeVariant.price">
                        <span class="text-4xl" style="font-family: var(--font-heading); color: var(--color-primary); letter-spacing: 0.05em;">
                            {{ $currencySymbol }}<span x-text="displayPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </span>
                        <span class="text-lg text-[#555] line-through">
                            {{ $currencySymbol }}<span x-text="(activeVariant?.regular_price ?? 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </span>
                        <span class="text-xs font-bold text-white px-2 py-0.5 rounded"
                              style="background-color: var(--color-secondary); font-family: var(--font-heading); letter-spacing: 0.1em;"
                              x-text="'-' + Math.round((1 - activeVariant.price / activeVariant.regular_price) * 100) + '%'"></span>
                    </div>
                    @endif

                    {{-- Dynamic price: per-unit price (line total shown in the clarifier below) --}}
                    <span class="text-4xl" style="font-family: var(--font-heading); letter-spacing: 0.05em;"
                          :style="(quantity > 1 || (hasVariants && activeVariant)) @if ($activeSpecial)&& !(hasVariants && activeVariant && activeVariant.regular_price > activeVariant.price)@endif ? '' : 'display:none'">
                        {{ $currencySymbol }}<span x-text="displayPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                    </span>

                    {{-- Headline shows the per-unit price; this clarifier makes the line
                         total explicit when buying more than one, so the big number can't
                         be misread as the total (OpenCart parity: product price is per-unit). --}}
                    <div x-show="quantity > 1" class="mt-1.5 text-sm" style="color: var(--color-text-muted, #888);">
                        <span class="text-[11px] uppercase tracking-wider">each</span>
                        <span> · <span x-text="quantity"></span> × {{ $currencySymbol }}<span x-text="displayPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span> = {{ $currencySymbol }}<span x-text="totalPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                    </div>

                    <div x-show="quantity <= 1 && (!hasVariants || !activeVariant)">
                        @if ($activeSpecial)
                            <div class="flex items-baseline gap-3">
                                <span class="text-4xl" style="font-family: var(--font-heading); color: var(--color-primary); letter-spacing: 0.05em;">
                                    {{ $currencySymbol }}{{ number_format($activeSpecial->price, 2) }}
                                </span>
                                <span class="text-lg text-[#555] line-through">
                                    {{ $currencySymbol }}{{ number_format($product->price, 2) }}
                                </span>
                                @php $pct = round((1 - $activeSpecial->price / $product->price) * 100); @endphp
                                <span class="text-xs font-bold text-white px-2 py-0.5 rounded" style="background-color: var(--color-secondary); font-family: var(--font-heading); letter-spacing: 0.1em;">
                                    -{{ $pct }}%
                                </span>
                            </div>

                            @if ($activeSpecial->date_end && $activeSpecial->date_end->lt(now()->addYear()))
                                <div class="mt-2" x-data="countdown({ endDate: '{{ $activeSpecial->date_end->toISOString() }}' })">
                                    <div class="countdown" x-show="!expired">
                                        <span class="unit" x-text="days"></span><span class="countdown-label">D</span>
                                        <span class="separator">:</span>
                                        <span class="unit" x-text="hours"></span><span class="countdown-label">H</span>
                                        <span class="separator">:</span>
                                        <span class="unit" x-text="minutes"></span><span class="countdown-label">M</span>
                                        <span class="separator">:</span>
                                        <span class="unit" x-text="seconds"></span><span class="countdown-label">S</span>
                                    </div>
                                </div>
                            @endif
                        @elseif ($overrideSpecial)
                            <span class="text-4xl" style="font-family: var(--font-heading); letter-spacing: 0.05em;">
                                {{ $currencySymbol }}{{ number_format($overrideSpecial->price, 2) }}
                            </span>
                        @else
                            <span class="text-4xl" style="font-family: var(--font-heading); letter-spacing: 0.05em;">
                                {{ $currencySymbol }}{{ number_format($product->price, 2) }}
                                </span>
                            @endif
                        </div>
                    </template>
                </div>

                @if ($hasFreeShipping)
                    <div class="flex items-center gap-2 mb-4" style="color: var(--color-primary);">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                        <span style="font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Free Shipping Nationwide</span>
                    </div>
                @endif

                {{-- Stock --}}
                @if ($settings['show_stock'] ?? true)
                    <div class="product-detail-stock flex items-center gap-2 mb-5 text-sm">
                        @if (!$product->track_stock || $product->quantity > 0)
                            <span class="led led-green"></span>
                            <span class="in-stock">In Stock</span>
                            @if ($product->track_stock)
                                <span class="text-[#555]">({{ $product->quantity }} available)</span>
                            @endif
                        @else
                            <span class="led led-red"></span>
                            <span class="out-of-stock">Out of Stock</span>
                        @endif
                    </div>
                @endif

                <div class="divider-glow my-5"></div>

                {{-- Variants --}}
                @if ($product->has_variants && $product->variantTypes->isNotEmpty())
                    <div class="space-y-4 mb-5">
                        <h3 class="text-sm tracking-[0.15em] text-[#999]" class="font-heading">AVAILABLE OPTIONS</h3>
                        @foreach ($product->variantTypes as $type)
                            <div>
                                <label class="block text-sm text-[#777] mb-1.5">{{ $type->name }} <span class="text-[var(--color-secondary)]">*</span></label>
                                <select class="product-variant-select"
                                        @change="selectValue({{ $type->id }}, $event.target.value)">
                                    <option value="">-- Please Select --</option>
                                    @foreach ($type->values as $option)
                                        @php $optStock = $valueStockMap[$option->id] ?? null; @endphp
                                        <option value="{{ $option->id }}">{{ $option->value }}{{ $optStock !== null ? ($optStock > 0 ? " — In Stock ({$optStock})" : ' — Out of Stock') : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach

                    </div>
                @endif
                {{-- Volume / tier pricing (current customer group) --}}
                @if (!empty($priceTiers) && collect($priceTiers)->where('qty', '>', 1)->isNotEmpty())
                    <div class="mb-5 rounded-lg border p-3" style="border-color: var(--color-border, #2a2a2a);">
                        <p class="text-[11px] uppercase tracking-wider mb-2" style="color: var(--color-text-muted, #888);">Volume pricing</p>
                        <div class="space-y-1 text-sm">
                            @foreach ($priceTiers as $i => $tier)
                                @if ($tier['qty'] > 1)
                                    @php
                                        $next = $priceTiers[$i + 1] ?? null;
                                        $range = $next ? $tier['qty'] . '–' . ($next['qty'] - 1) : $tier['qty'] . '+';
                                    @endphp
                                    <div class="flex items-center justify-between"
                                         :class="activeTier && activeTier.qty === {{ $tier['qty'] }} ? 'font-semibold' : ''"
                                         :style="activeTier && activeTier.qty === {{ $tier['qty'] }} ? 'color: var(--color-primary)' : 'color: var(--color-text-muted, #999)'">
                                        <span>{{ $range }} pcs</span>
                                        <span>{{ $currencySymbol }}{{ number_format($tier['price'], 2) }} each</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Add to Cart / Notify Me --}}
                <template x-if="!isOutOfStock">
                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <div class="cart-item-qty">
                                <button type="button" class="qty-btn" @click="if(quantity > 1) quantity--">-</button>
                                <input type="number" x-model.number="quantity" min="1" class="qty-input">
                                <button type="button" class="qty-btn" @click="quantity++">+</button>
                            </div>
                            <button class="product-add-btn" @click="submit()" :disabled="loading">
                                <template x-if="!loading">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                                        ADD TO CART
                                    </span>
                                </template>
                                <template x-if="loading">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        ADDING...
                                    </span>
                                </template>
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="isOutOfStock">
                    <div class="mb-6">
                        <button class="product-notify-btn" @click="notifyModal = true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            NOTIFY ME WHEN IN STOCK
                        </button>
                    </div>
                </template>

                {{-- ATC Message --}}
                <div x-show="message" x-transition.opacity>
                    <div class="atc-message"
                         :class="messageType === 'success' ? 'atc-message-success' : 'atc-message-error'"
                         x-text="message"></div>
                </div>

                {{-- Notify Me Modal --}}
                <div x-show="notifyModal" x-cloak class="checkout-modal-overlay" @click.self="notifyModal = false">
                    <div class="checkout-modal" @click.stop>
                        <h2 class="checkout-modal-title">NOTIFY ME WHEN IN STOCK</h2>
                        <p class="text-sm text-[#999] mb-4">
                            We'll email you when
                            <strong class="text-[#C8C8C8]" x-text="(hasVariants && activeVariant) ? activeVariant.name : '{{ addslashes($product->name) }}'"></strong>
                            is back in stock.
                        </p>
                        <div class="space-y-4">
                            <div>
                                <label class="account-label">Name</label>
                                <input type="text" x-model="notifyName" class="account-input w-full" placeholder="Your name">
                            </div>
                            <div>
                                <label class="account-label">Email</label>
                                <input type="email" x-model="notifyEmail" class="account-input w-full" placeholder="your@email.com">
                            </div>
                        </div>
                            <x-turnstile id="turnstile-notify" />
                        <div x-show="notifyMessage" class="mt-3 text-sm" :class="notifySuccess ? 'text-emerald-400' : 'text-[var(--color-secondary)]'" x-text="notifyMessage"></div>
                        <div class="checkout-modal-actions mt-6">
                            <button @click="notifyModal = false" class="checkout-modal-btn-back">Cancel</button>
                            <button @click="submitNotify()" class="checkout-modal-btn-confirm" :disabled="notifyLoading || !notifyName || !notifyEmail">
                                <span x-show="!notifyLoading">Notify Me</span>
                                <span x-show="notifyLoading" x-cloak>Submitting...</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Tags --}}
                @if ($product->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5 mb-4 mt-5">
                        @foreach ($product->tags as $tag)
                            <span class="product-tag">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif

            </div>
        </div>

        {{-- Description Tabs --}}
        <div class="mt-12" x-data="{ tab: 'description' }" id="reviews">
            <div class="flex border-b border-[#2A2A2A]">
                <button @click="tab = 'description'" :class="tab === 'description' ? 'active' : ''" class="product-tab-btn">
                    DESCRIPTION
                </button>
                <button @click="tab = 'reviews'" :class="tab === 'reviews' ? 'active' : ''" class="product-tab-btn">
                    REVIEWS ({{ $reviewCount }})
                </button>
            </div>
            <div class="py-6">
                <div x-show="tab === 'description'" class="product-description">
                    {!! $product->description !!}
                </div>
                <div x-show="tab === 'reviews'" x-cloak x-data="{ reviewPage: 1, perPage: 20 }">
                    @if ($approvedReviews->isNotEmpty())
                        <div class="space-y-6">
                            @foreach ($approvedReviews as $idx => $review)
                                <div class="review-card" x-show="{{ $idx }} >= (reviewPage - 1) * perPage && {{ $idx }} < reviewPage * perPage">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-sm font-medium text-[#E8E8E8]">{{ $review->name }}</span>
                                            </div>
                                            <div class="star-rating">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= $review->rating)
                                                        <span>&#9733;</span>
                                                    @else
                                                        <span class="star-empty">&#9733;</span>
                                                    @endif
                                                @endfor
                                            </div>
                                        </div>
                                        <span class="text-xs text-[#555]">{{ $review->published_at?->format('M j, Y') }}</span>
                                    </div>
                                    @if ($review->title)
                                        <h4 class="text-sm font-semibold text-[#E8E8E8] mb-1">{{ $review->title }}</h4>
                                    @endif
                                    @if ($review->body)
                                        <p class="text-sm text-[#999] whitespace-pre-wrap">{{ $review->body }}</p>
                                    @endif
                                    @if ($review->images->isNotEmpty())
                                        <div class="flex gap-2 mt-3">
                                            @foreach ($review->images as $img)
                                                <a href="{{ storage_url($img->path) }}" target="_blank" class="w-16 h-16 rounded overflow-hidden border border-[#2A2A2A]">
                                                    <img src="{{ storage_url($img->path) }}" alt="Review photo" class="w-full h-full object-cover">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if ($approvedReviews->count() > 20)
                            <div class="flex items-center justify-center gap-2 mt-6 pt-4 border-t border-[#2A2A2A]">
                                <button @click="reviewPage = Math.max(1, reviewPage - 1); document.getElementById('reviews').scrollIntoView({behavior: 'smooth'})"
                                    :disabled="reviewPage === 1"
                                    style="padding: 6px 12px; font-size: 13px; border-radius: 6px; border: 1px solid #333; color: #999; background: transparent; cursor: pointer;"
                                    :style="reviewPage === 1 && 'opacity: 0.3; cursor: not-allowed;'">
                                    &laquo; Prev
                                </button>
                                <span style="font-size: 13px; color: #777;">
                                    Page <span x-text="reviewPage"></span> of <span x-text="Math.ceil({{ $approvedReviews->count() }} / perPage)"></span>
                                </span>
                                <button @click="reviewPage = Math.min(Math.ceil({{ $approvedReviews->count() }} / perPage), reviewPage + 1); document.getElementById('reviews').scrollIntoView({behavior: 'smooth'})"
                                    :disabled="reviewPage >= Math.ceil({{ $approvedReviews->count() }} / perPage)"
                                    style="padding: 6px 12px; font-size: 13px; border-radius: 6px; border: 1px solid #333; color: #999; background: transparent; cursor: pointer;"
                                    :style="reviewPage >= Math.ceil({{ $approvedReviews->count() }} / perPage) && 'opacity: 0.3; cursor: not-allowed;'">
                                    Next &raquo;
                                </button>
                            </div>
                        @endif
                    @else
                        <p class="text-[#555] text-sm">No reviews yet.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Related Products --}}
        @if (in_array('related', $belowModules) && $relatedProducts->isNotEmpty())
            <div class="mt-8 border-t border-[#1A1A1A] pt-8">
                <h2 class="section-title">Related Products</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 reveal-stagger">
                    @foreach ($relatedProducts->take(4) as $related)
                        @include('partials.product-card', ['product' => $related])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Same Brand --}}
        @if (in_array('same_brand', $belowModules) && $sameBrandProducts->isNotEmpty())
            <div class="mt-8 border-t border-[#1A1A1A] pt-8">
                <h2 class="section-title">More from {{ $product->brand->name }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 reveal-stagger">
                    @foreach ($sameBrandProducts->take(4) as $brandProduct)
                        @include('partials.product-card', ['product' => $brandProduct])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-store-layout>
