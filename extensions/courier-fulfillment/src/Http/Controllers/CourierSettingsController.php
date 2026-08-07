<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Extensions\CourierFulfillment\CourierManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Courier settings — self-contained (own encryption, own save). Stores under
 * settings group 'courier'. Credentials are encrypted at rest and masked in
 * the UI. The webhook URL is derived from THIS install's APP_URL so each
 * deployed store shows its own copy-able URL.
 *
 * The write-allowlist and the settings page's per-courier card are both
 * generated from every registered courier's settingsSchema()
 * (CourierFulfillmentInterface) — adding a courier (a new
 * Couriers/{Name}/ folder + one CourierManager::register() call) never
 * requires editing this controller.
 */
class CourierSettingsController extends Controller
{
    /**
     * Settings that belong to the module as a whole, not to any one
     * courier's schema — never renamed without a data migration, since
     * they're read by literal key name elsewhere too: 'fulfillment_enabled'
     * by FulfillmentController::index() (opt-out pause toggle — missing/NULL
     * means active, only an explicit '0' pauses the queue), 'cod_payment_methods'
     * by FulfillmentService::isCodOrder(). (quadx_webhook_token is neither —
     * it's server-generated below and deliberately never part of any
     * allowlist at all.)
     */
    private const MODULE_KEYS = ['fulfillment_enabled', 'cod_payment_methods'];

    /** @return array<int, array{key:string,label:string,type:string,options?:array,encrypted?:bool,mode_scoped?:?string,help?:?string}> every registered courier's schema fields, flattened. */
    private static function schemaFields(): array
    {
        $fields = [];
        foreach (CourierManager::all() as $courier) {
            foreach ($courier->settingsSchema() as $field) {
                $fields[] = $field;
            }
        }
        return $fields;
    }

    /**
     * The ONLY keys writable via this form. Anything else in the request is
     * ignored — prevents arbitrary writes into the 'courier' settings group
     * (e.g. overwriting the server-generated {provider}_webhook_token, which
     * is deliberately never part of any schema).
     */
    private static function allowedKeys(): array
    {
        $keys = self::MODULE_KEYS;
        foreach (self::schemaFields() as $field) {
            $keys[] = $field['key'];
        }
        // Status mapping is a per-lifecycle-status nested structure, not a
        // flat schema field, so it isn't declared via settingsSchema() —
        // kept writable per courier here in the meantime. Full derivation of
        // StatusMapper's storage key from the provider key is tracked
        // separately (courier-agnostic status/tracking pass).
        foreach (CourierManager::all() as $courier) {
            $keys[] = $courier->getKey() . '_status_map';
        }
        return array_values(array_unique($keys));
    }

