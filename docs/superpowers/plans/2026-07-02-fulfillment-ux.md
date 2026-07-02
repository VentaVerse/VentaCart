# Fulfillment UX & Features — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the QuadX courier MVP into a usable admin feature: a settings home, a gated Sales page, an admin-friendly booking UX with a status stepper, and automatic core-order-status updates driven by courier status.

**Architecture:** All work lives in the `courier-fulfillment` extension (Venta owns courier; the ERP triggers booking via the existing API). New state is settings rows only — no migration. Two independent status mappings: a fixed in-code *visual phase* (drives the stepper) and an admin-configured *QuadX→Venta* map (drives the Sales order status). Spec: `docs/superpowers/specs/2026-07-02-fulfillment-ux-design.md`.

**Tech Stack:** Laravel 12, PHP 8.5, Blade + Alpine.js + Tailwind, MySQL. Tests: PHPUnit (unit-level, pure functions — NO DB-touching tests; the sandbox DB is live data).

## Global Constraints

- Extension-owned: no core files change except `resources/views/layouts/admin.blade.php` (menu) — that's the one allowed core touch, matching the existing `$fulfillmentInstalled` pattern.
- Settings live under `group='courier'`. Only keys in `CourierSettingsController::ALLOWED_KEYS` are writable via the form.
- `courier_*` order columns are NOT in core `Order::$fillable` — always `forceFill()` them; `courier_meta` is json-encoded manually (no cast).
- `fulfillment_enabled` defaults **off** (absent setting = off).
- Never run DB-refreshing tests — unit-test pure logic only (pass arrays/mocks, not real records).
- After Blade/CSS/JS changes: `php artisan view:clear` (npm build only if editing compiled CSS/JS — these are extension Blade views, no Vite entry, so view:clear suffices).
- Commits: author Paolo, no Co-Authored-By line.

---

### Task 1: Visual status phase (drives the stepper)

A fixed, courier-provided mapping from a courier status string to a stepper phase. Pure logic — no DB.

**Files:**
- Modify: `extensions/courier-fulfillment/src/Contracts/CourierFulfillmentInterface.php`
- Modify: `extensions/courier-fulfillment/src/Couriers/QuadX/QuadXFulfillment.php`
- Test: `extensions/courier-fulfillment/tests/StatusPhaseTest.php`

