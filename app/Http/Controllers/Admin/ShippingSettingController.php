<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DphStatus;
use App\Models\FlatShippingRate;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\WeightBasedMethod;
use Illuminate\Support\Facades\Crypt;

class ShippingSettingController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'dph_email',
        'dph_password',
        'dhl_site_id',
        'dhl_password',
    ];

    public function index()
    {
        $settings = [];
        foreach (Setting::all() as $s) {
            $settings[$s->group][$s->key] = $s->value;
        }

        $maskedKeys = [];
        foreach (self::ENCRYPTED_KEYS as $key) {
            $encrypted = $settings['shipping'][$key] ?? null;
            if ($encrypted) {
                try {
                    $plain = Crypt::decryptString($encrypted);
                    $maskedKeys[$key] = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);
                } catch (\Throwable) {
                    $maskedKeys[$key] = '********';
                }
            }
        }

        $flatRates = FlatShippingRate::with('geoZone', 'customerGroup')->orderBy('sort_order')->get();
        $weightMethods = WeightBasedMethod::with('geoZones', 'customerGroups', 'rates')->withCount('rates')->orderBy('sort_order')->get();
        $geoZones = \App\Models\GeoZone::where('is_active', true)->orderBy('name')->get();
        $customerGroups = \App\Models\CustomerGroup::orderBy('name')->get();

        $storeId = (int) config('app.store_id', 1);
        $dphStatuses = DphStatus::orderBy('sort_order')->get();
        $orderStatuses = OrderStatus::where('store_id', $storeId)->orderBy('sort_order')->orderBy('name')->get();

        $logFile = storage_path('logs/dph-' . now()->format('Y-m-d') . '.log');
        $raw = file_exists($logFile) ? file_get_contents($logFile) : '';
        $dphLogs = $raw ? self::formatDphLogEntries($raw) : 'No logs for today.';

        return view('admin.shipping-settings.index', compact(
            'settings', 'maskedKeys', 'flatRates', 'weightMethods', 'geoZones', 'customerGroups',
            'dphStatuses', 'orderStatuses', 'dphLogs'
        ));
    }

    private static function formatDphLogEntries(string $raw): string
    {
        $lines = explode("\n", trim($raw));
        $formatted = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

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
