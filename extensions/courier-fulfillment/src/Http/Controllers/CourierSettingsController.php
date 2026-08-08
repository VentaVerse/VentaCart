<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;
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
 * Task W3 (spx-courier plan) split the single combined page into:
 *
 *  - a HUB (index/update) — module-level settings only (the MODULE_KEYS
 *    below + the server-generated per-courier webhook tokens) plus one card
 *    per registered courier (name, enabled state, mode chip,
 *    credential-completeness hint) linking to…
 *  - a PER-COURIER PAGE (show/updateCourier) — that courier's own
 *    settingsSchema() fields, its Test Connection / Refresh statuses
 *    actions, and its status-mapping table.
 *
 * The write-allowlists are scoped per surface: the hub persists ONLY the
 * module keys, and a courier's page persists ONLY keys derived from THAT
 * courier's settingsSchema() (plus its "{key}_status_map") — so saving one
 * courier's page can never touch another courier's keys or the module's.
 * Everything is generated from CourierFulfillmentInterface — adding a
 * courier (a new Couriers/{Name}/ folder + one CourierManager::register()
 * call) never requires editing this controller. Settings keys and stored
 * values are FROZEN across this split (page split, not a data change).
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

    /**
     * The ONLY keys writable via the given courier's page. Anything else in
     * the request is ignored — prevents arbitrary writes into the 'courier'
     * settings group (e.g. overwriting the server-generated
     * {provider}_webhook_token, which is deliberately never part of any
     * schema) AND cross-courier/module writes from a courier's own form.
     */
    private static function courierAllowedKeys(CourierFulfillmentInterface $courier): array
    {
        $keys = array_map(fn (array $field) => $field['key'], $courier->settingsSchema());

        // Status mapping is a per-lifecycle-status nested structure, not a
        // flat schema field, so it isn't declared via settingsSchema() —
        // kept writable per courier here in the meantime. Full derivation of
        // StatusMapper's storage key from the provider key is tracked
        // separately (courier-agnostic status/tracking pass).
        $keys[] = $courier->getKey() . '_status_map';

        return array_values(array_unique($keys));
    }

    private static function courierEncryptedKeys(CourierFulfillmentInterface $courier): array
    {
        $keys = array_map(
            fn (array $field) => $field['key'],
            array_filter($courier->settingsSchema(), fn (array $field) => !empty($field['encrypted']))
        );

        return array_values(array_unique($keys));
    }

    /** The "{key}_mode"-suffixed schema field driving a courier's mode-scoped groups, or null. */
    private static function modeField(array $schema): ?array
    {
        foreach ($schema as $field) {
            if (str_ends_with($field['key'], '_mode')) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Hub: module-level settings + one card per registered courier. No
     * courier schema field renders here anymore — each courier's page owns
     * its own form (see show()).
     */
    public function index()
    {
        $settings = Setting::where('group', 'courier')->pluck('value', 'key')->toArray();

        // Ensure a stable webhook token exists per registered courier —
        // matches CourierWebhookController's generic "{provider}_webhook_token"
        // lookup, so every registered courier gets a working webhook URL
        // without this controller knowing any courier's literal key.
        // Task T3 exception: SPX authenticates its webhook via a signed
        // `check-sign` header (its own app-secret), not this shared
        // `?token=` scheme — see CourierWebhookController's SPX branch — so
        // no token is generated for it; the webhook URL built below omits
        // the query string entirely rather than showing a value that's
        // never actually checked.
        foreach (CourierManager::all() as $courier) {
            if ($courier->getKey() === 'spx') {
                continue;
            }
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

        // Card view model per registered courier — everything derived from
        // its own settingsSchema() + stored settings, never a literal key.
        $courierCards = [];
        foreach (CourierManager::all() as $courier) {
            $key = $courier->getKey();
            $schema = $courier->settingsSchema();

            $modeField = self::modeField($schema);
            $mode = $modeField
                ? ($settings[$modeField['key']] ?? ($modeField['options'][0]['value'] ?? null))
                : null;

            // Mode chip label — shown only when the mode is NOT production
            // (same convention as the booking modal's step-1 courier cards):
            // production is the unremarkable default, anything else deserves
            // a loud chip so staging credentials are never mistaken for live.
            $modeLabel = null;
            if ($modeField && $mode !== null && $mode !== 'production') {
                $modeLabel = collect($modeField['options'] ?? [])
                    ->firstWhere('value', $mode)['label'] ?? ucfirst($mode);
            }

            // Credential completeness for the CURRENT mode: encrypted fields
            // scoped to that mode (plus unscoped encrypted fields) that have
            // no stored value yet.
            $credFields = array_filter($schema, function (array $field) use ($mode) {
                $scope = $field['mode_scoped'] ?? null;

                return !empty($field['encrypted']) && ($scope === null || $scope === $mode);
            });
            $credTotal = count($credFields);
            $credMissing = count(array_filter(
                $credFields,
                fn (array $field) => empty($settings[$field['key']])
            ));

            $courierCards[$key] = [
                'courier'        => $courier,
                'enabled'        => ($settings[$key . '_enabled'] ?? '0') === '1',
                'modeLabel'      => $modeLabel,
                'credentialHint' => $credTotal === 0
                    ? null
                    : ($credMissing === 0
                        ? 'Credentials set'
                        : $credMissing . ' of ' . $credTotal . ' credentials missing'),
                // This store's own webhook URL — copy and send to the courier.
                // Module-level plumbing (server-generated token), so it lives
                // on the hub, not the courier's own form. SPX gets no
                // `?token=` (see the loop above) — its auth is a signed
                // header SPX computes itself from the app-secret it was
                // issued, nothing this store needs to hand it via the URL.
                'webhookUrl'     => rtrim((string) config('app.url'), '/')
                    . '/webhooks/courier/' . $key
                    . ($key === 'spx' ? '' : ('?token=' . $settings[$key . '_webhook_token'])),
                'url'            => route('admin.fulfillment.settings.courier', $key),
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
            'paymentMethodOptions',
            'codMethods',
            'courierCards'
        ));
    }

    /**
     * Per-courier page: this courier's own schema-driven form, its Test
     * Connection / Refresh statuses actions, and its status-mapping table.
     * Unknown courier key -> 404.
     */
    public function show(string $courier)
    {
        $c = CourierManager::get($courier);
        abort_if(!$c, 404);

        $settings = Setting::where('group', 'courier')->pluck('value', 'key')->toArray();
        $key = $c->getKey();
        $schema = $c->settingsSchema();

        $masked = [];
        foreach (self::courierEncryptedKeys($c) as $k) {
            if (!empty($settings[$k])) {
                try {
                    $plain = Crypt::decryptString($settings[$k]);
                    $masked[$k] = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);
                } catch (\Throwable) {
                    $masked[$k] = '********';
                }
            }
        }

        // Schema fields split into always-visible ("top") vs. mode-scoped
        // groups (see settingsSchema()'s docblock for the "_mode"-suffixed-key
        // convention).
        $modeField = self::modeField($schema);

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

        // Labels come from a cache populated by this page's "Refresh from
        // {courier}" button (see refreshStatuses()) — never a live API call
        // on page load.
        $cachedLabelsRaw = $settings[$key . '_status_labels'] ?? null;
        $cachedLabels = $cachedLabelsRaw ? json_decode($cachedLabelsRaw, true) : [];
        $cachedLabels = is_array($cachedLabels) ? $cachedLabels : [];

        $courierStatuses = [];
        foreach ($c->mappableStatuses() as $name) {
            $courierStatuses[$name] = $cachedLabels[$name] ?? ucwords(str_replace('_', ' ', $name));
        }

        // Smart defaults for the status-mapping table: only applied when this
        // courier's own map is still unconfigured, and only for names that
        // are actually in its own mappableStatuses() — inert data, not a
        // QuadX special case. Display-only pre-selections; nothing is written
        // to the DB here — the admin's first Save is what persists them.
        $defaultStatusNames = [
            'picked_up'        => 'Shipped',
            'in_transit'       => 'Shipped',
            'out_for_delivery' => 'Shipped',
            'delivered'        => 'Delivered',
            'canceled'         => 'Canceled',
        ];

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

        $orderStatuses = \App\Models\OrderStatus::orderBy('name')->pluck('name', 'id')->toArray();

        return view('courier-fulfillment::settings-courier', [
            'courier'         => $c,
            'settings'        => $settings,
            'masked'          => $masked,
            'topFields'       => $topFields,
            'modeGroups'      => $modeGroups,
            'modeFieldKey'    => $modeField['key'] ?? null,
            'initialMode'     => $modeField
                ? ($settings[$modeField['key']] ?? ($modeField['options'][0]['value'] ?? null))
                : null,
            'courierStatuses' => $courierStatuses,
            'statusMap'       => $statusMap,
            'orderStatuses'   => $orderStatuses,
        ]);
    }

    /** Hub save: the module-level keys ONLY — never any courier's schema keys. */
    public function update(Request $request)
    {
        $data = array_intersect_key(
            $request->input('settings', []),
            array_flip(self::MODULE_KEYS)
        );

        $this->persist($data, []);

        return back()->with('success', 'Courier settings saved.');
    }

    /**
     * Per-courier save: restricted to the allowlist derived from THAT
     * courier's own schema (plus its status map). Unknown courier key -> 404.
     */
    public function updateCourier(Request $request, string $courier)
    {
        $c = CourierManager::get($courier);
        abort_if(!$c, 404);

        $data = array_intersect_key(
            $request->input('settings', []),
            array_flip(self::courierAllowedKeys($c))
        );

        $this->persist($data, self::courierEncryptedKeys($c));

        return back()->with('success', $c->getName() . ' settings saved.');
    }

    /**
     * Shared persistence: encrypt the given keys (blank submission keeps the
     * stored secret), JSON-encode array values, upsert into group 'courier'.
     * $data must already be allowlist-filtered by the caller.
     */
    private function persist(array $data, array $encryptedKeys): void
    {
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
    }

    /**
     * Pull the live status list from the given courier and cache it as its
     * own admin-facing labels used by show() (and by the queue view's
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
     * Task T4: "Create shipper account" — dispatches to a courier's own
     * createShipperAccount() (SPX's 3.1 Create Account) IF it has one.
     * Not part of CourierFulfillmentInterface (no other courier has an
     * equivalent concept — see SpxFulfillment::createShipperAccount()'s
     * docblock for why it lives there instead), so this is a
     * method_exists() dispatch rather than an interface call: a future
     * courier can opt into the same button/route by adding the same method,
     * without any change here. Unknown courier key -> 404, same as every
     * other per-courier route in this controller.
     */
    public function createShipperAccount(string $courier)
    {
        $c = CourierManager::get($courier);
        abort_if(!$c, 404);

        if (!method_exists($c, 'createShipperAccount')) {
            return back()->with('error', $c->getName() . ' does not support creating a shipper account.');
        }

        $result = $c->createShipperAccount();
        if (!($result['ok'] ?? false)) {
            return back()->with('error', $result['error'] ?? 'Account creation failed.');
        }

        return back()->with('success', $result['message'] ?? ($c->getName() . ' shipper account created.'));
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
