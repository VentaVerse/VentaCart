<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Courier settings — self-contained (own encryption, own save). Stores under
 * settings group 'courier'. Credentials are encrypted at rest and masked in
 * the UI. The webhook URL is derived from THIS install's APP_URL so each
 * deployed store shows its own copy-able URL.
 */
class CourierSettingsController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'quadx_staging_api_key',
        'quadx_staging_secret_key',
        'quadx_production_api_key',
        'quadx_production_secret_key',
    ];

    /**
     * The ONLY keys writable via this form. Anything else in the request is
     * ignored — prevents arbitrary writes into the 'courier' settings group
     * (e.g. overwriting the server-generated quadx_webhook_token, which is
     * deliberately absent here).
     */
    private const ALLOWED_KEYS = [
        'quadx_enabled',
        'quadx_mode',
        'quadx_default_service',
        'quadx_default_parcel',
        'fulfillment_enabled',
        'quadx_status_map',
        'cod_payment_methods',
        'quadx_staging_api_key',
        'quadx_staging_secret_key',
        'quadx_production_api_key',
        'quadx_production_secret_key',
    ];

    public function index()
    {
        $settings = Setting::where('group', 'courier')->pluck('value', 'key')->toArray();

        // Ensure a stable webhook token exists for this store.
        if (empty($settings['quadx_webhook_token'])) {
            $token = Str::random(40);
            Setting::updateOrCreate(
                ['group' => 'courier', 'key' => 'quadx_webhook_token'],
                ['value' => $token]
            );
            $settings['quadx_webhook_token'] = $token;
        }

        $masked = [];
        foreach (self::ENCRYPTED_KEYS as $k) {
            if (!empty($settings[$k])) {
                try {
                    $plain = Crypt::decryptString($settings[$k]);
                    $masked[$k] = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);
                } catch (\Throwable) {
                    $masked[$k] = '********';
                }
            }
        }

        // This store's own webhook URL — copy and send to QuadX.
        $webhookUrl = rtrim((string) config('app.url'), '/')
            . '/webhooks/courier/quadx?token=' . $settings['quadx_webhook_token'];

        $orderStatuses = \App\Models\OrderStatus::orderBy('name')->pluck('name', 'id')->toArray();

        // Lifecycle statuses we let admins map (curated subset).
        $lifecycle = ['for_pickup', 'picked_up', 'in_transit', 'out_for_delivery',
                      'delivered', 'failed_pickup', 'failed_delivery', 'returned', 'canceled'];

        // Labels come from a cache populated by the "Refresh from QuadX" button
        // (see refreshStatuses()) — never a live API call on page load.
        $cachedLabelsRaw = Setting::where('group', 'courier')->where('key', 'quadx_status_labels')->value('value');
        $cachedLabels = $cachedLabelsRaw ? json_decode($cachedLabelsRaw, true) : [];
        $cachedLabels = is_array($cachedLabels) ? $cachedLabels : [];

        $quadxStatuses = [];
        foreach ($lifecycle as $name) {
            $quadxStatuses[$name] = $cachedLabels[$name] ?? ucwords(str_replace('_', ' ', $name));
        }

        // Payment-method keys seen in real orders + the core methods, for COD classification.
        $seenMethods = \Illuminate\Support\Facades\DB::table('orders')->whereNotNull('payment_method')->where('payment_method', '!=', '')->distinct()->pluck('payment_method')->all();
        $paymentMethodOptions = collect(array_merge($seenMethods, ['bank_transfer', 'hitpay', 'cod']))->unique()->sort()->values()->all();

        $codRaw = $settings['cod_payment_methods'] ?? null;
        if ($codRaw !== null) {
            $codMethods = array_values(array_filter((array) json_decode($codRaw, true), fn ($v) => $v !== '' && $v !== '__none__'));
        } else {
            // Unconfigured: pre-check the heuristic matches so first save codifies today's behavior.
            $codMethods = array_values(array_filter($paymentMethodOptions, fn ($m) => str_contains(strtolower($m), 'cod')));
        }

        $statusMap = \Extensions\CourierFulfillment\StatusMapper::currentMap();

        // Smart defaults: only when the admin hasn't configured a map yet. These
        // are display-only pre-selections for the dropdowns — nothing is written
        // to the DB here. The admin's first Save is what actually persists them.
        if (empty($statusMap)) {
            $defaultNames = [
                'picked_up'        => 'Shipped',
                'in_transit'       => 'Shipped',
                'out_for_delivery' => 'Shipped',
                'delivered'        => 'Delivered',
                'canceled'         => 'Canceled',
            ];
            foreach ($defaultNames as $quadxKey => $ventaName) {
                $id = \App\Models\OrderStatus::where('name', $ventaName)->value('id');
                if ($id) {
                    $statusMap[$quadxKey] = ['order_status_id' => $id, 'notify' => false];
                }
            }
        }

        return view('courier-fulfillment::settings', compact(
            'settings',
            'masked',
            'webhookUrl',
            'orderStatuses',
            'quadxStatuses',
            'statusMap',
            'paymentMethodOptions',
            'codMethods'
        ));
    }

    public function update(Request $request)
    {
        $data = $request->input('settings', []);

        // Restrict to an explicit allowlist — never persist arbitrary request keys.
        $data = array_intersect_key($data, array_flip(self::ALLOWED_KEYS));

        foreach ($data as $key => $value) {
            if (in_array($key, self::ENCRYPTED_KEYS, true)) {
                if (empty($value)) {
                    continue; // don't overwrite stored secret with blank
                }
                $value = Crypt::encryptString($value);
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }
            Setting::updateOrCreate(
                ['group' => 'courier', 'key' => $key],
                ['value' => $value]
            );
        }

        return back()->with('success', 'Courier settings saved.');
    }

    /**
     * Pull the live status list from QuadX and cache it as the admin-facing
     * labels used by index(). Explicit admin action only — never called on
     * page load.
     */
    public function refreshStatuses()
    {
        $client = new \Extensions\CourierFulfillment\Couriers\QuadX\QuadXClient();
        if (!$client->isConfigured()) {
            return back()->with('error', 'API key/secret not set — cannot refresh statuses.');
        }

        $res = $client->getStatuses();
        if (!$res['ok']) {
            return back()->with('error', 'Refresh failed: ' . ($res['error'] ?? 'unknown error'));
        }

        $labels = [];
        foreach ($res['body'] as $s) {
            if (!empty($s['name'])) {
                $labels[$s['name']] = $s['label'] ?? ucwords(str_replace('_', ' ', $s['name']));
            }
        }

        Setting::updateOrCreate(
            ['group' => 'courier', 'key' => 'quadx_status_labels'],
            ['value' => json_encode($labels)]
        );

        return back()->with('success', 'QuadX status list refreshed.');
    }

    public function testConnection()
    {
        $courier = \Extensions\CourierFulfillment\CourierManager::get('quadx');
        if (!$courier) {
            return response()->json(['ok' => false, 'message' => 'QuadX courier not registered.']);
        }
        $client = new \Extensions\CourierFulfillment\Couriers\QuadX\QuadXClient();
        if (!$client->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'API key/secret not set.']);
        }
        $res = $client->getStatuses();
        return response()->json($res['ok']
            ? ['ok' => true, 'message' => 'Connected — QuadX reachable and credentials valid.']
            : ['ok' => false, 'message' => 'Failed: ' . ($res['error'] ?? 'unknown (HTTP ' . $res['status'] . ')')]);
    }
}
