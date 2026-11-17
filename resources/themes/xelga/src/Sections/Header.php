<?php

namespace Themes\Xelga\Sections;

use App\Support\NavTree;
use App\Support\SafeInput;
use App\Support\StoreIdentity;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * The header's data: the store's name, the chosen menu as core composes it
 * (shaped into the header's panels), and the menu photos matched to their
 * menu items.
 */
final class Header implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $menuKey = (string) ($settings['menu'] ?? '');
        $tree = $menuKey !== '' ? NavTree::for($menuKey) : [];

        $promos = [];
        foreach ($blocks as $block) {
            $item = mb_strtolower(trim((string) ($block['settings']['menu_item'] ?? '')));
            if ($item === '' || isset($promos[$item])) {
                continue;
            }
            $promos[$item] = [
                'block' => $block,
                'picture' => Picture::of($block['settings']['image'] ?? ''),
                'url' => LinkValue::resolve($block['settings']['link'] ?? null),
            ];
        }

        $items = [];
        foreach ($tree as $node) {
            $label = trim((string) ($node['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $columns = $this->columns($node);
            $items[] = [
                'id' => (int) ($node['id'] ?? 0),
                'label' => $label,
                'url' => $this->link($node['url'] ?? null),
                'new_tab' => (bool) ($node['new_tab'] ?? false),
                'columns' => $columns,
                'all' => ($columns !== [] && ! empty($node['dropdown']['all_label'])) ? (string) $node['dropdown']['all_label'] : null,
                'promo' => $promos[mb_strtolower($label)] ?? null,
            ];
        }

        $name = trim((string) ($settings['logo_text'] ?? ''));

        return [
            'storeName' => $name !== '' ? $name : StoreIdentity::name(),
            'logo' => Picture::of($settings['logo_image'] ?? ''),
            'items' => $items,
            'promos' => $promos,
        ];
    }

    /**
     * A menu item's panel as columns: a child with children of its own is a
     * column with a heading; children without are gathered into one list.
     *
     * @return list<array{title: ?string, url: ?string, links: list<array{label: string, url: ?string}>}>
     */
    private function columns(array $node): array
    {
        $children = [];
        foreach ((array) ($node['children'] ?? []) as $child) {
            $children[] = ['label' => (string) ($child['label'] ?? ''), 'url' => $child['url'] ?? null, 'children' => array_map(fn ($c) => ['label' => (string) ($c['label'] ?? ''), 'url' => $c['url'] ?? null], (array) ($child['children'] ?? []))];
        }
        $dropdown = (array) ($node['dropdown'] ?? []);
        $kind = $dropdown['kind'] ?? null;
        if (in_array($kind, ['list', 'mega'], true)) {
            foreach ((array) ($dropdown['columns'] ?? []) as $column) {
                $children[] = ['label' => (string) ($column['name'] ?? ''), 'url' => $column['url'] ?? null, 'children' => array_map(fn ($c) => ['label' => (string) ($c['name'] ?? ''), 'url' => $c['url'] ?? null], (array) ($column['children'] ?? []))];
            }
        } elseif ($kind === 'brands') {
            foreach ((array) ($dropdown['brands'] ?? []) as $brand) {
                $children[] = ['label' => (string) ($brand['name'] ?? ''), 'url' => $brand['url'] ?? null, 'children' => []];
            }
        }

        $columns = [];
        $loose = [];
        foreach ($children as $child) {
            if ($child['label'] === '') {
                continue;
            }
            if ($child['children'] !== []) {
                $columns[] = [
                    'title' => $child['label'],
                    'url' => $this->link($child['url']),
                    'links' => array_values(array_filter(array_map(fn ($c) => ['label' => $c['label'], 'url' => $this->link($c['url'])], $child['children']), fn ($c) => $c['label'] !== '')),
                ];
            } else {
                $loose[] = ['label' => $child['label'], 'url' => $this->link($child['url'])];
            }
        }
        if ($loose !== []) {
            array_unshift($columns, ['title' => null, 'url' => null, 'links' => $loose]);
        }

        return $columns;
    }

    private function link(mixed $url): ?string
    {
        $safe = SafeInput::link(is_string($url) ? $url : '');

        return $safe === '' ? null : $safe;
    }
}
