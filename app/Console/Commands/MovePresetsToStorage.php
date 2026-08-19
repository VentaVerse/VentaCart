<?php

namespace App\Console\Commands;

use App\Services\ThemeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Moves presets a merchant saved out of the repo and into storage, once.
 *
 * Until 2026-08-20 "save current look as preset" wrote into
 * resources/themes/{theme}/presets, which is a tracked path and part of the
 * deployment artifact. Anything already sitting there that Venta does not ship
 * belongs to the merchant and is one `git add -A` from being published, or one
 * deploy from being deleted.
 *
 * Which is which is decided by git, not by a hardcoded list: a preset the
 * repository tracks is one Venta ships, and anything else in that directory
 * was saved by a person. That stays correct as the shipped set changes.
 *
 * Safe to run twice. A preset already moved is not in the source directory any
 * more, and one whose name is already taken in storage is reported rather than
 * overwritten.
 */
class MovePresetsToStorage extends Command
{
    protected $signature = 'theme:move-presets-to-storage {--dry-run : Report what would move without moving it}';

    protected $description = 'Move merchant-saved theme presets out of the repo and into storage, once';

    public function handle(ThemeService $theme): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run: nothing will be moved.');
        }

        $themesDir = resource_path('themes');
        $moved = 0;
        $skipped = 0;

        foreach (File::directories($themesDir) as $themePath) {
            $slugTheme = basename($themePath);
            $source = $themePath . '/presets';

            if (! is_dir($source)) {
                continue;
            }

            foreach (glob($source . '/*.json') as $file) {
                $relative = ltrim(str_replace(base_path(), '', $file), '/');

                // Tracked by git means Venta ships it. Leave it alone.
                exec('cd ' . escapeshellarg(base_path()) . ' && git ls-files --error-unmatch ' . escapeshellarg($relative) . ' 2>/dev/null', $out, $code);
                if ($code === 0) {
                    continue;
                }

                $target = storage_path('app/themes/' . $slugTheme . '/presets/' . basename($file));

                if (file_exists($target)) {
                    $this->warn("  skipped {$slugTheme}/" . basename($file) . ' (already in storage)');
                    $skipped++;
                    continue;
                }

                $this->line(($dryRun ? '  would move ' : '  moved ') . $slugTheme . '/' . basename($file));

                if (! $dryRun) {
                    File::ensureDirectoryExists(dirname($target));
                    File::move($file, $target);
                }

                $moved++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would move ' : 'Moved ') . $moved . ' merchant preset' . ($moved === 1 ? '' : 's') . '.'
            . ($skipped > 0 ? " Skipped {$skipped} already in storage." : ''));

        if ($moved === 0 && $skipped === 0) {
            $this->line('Nothing to move: every preset in resources is one Venta ships.');
        }

        return self::SUCCESS;
    }
}
