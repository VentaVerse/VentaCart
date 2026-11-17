<?php

namespace Themes\Xelga\Sections;

use App\Services\ExtensionManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * Blog posts from the blog extension: published posts only, newest first,
 * from every category or one. While the blog is switched off there is
 * nothing to show and the section draws nothing for shoppers.
 */
final class BlogPosts implements SectionData
{
    public static function enabled(): bool
    {
        if (! class_exists(\Extensions\Blog\Models\BlogPost::class) || ! Route::has('blog.show')) {
            return false;
        }
        try {
            return app(ExtensionManager::class)->state('blog') === 'enabled' && Schema::hasTable('blog_posts');
        } catch (\Throwable) {
            return false;
        }
    }

    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        if (! self::enabled()) {
            return ['enabled' => false, 'posts' => [], 'link' => null];
        }

        $query = \Extensions\Blog\Models\BlogPost::query()->published()->with('category')
            ->latest('published_at')->orderByDesc('id')
            ->limit(max(1, min(12, (int) ($settings['count'] ?? 3))));
        $category = (string) ($settings['blog_category'] ?? '');
        if (($settings['source'] ?? 'latest') === 'category' && ctype_digit($category) && $category !== '') {
            $query->where('blog_category_id', (int) $category);
        }

        $posts = [];
        foreach ($query->get(['id', 'title', 'slug', 'excerpt', 'featured_image', 'published_at', 'blog_category_id']) as $post) {
            $posts[] = [
                'title' => (string) $post->title,
                'url' => route('blog.show', $post->slug),
                'excerpt' => trim(strip_tags((string) $post->excerpt)),
                'picture' => $post->featured_image ? Picture::of($post->featured_image) : null,
                'date' => $post->published_at,
                'category' => $post->category?->name,
            ];
        }

        return [
            'enabled' => true,
            'posts' => $posts,
            'link' => LinkValue::resolve($settings['link'] ?? null),
        ];
    }
}
