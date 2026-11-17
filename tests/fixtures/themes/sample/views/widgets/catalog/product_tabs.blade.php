@php
    $config = $placement->config ?? [];
    $columns = max(2, min(6, (int) ($config['columns'] ?? 4)));
    $tabs = collect($tabs ?? [])->filter(fn ($t) => $t['products']->isNotEmpty())->values();
@endphp
@if ($tabs->isNotEmpty())
<section class="py-10 md:py-14">
    <div class="container-store" x-data="{ tab: 0 }">
        <div class="wtabs reveal">
            @if ($placement->title)
                <h2 class="section-title wtabs__title">{{ $placement->title }}</h2>
            @endif
            <div class="wtabs__bar" role="tablist">
                @foreach ($tabs as $i => $tab)
                    <button type="button" class="wtabs__tab" :class="{ 'is-on': tab === {{ $i }} }" role="tab"
                            :aria-selected="tab === {{ $i }} ? 'true' : 'false'" @click="tab = {{ $i }}">{{ $tab['title'] ?: 'Tab ' . ($i + 1) }}</button>
                @endforeach
            </div>
        </div>
        @foreach ($tabs as $i => $tab)
            <div class="wgrid wgrid--{{ $columns }}" role="tabpanel" x-show="tab === {{ $i }}" @if ($i > 0) x-cloak @endif>
                @foreach ($tab['products'] as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        @endforeach
    </div>
</section>
@endif
