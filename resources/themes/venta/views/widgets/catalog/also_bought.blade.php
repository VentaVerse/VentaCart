@if (($products ?? collect())->isNotEmpty())
@php
    $config = $placement->config ?? [];
    $columns = max(2, min(6, (int) ($config['columns'] ?? 4)));
@endphp
<section class="py-10 md:py-14">
    <div class="container-store">
        <h2 class="section-title reveal">{{ $placement->title ?: 'Customers also bought' }}</h2>
        <div class="wgrid wgrid--{{ $columns }} reveal-stagger">
            @foreach ($products as $product)
                @include('partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endif
