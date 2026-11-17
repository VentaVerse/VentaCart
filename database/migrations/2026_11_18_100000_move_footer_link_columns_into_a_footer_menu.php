<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Footer columns of links become the store's "footer" menu, so the links
 * survive the end of the Header & footer screen: themes draw their footer
 * from menus, edited at Content > Menus. The links of every enabled links
 * column go into the menu in order, one item each.
 *
 * Runs only while the old table exists and no footer menu has been made,
 * so it is safe on a fresh install (nothing to copy) and safe to run
 * again. The columns themselves are left for the migration that removes
 * the old storefront data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('footer_columns') || ! Schema::hasTable('nav_menus')
            || DB::table('nav_menus')->where('key', 'footer')->exists()) {
            return;
        }

        $links = [];
        $columns = DB::table('footer_columns')->where('type', 'links')->where('is_enabled', true)
            ->orderBy('sort_order')->orderBy('id')->get(['config']);
        foreach ($columns as $column) {
            $config = json_decode((string) $column->config, true);
            // Saved by the screen as "links"; the first stores were seeded with "items".
            foreach ((array) ($config['links'] ?? $config['items'] ?? []) as $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $url = \App\Support\SafeInput::link((string) ($link['url'] ?? ''));
                if ($label !== '' && $url !== '') {
                    $links[] = ['label' => mb_substr($label, 0, 255), 'url' => mb_substr($url, 0, 255)];
                }
            }
        }

        if ($links === []) {
            return;
        }

        DB::transaction(function () use ($links) {
            $now = now();
            $menuId = DB::table('nav_menus')->insertGetId(['key' => 'footer', 'name' => 'Footer menu', 'created_at' => $now, 'updated_at' => $now]);
            foreach ($links as $i => $link) {
                DB::table('nav_menu_items')->insert([
                    'menu_id' => $menuId,
                    'label' => $link['label'],
                    'type' => 'custom',
                    'url' => $link['url'],
                    'sort_order' => $i + 1,
                    'is_enabled' => true,
                ]);
            }
        });

        \Illuminate\Support\Facades\Cache::forget('theme_nav_items');
        \App\Support\NavTree::forget();
    }

    /** The menu is the merchant's from here on; it is not taken away again. */
    public function down(): void
    {
    }
};
