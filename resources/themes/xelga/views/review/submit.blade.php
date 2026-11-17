{{--
    Write a review, from the link core emails after an order: the rating
    (required), a title and text (optional) and up to two photos, posted to
    the reviews extension with the review's token. The stars are plain
    radio buttons, so the form works without the script.
--}}
@php
    app(\Themes\Xelga\Rendering\Storefront::class)->page('page');
    $productImage = $review->product?->images->first();
    $chosen = (int) old('rating', 0);
@endphp
<x-store-layout title="Write a review">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="review">
        <div class="s__inner">
            <div class="review-page">
                <header class="review-page__head">
                    @if ($productImage)
                        <img class="review-page__img" src="{{ storage_url($productImage->path) }}" alt="" width="96" height="96" loading="lazy">
                    @endif
                    <h1 class="auth__title">Write a review</h1>
                    <p class="muted">{{ $review->product?->name ?? 'A product no longer in the store' }}</p>
                </header>
                @include('xelga::partials.fixed.flash', ['showErrors' => true])
                <form class="auth__form" method="POST" action="{{ route('review.store', $review->token) }}" enctype="multipart/form-data">
                    @csrf
                    <fieldset class="rate" @error('rating') aria-invalid="true" @enderror>
                        <legend class="field__label">Your rating</legend>
                        <div class="rate__stars">
                            @for ($i = 5; $i >= 1; $i--)
                                <input class="visually-hidden rate__input" type="radio" id="rate-{{ $i }}" name="rating" value="{{ $i }}" @checked($chosen === $i) required>
                                <label class="rate__star" for="rate-{{ $i }}"><svg class="icon" aria-hidden="true"><use href="#i-star-fill"></use></svg><span class="visually-hidden">{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}</span></label>
                            @endfor
                        </div>
                    </fieldset>
                    <div class="field">
                        <label class="field__label" for="rv-title">Title <span class="muted">(optional)</span></label>
                        <input class="input" id="rv-title" type="text" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Sum it up in a few words">
                    </div>
                    <div class="field">
                        <label class="field__label" for="rv-body">Your review <span class="muted">(optional)</span></label>
                        <textarea class="input textarea" id="rv-body" name="body" rows="5" maxlength="5000" placeholder="What did you like, and what could be better?">{{ old('body') }}</textarea>
                    </div>
                    <div class="field">
                        <label class="field__label" for="rv-images">Photos <span class="muted">(optional, up to 2)</span></label>
                        <input class="input input--file" id="rv-images" type="file" name="images[]" multiple accept="image/*" aria-describedby="rv-images-help">
                        <p class="field__help" id="rv-images-help">Up to 2 MB per photo.</p>
                    </div>
                    <button type="submit" class="btn btn--block">Send my review</button>
                </form>
            </div>
        </div>
    </div>
</x-store-layout>
