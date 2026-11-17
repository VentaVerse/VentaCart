{{-- Image with text: a photo beside text, a text panel over its edge, or the photo behind the text; optional numbered steps. --}}
@php
    $picture = \Themes\Xelga\Rendering\Picture::of($settings['image']);
    $url = \Themes\Xelga\Settings\LinkValue::resolve($settings['button_link']);
    $showButton = $settings['button_label'] !== '' && $url !== null;
    $text = \Themes\Xelga\Settings\RichTextPolicy::clean($settings['text']) ?? '';
@endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-image-pos="{{ $settings['image_pos'] }}" data-ratio="{{ $settings['ratio'] }}" data-align="{{ $settings['align'] }}"
         data-media-width="{{ $settings['media_width'] }}" data-focal="{{ $settings['focal'] }}" data-focal-y="{{ $settings['focal_y'] }}"
         aria-labelledby="{{ $section['id'] }}-title">
    @include('xelga::snippets.section-bg')
    <div class="s__inner iwt">
        <div class="iwt__media">
            <img src="{{ $picture['src'] ?? '' }}" alt="{{ $settings['image_alt'] }}" {{ \Themes\Xelga\Rendering\Fold::attrs($context, (bool) $picture) }} {{ \Themes\Xelga\Rendering\Picture::sources($picture, '(min-width: 750px) 50vw, 100vw') }}
                 data-setting="image" data-setting-attr="image_alt:alt"
                 @if ($picture && $picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif
                 @if (! $picture) hidden @endif>
        </div>
        <div class="iwt__body">
            <h2 class="iwt__title" id="{{ $section['id'] }}-title" data-setting="heading" data-optional @if ($settings['heading'] === '') hidden @endif>{{ $settings['heading'] }}</h2>
            <div class="iwt__text rte" data-setting="text" data-richtext>{!! $text !!}</div>
            @if ($blocks !== [])
                <ol class="steps">
                    @foreach ($blocks as $block)
                        <li class="step" data-block-id="{{ $block['id'] }}" data-block-type="step" @if ($block['disabled']) hidden @endif><span class="step__title" data-setting="title">{{ $block['settings']['title'] }}</span><span class="step__text" data-setting="text" data-optional @if ($block['settings']['text'] === '') hidden @endif>{{ $block['settings']['text'] }}</span></li>
                    @endforeach
                </ol>
            @endif
            @if ($showButton || $context->preview)
                <a class="btn btn--secondary" href="{{ $url ?? '#' }}" data-setting-href="button_link" @if (! $showButton) hidden @endif><span data-setting="button_label">{{ $settings['button_label'] }}</span><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a>
            @endif
        </div>
    </div>
</section>
