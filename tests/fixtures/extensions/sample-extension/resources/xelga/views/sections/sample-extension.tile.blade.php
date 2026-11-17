{{-- A sample section from an extension narrow enough for any column: a heading and a mark sized for the width it is drawn at. --}}
<section class="s {{ $section['class'] }}" @include('xelga::snippets.section-attrs') data-span="{{ $context->span }}" @if ($context->has('hero')) data-beside-banner @endif data-sample-tile>
    <div class="s__inner">
        <h2 data-setting="heading">{{ $settings['heading'] }}</h2>
        <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="" width="1" height="1" sizes="{{ \Themes\Xelga\Rendering\Picture::spanSizes($context->span) }}">
    </div>
</section>
