{{-- Customer reviews: short reviews the merchant writes in, with first name, city, stars and an optional photo. --}}
@php
    $reviews = array_values(array_filter($blocks, fn ($b) => trim((string) $b['settings']['text']) !== '' || $context->preview));
@endphp
@if ($reviews !== [] || $context->preview)
    <section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
             data-cols="{{ $settings['cols'] }}" data-show-rating="{{ $settings['show_rating'] ? 'true' : 'false' }}"
             data-head-align="{{ $settings['head_align'] }}" aria-labelledby="{{ $section['id'] }}-title">
        @include('xelga::snippets.section-bg')
        <div class="s__inner">
            @include('xelga::snippets.section-head', ['link' => null, 'rail' => true])
            <ul class="quotes rail-track" aria-label="{{ $settings['heading'] !== '' ? $settings['heading'] : $section['label'] }}">
                @foreach ($reviews as $block)
                    @php
                        $s = $block['settings'];
                        $photo = \Themes\Xelga\Rendering\Picture::of($s['photo'] ?? '');
                    @endphp
                    <li class="quote" data-block-id="{{ $block['id'] }}" data-block-type="review" data-rating="{{ $s['rating'] }}" @if ($block['disabled']) hidden @endif>
                        <p class="quote__rating">@include('xelga::snippets.stars', ['rating' => (int) $s['rating']])</p>
                        <blockquote class="quote__text"><p data-setting="text">{{ $s['text'] }}</p></blockquote>
                        <div class="quote__by">
                            <img class="quote__photo" src="{{ $photo['src'] ?? '' }}" alt="" width="48" height="48" loading="lazy" data-setting="photo" @if (! $photo) hidden @endif>
                            <p class="quote__who"><span class="quote__name" data-setting="name">{{ $s['name'] }}</span><span class="quote__city" data-setting="city" data-optional @if ($s['city'] === '') hidden @endif>{{ $s['city'] }}</span></p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
