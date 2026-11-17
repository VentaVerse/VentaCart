{{-- Text section: a heading, the merchant's own text and an optional button. Rich text was cleaned when it was saved and is cleaned again here. --}}
@php
    $url = \Themes\Xelga\Settings\LinkValue::resolve($settings['button_link']);
    $showButton = $settings['button_label'] !== '' && $url !== null;
@endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-width="{{ $settings['width'] }}" data-heading-size="{{ $settings['heading_size'] }}" aria-labelledby="{{ $section['id'] }}-title">
    @include('xelga::snippets.section-bg')
    <div class="s__inner">
        <div class="richtext">
            <h2 class="richtext__title" id="{{ $section['id'] }}-title" data-setting="heading" data-optional @if ($settings['heading'] === '') hidden @endif>{{ $settings['heading'] }}</h2>
            <div class="richtext__body rte" data-setting="text" data-richtext>{!! \Themes\Xelga\Settings\RichTextPolicy::clean($settings['text']) ?? '' !!}</div>
            @if ($showButton || $context->preview)
                <div class="richtext__actions" @if (! $showButton) hidden @endif>
                    <a class="btn" href="{{ $url ?? '#' }}" data-setting-href="button_link"><span data-setting="button_label">{{ $settings['button_label'] }}</span><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a>
                </div>
            @endif
        </div>
    </div>
</section>
