@props([
    'name' => 'image',
    'value' => null,
    // The folder the library opens in when the field holds no image and no
    // folder is remembered for this kind of field.
    'root' => 'products',
    // The kind of image the field holds. The library remembers the last
    // folder per kind, so every brand logo field reopens where the last
    // brand logo came from.
    'kind' => null,
    'mode' => 'single',
    // How many images a multi field may hold in all. Null is no limit.
    'limit' => null,
    'label' => null,
    'trigger' => 'Choose image',
    'help' => null,
    'required' => false,
    // Bare renders no field of its own: the caller draws the preview and
    // carries the value, and opens the library with a media:open event.
    'bare' => false,
    // File extensions the field accepts, such as ['png']. Other images
    // stay visible in the library but cannot be chosen. Null accepts any.
    'accept' => null,
])
@php
    $mediaFolder = \App\Support\MediaLibrary::root($root);
    $mediaKind = $kind ?: basename($mediaFolder);

    // Multi carries a JSON array in one hidden input, single carries a path.
    $mediaValue = $mode === 'multi'
        ? array_values(array_filter(is_string($value) ? (json_decode($value, true) ?: []) : (array) ($value ?? []), 'is_string'))
        : (string) ($value ?? '');

    // Addresses for the preview of what the field already holds. Images
    // chosen later bring their own from the library.
    $mediaUrls = collect((array) $mediaValue)
        ->filter(fn ($path) => $path !== '')
        ->mapWithKeys(fn ($path) => [$path => \Illuminate\Support\Facades\Storage::disk('public')->url($path)])
        ->all();
@endphp
<x-media-library />
<div class="v-mpick {{ $bare ? 'v-mpick--bare' : '' }}"
     x-data="vMediaPicker({
        folder: @js($mediaFolder),
        kind: @js($mediaKind),
        mode: @js($mode),
        limit: @js($limit === null ? null : (int) $limit),
        value: @js($mediaValue),
        urls: @js((object) $mediaUrls),
        accept: @js($accept === null ? null : array_values((array) $accept)),
     })"
     data-kind="{{ $mediaKind }}"
     {{-- Opened from elsewhere on the page by dispatching media:open at this
          element, with the control that asked as detail.from. That is how
          the variant table aims one field at one row. --}}
     data-media-picker="{{ $name }}"
     @media:open="show($event.detail?.from)"
     {{ $attributes }}>

    @unless ($bare)
        @if ($label)
            <span class="v-field__label">
                {{ $label }}
                @if ($required)<span class="v-req">Required</span>@endif
                @if ($help)<x-admin.help :text="$help" />@endif
            </span>
        @endif

        <input type="hidden" name="{{ $name }}" value="{{ $mode === 'multi' ? json_encode($mediaValue) : $mediaValue }}" :value="payload">

        {{-- What is chosen, and the way to change or drop it. --}}
        <ul class="v-mpick__chosen" x-show="chosen.length" x-cloak>
            <template x-for="path in chosen" :key="path">
                <li class="v-mpick__one">
                    <img class="v-mpick__thumb" :src="urls[path] || ''" alt="" aria-hidden="true">
                    <span class="v-mpick__name mono" x-text="nameOf(path)" :title="path"></span>
                    <button type="button" class="v-btn v-btn--ghost v-btn--sm"
                            @click="mode === 'multi' ? drop(path) : clear()"
                            :aria-label="'Remove ' + nameOf(path)">Remove</button>
                </li>
            </template>
        </ul>

        <span class="v-actrow">
            <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="show($el)"
                    x-text="mode === 'single' && chosen.length ? 'Change' : @js($trigger)">{{ $mediaValue !== '' && $mediaValue !== [] && $mode === 'single' ? 'Change' : $trigger }}</button>
        </span>
    @endunless
</div>
