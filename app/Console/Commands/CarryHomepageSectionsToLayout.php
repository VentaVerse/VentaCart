<?php

namespace App\Console\Commands;

use App\Models\HomepageSection;
use App\Models\LayoutPlacement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carries the home page's sections into the layout system.
 *
 * homepage_sections was a layout table that only worked on one page and one
 * implicit region. Everything in it becomes a placement on page_type 'home',
 * region 'content_top', keeping order, title, config and enabled state.
 *
 * Non-destructive on purpose: the old table is left exactly as it is, so a
 * store can be compared before and after and rolled back by deleting the
 * placements. Safe to run twice; a section already carried is skipped.
 */
class CarryHomepageSectionsToLayout extends Command
{
    protected $signature = 'layout:carry-homepage-sections {--dry-run : Report what would move without writing}';

    protected $description = 'Carry homepage sections into layout placements';

    /** Old section type => widget key. */
    private const MAP = [
        'banner' => 'banners.group',
        'products' => 'catalog.products',
        'brands' => 'catalog.brands',
        'category_tabs' => 'catalog.category_tabs',
        'recently_viewed' => 'catalog.recently_viewed',
        'text_block' => 'content.text',
        'blog_posts' => 'blog.posts',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if (! \Illuminate\Support\Facades\Schema::hasTable('homepage_sections')) {
            $this->info('No homepage_sections table. Nothing to carry.');

            return self::SUCCESS;
        }

        $sections = HomepageSection::orderBy('sort_order')->get();
        $moved = 0;
        $skipped = 0;
        $unknown = [];

        foreach ($sections as $section) {
            $widget = self::MAP[$section->type] ?? null;

            if ($widget === null) {
                $unknown[] = $section->type;
                continue;
            }

            $exists = LayoutPlacement::where('page_type', 'home')
                ->where('region', 'content_top')
                ->where('widget', $widget)
                ->where('sort_order', $section->sort_order)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $this->line(($dry ? '  would carry ' : '  carried ') . $section->type . ' -> ' . $widget);

            if (! $dry) {
                LayoutPlacement::create([
                    'page_type' => 'home',
                    'region' => 'content_top',
                    'widget' => $widget,
                    'title' => $section->title,
                    'config' => $section->config,
                    'sort_order' => $section->sort_order,
                    'is_enabled' => (bool) $section->is_enabled,
                ]);
            }

            $moved++;
        }

        $this->newLine();
        $this->info(($dry ? 'Would carry ' : 'Carried ') . $moved . ' section' . ($moved === 1 ? '' : 's') . '.'
            . ($skipped > 0 ? " Skipped {$skipped} already carried." : ''));

        if ($unknown !== []) {
            // Named, never dropped in silence: an unmapped type is a section
            // the merchant is still looking at on their live home page.
            $this->warn('No widget for these section types, left in place: ' . implode(', ', array_unique($unknown)));
        }

        return self::SUCCESS;
    }
}
