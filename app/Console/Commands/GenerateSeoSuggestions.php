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
        $service = new SeoGeneratorService();

        if (!$service->isConfigured()) {
            $this->error('Claude API key not configured. Set it in Extensions > SEO > Settings.');
            return 1;
        }

        $types = ['product', 'category', 'brand', 'blog_post'];
        $totalSuggestions = 0;

        foreach ($types as $type) {
            $this->info("Generating SEO for {$type}s...");
            $result = $service->generateForType($type);

            if (isset($result['error'])) {
                $this->warn("  {$type}: {$result['error']}");
                continue;
            }

            $count = $result['suggestions'] ?? 0;
            $totalSuggestions += $count;
            $this->info("  {$type}: {$count} suggestions generated");
        }

        $this->newLine();
        $this->info("Done. Total: {$totalSuggestions} suggestions.");
        return 0;
    }
}
