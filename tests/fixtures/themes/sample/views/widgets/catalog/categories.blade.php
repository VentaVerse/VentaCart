@php
    $config = $placement->config ?? [];
    $columns = max(2, min(6, (int) ($config['columns'] ?? 4)));
@endphp
{{-- Hidden only when the store has no active category at all. --}}
@if (($categories ?? collect())->isNotEmpty())
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif

        <div class="wcats wcats--{{ $columns }} reveal-stagger">
            @foreach ($categories as $category)
                <a href="{{ route('category.show', $category->slug) }}" class="wcats__item">
                    {{-- No picture yet: the first letter on the theme's color, so every department still shows. --}}
                    <span class="wcats__shot{{ $category->image ? '' : ' wcats__shot--blank' }}">
                        @if ($category->image)
                            <img src="{{ thumb_url($category->image) }}" alt=""
                                 loading="lazy" decoding="async">
                        @else
                            <span class="wcats__mono" aria-hidden="true">{{ Str::upper(Str::substr($category->name, 0, 1)) }}</span>
                        @endif
                    </span>
                    <span class="wcats__body">
                        <span class="wcats__name">{{ $category->name }}</span>
                        @if (! is_null($category->products_count))
                            <span class="wcats__count">{{ trans_choice(':count item|:count items', $category->products_count, ['count' => $category->products_count]) }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
