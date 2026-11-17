{{-- A sample section from an extension narrow enough for any column: a heading only. --}}
<section class="s {{ $section['class'] }}" @include('xelga::snippets.section-attrs') data-sample-tile>
    <div class="s__inner">
        <h2 data-setting="heading">{{ $settings['heading'] }}</h2>
    </div>
</section>
