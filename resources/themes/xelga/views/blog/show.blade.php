{{-- One blog post: its template's sections (a special layout when the post has one). --}}
<x-store-layout :title="$post->meta_title ?: $post->title" :meta-description="$post->meta_description">
    @include('xelga::snippets.template', ['pageType' => 'blog-post', 'subject' => $post, 'page' => compact('post', 'relatedPosts', 'settings', 'ogTitle', 'ogDescription')])
</x-store-layout>
