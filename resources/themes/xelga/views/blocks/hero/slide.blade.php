{{-- One slide of the main banner: photo, heading, text and up to two buttons. --}}
@php
    $s = $block['settings'];
    $picture = \Themes\Xelga\Rendering\Picture::of($s['image'] ?? '');
    $phone = \Themes\Xelga\Rendering\Picture::of($s['image_mobile'] ?? '');
    $link1 = \Themes\Xelga\Settings\LinkValue::resolve($s['button_link'] ?? null);
    $link2 = \Themes\Xelga\Settings\LinkValue::resolve($s['button2_link'] ?? null);
    $label1 = (string) ($s['button_label'] ?? '');
    $label2 = (string) ($s['button2_label'] ?? '');
    $show1 = $label1 !== '' && $link1 !== null;
    $show2 = $label2 !== '' && $link2 !== null;
    $preview = $context->preview;
    // $slot is this slide's place among the visible slides (1 is the one shown first); null when hidden.
    $first = $slot === 1;
@endphp
<div class="hero__slide{{ $first ? ' is-active' : '' }}" @if ($slot !== null) data-slot="{{ $slot }}" @endif data-block-id="{{ $block['id'] }}" data-block-type="slide" data-focal="{{ $s['focal'] ?? 'center' }}" data-focal-y="{{ $s['focal_y'] ?? 'center' }}"
     data-text-x="{{ $s['text_x'] ?? 'start' }}" data-text-y="{{ $s['text_y'] ?? 'center' }}" data-box="{{ $s['box'] ?? 'none' }}" data-overlay="{{ $s['overlay'] ?? 35 }}"
     @if ($block['disabled']) hidden @endif>
    <div class="hero__media">
        @if ($picture && $phone)
            <picture>
                <source media="(max-width: 749px)" srcset="{{ $phone['src'] }}" @if ($phone['width']) width="{{ $phone['width'] }}" height="{{ $phone['height'] }}" @endif>
                <img src="{{ $picture['src'] }}" alt="{{ $s['image_alt'] ?? '' }}"
                     @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif
                     data-setting="image" data-setting-attr="image_alt:alt"
                     @if ($first) fetchpriority="high" @else loading="lazy" @endif>
            </picture>
        @elseif ($picture)
            <img src="{{ $picture['src'] }}" alt="{{ $s['image_alt'] ?? '' }}"
                 @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif
                 data-setting="image" data-setting-attr="image_alt:alt"
                 @if ($first) fetchpriority="high" @else loading="lazy" @endif>
        @else
            <img alt="" data-setting="image" data-setting-attr="image_alt:alt" hidden>
        @endif
    </div>
    <div class="hero__content">
        <div class="hero__box">
            <{{ $headingLevel }} class="hero__title" data-setting="heading">{{ $s['heading'] ?? '' }}</{{ $headingLevel }}>
            <p class="hero__text" data-setting="text" data-optional @if (($s['text'] ?? '') === '') hidden @endif>{{ $s['text'] ?? '' }}</p>
            @if ($show1 || $show2 || $preview)
                <div class="hero__actions">
                    @if ($show1 || $preview)
                        <a class="btn btn--lg" href="{{ $link1 ?? '#' }}" data-setting-href="button_link" data-setting="button_label" data-optional @if (! $show1) hidden @endif>{{ $label1 }}</a>
                    @endif
                    @if ($show2 || $preview)
                        <a class="btn btn--secondary btn--lg" href="{{ $link2 ?? '#' }}" data-setting-href="button2_link" data-setting="button2_label" data-optional @if (! $show2) hidden @endif>{{ $label2 }}</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
