<?php

namespace App\Support;

/**
 * How a block group finds the blocks it shows.
 *
 * A group holds the blocks placed directly below it in the same column,
 * as many as its "count" setting says. Those blocks stay ordinary
 * placements: each is added, moved, styled and configured in the editor
 * like any other, and the layout tables learn nothing new. Removing the
 * group simply lets them render one after another again.
 *
 * A group never holds another group: one met while gathering ends the
 * gathering and is drawn as a group of its own. A switched-off block (only
 * present while an editor draws a draft) is passed over without being
 * counted, so the storefront and the editor hold the same blocks.
 */
final class BlockGroup
{
    public const WIDGET = 'content.block_group';

    /** The most blocks one group holds. */
    public const MAX = 12;

    /**
     * Walk one column's placements and draw them, letting each group take
     * the blocks it holds.
     *
     * @param  iterable<\App\Models\LayoutPlacement>  $placements
     * @param  callable(\App\Models\LayoutPlacement, array): string  $render  draws one placement, with extra view data
     */
    public static function renderColumn(iterable $placements, callable $render): string
    {
        $list = array_values(is_array($placements) ? $placements : iterator_to_array($placements, false));
        $html = '';

        for ($i = 0, $n = count($list); $i < $n; $i++) {
            $placement = $list[$i];

            if ($placement->widget !== self::WIDGET || ! Widgets::has(self::WIDGET)) {
                $html .= $render($placement, []);
                continue;
            }

            $wanted = self::count($placement->config ?? []);
            $panels = [];
            $passed = '';

            while ($wanted > 0 && $i + 1 < $n && $list[$i + 1]->widget !== self::WIDGET) {
                $child = $list[++$i];

                if (! $child->is_enabled) {
                    $passed .= $render($child, []);
                    continue;
                }

                $wanted--;
                $label = trim((string) $child->title) ?: (Widgets::get($child->widget)['title'] ?? $child->widget);
                // The tab or section names the block, so the block does not
                // repeat its own heading inside it.
                $inner = clone $child;
                $inner->title = null;
                $body = $render($inner, []);

                // A block with nothing to show (an empty listing, a module
                // switched off) makes no empty tab.
                if (trim($body) === '') {
                    continue;
                }
                $panels[] = ['title' => $label, 'html' => $body];
            }

            $html .= $render($placement, ['panels' => $panels]) . $passed;
        }

        return $html;
    }

    /** How many blocks a group with these settings holds. */
    public static function count(array $config): int
    {
        $count = (int) ($config['count'] ?? 2);

        return max(1, min(self::MAX, $count ?: 2));
    }
}
