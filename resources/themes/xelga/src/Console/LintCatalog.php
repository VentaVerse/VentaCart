<?php

namespace Themes\Xelga\Console;

use Illuminate\Console\Command;
use Themes\Xelga\Catalog\CatalogLinter;

/** php artisan xelga:lint: checks every schema file and the shipped defaults. */
class LintCatalog extends Command
{
    protected $signature = 'xelga:lint';

    protected $description = 'Check the Xelga theme catalog: section schemas, theme settings, font pairs and shipped defaults.';

    public function handle(): int
    {
        $problems = CatalogLinter::check();

        if ($problems === []) {
            $this->info('The catalog is clean.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }
}
