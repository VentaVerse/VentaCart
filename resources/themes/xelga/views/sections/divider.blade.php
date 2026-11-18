{{-- Divider: a line, a line with a label, dots or space. Everything but the label is decoration, so screen readers skip it. --}}
@php
    $variant = $settings['variant'];
    $labelled = $variant === 'label';
@endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-width="{{ $settings['width'] }}" data-weight="{{ $settings['weight'] }}" @unless ($labelled) aria-hidden="true" @endunless>
    <div class="dv">
        @if ($variant === 'line')
            <span class="dv__line"></span>
        @elseif ($labelled)
            <span class="dv__line" aria-hidden="true"></span>
            <p class="dv__label" data-setting="label">{{ $settings['label'] }}</p>
            <span class="dv__line" aria-hidden="true"></span>
        @elseif ($variant === 'dots')
            <span class="dv__dot"></span><span class="dv__dot"></span><span class="dv__dot"></span>
        @endif
    </div>
</section>
