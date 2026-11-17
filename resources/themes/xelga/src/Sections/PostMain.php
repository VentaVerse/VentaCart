<?php

namespace Themes\Xelga\Sections;

use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * One blog post as the blog extension's controller loaded it: title,
 * summary, featured photo, category, tags and its text. The text is the
 * extension's rich text and is printed the way core prints it.
 */
final class PostMain implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $post = $context->get('post');
        if (! is_object($post) || ! BlogPosts::enabled()) {
            return ['post' => null, 'crumbs' => Breadcrumbs::trail($context, false)];
        }

        $crumbs = Breadcrumbs::trail($context, false);
        $category = $post->category ?? null;
        if ($category !== null) {
            $crumbs[] = ['label' => (string) $category->name, 'url' => route('blog.index', ['category' => $category->slug])];
        }

        $tags = [];
        foreach ($post->tags ?? [] as $tag) {
            $tags[] = ['label' => (string) $tag->name, 'url' => route('blog.index', ['tag' => $tag->slug])];
        }

        return [
            'post' => [
                'title' => (string) $post->title,
                'excerpt' => trim(strip_tags((string) $post->excerpt)),
                'date' => $post->published_at,
                'category' => $category !== null ? ['label' => (string) $category->name, 'url' => route('blog.index', ['category' => $category->slug])] : null,
                'picture' => $post->featured_image ? Picture::of($post->featured_image) : null,
                'content' => (string) $post->content,
                'tags' => $tags,
            ],
            'crumbs' => $crumbs,
            'blogUrl' => route('blog.index'),
        ];
    }
}
