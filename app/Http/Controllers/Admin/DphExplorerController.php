<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DeliveryPhService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DphExplorerController extends Controller
{
    public function index()
    {
        $logFile = storage_path('logs/dph-' . now()->format('Y-m-d') . '.log');
        $raw = file_exists($logFile) ? file_get_contents($logFile) : '';
        $logs = $raw ? self::formatLogEntries($raw) : 'No logs for today.';

        return view('admin.dph-explorer.index', compact('logs'));
    }

    public function execute(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $endpoint = $request->input('endpoint');
        $body = json_decode($request->input('body', '{}'), true) ?: [];
        $query = json_decode($request->input('query', '{}'), true) ?: [];

        try {
            $dph = app(DeliveryPhService::class);

            $startTime = microtime(true);

            $result = match ($endpoint) {
                'login' => $this->doLogin($dph),
                'getPartners' => $dph->getPartners(),
                'getScheduledRates' => $dph->getScheduledRates(
                    $body['pickup'] ?? [],
                    $body['delivery'] ?? [],
                    $body['dimensions'] ?? null,
                    $body['weight'] ?? null
                ),
                'getOnDemandRates' => $dph->getOnDemandRates(
                    $body['pickup'] ?? [],
                    $body['delivery'] ?? [],
                    $body['dimensions'] ?? [],
                    $body['weight'] ?? 0.5
                ),
                'createPost' => $dph->createPost(
                    $body['pickupDetails'] ?? [],
                    $body['deliveryDetails'] ?? [],
                    $query['partnerId'] ?? '',
                    $body['refNo'] ?? 'test-001',
                    $body['isPSGC'] ?? true
                ),
                'getPost' => $dph->getPost($query['postId'] ?? ''),
                'cancelPost' => $dph->cancelPost(
                    $body['postId'] ?? '',
                    $body['trackingNumber'] ?? '',
                    $body['courierId'] ?? ''
                ),
                default => ['error' => 'Unknown endpoint'],
            };

            $duration = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => true,
                'endpoint' => $endpoint,
                'request' => ['body' => $body, 'query' => $query],
                'response' => $result,
                'duration_ms' => $duration,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'endpoint' => $endpoint,
                'request' => ['body' => $body, 'query' => $query],
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function doLogin(DeliveryPhService $dph): array
    {
        Cache::forget('dph_auth');
        $dph->login();
        $auth = Cache::get('dph_auth');

        return [
            'success' => true,
            'access_token' => substr($auth['access_token'], 0, 40) . '...',
            'access_expires' => date('Y-m-d H:i:s', $auth['access_expires_at'] / 1000),
            'refresh_expires' => date('Y-m-d H:i:s', $auth['refresh_expires_at'] / 1000),
        ];
    }

    public function logs()
    {
        $logFile = storage_path('logs/dph-' . now()->format('Y-m-d') . '.log');
        $content = file_exists($logFile) ? file_get_contents($logFile) : 'No logs for today.';

        return response()->json(['logs' => self::formatLogEntries($content)]);
    }

    private static function formatLogEntries(string $raw): string
    {
        $lines = explode("\n", trim($raw));
        $formatted = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // Match: [timestamp] channel.LEVEL: METHOD /endpoint {json}
            if (preg_match('/^(\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]) (\S+): (.+?) (\{.+\})\s*$/', $line, $m)) {
                $json = json_decode($m[4], true);
                $prettyJson = $json !== null
                    ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : $m[4];
                $formatted[] = "{$m[1]} {$m[2]}: {$m[3]}\n{$prettyJson}";
            } else {
                $formatted[] = $line;
            }
        }

        return implode("\n\n" . str_repeat('─', 80) . "\n\n", $formatted);
    }
}
