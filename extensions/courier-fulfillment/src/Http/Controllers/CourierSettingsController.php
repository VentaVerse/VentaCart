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
     * by FulfillmentService::isCodOrder(). ("{courier}_webhook_token" is
     * neither — it's server-generated below, per registered courier, and
     * deliberately never part of any allowlist at all.)
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

        // Ensure a stable webhook token exists per registered courier —
        // matches CourierWebhookController's generic "{provider}_webhook_token"
        // lookup, so every registered courier gets a working webhook URL
        // without this controller knowing any courier's literal key.
        foreach (CourierManager::all() as $courier) {
            $tokenKey = $courier->getKey() . '_webhook_token';
            if (empty($settings[$tokenKey])) {
                $token = Str::random(40);
                Setting::updateOrCreate(
                    ['group' => 'courier', 'key' => $tokenKey],
                    ['value' => $token]
                );
                $settings[$tokenKey] = $token;
            }
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

        $orderStatuses = \App\Models\OrderStatus::orderBy('name')->pluck('name', 'id')->toArray();

        // Smart defaults for the status-mapping table: only applied per
        // courier when that courier's own map is still unconfigured, and
        // only for names that are actually in that courier's own
        // mappableStatuses() — so this is inert data, not a QuadX special
        // case, even though today only QuadX's vocabulary matches it.
        // Display-only pre-selections; nothing is written to the DB here —
        // the admin's first Save is what actually persists them.
        $defaultStatusNames = [
            'picked_up'        => 'Shipped',
            'in_transit'       => 'Shipped',
            'out_for_delivery' => 'Shipped',
            'delivered'        => 'Delivered',
            'canceled'         => 'Canceled',
        ];

        // Per-courier view model: schema fields split into always-visible
        // ("top") vs. mode-scoped groups (see settingsSchema()'s docblock for
        // the "_mode"-suffixed-key convention), plus this store's own webhook
        // URL, curated status vocabulary + cached display labels, and the
        // admin's saved status map — everything the settings page needs to
        // render one card per registered courier without knowing any
        // courier's literal key.
        $courierCards = [];
        foreach (CourierManager::all() as $courier) {
            $key = $courier->getKey();
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

            // Labels come from a cache populated by this card's "Refresh from
            // {courier}" button (see refreshStatuses()) — never a live API
            // call on page load.
            $cachedLabelsRaw = $settings[$key . '_status_labels'] ?? null;
            $cachedLabels = $cachedLabelsRaw ? json_decode($cachedLabelsRaw, true) : [];
            $cachedLabels = is_array($cachedLabels) ? $cachedLabels : [];

            $courierStatuses = [];
            foreach ($courier->mappableStatuses() as $name) {
                $courierStatuses[$name] = $cachedLabels[$name] ?? ucwords(str_replace('_', ' ', $name));
            }

            $statusMap = \Extensions\CourierFulfillment\StatusMapper::currentMap($key);
            if (empty($statusMap)) {
                foreach ($defaultStatusNames as $statusName => $ventaName) {
                    if (!array_key_exists($statusName, $courierStatuses)) {
                        continue; // not part of THIS courier's own vocabulary
                    }
                    $id = \App\Models\OrderStatus::where('name', $ventaName)->value('id');
                    if ($id) {
                        $statusMap[$statusName] = ['order_status_id' => $id, 'notify' => false];
                    }
                }
            }

            $courierCards[$key] = [
                'courier'         => $courier,
                'topFields'       => $topFields,
                'modeGroups'      => $modeGroups,
                'modeFieldKey'    => $modeField['key'] ?? null,
                'initialMode'     => $modeField
                    ? ($settings[$modeField['key']] ?? ($modeField['options'][0]['value'] ?? null))
                    : null,
                // This store's own webhook URL — copy and send to the courier.
                'webhookUrl'      => rtrim((string) config('app.url'), '/')
                    . '/webhooks/courier/' . $key . '?token=' . $settings[$key . '_webhook_token'],
                'courierStatuses' => $courierStatuses,
                'statusMap'       => $statusMap,
            ];
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

        return view('courier-fulfillment::settings', compact(
            'settings',
            'masked',
            'orderStatuses',
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
     * Pull the live status list from the given courier and cache it as its
     * own admin-facing labels used by index() (and by the queue view's
     * status humanization). Explicit per-courier admin action only — never
     * called on page load. Delegates the actual API call to the courier
     * itself (CourierFulfillmentInterface::refreshStatusLabels()) — this
     * controller never talks to a courier's client directly.
     */
    public function refreshStatuses(string $courier)
    {
        $c = CourierManager::get($courier);
        if (!$c) {
            return back()->with('error', 'Courier not registered.');
        }

        $res = $c->refreshStatusLabels();
        if (!($res['ok'] ?? false)) {
            return back()->with('error', $res['error'] ?? 'Refresh failed: unknown error');
        }

        Setting::updateOrCreate(
            ['group' => 'courier', 'key' => $c->getKey() . '_status_labels'],
            ['value' => json_encode($res['labels'] ?? [])]
        );

        return back()->with('success', $c->getName() . ' status list refreshed.');
    }

    /**
     * Verify the given courier's saved credentials by delegating to its own
     * CourierFulfillmentInterface::testConnection() — this controller never
     * talks to a courier's client directly.
     */
    public function testConnection(string $courier)
    {
        $c = CourierManager::get($courier);
        if (!$c) {
            return response()->json(['ok' => false, 'message' => 'Courier not registered.']);
        }

        return response()->json($c->testConnection());
    }
}