**Interfaces:**
- Produces: `CourierFulfillmentInterface::statusPhase(?string $status): array` → `['step' => int (0..3), 'failed' => bool]`. Steps: 0=Pending, 1=Booked, 2=Shipped, 3=Delivered. `failed=true` = fork (render current node red). Null/empty/unknown status → `['step'=>1,'failed'=>false]` when called for a booked order (the page decides step 0 when there's no tracking number).

- [ ] **Step 1: Write the failing test**

Create `extensions/courier-fulfillment/tests/StatusPhaseTest.php`:
```php
<?php
namespace Extensions\CourierFulfillment\Tests;

use Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment;
use PHPUnit\Framework\TestCase;

class StatusPhaseTest extends TestCase
{
    private function phase(string $s): array
    {
        return (new QuadXFulfillment())->statusPhase($s);
    }

    public function test_booked_states_are_step_1(): void
    {
        $this->assertSame(['step' => 1, 'failed' => false], $this->phase('for_pickup'));
        $this->assertSame(['step' => 1, 'failed' => false], $this->phase('confirmed'));
    }

    public function test_in_transit_states_are_step_2(): void
    {
        $this->assertSame(['step' => 2, 'failed' => false], $this->phase('picked_up'));
        $this->assertSame(['step' => 2, 'failed' => false], $this->phase('out_for_delivery'));
    }

    public function test_delivered_is_step_3(): void
    {
        $this->assertSame(['step' => 3, 'failed' => false], $this->phase('delivered'));
    }

    public function test_failures_set_fork(): void
    {
        $this->assertTrue($this->phase('failed_pickup')['failed']);
        $this->assertTrue($this->phase('failed_delivery')['failed']);
        $this->assertTrue($this->phase('returned')['failed']);
        $this->assertTrue($this->phase('canceled')['failed']);
    }

    public function test_unknown_defaults_to_booked(): void
    {
        $this->assertSame(['step' => 1, 'failed' => false], $this->phase('some_hub_noise'));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `./vendor/bin/phpunit extensions/courier-fulfillment/tests/StatusPhaseTest.php`
Expected: FAIL — `Call to undefined method ...statusPhase()`.

- [ ] **Step 3: Add the interface method**

In `CourierFulfillmentInterface.php`, after `terminalStatuses(): array;` add:
```php
    /**
     * Coarse visual phase for the fulfillment stepper.
     * @return array{step:int, failed:bool}  step: 0 Pending,1 Booked,2 Shipped,3 Delivered
     */
    public function statusPhase(?string $status): array;
```

- [ ] **Step 4: Implement in QuadXFulfillment**

Add to `QuadXFulfillment.php`:
```php
    public function statusPhase(?string $status): array
    {
        $s = (string) $status;

        $failed = ['failed_pickup','failed_pickup_attempt','failed_delivery','for_reattempt',
                   'returned','return_in_transit','out_for_return','for_return','canceled',
                   'rejected','lost','damaged','disposed'];
        if (in_array($s, $failed, true)) {
            // Keep the node where it broke: pickup failures at step 1, else step 2.
            $atPickup = str_contains($s, 'pickup') || $s === 'canceled' || $s === 'rejected';
            return ['step' => $atPickup ? 1 : 2, 'failed' => true];
        }

        if ($s === 'delivered') {
            return ['step' => 3, 'failed' => false];
        }

        $shipped = ['picked_up','in_transit','out_for_delivery','for_delivery',
                    'in_transit_to_delivery_area','received_at_delivery_hub',
                    'received_at_delivery_area','at_sorting_center','at_3pl_facility',
                    'received_at_pickup_hub','claimed','confirmed_transit'];
        if (in_array($s, $shipped, true)) {
            return ['step' => 2, 'failed' => false];
        }

        // Everything else that means "booked, awaiting pickup" -> step 1.
        return ['step' => 1, 'failed' => false];
    }
```

- [ ] **Step 5: Run test, verify PASS**

Run: `./vendor/bin/phpunit extensions/courier-fulfillment/tests/StatusPhaseTest.php`
Expected: PASS (all 5).

- [ ] **Step 6: Commit**

```bash
git add extensions/courier-fulfillment/src/Contracts/CourierFulfillmentInterface.php \
        extensions/courier-fulfillment/src/Couriers/QuadX/QuadXFulfillment.php \
        extensions/courier-fulfillment/tests/StatusPhaseTest.php
git commit -m "feat(courier): status phase mapping for the fulfillment stepper"
```

---

### Task 2: Status-map resolution + settings persistence

The pure resolver (QuadX status + map → target Venta status/notify) and the settings allowlist additions.

**Files:**
- Create: `extensions/courier-fulfillment/src/StatusMapper.php`
- Modify: `extensions/courier-fulfillment/src/Http/Controllers/CourierSettingsController.php`
- Test: `extensions/courier-fulfillment/tests/StatusMapperTest.php`

**Interfaces:**
- Produces: `StatusMapper::resolve(array $map, ?string $quadxStatus): ?array` → `['order_status_id'=>int,'notify'=>bool]` or `null` if unmapped. `$map` shape: `['<quadx_status>' => ['order_status_id'=>int,'notify'=>bool], ...]`.
- Produces: `StatusMapper::apply(Order $order, ?string $quadxStatus): bool` — resolves against the stored `courier.quadx_status_map` setting; if mapped and the target differs from the order's current `order_status_id`, updates it + writes an `OrderHistory` row (with the resolved `notify`). Returns true if it changed the core status. (DB method — exercised via manual verification, not unit test.)
- `CourierSettingsController::ALLOWED_KEYS` gains `fulfillment_enabled` and `quadx_status_map`.

- [ ] **Step 1: Write the failing test (pure resolver only)**

Create `extensions/courier-fulfillment/tests/StatusMapperTest.php`:
```php
<?php
namespace Extensions\CourierFulfillment\Tests;

use Extensions\CourierFulfillment\StatusMapper;
use PHPUnit\Framework\TestCase;

class StatusMapperTest extends TestCase
{
    private array $map = [
        'delivered'  => ['order_status_id' => 26, 'notify' => true],
        'picked_up'  => ['order_status_id' => 3,  'notify' => false],
    ];

    public function test_mapped_status_resolves(): void
    {
        $this->assertSame(['order_status_id' => 3, 'notify' => false],
            StatusMapper::resolve($this->map, 'picked_up'));
        $this->assertSame(['order_status_id' => 26, 'notify' => true],
            StatusMapper::resolve($this->map, 'delivered'));
    }

    public function test_unmapped_returns_null(): void
    {
        $this->assertNull(StatusMapper::resolve($this->map, 'in_transit'));
        $this->assertNull(StatusMapper::resolve($this->map, null));
        $this->assertNull(StatusMapper::resolve([], 'delivered'));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `./vendor/bin/phpunit extensions/courier-fulfillment/tests/StatusMapperTest.php`
Expected: FAIL — class `StatusMapper` not found.

- [ ] **Step 3: Create StatusMapper**

`extensions/courier-fulfillment/src/StatusMapper.php`:
```php
<?php
namespace Extensions\CourierFulfillment;

use App\Models\OrderHistory;
use App\Models\Setting;

/**
 * Applies the admin-configured QuadX->Venta status map: when a courier status
 * arrives, if it's mapped, move the core order status + record history.
 * resolve() is pure (testable); apply() does the DB write.
 */
class StatusMapper
{
    public static function resolve(array $map, ?string $quadxStatus): ?array
    {
        if ($quadxStatus === null || !isset($map[$quadxStatus])) {
            return null;
        }
        $row = $map[$quadxStatus];
        if (empty($row['order_status_id'])) {
            return null;
        }
        return [
            'order_status_id' => (int) $row['order_status_id'],
            'notify'          => (bool) ($row['notify'] ?? false),
        ];
    }

    public static function currentMap(): array
    {
        $raw = Setting::where('group', 'courier')->where('key', 'quadx_status_map')->value('value');
        $decoded = $raw ? json_decode($raw, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    /** @return bool true if the core order status changed */
    public static function apply(\App\Models\Order $order, ?string $quadxStatus): bool
    {
        $target = self::resolve(self::currentMap(), $quadxStatus);
        if (!$target || (int) $order->order_status_id === $target['order_status_id']) {
            return false;
        }

        $order->update(['order_status_id' => $target['order_status_id']]);
        OrderHistory::create([
            'order_id'         => $order->id,
            'order_status_id'  => $target['order_status_id'],
            'comment'          => 'Courier status: ' . $quadxStatus,
            'notify_customer'  => $target['notify'],
        ]);
        return true;
    }
}
```

- [ ] **Step 4: Run test, verify PASS**

Run: `./vendor/bin/phpunit extensions/courier-fulfillment/tests/StatusMapperTest.php`
Expected: PASS.

- [ ] **Step 5: Add settings keys to the allowlist**

In `CourierSettingsController.php`, add to `ALLOWED_KEYS` (after `'quadx_default_parcel',`):
```php
        'fulfillment_enabled',
        'quadx_status_map',
```
The existing `update()` already json-encodes array values and stores them, so `quadx_status_map` (posted as an array) persists as JSON automatically.

- [ ] **Step 6: Commit**

```bash
git add extensions/courier-fulfillment/src/StatusMapper.php \
        extensions/courier-fulfillment/src/Http/Controllers/CourierSettingsController.php \
        extensions/courier-fulfillment/tests/StatusMapperTest.php
git commit -m "feat(courier): QuadX->Venta status map resolver + settings persistence"
```

---

### Task 3: Menu split + gating

Extensions → Fulfillment points to **settings**; add Sales → Order Fulfillment, gated on `fulfillment_enabled`.

**Files:**
- Modify: `extensions/courier-fulfillment/src/CourierFulfillmentServiceProvider.php`
- Modify: `resources/views/layouts/admin.blade.php:57` (Sales group) and `:81` (Extensions fulfillment link)

**Interfaces:**
- Produces: view composer sets `$fulfillmentInstalled = true` and `$fulfillmentEnabled = (bool)` (from `courier.fulfillment_enabled`) on `layouts.admin`.

- [ ] **Step 1: Provide the enabled flag from the service provider**

In `CourierFulfillmentServiceProvider.php`, replace the existing `View::composer('layouts.admin', ...)` block with:
```php
        View::composer('layouts.admin', function ($view) {
            $enabled = \App\Models\Setting::where('group', 'courier')
                ->where('key', 'fulfillment_enabled')->value('value') === '1';
            $view->with('fulfillmentInstalled', true);
            $view->with('fulfillmentEnabled', $enabled);
        });
```

- [ ] **Step 2: Point the Extensions link at settings**

In `resources/views/layouts/admin.blade.php` line ~81, change the Extensions fulfillment link route from `admin.fulfillment.index` to `admin.fulfillment.settings` and its label stays `'Fulfillment'`:
```php
                                ...(!empty($fulfillmentInstalled) ? [$link('admin.fulfillment.settings', 'Fulfillment', 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25', 'admin.fulfillment.settings*')] : []),
```

- [ ] **Step 3: Add the Sales → Order Fulfillment entry (gated)**

In the Sales group (line ~57): add `'admin.fulfillment.index*'` to the group `patterns` array, and add this child after the existing Sales children (inside the `'children' => [ ... ]`):
```php
                                ...(!empty($fulfillmentEnabled) ? [$link('admin.fulfillment.index', 'Order Fulfillment', 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12', 'admin.fulfillment.index*')] : []),
```

- [ ] **Step 4: Verify gating**

```bash
php artisan view:clear
# enabled OFF (default): Sales has no "Order Fulfillment"
php artisan tinker --execute='\App\Models\Setting::where("group","courier")->where("key","fulfillment_enabled")->delete();'
curl -s -o /dev/null -w "settings page: %{http_code}\n" -L http://127.0.0.1:8001/opensesame/fulfillment/settings
# turn ON:
php artisan tinker --execute='\App\Models\Setting::updateOrCreate(["group"=>"courier","key"=>"fulfillment_enabled"],["value"=>"1"]);'
```
Then load `/opensesame` in the browser (dev account) — confirm "Order Fulfillment" appears under Sales only when enabled, and "Fulfillment" under Extensions opens the settings page.

- [ ] **Step 5: Commit**

```bash
git add extensions/courier-fulfillment/src/CourierFulfillmentServiceProvider.php \
        resources/views/layouts/admin.blade.php
git commit -m "feat(courier): split menu — Extensions>Fulfillment (settings) + gated Sales>Order Fulfillment"
```

---

### Task 4: Test Connection endpoint

**Files:**
- Modify: `extensions/courier-fulfillment/src/Http/Controllers/CourierSettingsController.php`
- Modify: `extensions/courier-fulfillment/routes/web.php`

**Interfaces:**
- Produces: `POST admin.fulfillment.test-connection` → JSON `{ok:bool, message:string}`. Calls the enabled courier's `getStatuses`-backed check via a light client call.

- [ ] **Step 1: Add the controller method**

In `CourierSettingsController.php`:
```php
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
```

- [ ] **Step 2: Add the route**

In `extensions/courier-fulfillment/routes/web.php`, inside the existing `admin/fulfillment` group (`->name('admin.fulfillment.')`), add:
```php
        Route::post('/settings/test-connection', [CourierSettingsController::class, 'testConnection'])->name('test-connection');
```

- [ ] **Step 3: Verify**

Run: `php artisan route:list | grep test-connection` → shows `admin.fulfillment.test-connection`.
```bash
curl -s -X POST http://127.0.0.1:8001/opensesame/fulfillment/settings/test-connection -H "X-Requested-With: XMLHttpRequest" -b <admin-session>
```
(Or click the button after Task 5.) Expected: `{"ok":true,"message":"Connected — ..."}` against staging.

- [ ] **Step 4: Commit**

```bash
git add extensions/courier-fulfillment/src/Http/Controllers/CourierSettingsController.php \
        extensions/courier-fulfillment/routes/web.php
git commit -m "feat(courier): Test Connection endpoint for courier settings"
```

---

### Task 5: Settings page UI (enable toggle · masked API + test · status map)

**Files:**
- Modify: `extensions/courier-fulfillment/src/Http/Controllers/CourierSettingsController.php` (pass view data)
- Modify: `extensions/courier-fulfillment/resources/views/settings.blade.php`

**Interfaces:**
- Consumes: `$settings`, `$masked`, `$webhookUrl` (existing), plus new `$orderStatuses` (id→name), `$quadxStatuses` (list from `getStatuses`, cached/fallback), `$statusMap` (current), `$defaultMap`.

- [ ] **Step 1: Provide view data**

In `CourierSettingsController::index()`, before the `return view(...)`, add:
```php
        $orderStatuses = \App\Models\OrderStatus::orderBy('name')->pluck('name', 'id')->toArray();

        // Lifecycle statuses we let admins map (curated subset).
        $lifecycle = ['for_pickup','picked_up','in_transit','out_for_delivery',
                      'delivered','failed_pickup','failed_delivery','returned','canceled'];
        $client = new \Extensions\CourierFulfillment\Couriers\QuadX\QuadXClient();
        $labels = [];
        if ($client->isConfigured()) {
            $res = $client->getStatuses();
            foreach (($res['ok'] ? $res['body'] : []) as $s) { $labels[$s['name']] = $s['label']; }
        }
        $quadxStatuses = [];
        foreach ($lifecycle as $name) { $quadxStatuses[$name] = $labels[$name] ?? ucwords(str_replace('_',' ',$name)); }

        $statusMap = \Extensions\CourierFulfillment\StatusMapper::currentMap();
```
Add `'orderStatuses', 'quadxStatuses', 'statusMap'` to the `compact(...)`.

- [ ] **Step 2: Add the Enable toggle to the settings form**

At the top of the settings form in `settings.blade.php` (inside the existing `<form method="POST" action="{{ route('admin.fulfillment.settings.update') }}">`), add:
```blade
<div class="flex items-center gap-3 mb-6">
    <input type="hidden" name="settings[fulfillment_enabled]" value="0">
    <input type="checkbox" name="settings[fulfillment_enabled]" value="1"
        {{ ($settings['fulfillment_enabled'] ?? '0') === '1' ? 'checked' : '' }}
        class="rounded border-gray-300 text-indigo-600">
    <span class="text-sm text-gray-700">Enable Fulfillment (shows the Sales &rsaquo; Order Fulfillment page)</span>
</div>
```

- [ ] **Step 3: Add the Test Connection button (near the API fields)**

```blade
<div x-data="{ testing:false, result:null }" class="mt-2">
    <button type="button" @click="testing=true; result=null;
        fetch('{{ route('admin.fulfillment.test-connection') }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
        .then(r=>r.json()).then(d=>result=d).finally(()=>testing=false)"
        class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
        <span x-show="!testing">Test Connection</span><span x-show="testing">Testing…</span>
    </button>
    <span x-show="result" x-text="result?.message" :class="result?.ok ? 'text-emerald-600' : 'text-red-600'" class="ml-2 text-sm"></span>
</div>
```

- [ ] **Step 4: Add the status-mapping table**

```blade
<div class="mt-8">
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Order status mapping</h3>
    <p class="text-xs text-gray-500 mb-3">When QuadX reports a status, set the Venta order status. Leave blank to only track it on the fulfillment page.</p>
    <table class="w-full text-sm">
        <thead><tr class="text-[11px] uppercase text-gray-400">
            <th class="text-left py-1">QuadX status</th><th class="text-left py-1">Venta order status</th><th class="text-left py-1">Notify customer</th>
        </tr></thead>
        <tbody>
        @foreach ($quadxStatuses as $key => $label)
            <tr class="border-t border-gray-100">
                <td class="py-2">{{ $label }}</td>
                <td class="py-2">
                    <select name="settings[quadx_status_map][{{ $key }}][order_status_id]" class="rounded-md border-gray-300 text-sm">
                        <option value="">— none —</option>
                        @foreach ($orderStatuses as $id => $name)
                            <option value="{{ $id }}" {{ (string)($statusMap[$key]['order_status_id'] ?? '') === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="py-2">
                    <input type="hidden" name="settings[quadx_status_map][{{ $key }}][notify]" value="0">
                    <input type="checkbox" name="settings[quadx_status_map][{{ $key }}][notify]" value="1"
                        {{ !empty($statusMap[$key]['notify']) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
```
(The nested `settings[quadx_status_map][...]` array posts as a PHP array; `update()` json-encodes it into `quadx_status_map`. Rows left as `order_status_id=""` are harmless — `StatusMapper::resolve` treats empty as unmapped.)

- [ ] **Step 5: Verify in browser**

`php artisan view:clear`, then open `/opensesame/fulfillment/settings` (dev account): toggle enable, click Test Connection (expect green "Connected"), set a couple mappings (e.g. delivered→Delivered w/ notify, picked_up→Shipped), Save. Reload → values persist and masked API fields still show `••••`.

- [ ] **Step 6: Commit**

```bash
git add extensions/courier-fulfillment/src/Http/Controllers/CourierSettingsController.php \
        extensions/courier-fulfillment/resources/views/settings.blade.php
git commit -m "feat(courier): settings UI — enable toggle, test connection, status mapping"
```

---

### Task 6: Wire the status engine into poll + webhook

Make status updates apply the map to the core order.

**Files:**
- Modify: `extensions/courier-fulfillment/src/Console/Commands/PollCourierStatus.php`
- Modify: `extensions/courier-fulfillment/src/Http/Controllers/CourierWebhookController.php`

**Interfaces:**
- Consumes: `StatusMapper::apply(Order, ?string)` from Task 2.

- [ ] **Step 1: Poll — apply mapping when status changes**

In `PollCourierStatus.php`, inside the `if ($status && $status !== $old)` block, AFTER the `forceFill(...)->save()`, add:
```php
                    \Extensions\CourierFulfillment\StatusMapper::apply($order, $status);
```

- [ ] **Step 2: Webhook — apply mapping in applyEvent**

In `CourierWebhookController::applyEvent()`, after `$order->forceFill(['courier_status' => $event['status']])->save();` add:
```php
        \Extensions\CourierFulfillment\StatusMapper::apply($order, $event['status'] ?? null);
```

- [ ] **Step 3: Manual verification (safe, one order)**

```bash
# Configure a mapping first (delivered -> 26 Delivered):
php artisan tinker --execute='\App\Models\Setting::updateOrCreate(["group"=>"courier","key"=>"quadx_status_map"],["value"=>json_encode(["delivered"=>["order_status_id"=>26,"notify"=>false]])]);'
# Attach a live delivered tracking number to a throwaway/test order, then poll:
php artisan tinker --execute='$o=\App\Models\Order::factory ? null : \App\Models\Order::first(); /* pick a test order */'
php artisan courier:poll-status
# Confirm: that order's courier_status = delivered AND order_status_id = 26, and an order_histories row exists.
```
(Use a dedicated test order id; this writes to a real row — revert on the sandbox after.)

- [ ] **Step 4: Commit**

```bash
git add extensions/courier-fulfillment/src/Console/Commands/PollCourierStatus.php \
        extensions/courier-fulfillment/src/Http/Controllers/CourierWebhookController.php
git commit -m "feat(courier): apply status map to core order status on poll + webhook"
```

---

### Task 7: Order Fulfillment page redesign (list + mini-stepper + drawer)

**Files:**
- Modify: `extensions/courier-fulfillment/src/Http/Controllers/FulfillmentController.php` (index data)
- Modify: `extensions/courier-fulfillment/resources/views/index.blade.php`

**Interfaces:**
- Consumes: `CourierFulfillmentInterface::statusPhase()` (Task 1).

- [ ] **Step 1: Controller — richer index data + filters**

In `FulfillmentController::index()`, extend the filter set and eager-load status; pass the courier for phase rendering. Replace the filter block so filters are: `to_book` (no tracking), `in_transit` (tracking, status not in terminalStatuses+delivered), `delivered` (courier_status delivered), `all`. Eager load `status:id,name`. Pass `$couriers` (already) — the view uses the first enabled courier's `statusPhase()`.
```php
        $filter = $request->query('filter', 'to_book');
        $primary = $couriers[0] ?? null;
        $query = Order::query()->with('status:id,name')->latest();
        if ($filter === 'to_book')      $query->whereNull('courier_tracking_number');
        elseif ($filter === 'in_transit') $query->whereNotNull('courier_tracking_number')->where('courier_status','!=','delivered');
        elseif ($filter === 'delivered')  $query->where('courier_status','delivered');
        if ($s = $request->query('q')) {
            $query->where(fn($x)=>$x->where('id',$s)
                ->orWhere('courier_tracking_number','like',"%$s%")
                ->orWhereRaw("CONCAT(shipping_first_name,' ',shipping_last_name) like ?", ["%$s%"]));
        }
        $orders = $query->paginate(25)->withQueryString();
```
Add `'primary'` to compact and change the filter tabs data to `['to_book'=>'To Book','in_transit'=>'In Transit','delivered'=>'Delivered','all'=>'All']`.

- [ ] **Step 2: List markup — new columns + mini-stepper, no payment**

In `index.blade.php`: replace the `<thead>`/`<tbody>` columns with: Order · Customer · **Order Status** · Items · **Progress** (mini-stepper) · Tracking/Status · Action. Remove the Payment `<th>`/`<td>`. For each row render a compact stepper (4 dots) from the phase:
```blade
@php
    $phase = $order->courier_tracking_number
        ? $primary?->statusPhase($order->courier_status) : ['step'=>0,'failed'=>false];
@endphp
<td class="px-4 py-3">
    <div class="flex items-center gap-0.5" title="{{ $order->courier_status ?: 'not booked' }}">
        @for ($i = 0; $i < 4; $i++)
            <span class="w-2.5 h-2.5 rounded-full {{ $i <= $phase['step'] ? ($phase['failed'] && $i===$phase['step'] ? 'bg-red-500' : 'bg-emerald-500') : 'bg-gray-200' }}"></span>
            @if ($i < 3)<span class="w-4 h-px {{ $i < $phase['step'] ? 'bg-emerald-500' : 'bg-gray-200' }}"></span>@endif
        @endfor
    </div>
</td>
```
Order Status column: `<td>...<span class="badge">{{ $order->status?->name ?? '—' }}</span></td>`. Items column: `{{ $order->products_count ?? $order->products()->count() }} item(s)` (add `withCount('products')` to the query in Step 1 for efficiency). Keep the existing Book button / tracking display in the Action + Tracking columns. **Design-risk fallback:** if rows crowd at ~1280px, drop the Progress column to a single status pill and keep the full stepper only in the drawer (Step 3).

- [ ] **Step 3: Detail drawer (click a row)**

Add an Alpine drawer (right-side panel) opened by clicking a row, showing the **big** stepper (labelled Pending/Booked/Shipped/Delivered), order status, items, and actions. Reuse the existing booking `<form>` for unbooked orders (move it into the drawer); for booked orders show Print AWB / Refresh / Cancel + tracking link (`https://…` from courier_meta if present) + estimated delivery date (`courier_meta.booked_response.estimated_delivery_date`). Big stepper markup mirrors Step 2 but larger with labels under each node.

- [ ] **Step 4: Verify in browser**

`php artisan view:clear`; open `/opensesame/fulfillment` (enable must be ON): confirm columns (no payment), mini-steppers reflect status (e.g. a delivered order = all green), filters + search work, clicking a row opens the drawer, an unbooked order books from the drawer, a booked order shows tracking/print/refresh.

- [ ] **Step 5: Commit**

```bash
git add extensions/courier-fulfillment/src/Http/Controllers/FulfillmentController.php \
        extensions/courier-fulfillment/resources/views/index.blade.php
git commit -m "feat(courier): redesign Order Fulfillment page — stepper list + detail drawer"
```

---

## Self-Review

- **Spec coverage:** A menu split → Task 3. B enable toggle → T2/T3/T5; masked API + test → T4/T5; status map → T2/T5. C list/stepper/drawer/filters/no-payment/extras → T7. D status engine (both mappings) → T1 (visual) + T2/T6 (core) + notify → T2/T5/T6. All covered.
- **Placeholder scan:** Task 6 Step 3 leaves the exact test-order id to the operator (a manual verification, not code) — acceptable; all code steps are complete.
- **Type consistency:** `statusPhase(): ['step','failed']` used identically in T1 and T7. `StatusMapper::resolve/apply/currentMap` signatures consistent across T2/T5/T6. Settings keys `fulfillment_enabled`, `quadx_status_map` consistent T2/T3/T5.
