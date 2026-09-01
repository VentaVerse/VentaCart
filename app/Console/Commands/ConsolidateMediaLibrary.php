<?php

namespace App\Console\Commands;

use App\Support\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Move a store built on the old five-folder layout into the one library.
 *
 * Before: products/, brands/, categories/, blog/, banners/ and the retired
 * catalog/ sat at the top of public storage beside things that are not media
 * at all, and a picker was locked to one of them.
 *
 * After: they are ordinary folders inside media/, which is the only tree the
 * admin may touch. Everything outside it (identity, bank-qr, theme, the
 * theme's own demo art) is left exactly where it is and becomes unreachable
 * from a picker by construction rather than by an allow list.
 *
 * A store's image paths are not all in obvious columns: some sit inside JSON,
 * and order_products keeps a snapshot of the picture for every line ever
 * sold. So the columns are discovered rather than listed, and a value is only
 * rewritten when the whole string is a media path or when it is a string
 * inside JSON that is one. A description mentioning /products/ in a URL is
 * left alone.
 *
 *   php artisan media:consolidate            # says what it would do
 *   php artisan media:consolidate --force    # does it
 */
class ConsolidateMediaLibrary extends Command
{
    protected $signature = 'media:consolidate {--force : Actually move and rewrite}';

    protected $description = 'Move the old top level image folders into media/ and repoint every path at them';

    /** The folders that become subfolders of the library. */
    private const MOVE = ['products', 'brands', 'categories', 'blog', 'banners', 'catalog'];

    public function handle(): int
    {
        $write = (bool) $this->option('force');
        $disk = Storage::disk('public');

        $this->line('');
        $this->info($write ? 'Consolidating the media library.' : 'Dry run. Nothing will be written.');
        $this->line('');

        // --- the files ------------------------------------------------------
        $this->line('  Folders');
        $moved = 0;

        foreach (self::MOVE as $folder) {
            $to = MediaLibrary::ROOT . '/' . $folder;

            if (! $disk->exists($folder)) {
                continue;
            }

            if ($disk->exists($to)) {
                $this->warn("    {$folder} -> {$to}  ALREADY THERE, left alone");

                continue;
            }

            $files = count($disk->allFiles($folder));
            $this->line("    {$folder} -> {$to}  ({$files} files)");

            if ($write) {
                $disk->makeDirectory(MediaLibrary::ROOT);
                // A rename on the same disk, so nothing is copied and no
                // second gigabyte is needed to hold the move.
                rename(storage_path('app/public/' . $folder), storage_path('app/public/' . $to));
            }

            $moved++;
        }

        if ($moved === 0) {
            $this->line('    nothing to move');
        }

        // --- the paths ------------------------------------------------------
        $this->line('');
        $this->line('  Stored paths');

        $changed = 0;
        $touched = [];

        foreach ($this->candidateColumns() as [$table, $column, $key]) {
            $n = 0;

            DB::table($table)->select([$key, $column])->orderBy($key)->chunk(500, function ($rows) use ($table, $column, $key, $write, &$n) {
                foreach ($rows as $row) {
                    $before = $row->$column;

                    if ($before === null || $before === '') {
                        continue;
                    }

                    $after = $this->rewrite($before);

                    if ($after === $before) {
                        continue;
                    }

                    $n++;

                    if ($write) {
                        DB::table($table)->where($key, $row->$key)->update([$column => $after]);
                    }
                }
            });

            if ($n > 0) {
                $touched[] = sprintf('    %-22s %-20s %6d', $table, $column, $n);
                $changed += $n;
            }
        }

        foreach ($touched as $line) {
            $this->line($line);
        }

        if ($changed === 0) {
            $this->line('    nothing to repoint');
        }

        $this->line('');
        $this->info(sprintf(
            '%s %d folders moved, %d paths repointed.',
            $write ? 'Done:' : 'Would be:',
            $moved,
            $changed
        ));

        if (! $write) {
            $this->line('  Re-run with --force to apply.');
        }

        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Every text column on every table, with its primary key. Discovered
     * rather than listed, because an extension's table holds paths too and a
     * list only covers the ones somebody remembered.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function candidateColumns(): array
    {
        $driver = DB::connection()->getDriverName();
        $out = [];

        $tables = $driver === 'sqlite'
            ? array_column(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"), 'name')
            : array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));

        foreach ($tables as $table) {
            $columns = \Schema::getColumnListing($table);

            if (! in_array('id', $columns, true)) {
                continue;
            }

            foreach ($columns as $column) {
                // The raw SQL type, not Laravel's abstract name: this returns
                // "varchar" and "longtext", so matching on "string" finds
                // nothing at all and the migration silently does no work.
                $type = strtolower((string) \Schema::getColumnType($table, $column));

                if (str_contains($type, 'char') || str_contains($type, 'text') || $type === 'json') {
                    $out[] = [$table, $column, 'id'];
                }
            }
        }

        return $out;
    }

    /**
     * A value with its media paths moved into the library.
     *
     * Whole-string paths are prefixed. JSON is walked and its strings treated
     * the same way. Anything else is returned untouched, so a sentence that
     * happens to contain "products/" is not quietly edited.
     */
    private function rewrite(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $walked = $this->walk($decoded);

                return $walked === $decoded ? $value : json_encode($walked);
            }
        }

        return $this->path($value);
    }

    private function walk(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->walk($v);
            } elseif (is_string($v)) {
                $data[$k] = $this->path($v);
            }
        }

        return $data;
    }

    /** One string: a media path gets the prefix, anything else is returned as is. */
    private function path(string $value): string
    {
        if (str_starts_with($value, MediaLibrary::ROOT . '/')) {
            return $value;
        }

        $head = explode('/', $value)[0];

        if (! in_array($head, self::MOVE, true) || ! str_contains($value, '/')) {
            return $value;
        }

        // It has to look like a file, not a route: "products/x.jpg" moves,
        // "products/summer-sale" as a link does not.
        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));

        return in_array($extension, MediaLibrary::EXTENSIONS, true)
            ? MediaLibrary::ROOT . '/' . $value
            : $value;
    }
}
