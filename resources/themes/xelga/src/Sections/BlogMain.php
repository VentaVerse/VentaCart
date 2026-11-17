<?php

namespace Themes\Xelga\Sections;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * The blog page: the posts the blog extension's controller found for this
 * request (its category, tag and search filters, its page size), with the
 * category links and the trail. The theme only draws them; it never asks
 * for posts of its own here.
 */
final class BlogMain implements SectionData
{
    /** How many page numbers the pager shows around the current one. */
    private const PAGER_WINDOW = 2;

    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $paginator = $context->get('posts');
        $enabled = BlogPosts::enabled() && $paginator instanceof LengthAwarePaginator;

        $category = self::queryString('category');
        $tag = self::queryString('tag');
        $search = $context->get('search');
        $search = is_string($search) && $search !== '' ? $search : null;

        $view = self::queryString('view');
        $variant = in_array($view, ['grid', 'list'], true) ? $view : (string) ($settings['variant'] ?? 'grid');

        return [
            'enabled' => $enabled,
            'crumbs' => \Themes\Xelga\Sections\Breadcrumbs::trail($context),
            'variant' => $variant,
            'posts' => $enabled ? self::posts($paginator) : [],
            'total' => $enabled ? $paginator->total() : 0,
            'categories' => $enabled ? self::categories($context->get('categories', []), $category, $search === null && $tag === null ? $paginator->total() : null) : [],
            'category' => $category,
            'tag' => self::tagName($context->get('tags', []), $tag),
            'search' => $search,
            'searchMin' => $enabled ? \Extensions\Blog\Models\BlogPost::SEARCH_MIN : 2,
            'searchMax' => $enabled ? \Extensions\Blog\Models\BlogPost::SEARCH_MAX : 100,
            'pager' => $enabled ? self::pager($paginator) : null,
            'resetUrl' => route('blog.index'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function posts(LengthAwarePaginator $paginator): array
    {
        $out = [];
        foreach ($paginator->items() as $post) {
            $out[] = [
                'title' => (string) $post->title,
                'url' => route('blog.show', $post->slug),
                'excerpt' => trim(strip_tags((string) $post->excerpt)),
                'picture' => $post->featured_image ? Picture::of($post->featured_image) : null,
                'date' => $post->published_at,
                'category' => $post->relationLoaded('category') ? $post->category?->name : null,
            ];
        }

        return $out;
    }

    /**
     * The category links: "All posts" and every category with a published
     * post. Counts come from the controller's own count of published posts.
     *
     * @return list<array{label: string, url: string, count: ?int, current: bool}>
     */
    private static function categories(iterable $categories, ?string $current, ?int $allCount): array
    {
        $links = [['label' => 'All posts', 'url' => route('blog.index'), 'count' => $allCount, 'current' => $current === null]];
        foreach ($categories as $category) {
            $count = (int) ($category->posts_count ?? 0);
            if ($count < 1 && $category->slug !== $current) {
                continue;
            }
            $links[] = [
                'label' => (string) $category->name,
                'url' => route('blog.index', ['category' => $category->slug]),
                'count' => $count,
                'current' => $category->slug === $current,
            ];
        }

        return count($links) > 1 ? $links : [];
    }

    private static function tagName(iterable $tags, ?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }
        foreach ($tags as $tag) {
            if ($tag->slug === $slug) {
                return (string) $tag->name;
            }
        }

        return $slug;
    }

    /** @return array{prev: ?string, next: ?string, pages: list<array{number: ?int, url: ?string, current: bool}>}|null */
    private static function pager(LengthAwarePaginator $paginator): ?array
    {
        $last = $paginator->lastPage();
        if ($last < 2) {
            return null;
        }
        $current = $paginator->currentPage();
        $pages = [];
        $gap = false;
        for ($n = 1; $n <= $last; $n++) {
            $near = abs($n - $current) <= self::PAGER_WINDOW;
            if ($n === 1 || $n === $last || $near) {
                $pages[] = ['number' => $n, 'url' => $paginator->url($n), 'current' => $n === $current];
                $gap = false;
            } elseif (! $gap) {
                $pages[] = ['number' => null, 'url' => null, 'current' => false];
                $gap = true;
            }
        }

        return [
            'prev' => $current > 1 ? $paginator->url($current - 1) : null,
            'next' => $current < $last ? $paginator->url($current + 1) : null,
            'pages' => $pages,
        ];
    }

    private static function queryString(string $key): ?string
    {
        $value = request()->query($key);

        return is_string($value) && $value !== '' ? Str::limit($value, 255, '') : null;
    }
}
