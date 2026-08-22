<?php

namespace App\Console\Commands;

use App\Support\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Merge folders that are the same folder wearing different punctuation.
 *
 * The migrated catalogue grew six of these: Wampler and wampler, Friedman and
 * friedman, "TC Electronic" and tc_electronic, "Flamma Innovation" and
 * Flamma-Innovation, "Abasi Guitars" and abasi-guitars, Qable and qable.
 * Nothing in the admin distinguishes them, so a merchant uploading a photo
 * picks one at random and the library forks a little further every week.
 *
 * There is no move operation in the picker, deliberately, so this is the tool
 * that fixes what the picker cannot. Every stored path is repointed with the
 * files, because a merge that leaves the database behind breaks every product
 * in the folder at once.
 */
class TidyMediaFolders extends Command
{
    protected $signature = 'media:tidy
                            {--root=products : Which media root to look through}
                            {--apply : Actually merge. Without this it only reports}';

    protected $description = 'Find and merge folders that differ only by case or punctuation';

    public function handle(): int
    {
        $root = MediaLibrary::root($this->option('root'));
        $apply = (bool) $this->option('apply');

        $groups = collect(Storage::disk('public')->directories($root))
            ->groupBy(fn ($dir) => $this->fingerprint(basename($dir)))
            ->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info("Nothing to merge under {$root}.");

            return self::SUCCESS;
        }

        $this->line('');
        $moved = 0;

        foreach ($groups as $group) {
            // The one that already holds the most images wins, because it is
            // the one most links already point at and the one a merchant has
            // been using. Ties break toward the tidier name.
            $ranked = $group->sortByDesc(fn ($dir) => [
                count(Storage::disk('public')->allFiles($dir)),
                -substr_count($dir, ' '),
            ])->values();

            $keep = $ranked->first();
            $rest = $ranked->slice(1);

            $this->line('  <fg=green>keep</> ' . $keep . ' (' . count(Storage::disk('public')->allFiles($keep)) . ' files)');

            foreach ($rest as $from) {
                $files = collect(Storage::disk('public')->allFiles($from));
                $this->line('  <fg=yellow>into</> ' . $from . ' (' . $files->count() . ' files)');

                if (! $apply) {
                    continue;
                }

                foreach ($files as $file) {
                    $target = $keep . '/' . basename($file);

                    // A name that already exists in the winner is a different
                    // file with the same name; it keeps its own name rather
                    // than overwriting something a product may point at.
                    if (Storage::disk('public')->exists($target)) {
                        $info = pathinfo($target);
                        $target = $info['dirname'] . '/' . $info['filename'] . '-' . substr(md5($file), 0, 6) . '.' . ($info['extension'] ?? 'jpg');
                    }

                    Storage::disk('public')->move($file, $target);
                    $moved += MediaLibrary::repoint($file, $target);
                }

                Storage::disk('public')->deleteDirectory($from);
            }
        }

        $this->line('');

        if (! $apply) {
            $this->warn('Nothing was changed. Run it again with --apply to merge.');

            return self::SUCCESS;
        }

        $this->info("Merged. {$moved} stored path" . ($moved === 1 ? '' : 's') . ' repointed.');

        return self::SUCCESS;
    }

    /** Wampler, wampler, "TC Electronic" and tc_electronic all collapse here. */
    private function fingerprint(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($name));
    }
}
