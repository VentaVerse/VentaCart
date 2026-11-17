{{-- Promo banners: one to three photo banners, each a link with its own color scheme. --}}
@php
    $banners = [];
    foreach ($blocks as $block) {
        $url = \Themes\Xelga\Settings\LinkValue::resolve($block['settings']['link'] ?? null);
        $banners[] = ['block' => $block, 'url' => $url, 'picture' => \Themes\Xelga\Rendering\Picture::of($block['settings']['image'] ?? '')];
    }
@endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-height="{{ $settings['height'] }}" data-text-y="{{ $settings['text_y'] }}" data-count="{{ count($banners) }}"
         aria-label="{{ $section['label'] }}">
    @include('xelga::snippets.section-bg')
    <div class="s__inner">
        <ul class="promos">
            @foreach ($banners as $banner)
                @php $s = $banner['block']['settings']; @endphp
                <li class="promo {{ $s['scheme'] }}" data-block-id="{{ $banner['block']['id'] }}" data-block-type="banner"
                    data-focal="{{ $s['focal'] ?? 'center' }}" data-focal-y="{{ $s['focal_y'] ?? 'center' }}"
                    @if ($banner['block']['disabled']) hidden @endif>
                    <div class="promo__media">
                        <img src="{{ $banner['picture']['src'] ?? '' }}" alt="{{ $s['image_alt'] ?? '' }}" loading="lazy" decoding="async" {{ \Themes\Xelga\Rendering\Picture::sources($banner['picture'], '(min-width: 750px) 50vw, 100vw') }}
                             data-setting="image" data-setting-attr="image_alt:alt"
                             @if ($banner['picture'] && $banner['picture']['width']) width="{{ $banner['picture']['width'] }}" height="{{ $banner['picture']['height'] }}" @endif
                             @if (! $banner['picture']) hidden @endif>
                    </div>
                    <div class="promo__body">
                        <h3 class="promo__title">
                            @if ($banner['url'] !== null || $context->preview)
                                <a class="promo__link" href="{{ $banner['url'] ?? '#' }}" data-setting-href="link"><span data-setting="heading">{{ $s['heading'] }}</span></a>
                            @else
                                <span data-setting="heading">{{ $s['heading'] }}</span>
                            @endif
                        </h3>
                        <p class="promo__text" data-setting="text" data-optional @if ($s['text'] === '') hidden @endif>{{ $s['text'] }}</p>
                        @if ($banner['url'] !== null)
                            <span class="btn btn--sm" aria-hidden="true" @if ($s['button_label'] === '') hidden @endif><span data-setting="button_label">{{ $s['button_label'] }}</span><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
