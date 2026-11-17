{{--
    A section's heading row: heading, optional text under it, and a link at
    the side. With $rail, arrows for a swipe row sit beside the link; with
    $railPause set (true when the row moves by itself) a pause button joins them.
--}}
@php
    $headId = $section['id'] . '-title';
    $sub = (string) ($settings['subheading'] ?? '');
    $linkLabel = (string) ($settings['link_label'] ?? '');
    $linkUrl = $link ?? null;
    $showLink = $linkLabel !== '' && $linkUrl !== null;
@endphp
<div class="s-head">
    <div class="s-head__text">
        <h2 class="s-head__title" id="{{ $headId }}" data-setting="heading" data-optional @if (($settings['heading'] ?? '') === '') hidden @endif>{{ $settings['heading'] ?? '' }}</h2>
        @if (array_key_exists('subheading', $settings))
            <p class="s-head__sub" data-setting="subheading" data-optional @if ($sub === '') hidden @endif>{{ $sub }}</p>
        @endif
    </div>
    @if (($rail ?? false) || $showLink || ($context->preview && array_key_exists('link_label', $settings)))
        <div class="s-head__aside">
            @if ($rail ?? false)
                <div class="rail__nav">
                    <button type="button" class="icon-btn icon-btn--outline" data-rail-prev aria-label="Scroll back"><svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg></button>
                    <button type="button" class="icon-btn icon-btn--outline" data-rail-next aria-label="Scroll forward"><svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg></button>
                    @isset($railPause)
                        <button type="button" class="icon-btn icon-btn--outline" data-rail-pause aria-label="Pause moving" @if (! $railPause) hidden @endif><svg class="icon" aria-hidden="true"><use href="#i-pause"></use></svg></button>
                    @endisset
                </div>
            @endif
            @if ($showLink || $context->preview)
                <a class="link-arrow" href="{{ $linkUrl ?? '#' }}" data-setting-href="link" @if (! $showLink) hidden @endif><span data-setting="link_label">{{ $linkLabel }}</span><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a>
            @endif
        </div>
    @endif
</div>
