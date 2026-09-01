@php
    $config = $placement->config ?? [];
    $columns = max(2, min(6, (int) ($config['columns'] ?? 4)));
@endphp
{{-- A department with nothing to show is not a department yet. --}}
@if (($categories ?? collect())->isNotEmpty())
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif

        <div class="wcats wcats--{{ $columns }} reveal-stagger">
            @foreach ($categories as $category)
                <a href="{{ route('category.show', $category->slug) }}" class="wcats__item">
                    <span class="wcats__shot">
                        <img src="{{ thumb_url($category->image) }}" alt="{{ $category->name }}"
                             loading="lazy" decoding="async">
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
