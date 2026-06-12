<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Migrate image storage off the OpenCart-era catalog/ umbrella into
 * entity-specific folders (products/{brand}/, brands/, categories/, blog/,
 * banners/). Moves files and rewrites DB rows in lockstep, heals the
 * admin-save silent-failure damage, is idempotent, and cleans up the empty
 * catalog/ tree. See docs/superpowers/specs/2026-05-01-… image storage spec.
 *
 * Dry-run by default. Requires --execute (and --i-have-a-backup) to write.
 */
class MigrateImagePaths extends Command
{
    protected $signature = 'venta:migrate-image-paths
        {--execute : Actually move files and rewrite rows (default is a dry run)}
        {--i-have-a-backup : Required with --execute; confirms the DB is backed up}';

    protected $description = 'Move images from the legacy catalog/ umbrella into entity-specific folders and rewrite DB paths in lockstep.';

    /** Column-driven coverage: every DB column that may hold a catalog/ path. */
    private array $columns = [
        ['table' => 'product_images', 'column' => 'path',           'root' => 'products'],
        ['table' => 'products',       'column' => 'image',          'root' => 'products'],
        ['table' => 'brands',         'column' => 'image',          'root' => 'brands'],
        ['table' => 'categories',     'column' => 'image',          'root' => 'categories'],
        ['table' => 'blog_posts',     'column' => 'featured_image', 'root' => 'blog'],
        ['table' => 'banners',        'column' => 'bg_image',       'root' => 'banners'],
    ];

    private \Illuminate\Contracts\Filesystem\Filesystem $disk;

    public function handle(): int
    {
        $this->disk = Storage::disk('public');
        $execute = (bool) $this->option('execute');

        if ($execute && ! $this->option('i-have-a-backup')) {
            $this->error('Refusing to run with --execute without --i-have-a-backup.');
            $this->line('Back up the database (and ideally storage/app/public) first, then re-run with both flags.');
            return self::FAILURE;
        }

        $this->info('venta:migrate-image-paths — ' . ($execute ? 'EXECUTE' : 'DRY RUN (no changes)'));
        $this->newLine();

        // 1. Plan every row.
        [$plan, $stats] = $this->buildPlan();

        // 2. Pre-flight: writability + collisions + cross-entity shared sources.
        if (! $this->preflight($plan, $execute)) {
            return self::FAILURE;
        }

        // 3. Apply (or preview).
        $this->apply($plan, $execute, $stats);

        // 4. Cleanup empty catalog/ subdirectories (execute only).
        if ($execute) {
            $removed = $this->cleanupEmptyCatalog();
            $stats['catalog_dirs_removed'] = $removed;
        }

        // 5. Report.
        $this->summary($stats, $plan, $execute);

        return self::SUCCESS;
    }

    /**
     * Classify every row into an action. Returns [planItems, stats].
     * Plan item: ['table','column','id','root','source','dest','op','updateDb','brand'].
     *   op: 'move' (file move + DB rewrite) | 'dbfix' (file already at dest, DB rewrite only)
     *       | 'repair' (file at legacy, DB already correct → move only)
     */
    private function buildPlan(): array
    {
        $plan = [];
        $stats = [
            'scanned' => 0, 'already' => 0, 'skipped_missing' => 0,
            'skipped_other' => 0, 'planned' => 0, 'per_entity' => [], 'per_brand' => [],
        ];

        foreach ($this->columns as $col) {
            [$table, $column, $root] = [$col['table'], $col['column'], $col['root']];

            if (! $this->tableHasColumn($table, $column)) {
                continue;
            }

            $rows = DB::table($table)
                ->select('id', $column . ' as v')
                ->whereNotNull($column)
                ->where($column, '<>', '')
                ->get();

            foreach ($rows as $row) {
                $stats['scanned']++;
                $cur = ltrim(str_replace('\\', '/', (string) $row->v), '/');
                $item = $this->classify($table, $column, $root, (int) $row->id, $cur);

                if ($item === null) {
                    $stats['skipped_other']++;
                    continue;
                }
                if ($item['op'] === 'already') {
                    $stats['already']++;
                    continue;
                }
                if ($item['op'] === 'missing') {
                    $stats['skipped_missing']++;
                    continue;
                }

                $plan[] = $item;
                $stats['planned']++;
                $stats['per_entity'][$table] = ($stats['per_entity'][$table] ?? 0) + 1;
                if ($item['brand']) {
                    $stats['per_brand'][$item['brand']] = ($stats['per_brand'][$item['brand']] ?? 0) + 1;
                }
            }
        }

        return [$plan, $stats];
    }

