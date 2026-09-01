<?php

namespace Database\Seeders;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\Product;
use App\Support\RowStyle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The home page a fresh install opens on.
 *
 * Until now an install arranged nothing, so the storefront was a header, a
 * void and a footer even once it had a catalogue: the demo goods existed and
 * the front page did not mention them. A merchant evaluating the platform saw
 * an empty shop and had to go looking in the admin to find out it was not.
 *
 * Seven bands, in the order a shop front usually reads: something to look at,
 * why buy here, what departments exist, what is new, what is reduced, who
 * makes it, and a way to be told about the next thing.
 *
 * Every one of them is a row and a widget the merchant can drag, retitle or
 * throw away in the layout editor. Nothing here is special or protected; it
 * is a starting arrangement, not a fixed one.
 */
class DemoLayoutSeeder extends Seeder
{
    public function run(): void
    {
        $theme = (string) (DB::table('settings')
            ->where('group', 'identity')->where('key', 'active_theme')->value('value') ?: 'venta');

        // Only ever into an empty page. A merchant who has arranged their own
        // home page, or emptied it on purpose, is left alone.
        if (LayoutRow::where('theme', $theme)->where('page_type', 'home')->exists()
            || LayoutPlacement::where('theme', $theme)->where('page_type', 'home')->exists()) {
            $this->command?->info('Home page already arranged, layout skipped.');

            return;
        }

        // Nothing to arrange around. The bands would all render empty.
        if (! Product::withoutGlobalScopes()->exists()) {
            $this->command?->info('No catalogue yet, layout skipped.');

            return;
        }

        DB::transaction(fn () => $this->build($theme));

        $this->command?->info(sprintf(
            'Home page: %d rows.',
            LayoutRow::where('theme', $theme)->where('page_type', 'home')->count()
        ));
    }

    private function build(string $theme): void
    {
        $sort = 0;

        // 1. The hero. A row carrying the photograph as its background with a
        //    call to action laid over it, which needs no banner extension and
        //    no slider: a fresh install has neither.
        $this->row($theme, $sort++, [
            'bg' => 'image',
            'bg_image' => 'media/hero/desk.jpg',
            'bg_size' => 'cover',
            'bg_position' => 'center',
            'width' => 'full',
            'pad' => 'xl',
            'title_align' => 'left',
            // No white heading here: content.cta paints its own opaque card
            // over the photograph, so a white title would be white on white.
            // The panel is the hero's content, the photograph is behind it.
        ], [[
            'widget' => 'content.cta',
            'title' => 'Good tools, plainly sold',
            'config' => [
                'text' => "Phones, laptops, displays and the small things that connect them.\nTwo year guarantee on everything, and someone to talk to when it matters.",
                'button' => 'Shop everything',
                'url' => '/search?q=',
                'alignment' => 'left',
            ],
        ]]);

        // 2. Why buy here. Directly under the hero, where a shopper decides
        //    whether to keep reading.
        $this->row($theme, $sort++, ['pad' => 'md'], [[
            'widget' => 'content.icon_list',
            'title' => null,
            'config' => [
                'columns' => '4',
                'items' => [
                    ['icon' => 'truck', 'title' => 'Free delivery over $50', 'text' => 'Dispatched the same weekday afternoon'],
                    ['icon' => 'shield', 'title' => 'Two year guarantee', 'text' => 'On everything, no registration needed'],
                    ['icon' => 'refresh', 'title' => '30 day returns', 'text' => 'Opened is fine, we test everything anyway'],
                    ['icon' => 'chat', 'title' => 'Someone to talk to', 'text' => 'Seven days a week, by phone or email'],
                ],
            ],
        ]]);

        // 3. The departments, as pictures rather than a menu.
        $this->row($theme, $sort++, [], [[
            'widget' => 'catalog.categories',
            'title' => 'Shop by department',
            'config' => ['show' => 'top', 'limit' => 4, 'columns' => '4'],
        ]]);

        // 4. What is new. Four across, the newest first.
        $this->row($theme, $sort++, [], [[
            'widget' => 'catalog.products',
            'title' => 'New arrivals',
            'config' => ['filter' => 'new', 'limit' => '4', 'layout' => 'grid', 'columns' => '4', 'card' => 'default'],
        ]]);

        // 5. What is reduced. A different set from the band above, because the
        //    demo catalogue puts four things on sale and none of them are the
        //    four newest.
        $this->row($theme, $sort++, ['bg' => 'color', 'bg_color' => '#F8FAFC', 'pad' => 'lg'], [[
            'widget' => 'catalog.products',
            'title' => 'On sale now',
            'config' => ['filter' => 'specials', 'limit' => '4', 'layout' => 'grid', 'columns' => '4', 'card' => 'default'],
        ]]);

        // 6. Who makes it.
        $this->row($theme, $sort++, [], [[
            'widget' => 'catalog.brands',
            'title' => 'Brands we carry',
            'config' => ['autoplay' => false, 'speed' => 3000],
        ]]);

        // 7. The last thing on the page asks for the one thing a new shop
        //    actually wants, which is permission to write to you again.
        $this->row($theme, $sort++, ['bg' => 'color', 'bg_color' => '#0F172A', 'pad' => 'lg'], [[
            'widget' => 'content.cta',
            'title' => 'Told first, not often',
            'config' => [
                'text' => "A note when something lands or drops in price.\nAbout twice a month, and never anything else.",
                'button' => 'Create an account',
                'url' => '/register',
                'alignment' => 'center',
            ],
        ]]);
    }

    /**
     * One full width row holding one widget.
     *
     * @param  array<string, mixed>  $style
     * @param  list<array{widget: string, title: ?string, config: array}>  $placements
     */
    private function row(string $theme, int $sort, array $style, array $placements): void
    {
        $row = LayoutRow::create([
            'theme' => $theme,
            'page_type' => 'home',
            'region' => 'content_top',
            'sort_order' => $sort,
            'columns' => [8],
            // Through RowStyle so the row carries every key the editor expects
            // to find, rather than the handful stated here.
            'style' => RowStyle::normalize($style),
            'responsive' => ['tablet' => [null], 'phone_order' => [1]],
            'is_enabled' => true,
        ]);

        foreach (array_values($placements) as $i => $p) {
            LayoutPlacement::create([
                'theme' => $theme,
                'page_type' => 'home',
                'region' => 'content_top',
                'row_id' => $row->id,
                'column_index' => 0,
                'widget' => $p['widget'],
                'title' => $p['title'] ?? null,
                'config' => $p['config'] ?? [],
                'sort_order' => $i,
                'is_enabled' => true,
            ]);
        }
    }
}
