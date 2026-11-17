{{-- The sample page's main section: the thing the controller handed the page. --}}
<section class="s {{ $section['class'] }}" @include('xelga::snippets.section-attrs') data-sample-main>
    <div class="s__inner">
        @if ($settings['show_name'])<h1>{{ $context->get('thing')?->name }}</h1>@endif
    </div>
</section>