    /** Decide what to do with one row's path. */
    private function classify(string $table, string $column, string $root, int $id, string $cur): ?array
    {
        $base = [
            'table' => $table, 'column' => $column, 'id' => $id, 'root' => $root,
            'brand' => null,
        ];

        if (str_starts_with($cur, 'catalog/')) {
            $rest = substr($cur, strlen('catalog/'));
            $dest = $root . '/' . $rest;
            $base['brand'] = $root === 'products' ? strtok($rest, '/') : null;

            if ($this->disk->exists($cur)) {
                return $base + ['source' => $cur, 'dest' => $dest, 'op' => 'move', 'updateDb' => true];
            }
            if ($this->disk->exists($dest)) {
                // File already moved on a prior run; DB still says catalog → fix DB only.
                return $base + ['source' => null, 'dest' => $dest, 'op' => 'dbfix', 'updateDb' => true];
            }
            return ['op' => 'missing'] + $base;
        }

        if (str_starts_with($cur, $root . '/')) {
            if ($this->disk->exists($cur)) {
                return ['op' => 'already'] + $base; // canonical and present
            }
            // Silent-failure repair: DB says new layout but file sits at legacy catalog/.
            $rest = substr($cur, strlen($root . '/'));
            $legacy = 'catalog/' . $rest;
            $base['brand'] = $root === 'products' ? strtok($rest, '/') : null;
            if ($this->disk->exists($legacy)) {
                return $base + ['source' => $legacy, 'dest' => $cur, 'op' => 'repair', 'updateDb' => false];
            }
            return ['op' => 'missing'] + $base;
        }

        // Other prefixes (stores/, external URLs, …) are not ours to touch.
        return null;
    }

    /** Writable destination + collision + cross-entity shared-source checks. */
    private function preflight(array $plan, bool $execute): bool
    {
        $root = storage_path('app/public');
        if ($execute && ! is_writable($root)) {
            $this->error("Destination not writable: {$root}");
            return false;
        }

        // Two distinct sources mapping to the same destination → ambiguous, abort.
        $byDest = [];
        foreach ($plan as $item) {
            if (! empty($item['source'])) {
                $byDest[$item['dest']][$item['source']] = true;
            }
        }
        $collisions = array_filter($byDest, fn ($srcs) => count($srcs) > 1);
        if (! empty($collisions)) {
            $this->error('Destination collisions detected (distinct sources → same destination). Aborting.');
            foreach ($collisions as $dest => $srcs) {
                $this->line("  {$dest}  ←  " . implode(' , ', array_keys($srcs)));
            }
            return false;
        }

        // One source feeding multiple destinations (e.g. a file used by a product
        // AND a blog post): copy for the extras, move once. Mark them here.
        $bySource = [];
        foreach ($plan as $i => $item) {
            if (! empty($item['source'])) {
                $bySource[$item['source']][] = $i;
            }
        }
        $this->sharedSources = [];
        foreach ($bySource as $source => $indexes) {
            if (count($indexes) > 1) {
                $this->sharedSources[$source] = $indexes;
            }
        }

        return true;
    }

    private array $sharedSources = [];

