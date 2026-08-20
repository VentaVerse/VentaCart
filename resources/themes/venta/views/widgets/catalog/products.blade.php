@if (($products ?? collect())->isNotEmpty())
<section class="py-10 md:py-14">
    <div class="container-store">
        <h2 class="section-title reveal">{{ $placement->title }}</h2>
@php
    $config = $placement->config ?? [];
    $colsMobile = $config['columns_mobile'] ?? 2;
    $colsTablet = $config['columns_tablet'] ?? 3;
    $colsDesktop = $config['columns_desktop'] ?? 4;
    $gridId = 'section-grid-' . $placement->id;
@endphp

@if ($products->isNotEmpty())
    <div id="{{ $gridId }}" class="grid gap-5 reveal-stagger">
        @foreach ($products as $product)
            @include('partials.product-card', ['product' => $product])
        @endforeach
    </div>

    @push('head')
    <style>
        #{{ $gridId }} { grid-template-columns: repeat({{ $colsMobile }}, minmax(0, 1fr)); }
        @media (min-width: 768px) { #{{ $gridId }} { grid-template-columns: repeat({{ $colsTablet }}, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { #{{ $gridId }} { grid-template-columns: repeat({{ $colsDesktop }}, minmax(0, 1fr)); } }
    </style>
    @endpush
@else
    <p class="text-center text-[var(--color-text)] py-8" class="font-body">No products to display.</p>
@endif
    </div>
</section>
@endif
