<?php

namespace App\Console\Commands;

use App\Support\ImageRenditions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Make the smaller copies of every photo on the public disk (see
 * ImageRenditions), for photos that arrived without them: an import, files
 * copied in by hand, or a store upgraded from a version that made none.
 *
 * Photos whose copies are current are skipped, so this is safe to stop and
 * run again; pages already make missing copies on first sight, this only
 * saves the first visitors from the originals.
 */
class GenerateImageRenditions extends Command
{
    protected $signature = 'images:renditions
                            {--path= : Only this folder under the public disk}
                            {--force : Make the copies again even when they are current}';

    protected $description = 'Make the smaller copies storefront pages offer phones in place of full-size photos';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD is not loaded, so nothing can be resized.');

            return self::FAILURE;
        }
        if (config('filesystems.disks.public.driver') !== 'local') {
            $this->error('The public disk is not local; copies are only made on a local disk.');

            return self::FAILURE;
        }

        $folder = trim((string) $this->option('path'), '/');
        if (str_contains($folder, '..')) {
            $this->error('The folder must be inside the public disk.');

            return self::FAILURE;
        }

        $files = collect(Storage::disk('public')->allFiles($folder))
            ->reject(fn (string $f) => str_starts_with($f, ImageRenditions::FOLDER . '/') || str_contains($f, '_thumb.'))
            ->filter(fn (string $f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ImageRenditions::EXTENSIONS, true))
            ->values();

        if ($files->isEmpty()) {
            $this->info('No photos found.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $photos = 0;
        $copies = 0;

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();
        foreach ($files as $file) {
            $made = ImageRenditions::make($file, $force);
            $copies += $made;
            $photos += $made > 0 ? 1 : 0;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf('%s copies made for %s photos; %s already current or too small to need any.', number_format($copies), number_format($photos), number_format($files->count() - $photos)));

        return self::SUCCESS;
    }
}
