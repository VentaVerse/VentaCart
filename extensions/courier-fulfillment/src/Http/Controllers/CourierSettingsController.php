<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\PlacementMap;
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
     * by FulfillmentService::isCodOrder(), 'cancel_order_status_id' by
     * FulfillmentController::cancelOrder() (fulfillment-tabs-v Task 4: the
     * core status a queue "Cancel order" sets; default is the status named
     * "Canceled"), 'booked_order_status_id' / 'unbooked_order_status_id' by
     * FulfillmentService::bookedStatus()/unbookedStatus()
     * (fulfillment-placement Task 3: the core status a successful booking
     * sets, and the one a cancelled booking returns the order to).
     * ("{courier}_webhook_token" is neither: it is server-generated below,
     * per registered courier, and deliberately never part of any allowlist
     * at all.)
     */
    private const MODULE_KEYS = [
        'fulfillment_enabled',
        'cod_payment_methods',
        'cancel_order_status_id',
        'booked_order_status_id',
        'unbooked_order_status_id',
    ];

    /**
     * Couriers this store generates no `?token=` webhook token for, and so
     * has no token-bearing URL to hand out. Two different reasons, both
     * about not showing a value nobody checks:
     *
     *  - `spx` authenticates its webhook with a signed `check-sign` header
     *    it computes from its own app-secret (see CourierWebhookController's
     *    SPX branch), so a token in the URL would never be read. Its card
     *    still shows the bare URL, which SPX genuinely posts to.
     *  - `lbc` has no push API at all. All four documented LBC APIs are
     *    request/response, and Track and Trace V2 is POLLED over SOAP, so
     *    there is no URL to give LBC and its card shows none.
     */
    private const NO_WEBHOOK_URL = ['spx', 'lbc'];

    /**
     * Mode values that mean "the real environment", and so earn no chip on a
     * courier's hub card. One list rather than one literal because the word
     * is the courier's own: QuadX and SPX call it production, LBC calls it
     * live. Every other value is a test environment and gets the chip.
     */
    private const LIVE_MODES = ['production', 'live'];

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
            if (in_array($courier->getKey(), self::NO_WEBHOOK_URL, true)) {
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

            // Mode chip label — shown only when the mode is NOT the real one
            // (same convention as the booking modal's step-1 courier cards):
            // the live environment is the unremarkable default, anything else
            // deserves a loud chip so test credentials are never mistaken for
            // live ones. Couriers spell that environment differently (QuadX
            // and SPX say "production", LBC says "live"), so both words count.
            $modeLabel = null;
            if ($modeField && $mode !== null && !in_array($mode, self::LIVE_MODES, true)) {
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
                // `?token=` (see NO_WEBHOOK_URL): its auth is a signed
                // header SPX computes itself from the app-secret it was
                // issued, nothing this store needs to hand it via the URL.
                // LBC gets no URL at all: it has no push API to give one to,
                // and the hub renders nothing for a null (see settings.blade).
                'webhookUrl'     => $key === 'lbc' ? null : (
                    rtrim((string) config('app.url'), '/')
                    . '/webhooks/courier/' . $key
                    . ($key === 'spx' ? '' : ('?token=' . ($settings[$key . '_webhook_token'] ?? '')))
                ),
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
        $bankNames = ($bankIds && class_exists(\Extensions\PaymentBankTransfer\Models\BankAccount::class))
            ? \Extensions\PaymentBankTransfer\Models\BankAccount::whereIn('id', $bankIds)->pluck('bank_name', 'id')
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

        // fulfillment-tabs-v Task 4: the queue's "Cancel order" target
        // status. Null when unset — the view renders a "Use default"
        // placeholder for that case (fix-round minor: pre-selecting the
        // default status here made the first hub save silently PERSIST it,
        // turning "never chose" into "explicitly chose"). The default
        // itself (the status named "Canceled") is resolved at cancel time
        // by FulfillmentController::cancelOrder().
        //
        // FINAL-review fix (F5): only cancelled-STAGE statuses are offered -
        // they are the only coherent targets (a Cancel order that lands the
        // order anywhere but the Cancelled tab would contradict the whole
        // stage model). update() enforces the same scope server-side.
        $orderStatuses = \App\Models\OrderStatus::whereIn('id', PlacementMap::statusIdsFor('cancelled'))
            ->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->toArray();
        $cancelOrderStatusId = $settings['cancel_order_status_id'] ?? null;

        // fulfillment-placement Task 3: the two statuses that carry an order
        // ACROSS the To Pack / To Handover line, each select scoped to the
        // placement that makes it coherent (update() enforces the same scope
        // server-side). Same "never pre-select" convention as the cancel
        // select above: an unset value keeps the placeholder selected so
        // saving the hub cannot turn "never chose" into "explicitly chose" -
        // the placeholder NAMES the status that would be used instead, which
        // is resolved by FulfillmentService and shown here so the admin can
        // see the default without having to save it.
        $handoverStatuses = \App\Models\OrderStatus::whereIn('id', PlacementMap::statusIdsFor(FulfillmentService::BOOKED_PLACEMENT))
            ->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->toArray();
        $packStatuses = \App\Models\OrderStatus::whereIn('id', PlacementMap::statusIdsFor(FulfillmentService::UNBOOKED_PLACEMENT))
            ->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->toArray();

        // Status placement summary for the hub's own card: how much of the
        // mapping is done, without duplicating the page that owns it.
        $storeStatusIds = \App\Models\OrderStatus::where('store_id', (int) config('app.store_id', 1))->pluck('id');
        $placedStatusCount = $storeStatusIds->filter(fn ($id) => PlacementMap::isMapped((int) $id))->count();
        $unplacedStatusCount = $storeStatusIds->count() - $placedStatusCount;

        $bookedOrderStatusId = $settings[FulfillmentService::BOOKED_STATUS_SETTING] ?? null;
        $unbookedOrderStatusId = $settings[FulfillmentService::UNBOOKED_STATUS_SETTING] ?? null;
        $bookedDefaultName = FulfillmentService::bookedStatus()?->name;
        $unbookedDefaultName = FulfillmentService::unbookedStatus()?->name;

        return view('courier-fulfillment::settings', compact(
            'settings',
            'paymentMethodOptions',
            'codMethods',
            'courierCards',
            'orderStatuses',
            'cancelOrderStatusId',
            'handoverStatuses',
            'packStatuses',
            'placedStatusCount',
            'unplacedStatusCount',
            'bookedOrderStatusId',
            'unbookedOrderStatusId',
            'bookedDefaultName',
            'unbookedDefaultName'
        ) + [
            // The store's origin, one readable line, so the hub says whether
            // booking has somewhere to leave from without a second click.
            'defaultPickupAddress' => \Extensions\CourierFulfillment\Models\PickupAddress::defaultAddress()?->summary(),
        ]);
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

        // This courier's webhook URL (owner ruling 2026-08-19: it lives on
        // the courier's own page). SPX gets no ?token= (signed-header auth);
        // LBC has no push API, so null renders nothing.
        $webhookUrl = $key === 'lbc' ? null : (
            rtrim((string) config('app.url'), '/')
            . '/webhooks/courier/' . $key
            . ($key === 'spx' ? '' : ('?token=' . ($settings[$key . '_webhook_token'] ?? '')))
        );

        // The state chips the hub cards used to carry (owner ruling
        // 2026-08-19: couriers are menu entries now, so each page shows
        // its own state). Same derivation as before the move.
        $modeFieldForState = self::modeField($schema ?? $c->settingsSchema());
        $modeForState = $modeFieldForState
            ? ($settings[$modeFieldForState['key']] ?? ($modeFieldForState['options'][0]['value'] ?? null))
            : null;
        $stateModeLabel = null;
        if ($modeFieldForState && $modeForState !== null && !in_array($modeForState, self::LIVE_MODES, true)) {
            $stateModeLabel = collect($modeFieldForState['options'] ?? [])
                ->firstWhere('value', $modeForState)['label'] ?? ucfirst($modeForState);
        }
        $credFieldsForState = array_filter($c->settingsSchema(), function (array $field) use ($modeForState) {
            $scope = $field['mode_scoped'] ?? null;

            return !empty($field['encrypted']) && ($scope === null || $scope === $modeForState);
        });
        $credTotalForState = count($credFieldsForState);
        $credMissingForState = count(array_filter(
            $credFieldsForState,
            fn (array $field) => empty($settings[$field['key']])
        ));
        $courierState = [
            'enabled' => ($settings[$key . '_enabled'] ?? '0') === '1',
            'modeLabel' => $stateModeLabel,
            'credentialHint' => $credTotalForState === 0
                ? null
                : ($credMissingForState === 0
                    ? 'Credentials set'
                    : $credMissingForState . ' of ' . $credTotalForState . ' credentials missing'),
        ];
        $schema = $c->settingsSchema();

        // Decrypted alongside the mask, for schema fields declared
        // type='secret', the one-field stored-key control the security
        // settings page already uses, where View reveals the value in the
        // same box it rests in. type='password' fields never render this,
        // so QuadX's and SPX's own pages are unchanged: the value is
        // computed, not printed.
        $masked = [];
        $plain = [];
        foreach (self::courierEncryptedKeys($c) as $k) {
            if (!empty($settings[$k])) {
                try {
                    $decrypted = Crypt::decryptString($settings[$k]);
                    $plain[$k] = $decrypted;
                    $masked[$k] = str_repeat('*', max(0, strlen($decrypted) - 4)) . substr($decrypted, -4);
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

        // Owner task (2026-08-08, "SPX status from code to their actual
        // meaning"): a courier MAY expose a static, pre-known vocabulary via
        // statusLabels() (SpxFulfillment does — see its docblock) so the
        // page reads meaningful text even before the admin ever clicks
        // Refresh. Dispatched via method_exists() since it isn't part of
        // CourierFulfillmentInterface (same convention as
        // createShipperAccount() above) — a courier without it (QuadX) is
        // unaffected, falling straight through to the cache-then-ucwords
        // chain exactly as before.
        $builtinLabels = method_exists($c, 'statusLabels') ? $c->statusLabels() : [];

        $courierStatuses = [];
        foreach ($c->mappableStatuses() as $name) {
            $courierStatuses[$name] = $cachedLabels[$name] ?? $builtinLabels[$name] ?? ucwords(str_replace('_', ' ', $name));
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
            'webhookUrl'      => $webhookUrl,
            'courierState'    => $courierState,
            'settings'        => $settings,
            'masked'          => $masked,
            'plain'           => $plain,
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
        // fulfillment-tabs-v Task 4: the cancel-status target must be a
        // real order status — an arbitrary id would send every queue
        // "Cancel order" into a silent fallback. FINAL-review fix (F5):
        // and a cancelled-STAGE one, matching the select's own scope -
        // any other stage would cancel the order into a tab that denies
        // it was cancelled.
        $request->validate([
            'settings.cancel_order_status_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists(PlacementMap::TABLE, 'order_status_id')->where('placement', 'cancelled'),
            ],
            // fulfillment-placement Task 3: each target must not only exist,
            // it must carry the placement that makes it do its job - a
            // booked status placed anywhere but To Handover would book
            // orders straight out of the queue's working tabs, and an
            // unbooked status placed anywhere but To Pack would leave a
            // cancelled booking unbookable.
            'settings.' . FulfillmentService::BOOKED_STATUS_SETTING => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists(PlacementMap::TABLE, 'order_status_id')
                    ->where('placement', FulfillmentService::BOOKED_PLACEMENT),
            ],
            'settings.' . FulfillmentService::UNBOOKED_STATUS_SETTING => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists(PlacementMap::TABLE, 'order_status_id')
                    ->where('placement', FulfillmentService::UNBOOKED_PLACEMENT),
            ],
        ]);

        $data = array_intersect_key(
            $request->input('settings', []),
            array_flip(self::MODULE_KEYS)
        );

        // "Use default" placeholder submitted (empty -> null via
        // ConvertEmptyStringsToNull): unset the stored override entirely so
        // each reader falls back to its own default (cancelOrder() to the
        // status named "Canceled", FulfillmentService::bookedStatus()/
        // unbookedStatus() to their own resolution chains). Never persist an
        // empty/placeholder value.
        $clearable = [
            'cancel_order_status_id',
            FulfillmentService::BOOKED_STATUS_SETTING,
            FulfillmentService::UNBOOKED_STATUS_SETTING,
        ];
        foreach ($clearable as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === null || $data[$key] === '')) {
                Setting::where('group', 'courier')->where('key', $key)->delete();
                unset($data[$key]);
            }
        }

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
