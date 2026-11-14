@if (($products ?? collect())->isNotEmpty())
@php
    $config = $placement->config ?? [];
    $columns = max(2, min(6, (int) ($config['columns'] ?? 4)));
    $layout = ($config['layout'] ?? 'grid') === 'slider' ? 'slider' : 'grid';
    $cardStyle = $config['card'] ?? 'default';
    $cardStyle = in_array($cardStyle, ['default', 'compact', 'overlay'], true) ? $cardStyle : 'default';
@endphp
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif

        @if ($layout === 'slider')
            <div class="wslider reveal" x-data="brandCarousel()">
                <button type="button" class="wslider__btn wslider__btn--prev" @click="scrollLeft()" aria-label="Previous">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div x-ref="scroll" class="wslider__track wslider__track--{{ $columns }}">
                    @foreach ($products as $product)
                        <div class="wslider__item">
                            @include('partials.product-card', ['product' => $product, 'cardStyle' => $cardStyle])
                        </div>
                    @endforeach
                </div>
                <button type="button" class="wslider__btn wslider__btn--next" @click="scrollRight()" aria-label="Next">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        @else
            <div class="wgrid wgrid--{{ $columns }} reveal-stagger">
                @foreach ($products as $product)
                    @include('partials.product-card', ['product' => $product, 'cardStyle' => $cardStyle])
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
