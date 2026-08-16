<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off for stores created before the master-theme consolidation:
 * points the store at the venta master theme and maps the old folder
 * theme to its preset. Live colors, fonts, and options are untouched,
 * so the storefront renders identically before and after.
 * Fresh installs never need this; the seeder starts on venta.
 */
class AdoptMasterTheme extends Command
{
    protected $signature = 'venta:adopt-master-theme {--dry-run : Report what would change without writing}';

    protected $description = 'Adopt the venta master theme, converting the active legacy theme into a preset';

    public function handle(ThemeService $theme): int
    {
        $current = $theme->getActiveTheme();

        if ($current === 'venta') {
            $this->info('Already on the venta master theme. Nothing to do.');

            return self::SUCCESS;
        }

        if (!file_exists(resource_path("themes/venta/presets/{$current}.json"))) {
            $this->error("No venta preset matches the active theme [{$current}]. Aborting.");

            return self::FAILURE;
        }

        $optionRows = Setting::where('group', 'theme_settings')
            ->where('key', 'like', '%\_%')
            ->get()
            ->filter(fn ($row) => preg_match('/^(stage-ready|clean-light|crimson-dusk)_/', $row->key));

        $this->line("Active theme: {$current} -> venta (preset: {$current})");
        $this->line('Theme option keys to remap: ' . $optionRows->count());

        if ($this->option('dry-run')) {
            foreach ($optionRows as $row) {
                $this->line("  would remap theme_settings.{$row->key}");
            }

            return self::SUCCESS;
        }

        DB::transaction(function () use ($current, $optionRows) {
            Setting::updateOrCreate(
                ['group' => 'identity', 'key' => 'active_theme'],
                ['value' => 'venta']
            );
            Setting::updateOrCreate(
                ['group' => 'identity', 'key' => 'active_preset'],
                ['value' => $current]
            );

            foreach ($optionRows as $row) {
                [$slug, $key] = explode('_', $row->key, 2);
                if ($slug === $current) {
                    // The active theme's options stay live under the master key
                    Setting::updateOrCreate(
                        ['group' => 'theme_settings', 'key' => "venta_{$key}"],
                        ['value' => $row->value]
                    );
                } else {
                    // Other themes' options become that preset's snapshot
                    Setting::updateOrCreate(
                        ['group' => "theme_settings__{$slug}", 'key' => "venta_{$key}"],
                        ['value' => $row->value]
                    );
                }
                $row->delete();
            }

            // Dead key from the old switch flow; nothing ever read it
            Setting::where('group', 'fonts')->where('key', 'google_fonts_url')->delete();

            // The master vocabulary is wider than the legacy themes' (status
            // tokens, tracking, tile backgrounds). Fill every key the live
            // groups are missing from the preset so the look stays whole;
            // existing values are never touched.
            $themeJson = json_decode((string) file_get_contents(resource_path('themes/venta/theme.json')), true) ?: [];
            $preset = json_decode((string) file_get_contents(resource_path("themes/venta/presets/{$current}.json")), true) ?: [];
            foreach (['colors', 'fonts', 'vars'] as $group) {
                $full = array_merge($themeJson[$group] ?? [], $preset[$group] ?? []);
                foreach ($full as $key => $value) {
                    Setting::firstOrCreate(
                        ['group' => $group, 'key' => $key],
                        ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
                    );
                }
            }
        });

        $theme->clearCache();

        $this->info("Done. The storefront now runs the venta master theme with the '{$current}' preset live.");

        return self::SUCCESS;
    }
}
