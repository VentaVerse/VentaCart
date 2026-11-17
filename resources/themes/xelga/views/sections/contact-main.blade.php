{{--
    The contact page: a heading, the store's details and the message form.
    The form posts to core's contact route with core's field names, so
    core's checks, captcha and rate limit decide what is sent.
--}}
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs') aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner">
        @include('xelga::snippets.page-crumbs', ['crumbs' => $data['crumbs']])
        @unless (collect($blocks)->contains(fn ($b) => $b['type'] === 'heading' && ! $b['disabled']))
            <h1 class="visually-hidden" id="{{ $section['id'] }}-title">Contact us</h1>
        @endunless
        <div class="contact">
            @foreach ($blocks as $block)
                @include('xelga::blocks.contact-main.' . $block['type'])
            @endforeach
        </div>
    </div>
</section>
