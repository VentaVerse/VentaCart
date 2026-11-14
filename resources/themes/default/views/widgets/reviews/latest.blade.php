@php
    $config = $placement->config ?? [];
    $columns = max(2, min(4, (int) ($config['columns'] ?? 3)));
    $reviews = ($reviews ?? collect())->filter(fn ($r) => $r->product);
@endphp
@if ($reviews->isNotEmpty())
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <div class="wreviews wreviews--{{ $columns }} reveal-stagger">
            @foreach ($reviews as $review)
                <article class="wreviews__card">
                    <span class="wreviews__stars" aria-label="{{ $review->rating }} out of 5">
                        @for ($i = 1; $i <= 5; $i++)<i class="{{ $i <= $review->rating ? 'is-on' : '' }}"></i>@endfor
                    </span>
                    @if ($review->title)<b class="wreviews__title">{{ $review->title }}</b>@endif
                    @if ($review->body)<p class="wreviews__body">{{ \Illuminate\Support\Str::limit(strip_tags($review->body), 160) }}</p>@endif
                    <span class="wreviews__meta">{{ $review->name ?: 'A customer' }} on <a href="{{ route('product.show', $review->product->slug) }}">{{ $review->product->name }}</a></span>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
