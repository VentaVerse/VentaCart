{{-- A sample section from an extension: a heading, a link, lines, and the thing the page is about. --}}
<section class="s {{ $section['class'] }}" @include('xelga::snippets.section-attrs') data-size="{{ $settings['size'] }}" data-sample-card>
    <div class="s__inner">
        <h2 data-setting="heading">{{ $settings['heading'] }}</h2>
        @if ($data['link'] !== null)<a href="{{ $data['link'] }}" data-sample-link>Visit</a>@endif
        @foreach ($blocks as $block)
            <p data-block-id="{{ $block['id'] }}" data-block-type="{{ $block['type'] }}">{{ $block['settings']['text'] }}</p>
        @endforeach
        @if ($data['thing'] !== null)<p data-thing>{{ $data['thing']->name }}</p>@endif
    </div>
</section>
