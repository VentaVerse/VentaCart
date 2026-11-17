<?php

namespace Themes\Xelga\Sections;

use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * The search page: the words searched, the page of products core's
 * SearchController found (it takes a query and a sort only, so there are
 * no filters here), and what to offer when nothing matches.
 */
final class SearchMain implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $query = trim((string) $context->get('query', ''));
        $list = Listing::page($context->get('products'), $context);
        if ($query !== '') {
            $list['count_text'] .= ' for "' . $query . '"';
        }

        $suggestions = [];
        foreach (preg_split('/[\r\n,]+/', (string) ($settings['suggestions'] ?? '')) ?: [] as $word) {
            $word = trim($word);
            if ($word !== '' && count($suggestions) < 12) {
                $suggestions[] = ['label' => $word, 'url' => route('search.results', ['q' => $word])];
            }
        }

        $links = [];
        foreach ($blocks as $block) {
            if ($block['type'] !== 'link') {
                continue;
            }
            $label = trim((string) ($block['settings']['label'] ?? ''));
            $url = LinkValue::resolve($block['settings']['link'] ?? null);
            if (($label === '' || $url === null) && ! $context->preview) {
                continue;
            }
            $links[] = ['block' => $block, 'label' => $label, 'url' => $url];
        }

        return [
            'query' => $query,
            'list' => $list,
            'sorts' => Listing::sorts(Listing::SEARCH_SORTS, (string) $context->get('sort', 'relevance')),
            'carried' => Listing::carried(['q']),
            'suggestions' => $suggestions,
            'links' => $links,
        ];
    }
}
