<?php

namespace Extensions\Seo\Services;

use App\Models\Setting;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleSearchConsoleService
{
    private ?SearchConsole $service = null;
    private string $siteUrl;

    public function __construct()
    {
        $this->siteUrl = Setting::where('group', 'seo')->where('key', 'gsc_site_url')->value('value') ?? '';
    }

    public function isConfigured(): bool
    {
        return Storage::disk('local')->exists('credentials/gsc-credentials.json') && !empty($this->siteUrl);
    }

    private function getService(): ?SearchConsole
    {
        if ($this->service) {
            return $this->service;
        }

        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig(Storage::disk('local')->path('credentials/gsc-credentials.json'));
            $client->addScope(SearchConsole::WEBMASTERS_READONLY);
            $this->service = new SearchConsole($client);
            return $this->service;
        } catch (\Throwable $e) {
            Log::error('GSC: failed to initialize', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get search performance data for the last N days.
     * Returns: queries with clicks, impressions, CTR, position
     */
    public function getSearchPerformance(int $days = 28, int $rowLimit = 100): array
    {
        $service = $this->getService();
        if (!$service) {
            return [];
        }

        try {
            $request = new SearchAnalyticsQueryRequest();
            $request->setStartDate(now()->subDays($days)->format('Y-m-d'));
            $request->setEndDate(now()->subDay()->format('Y-m-d'));
            $request->setDimensions(['query']);
            $request->setRowLimit($rowLimit);

            $response = $service->searchanalytics->query($this->siteUrl, $request);

            return collect($response->getRows() ?? [])->map(function ($row) {
                return [
                    'query' => $row->getKeys()[0],
                    'clicks' => $row->getClicks(),
                    'impressions' => $row->getImpressions(),
                    'ctr' => round($row->getCtr() * 100, 2),
                    'position' => round($row->getPosition(), 1),
                ];
            })->toArray();
        } catch (\Throwable $e) {
            Log::error('GSC: search performance query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get page-level performance data.
     */
    public function getPagePerformance(int $days = 28, int $rowLimit = 100): array
    {
        $service = $this->getService();
        if (!$service) {
            return [];
        }

        try {
            $request = new SearchAnalyticsQueryRequest();
            $request->setStartDate(now()->subDays($days)->format('Y-m-d'));
            $request->setEndDate(now()->subDay()->format('Y-m-d'));
            $request->setDimensions(['page']);
            $request->setRowLimit($rowLimit);

            $response = $service->searchanalytics->query($this->siteUrl, $request);

            return collect($response->getRows() ?? [])->map(function ($row) {
                return [
                    'page' => $row->getKeys()[0],
                    'clicks' => $row->getClicks(),
                    'impressions' => $row->getImpressions(),
                    'ctr' => round($row->getCtr() * 100, 2),
                    'position' => round($row->getPosition(), 1),
                ];
            })->toArray();
        } catch (\Throwable $e) {
            Log::error('GSC: page performance query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
