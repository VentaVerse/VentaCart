{{-- Main banner: full-width photo, photo and text side by side, slideshow or collage. --}}
@php
    $id = $section['id'];
    $flag = static fn ($v) => $v ? 'true' : 'false';
    // Hidden slides never reach shoppers. The preview keeps them, marked hidden,
    // so showing one again is instant; they take no place in the order.
    $visible = array_values(array_filter($blocks, static fn (array $b) => ! $b['disabled']));
    $slots = [];
    foreach ($visible as $i => $b) {
        $slots[$b['id']] = $i + 1;
    }
    $preview = $context->preview;
@endphp
@if ($visible !== [] || $preview)
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}"
         data-section-id="{{ $id }}" data-section-type="hero"
         data-variant="{{ $settings['variant'] }}"
         data-height="{{ $settings['height'] }}"
         data-autoplay="{{ $flag($settings['autoplay']) }}"
         data-interval="{{ $settings['interval'] }}"
         data-slide-change="{{ $settings['slide_change'] }}"
         data-heading-size="{{ $settings['heading_size'] }}"
         aria-label="{{ $section['label'] }}"
         @if ($section['disabled']) hidden @endif>
    <div class="hero__slides">
        @foreach ($blocks as $block)
            @include('xelga::blocks.hero.slide', ['block' => $block, 'slot' => $slots[$block['id']] ?? null, 'headingLevel' => ($slots[$block['id']] ?? null) === 1 ? 'h2' : 'h3'])
        @endforeach
    </div>
    @if ($preview)
        <div class="hero__empty" data-hero-empty @if ($visible !== []) hidden @endif>
            @include('xelga::snippets.editor-note', ['note' => 'All slides are hidden, so shoppers do not see this banner. Show a slide to bring it back.'])
        </div>
    @endif
    <div class="hero__controls" @if (count($visible) < 2) hidden @endif>
        <button type="button" class="icon-btn" data-hero-prev aria-label="Previous slide"><svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg></button>
        <ol class="hero__dots"></ol>
        <button type="button" class="icon-btn" data-hero-next aria-label="Next slide"><svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg></button>
        <button type="button" class="icon-btn" data-hero-pause aria-label="Pause slideshow" @if (! $settings['autoplay']) hidden @endif><svg class="icon" aria-hidden="true"><use href="#i-pause"></use></svg></button>
    </div>
    <p class="visually-hidden hero__live" aria-live="polite"></p>
</section>
@endif