    /** Execute (or preview) the planned operations. */
    private function apply(array $plan, bool $execute, array &$stats): void
    {
        $stats['moved'] = 0;
        $stats['copied'] = 0;
        $stats['dbfixed'] = 0;
        $stats['repaired'] = 0;
        $stats['failed'] = 0;

        // Sources shared across multiple destinations: copy to each, delete source once.
        $handledSharedSource = [];

        foreach ($plan as $idx => $item) {
            $label = "{$item['table']}.{$item['column']}#{$item['id']}";

            try {
                if ($item['op'] === 'dbfix') {
                    if ($execute) {
                        $this->updateRow($item);
                    }
                    $stats['dbfixed']++;
                    $this->line("  [dbfix]  {$label}  →  {$item['dest']}");
                    continue;
                }

                $source = $item['source'];
                $dest = $item['dest'];
                $shared = isset($this->sharedSources[$source]);

                if ($shared) {
                    // Copy to this destination; the source is deleted after the last one.
                    if ($execute) {
                        $this->ensureDir($dest);
                        if (! $this->disk->exists($dest)) {
                            $this->disk->copy($source, $dest);
                        }
                        $this->copyThumb($source, $dest);
                    }
                    $stats['copied']++;
                    $this->line("  [copy]   {$label}  {$source}  →  {$dest}");

                    // After the final consumer of this shared source, drop the original.
                    $indexes = $this->sharedSources[$source];
                    if ($idx === end($indexes) && $execute) {
                        $this->disk->delete($source);
                        $this->deleteThumb($source);
                    }
                } else {
                    if ($execute) {
                        $this->ensureDir($dest);
                        if (! $this->disk->exists($dest)) {
                            $this->disk->move($source, $dest);
                        } else {
                            $this->disk->delete($source);
                        }
                        $this->moveThumb($source, $dest);
                    }
                    $item['op'] === 'repair' ? $stats['repaired']++ : $stats['moved']++;
                    $tag = $item['op'] === 'repair' ? 'repair' : 'move';
                    $this->line("  [{$tag}]   {$label}  {$source}  →  {$dest}");
                }

                if ($item['updateDb'] && $execute) {
                    $this->updateRow($item);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->warn("  [FAIL]   {$label}: " . $e->getMessage());
            }
        }
    }

    private function updateRow(array $item): void
    {
        DB::table($item['table'])->where('id', $item['id'])->update([$item['column'] => $item['dest']]);
    }

    private function ensureDir(string $path): void
    {
        $dir = dirname($path);
        if ($dir && $dir !== '.' && ! $this->disk->exists($dir)) {
            $this->disk->makeDirectory($dir);
        }
    }

    private function thumbOf(string $path): string
    {
        $info = pathinfo($path);
        $ext = $info['extension'] ?? 'jpg';
        return ($info['dirname'] === '.' ? '' : $info['dirname'] . '/') . $info['filename'] . '_thumb.' . $ext;
    }

    private function moveThumb(string $source, string $dest): void
    {
        $st = $this->thumbOf($source);
        if ($this->disk->exists($st)) {
            $dt = $this->thumbOf($dest);
            $this->ensureDir($dt);
            $this->disk->exists($dt) ? $this->disk->delete($st) : $this->disk->move($st, $dt);
        }
    }

    private function copyThumb(string $source, string $dest): void
    {
        $st = $this->thumbOf($source);
        if ($this->disk->exists($st)) {
            $dt = $this->thumbOf($dest);
            $this->ensureDir($dt);
            if (! $this->disk->exists($dt)) {
                $this->disk->copy($st, $dt);
            }
        }
    }

    private function deleteThumb(string $source): void
    {
        $st = $this->thumbOf($source);
        if ($this->disk->exists($st)) {
            $this->disk->delete($st);
        }
    }

    /** Recursively remove empty catalog/ subdirectories. Skips any dir with files. */
    private function cleanupEmptyCatalog(): int
    {
        if (! $this->disk->exists('catalog')) {
            return 0;
        }
        return $this->removeEmptyDirs('catalog');
    }

    private function removeEmptyDirs(string $dir): int
    {
        $removed = 0;
        foreach ($this->disk->directories($dir) as $sub) {
            $removed += $this->removeEmptyDirs($sub);
        }
        if (empty($this->disk->files($dir)) && empty($this->disk->directories($dir))) {
            $this->disk->deleteDirectory($dir);
            $removed++;
        }
        return $removed;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable($table)
            && \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
    }

    private function summary(array $stats, array $plan, bool $execute): void
    {
        $this->newLine();
        $this->info($execute ? '── Done ──' : '── Dry run summary (re-run with --execute --i-have-a-backup) ──');

        $this->table(['Metric', 'Count'], [
            ['Rows scanned', $stats['scanned']],
            ['Already correct', $stats['already']],
            ['Planned operations', $stats['planned']],
            ['  • moved', $stats['moved'] ?? 0],
            ['  • repaired (silent-failure heal)', $stats['repaired'] ?? 0],
            ['  • db-only fixes', $stats['dbfixed'] ?? 0],
            ['  • copied (shared source)', $stats['copied'] ?? 0],
            ['  • failed', $stats['failed'] ?? 0],
            ['Skipped — missing source', $stats['skipped_missing']],
            ['Skipped — other prefix', $stats['skipped_other']],
            ['Empty catalog/ dirs removed', $stats['catalog_dirs_removed'] ?? '—'],
        ]);

        if (! empty($stats['per_entity'])) {
            $this->line('Per entity:');
            foreach ($stats['per_entity'] as $t => $n) {
                $this->line("  {$t}: {$n}");
            }
        }
        if (! empty($stats['per_brand'])) {
            arsort($stats['per_brand']);
            $this->line('Per brand (products):');
            foreach (array_slice($stats['per_brand'], 0, 20, true) as $b => $n) {
                $this->line("  {$b}: {$n}");
            }
        }
    }
}
