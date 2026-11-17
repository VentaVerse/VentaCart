<?php

namespace Themes\Xelga\Sections;

use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * The trail from the home page to the page shown, from what core's
 * controller handed the page: the category chain above a product or a
 * category, the brand list above a brand, the blog above a post.
 */
final class Breadcrumbs implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        return ['crumbs' => self::trail($context, (bool) ($settings['show_current'] ?? true))];
    }

    /**
     * The trail for the page being drawn; main sections that draw their own
     * breadcrumbs (the blog, a content page) use it too.
     *
     * @return list<array{label: string, url: ?string}>
     */
    public static function trail(RenderContext $context, bool $showCurrent = true): array
    {
        $crumbs = [['label' => 'Home', 'url' => route('home')]];
        $current = null;

        switch ($context->pageType) {
            case 'product':
                $crumbs = [...$crumbs, ...self::categories($context->get('breadcrumbs', []))];
                $current = $context->get('product')?->name;
                break;
            case 'category':
                $chain = self::categories($context->get('breadcrumbs', []));
                $last = array_pop($chain);
                $crumbs = [...$crumbs, ...$chain];
                $current = $last['label'] ?? $context->get('category')?->name;
                break;
            case 'brand':
                $crumbs[] = self::link('Brands', 'brand.index');
                $current = $context->get('brand')?->name;
                break;
            case 'brand-list':
                $current = 'Brands';
                break;
            case 'search':
                $query = trim((string) $context->get('query', ''));
                $current = $query === '' ? 'Search' : 'Search: ' . $query;
                break;
            case 'cart':
                $current = 'Cart';
                break;
            case 'blog':
                $current = 'Blog';
                break;
            case 'blog-post':
                $crumbs[] = self::link('Blog', 'blog.index');
                $current = $context->get('post')?->title;
                break;
            case 'contact':
                $current = 'Contact';
                break;
            case 'not-found':
                $current = 'Page not found';
                break;
            default:
                if (\App\Support\StorefrontPage::isExtensionType($context->pageType)) {
                    // An extension's page hands its trail; the last step without a link is the page itself.
                    $trail = \App\Support\StorefrontPage::breadcrumbs($context->get('breadcrumbs'));
                    $last = end($trail);
                    if ($last !== false && $last['url'] === null) {
                        array_pop($trail);
                        $current = $last['label'];
                    }
                    $crumbs = [...$crumbs, ...$trail];
                    break;
                }
                $page = $context->get('page');
                $current = is_object($page) ? ($page->title ?? null) : null;
        }

        $crumbs = array_values(array_filter($crumbs, fn ($c) => $c !== null && $c['url'] !== null));
        if ($showCurrent && is_string($current) && trim($current) !== '') {
            $crumbs[] = ['label' => trim($current), 'url' => null];
        }

        return $crumbs;
    }

    /** @return list<array{label: string, url: ?string}> */
    private static function categories(iterable $chain): array
    {
        $out = [];
        foreach ($chain as $category) {
            if (is_object($category) && isset($category->slug) && ($category->is_active ?? true)) {
                $out[] = ['label' => (string) $category->name, 'url' => route('category.show', $category->slug)];
            }
        }

        return $out;
    }

    private static function link(string $label, string $route): ?array
    {
        return Route::has($route) ? ['label' => $label, 'url' => route($route)] : null;
    }
}
