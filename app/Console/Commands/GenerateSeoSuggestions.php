<?php

namespace App\Console\Commands;

use App\Services\SeoGeneratorService;
use Illuminate\Console\Command;

class GenerateSeoSuggestions extends Command
{
    protected $signature = 'seo:generate';
    protected $description = 'Generate SEO suggestions using Claude AI';

    public function handle(): int
    {
        $this->info('Starting SEO generation...');

        $service = new SeoGeneratorService();
        $result = $service->generate();

        if (isset($result['error'])) {
            $this->error($result['error']);
            return 1;
        }

        if (isset($result['message'])) {
            $this->info($result['message']);
            return 0;
        }

        $this->info("Suggestions: {$result['suggestions']}, Errors: {$result['errors']}, Health: {$result['health_score']}%");

        return 0;
    }
}