    private static function encryptedKeys(): array
    {
        $keys = array_map(
            fn (array $field) => $field['key'],
            array_filter(self::schemaFields(), fn (array $field) => !empty($field['encrypted']))
        );
        return array_values(array_unique($keys));
    }

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
        foreach (self::encryptedKeys() as $k) {
            if (!empty($settings[$k])) {
                try {
                    $plain = Crypt::decryptString($settings[$k]);
                    $masked[$k] = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);
                } catch (\Throwable) {
                    $masked[$k] = '********';
                }
            }
        }

        // Per-courier view model: schema fields split into always-visible
        // ("top") vs. mode-scoped groups, plus which field (if any) is the
        // Alpine mode toggle those groups key off of — see
        // CourierFulfillmentInterface::settingsSchema()'s docblock for the
        // "_mode"-suffixed-key convention this relies on.
        $courierCards = [];
        foreach (CourierManager::all() as $courier) {
            $schema = $courier->settingsSchema();

            $modeField = null;
            foreach ($schema as $field) {
                if (str_ends_with($field['key'], '_mode')) {
                    $modeField = $field;
                    break;
                }
            }

            $topFields = [];
            $modeGroups = [];
            foreach ($schema as $field) {
                $scope = $field['mode_scoped'] ?? null;
                if ($scope) {
                    $modeGroups[$scope][] = $field;
                } else {
                    $topFields[] = $field;
                }
            }

            $courierCards[$courier->getKey()] = [
                'courier'      => $courier,
                'topFields'    => $topFields,
                'modeGroups'   => $modeGroups,
                'modeFieldKey' => $modeField['key'] ?? null,
                'initialMode'  => $modeField
                    ? ($settings[$modeField['key']] ?? ($modeField['options'][0]['value'] ?? null))
                    : null,
            ];
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
        // Marketplace keys are excluded: those orders are marketplace-fulfilled and never
        // courier-booked by the store, so classifying them is meaningless (absent = prepaid).
        $marketplaceKeys = ['shopee', 'lazada', 'tiktok'];
        $seenMethods = \Illuminate\Support\Facades\DB::table('orders')->whereNotNull('payment_method')->where('payment_method', '!=', '')->distinct()->pluck('payment_method')->all();
        $methodSlugs = collect(array_merge($seenMethods, \App\Services\PaymentManager::registeredBaseKeys()))->unique()->reject(fn ($m) => in_array($m, $marketplaceKeys, true))->sort()->values()->all();

        // Last-used date per slug, in ONE grouped query (never per-slug queries).
        $lastUsedBySlug = \Illuminate\Support\Facades\DB::table('orders')
            ->whereNotNull('payment_method')
            ->where('payment_method', '!=', '')
            ->groupBy('payment_method')
            ->pluck(\Illuminate\Support\Facades\DB::raw('MAX(created_at)'), 'payment_method');

        $liveCoreMethods = \App\Services\PaymentManager::registeredBaseKeys();
        $legacyCutoff = now()->subMonths(12);

        // Pre-resolve bank names for bank_transfer_<id> slugs in ONE query
        // (never per-slug) — used below to shorten the label to just the bank
        // name and suppress the redundant slug hint in the view.
        $bankIds = [];
        foreach ($methodSlugs as $slug) {
            if (preg_match('/^bank_transfer_(\d+)$/', $slug, $m)) {
                $bankIds[] = (int) $m[1];
            }
        }
        $bankNames = $bankIds
            ? \App\Models\BankAccount::whereIn('id', $bankIds)->pluck('bank_name', 'id')
            : collect();

        $paymentMethodOptions = [];
        foreach ($methodSlugs as $slug) {
            $probe = new \App\Models\Order();
            $probe->payment_method = $slug;
            $label = $probe->payment_method_label;
            if ($label === '') {
                $label = ucwords(str_replace('_', ' ', $slug));
            }

            // bank_transfer_<id> with a resolvable bank name: the bank name
            // alone IS the label (e.g. "Maya" instead of "Bank Transfer — Maya").
            if (preg_match('/^bank_transfer_(\d+)$/', $slug, $m)) {
                $bankName = $bankNames[(int) $m[1]] ?? null;
                if ($bankName) {
                    $label = $bankName;
                }
            }

            $lastUsed = $lastUsedBySlug[$slug] ?? null;
            $legacy = !in_array($slug, $liveCoreMethods, true)
                && (!$lastUsed || \Illuminate\Support\Carbon::parse($lastUsed)->lt($legacyCutoff));

            $paymentMethodOptions[$slug] = ['label' => $label, 'legacy' => $legacy];
        }

        $codRaw = $settings['cod_payment_methods'] ?? null;
        if ($codRaw !== null) {
            $codMethods = array_values(array_filter((array) json_decode($codRaw, true), fn ($v) => $v !== '' && $v !== '__none__'));
        } else {
            // Unconfigured: pre-check the heuristic matches so first save codifies today's behavior.
            $codMethods = array_values(array_filter(array_keys($paymentMethodOptions), fn ($m) => str_contains(strtolower($m), 'cod')));
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
            'codMethods',
            'courierCards'
        ));
    }

    public function update(Request $request)
    {
        $data = $request->input('settings', []);

        // Restrict to an explicit allowlist — never persist arbitrary request keys.
        $data = array_intersect_key($data, array_flip(self::allowedKeys()));

        $encryptedKeys = self::encryptedKeys();
        foreach ($data as $key => $value) {
            if (in_array($key, $encryptedKeys, true)) {
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
