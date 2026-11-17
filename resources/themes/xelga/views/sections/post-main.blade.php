{{--
    One blog post: title, featured photo, text and tags, in the order the
    merchant set. The text is the blog extension's rich text, printed as
    core prints it.
--}}
@php $post = $data['post']; @endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-image-width="{{ $settings['image_width'] }}" aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner">
        @include('xelga::snippets.page-crumbs', ['crumbs' => $data['crumbs']])
        @if ($post === null)
            <h1 class="visually-hidden" id="{{ $section['id'] }}-title">Blog post</h1>
            @include('xelga::snippets.editor-note', ['note' => 'Open a blog post to see this section with its text. While the blog is switched off, posts are not shown to shoppers.'])
        @else
            @unless (collect($blocks)->contains(fn ($b) => $b['type'] === 'title' && ! $b['disabled']))
                <h1 class="visually-hidden" id="{{ $section['id'] }}-title">{{ $post['title'] }}</h1>
            @endunless
            <article class="article">
                @foreach ($blocks as $block)
                    @include('xelga::blocks.post-main.' . $block['type'])
                @endforeach
            </article>
        @endif
    </div>
</section>
