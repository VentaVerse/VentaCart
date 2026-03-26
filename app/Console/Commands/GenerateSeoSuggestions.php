<?php

namespace App\Console\Commands;

use App\Models\Setting;
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

        // Respect scope rules from settings
        $scopeMap = [
            'product' => 'rule_scope_products',
            'category' => 'rule_scope_categories',
            'brand' => 'rule_scope_brands',
            'blog_post' => 'rule_scope_blog_posts',
            'page' => 'rule_scope_pages',
        ];

        $scopeSettings = Setting::where('group', 'seo')
            ->whereIn('key', array_values($scopeMap))
            ->pluck('value', 'key')
            ->toArray();

        $types = [];
        foreach ($scopeMap as $type => $settingKey) {
            if (($scopeSettings[$settingKey] ?? '1') === '1') {
                $types[] = $type;
            }
        }

        if (empty($types)) {
            $this->warn('All entity types are disabled in SEO settings scope.');
            return 0;
        }

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
