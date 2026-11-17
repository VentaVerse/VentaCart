{{--
    Product reviews: the average, the count and the approved reviews, the
    first few shown and the rest behind Show more reviews. Customers write
    a review from the link core emails them after their order.
--}}
@php
    $reviews = $data['reviews'];
    $perPage = max(1, (int) $settings['per_page']);
    $show = $data['enabled'] && ($data['count'] > 0 || $settings['show_empty']);
@endphp
@if ($show || ($context->preview && $data['enabled']))
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" id="reviews" @include('xelga::snippets.section-attrs') aria-labelledby="{{ $section['id'] }}-title" data-reviews>
        <div class="s__inner reviews">
            <div class="reviews__summary">
                <h2 class="s-head__title" id="{{ $section['id'] }}-title" data-setting="heading">{{ $settings['heading'] }}</h2>
                @if ($data['count'] > 0)
                    <p class="reviews__score"><span class="reviews__avg">{{ number_format($data['average'], 1) }}</span>@include('xelga::snippets.stars', ['rating' => $data['average']])</p>
                    <p class="muted">From {{ $data['count'] }} {{ $data['count'] === 1 ? 'review' : 'reviews' }}</p>
                @else
                    <p class="muted">No reviews yet.</p>
                @endif
                @if ($settings['show_how'])
                    <p class="reviews__how muted">Bought this? The store emails you a link to review it after your order arrives.</p>
                @endif
                @if ($data['count'] === 0)
                    @include('xelga::snippets.editor-note', ['note' => 'Shoppers see this section once the product has an approved review, unless "Show the section when there are no reviews yet" is on.'])
                @endif
            </div>
            @if ($data['count'] > 0)
                <ul class="reviews__list" data-reviews-list>
                    @foreach ($reviews as $i => $review)
                        <li class="review" @if ($i >= $perPage) hidden data-review-more @endif>
                            <article aria-labelledby="{{ $section['id'] }}-review-{{ $review->id }}">
                                <p class="review__stars">@include('xelga::snippets.stars', ['rating' => (int) $review->rating])</p>
                                <h3 class="review__title" id="{{ $section['id'] }}-review-{{ $review->id }}">{{ $review->title ?: 'Rated ' . (int) $review->rating . ' out of 5' }}</h3>
                                @if ($review->body)
                                    <p class="review__text">{{ $review->body }}</p>
                                @endif
                                @if ($review->relationLoaded('images') && $review->images->isNotEmpty())
                                    <ul class="review__photos">
                                        @foreach ($review->images as $photo)
                                            <li><a href="{{ storage_url($photo->path) }}" target="_blank" rel="noopener"><img src="{{ storage_url($photo->path) }}" alt="Photo from {{ $review->name }}'s review" loading="lazy" decoding="async"></a></li>
                                        @endforeach
                                    </ul>
                                @endif
                                <p class="review__by">
                                    <span class="review__name">{{ $review->name }}</span>
                                    @if ($review->is_verified_purchase)
                                        <span class="review__verified"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-check"></use></svg>Verified purchase</span>
                                    @endif
                                    @if ($review->published_at)
                                        <time class="muted" datetime="{{ $review->published_at->toDateString() }}">{{ $review->published_at->format('j F Y') }}</time>
                                    @endif
                                </p>
                            </article>
                        </li>
                    @endforeach
                </ul>
                @if ($data['count'] > $perPage)
                    <p class="reviews__more"><button type="button" class="btn btn--secondary" data-reviews-more data-step="{{ $perPage }}">Show more reviews</button></p>
                @endif
            @endif
        </div>
    </section>
@endif
