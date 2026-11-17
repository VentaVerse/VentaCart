<?php

namespace Themes\Xelga\Sections;

use App\Models\Page;
use App\Support\NavTree;
use App\Support\SafeInput;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * An information page as core's page controller found it, and the menu
 * beside it: a menu the merchant made in VentaCart, or else every active
 * page the store has, with Contact us at the end.
 */
final class PageMain implements SectionData
{
    /** Most pages listed when no menu is chosen. */
    private const PAGES_MAX = 12;

    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $page = $context->get('page');
        $page = $page instanceof Page ? $page : null;

        $menus = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'pages' && ($settings['variant'] ?? 'sidebar') === 'sidebar') {
                $menus[$block['id']] = self::links((string) ($block['settings']['menu'] ?? ''), $page);
            }
        }

        return [
            'page' => $page === null ? null : [
                'title' => (string) $page->title,
                'body' => (string) $page->body,
            ],
            'crumbs' => Breadcrumbs::trail($context),
            'menus' => $menus,
        ];
    }

    /** @return list<array{label: string, url: string, current: bool}> */
    private static function links(string $menuKey, ?Page $current): array
    {
        $here = request()->url();
        $links = [];

        if ($menuKey !== '') {
            foreach (NavTree::for($menuKey) as $node) {
                $label = trim((string) ($node['label'] ?? ''));
                $url = SafeInput::link((string) ($node['url'] ?? ''));
                if ($label !== '' && $url !== '') {
                    $links[] = ['label' => $label, 'url' => $url, 'current' => rtrim($url, '/') === rtrim($here, '/')];
                }
            }

            return $links;
        }

        if (! Route::has('page.show')) {
            return [];
        }
        $pages = Page::query()->active()->orderBy('sort_order')->orderBy('title')->limit(self::PAGES_MAX)->get(['id', 'title', 'slug']);
        foreach ($pages as $page) {
            $links[] = ['label' => (string) $page->title, 'url' => route('page.show', $page->slug), 'current' => $current !== null && $page->id === $current->id];
        }
        if (Route::has('contact')) {
            $links[] = ['label' => 'Contact us', 'url' => route('contact'), 'current' => false];
        }

        return $links;
    }
}
