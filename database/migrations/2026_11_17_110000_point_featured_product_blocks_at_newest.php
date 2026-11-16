<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product grids and product tabs offered "Featured", but products have no
 * featured flag, so the choice always listed the newest products. The option
 * is gone (hand-picked products replace it); a block saved with it now says
 * "Newest", which is what it showed all along.
 *
 * Covers every place core keeps a block's settings: placed blocks, saved row
 * templates and unsaved editor drafts. Rows without such a block are left
 * untouched, so a fresh install and a second run change nothing.
 */
return new class extends Migration
{
    private const WIDGETS = ['catalog.products', 'catalog.product_tabs'];

    public function up(): void
    {
        if (Schema::hasTable('layout_placements')) {
            DB::table('layout_placements')
                ->whereIn('widget', self::WIDGETS)
                ->orderBy('id')
                ->each(function ($row) {
                    $config = json_decode((string) $row->config, true);
                    if (is_array($config) && $this->fixConfig($config)) {
                        DB::table('layout_placements')->where('id', $row->id)->update(['config' => json_encode($config)]);
                    }
                });
        }

        foreach (['layout_templates' => 'rows', 'layout_drafts' => 'payload'] as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
                $data = json_decode((string) $row->{$column}, true);
                if (is_array($data) && $this->walk($data)) {
                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($data)]);
                }
            });
        }
    }

    /**
     * There is nothing to put back: "Featured" and "Newest" listed the same
     * products, and which blocks said which is not recorded.
     */
    public function down(): void
    {
    }

    /** Fix every product block found anywhere under $node. True when something changed. */
    private function walk(array &$node): bool
    {
        $changed = false;

        if (in_array($node['widget'] ?? null, self::WIDGETS, true) && is_array($node['config'] ?? null)) {
            $changed = $this->fixConfig($node['config']);
        }

        foreach ($node as &$child) {
            if (is_array($child)) {
                $changed = $this->walk($child) || $changed;
            }
        }
        unset($child);

        return $changed;
    }

    private function fixConfig(array &$config): bool
    {
        $changed = false;

        if (($config['filter'] ?? null) === 'featured') {
            $config['filter'] = 'new';
            $changed = true;
        }

        if (is_array($config['tabs'] ?? null)) {
            foreach ($config['tabs'] as &$tab) {
                if (is_array($tab) && ($tab['filter'] ?? null) === 'featured') {
                    $tab['filter'] = 'new';
                    $changed = true;
                }
            }
            unset($tab);
        }

        return $changed;
    }
};
