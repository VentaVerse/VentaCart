{{--
    A section's optional background photo, drawn behind its content. The
    texture is a pattern in the stylesheet, chosen by the section's
    data-texture attribute.
--}}
@php $bgPicture = \Themes\Xelga\Rendering\Picture::of($settings['bg_image'] ?? ''); @endphp
@if ($bgPicture || $context->preview)
    <div class="s__bg" aria-hidden="true" @if (! $bgPicture) hidden @endif>
        <img src="{{ $bgPicture['src'] ?? '' }}" alt="" {{ \Themes\Xelga\Rendering\Fold::attrs($context, (bool) $bgPicture) }} data-setting="bg_image" {{ \Themes\Xelga\Rendering\Picture::sources($bgPicture, '100vw') }}
             @if ($bgPicture && $bgPicture['width']) width="{{ $bgPicture['width'] }}" height="{{ $bgPicture['height'] }}" @endif
             @if (! $bgPicture) hidden @endif>
    </div>
@endif
