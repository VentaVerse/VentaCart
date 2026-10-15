# One doorway for order status changes, pass 1

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every order status change, whoever makes it, tells the customer according to the store's own ticks: email from a tick on the core status, SMS from the UniSMS extension's own list.

**Architecture:** `OrderStatusChanger::apply()` becomes the single doorway. It writes history, moves stock, and then announces the change to `OrderNotificationService::announce()`, which resolves who should be told and queues one job per message. Callers stop deciding for themselves: they pass a `NotifyChoice` that either defers to the store's ticks or states an explicit choice for that one change. The four paths that wrote `order_status_id` by hand are routed through the doorway.

**Tech Stack:** PHP 8.2, Laravel 12, MySQL/MariaDB in production and SQLite in tests, database queue, PHPUnit via `php artisan test`.

**Spec:** `docs/superpowers/specs/2026-09-25-order-status-notifications-design.md`

## Global Constraints

- The SMS text never contains a URL. Philippine telcos drop messages carrying links.
- Default SMS template, seeded exactly: `{store_name}: Order #{order_id} is now {order_status}`.
- No extension may depend on another extension. Core only.
- No em dashes anywhere: code comments, UI strings, commit messages.
- Admin UI uses the existing `x-admin.*` components and `v-` CSS vocabulary. No instructional paragraphs; name the control well and use its help slot at most.
- A channel never throws. A gateway being down must not roll back a status change.
- Tests run on SQLite in memory with `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array` (set in `phpunit.xml`), so queued jobs run inline and mail is captured.
- Commits are conventional and carry no co-author line.

## Review Focus

1. **Same status applied twice** (ERP sets Shipped, the courier poll maps to Shipped ten minutes later): the customer must be told once. Test lives in Task 4.
2. **A gateway that fails or throws** (UniSMS down, refused key): the order still moves, the history records the failure, nothing else changes. Test lives in Task 3.
3. **An order with no email or no dialable phone**: nothing is sent, the reason is recorded, no exception surfaces to the caller. Test lives in Task 3.
4. **A placeholder with nothing behind it** (no tracking number on the order): the message prints an empty string, never the literal `{tracking_number}`. Test lives in Task 10.
5. **A merchant typing a link into the template**: the URL is stripped before sending, so the template cannot break the store's own deliverability. Test lives in Task 10.

---

### Task 1: A status says whether it emails the customer

**Files:**
- Create: `database/migrations/2026_09_25_100001_add_emails_customer_to_order_statuses.php`
- Modify: `app/Models/OrderStatus.php` (fillable + casts)
- Modify: `app/Http/Controllers/Admin/Settings/OrderStatusController.php` (validation + save)
- Modify: `resources/views/admin/settings/order-statuses/index.blade.php` (a tick per row)
- Test: `tests/Feature/Notifications/StatusEmailTickTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `OrderStatus::$emails_customer` (bool), settable through the Order Statuses screen.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Does this status tell the customer" is a fact about the status, kept
 * beside the other facts a status already carries.
 */
class StatusEmailTickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());
    }

    public function test_a_status_starts_silent(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);

        $this->assertFalse($status->emails_customer, 'A new status must not email anyone until the store says so.');
    }

    public function test_the_tick_is_saved_from_the_order_statuses_screen(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Shipped',
            'sort_order' => 2,
            'emails_customer' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($status->fresh()->emails_customer);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Notifications/StatusEmailTickTest.php`
Expected: FAIL with "Undefined property" or a column-not-found error for `emails_customer`.

- [ ] **Step 3: Write minimal implementation**

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            // Named for the channel it governs. SMS keeps its own list in the
            // UniSMS extension, because SMS costs money per message.
            $table->boolean('emails_customer')->default(false)->after('customer_can_cancel');
        });
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('emails_customer');
        });
    }
};
```

In `app/Models/OrderStatus.php`, add `'emails_customer'` to `$fillable` and `'emails_customer' => 'boolean'` to the `casts()` array.

In `OrderStatusController`, add to the validated rules `'emails_customer' => ['nullable', 'boolean']` and before saving set `$validated['emails_customer'] = $request->boolean('emails_customer');` in both the store and update methods, matching how the controller already handles its other booleans.

In the view, add a column to the statuses table, in the same shape as the existing boolean cells:

```blade
<td class="v-cell-mid">
    <label class="v-check">
        <input type="hidden" name="emails_customer" value="0">
        <input type="checkbox" name="emails_customer" value="1" @checked($status->emails_customer)
               aria-label="Email the customer when an order reaches {{ $status->name }}">
    </label>
</td>
```

Add the matching `<th>Emails customer</th>` to the header row.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Notifications/StatusEmailTickTest.php`
Expected: PASS, 2 tests.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_25_100001_add_emails_customer_to_order_statuses.php app/Models/OrderStatus.php app/Http/Controllers/Admin/Settings/OrderStatusController.php resources/views/admin/settings/order-statuses/index.blade.php tests/Feature/Notifications/StatusEmailTickTest.php
git commit -m "feat(orders): a status says whether it emails the customer"
```

---

### Task 2: The caller's choice, as a value

**Files:**
- Create: `app/Support/NotifyChoice.php`
- Test: `tests/Feature/Notifications/NotifyChoiceTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Support\NotifyChoice` with readonly `?bool $email` and `?array $channels` (null on either half means "follow the store's ticks"), and the factories `follow()`, `explicit(bool $email, array $channels)`, `emailAlways()`, `emailHandledElsewhere()`, `silent()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notifications;

use App\Support\NotifyChoice;
use Tests\TestCase;

class NotifyChoiceTest extends TestCase
{
    public function test_follow_defers_both_halves_to_the_store(): void
    {
        $choice = NotifyChoice::follow();

        $this->assertNull($choice->email);
        $this->assertNull($choice->channels);
    }

    public function test_an_explicit_choice_states_both_halves(): void
    {
        $choice = NotifyChoice::explicit(true, ['sms']);

        $this->assertTrue($choice->email);
        $this->assertSame(['sms'], $choice->channels);
    }

    public function test_the_halves_are_independent(): void
    {
        $this->assertTrue(NotifyChoice::emailAlways()->email);
        $this->assertNull(NotifyChoice::emailAlways()->channels, 'SMS still follows the store.');

        $this->assertFalse(NotifyChoice::emailHandledElsewhere()->email);
        $this->assertNull(NotifyChoice::emailHandledElsewhere()->channels);

        $this->assertFalse(NotifyChoice::silent()->email);
        $this->assertSame([], NotifyChoice::silent()->channels);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Notifications/NotifyChoiceTest.php`
Expected: FAIL with "Class App\Support\NotifyChoice not found".

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Support;

/**
 * What the caller of a status change wants said, if anything.
 *
 * Each half is answered separately, because they are separate acts: a store
 * may email without texting. Null means "the store already answered this on
 * the status, or in the channel's own settings", which is the normal case.
 * An explicit value is one person deciding for one order, and it wins.
 */
final class NotifyChoice
{
    /** @param list<string>|null $channels */
    private function __construct(
        public readonly ?bool $email,
        public readonly ?array $channels,
    ) {
    }

    /** The store's ticks decide both halves. */
    public static function follow(): self
    {
        return new self(null, null);
    }

    /**
     * Someone with the order open decided, for this change only.
     *
     * @param list<string> $channels channel keys that were ticked
     */
    public static function explicit(bool $email, array $channels = []): self
    {
        return new self($email, $channels);
    }

    /**
     * Email regardless of the ticks; channels still follow them.
     *
     * For the paths where the email is the point of the act, such as a
     * customer cancelling their own order and being told it worked.
     */
    public static function emailAlways(): self
    {
        return new self(true, null);
    }

    /**
     * No status email, because this path sends its own; channels follow.
     *
     * Checkout and the payment paths already send an order confirmation. A
     * status email on top would tell the same customer the same thing twice
     * in the same second.
     */
    public static function emailHandledElsewhere(): self
    {
        return new self(false, null);
    }

    /** Tell nobody: an internal move the customer has no business hearing. */
    public static function silent(): self
    {
        return new self(false, []);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Notifications/NotifyChoiceTest.php`
Expected: PASS, 3 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support/NotifyChoice.php tests/Feature/Notifications/NotifyChoiceTest.php
git commit -m "feat(orders): a caller says what should be said, or defers to the store"
```

---

### Task 3: The announcement, and one queued job per message

**Files:**
- Create: `app/Jobs/SendOrderStatusNotification.php`
- Create: `database/migrations/2026_09_25_100002_add_notifications_to_order_histories.php`
- Modify: `app/Contracts/OrderNotificationChannel.php` (add `wantsStatus()`)
- Modify: `app/Services/OrderNotificationService.php` (add `announce()`)
- Modify: `app/Models/OrderHistory.php` (fillable + cast)
- Modify: `tests/Feature/Fulfillment/NotificationChannelTest.php` (SpyChannel gains `wantsStatus()`)
- Test: `tests/Feature/Notifications/AnnouncementTest.php`

**Interfaces:**
- Consumes: `NotifyChoice` from Task 2, `OrderStatus::$emails_customer` from Task 1.
- Produces:
  - `OrderNotificationChannel::wantsStatus(?int $orderStatusId): bool`
  - `OrderNotificationService::announce(Order $order, ?OrderStatus $newStatus, ?string $oldStatusName, ?string $comment, NotifyChoice $choice, ?int $historyId = null): array` returning `['email' => bool, 'channels' => list<string>]`, the plan of what was queued.
  - `App\Jobs\SendOrderStatusNotification` with constructor `(int $orderId, string $target, ?string $oldStatus, ?string $newStatus, ?string $comment, ?int $historyId)` where `$target` is `'email'` or a channel key.
  - `order_histories.notifications` (json, nullable): `{"email": {"sent": true, "reason": null}, "sms": {"sent": false, "reason": "No mobile number..."}}`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notifications;

use App\Contracts\OrderNotificationChannel;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Services\OrderNotificationService;
use App\Support\NotifyChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'emails_customer' => true,
        ]);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        Mail::fake();
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->shipped->id,
        ], $overrides));
    }

    public function test_following_the_store_emails_when_the_status_says_so(): void
    {
        $order = $this->order();

        $plan = OrderNotificationService::announce($order, $this->shipped, 'Packing', 'On its way', NotifyChoice::follow());

        $this->assertTrue($plan['email']);
        $this->assertSame(['recorder'], $plan['channels'], 'The channel said it wants this status.');
        $this->assertSame(1, RecordingChannel::$sends);
    }

    public function test_a_channel_that_does_not_want_the_status_is_not_used(): void
    {
        RecordingChannel::$wants = false;
        $order = $this->order();

        $plan = OrderNotificationService::announce($order, $this->shipped, 'Packing', null, NotifyChoice::follow());

        $this->assertSame([], $plan['channels']);
        $this->assertSame(0, RecordingChannel::$sends);
    }

    public function test_an_explicit_choice_overrides_the_store(): void
    {
        $order = $this->order();

        $plan = OrderNotificationService::announce($order, $this->shipped, 'Packing', null, NotifyChoice::explicit(false, []));

        $this->assertFalse($plan['email'], 'The person with the order open unticked it.');
        $this->assertSame([], $plan['channels']);
        $this->assertSame(0, RecordingChannel::$sends);
    }

    /** Review Focus 2: a gateway that throws changes nothing else. */
    public function test_a_channel_that_throws_is_recorded_and_harmless(): void
    {
        RecordingChannel::$throw = true;
        $order = $this->order();
        $history = OrderHistory::create([
            'order_id' => $order->id, 'order_status_id' => $this->shipped->id, 'comment' => null,
        ]);

        OrderNotificationService::announce($order, $this->shipped, 'Packing', null, NotifyChoice::follow(), $history->id);

        $recorded = $history->fresh()->notifications;
        $this->assertFalse($recorded['recorder']['sent']);
        $this->assertStringContainsString('gateway is down', $recorded['recorder']['reason']);
    }

    /** Review Focus 3: nobody to email means nothing sent and a reason kept. */
    public function test_an_order_with_no_email_records_why(): void
    {
        $order = $this->order(['email' => null]);
        $history = OrderHistory::create([
            'order_id' => $order->id, 'order_status_id' => $this->shipped->id, 'comment' => null,
        ]);

        OrderNotificationService::announce($order, $this->shipped, 'Packing', null, NotifyChoice::follow(), $history->id);

        $recorded = $history->fresh()->notifications;
        $this->assertFalse($recorded['email']['sent']);
        $this->assertNotNull($recorded['email']['reason']);
    }
}

class RecordingChannel implements OrderNotificationChannel
{
    public static int $sends = 0;
    public static bool $wants = true;
    public static bool $throw = false;

    public static function reset(): void
    {
        self::$sends = 0;
        self::$wants = true;
        self::$throw = false;
    }

    public function key(): string
    {
        return 'recorder';
    }

    public function label(): string
    {
        return 'Record it';
    }

    public function available(): bool
    {
        return true;
    }

    public function wantsStatus(?int $orderStatusId): bool
    {
        return self::$wants;
    }

    public function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null): array
    {
        if (self::$throw) {
            throw new \RuntimeException('the gateway is down');
        }

        self::$sends++;

        return ['sent' => true, 'reason' => null];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Notifications/AnnouncementTest.php`
Expected: FAIL with "Call to undefined method ...::announce()".

- [ ] **Step 3: Write minimal implementation**

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_histories', function (Blueprint $table) {
            // What each channel did with this change: sent or not, and why
            // not. We record that we sent and that the gateway accepted it.
            // We never claim the handset received it.
            $table->json('notifications')->nullable()->after('notify_customer');
        });
    }

    public function down(): void
    {
        Schema::table('order_histories', function (Blueprint $table) {
            $table->dropColumn('notifications');
        });
    }
};
```

In `app/Models/OrderHistory.php`, add `'notifications'` to `$fillable` and `'notifications' => 'array'` to the casts.

In the contract, add:

```php
    /**
     * Does this channel want to speak about this status?
     *
     * The channel answers from its own settings, so no caller has to know
     * that a channel has settings at all. A channel with no policy of its
     * own answers true and lets the ticked/unticked caller decide.
     */
    public function wantsStatus(?int $orderStatusId): bool;
```

Add `wantsStatus()` returning `self::$wants` to the existing `SpyChannel` in `tests/Feature/Fulfillment/NotificationChannelTest.php` (a public static `$wants = true` beside its other flags), so the existing suite keeps compiling.

In `OrderNotificationService`:

```php
    /**
     * Tell whoever should be told that this order moved.
     *
     * The one place that resolves the question. Email answers from the
     * status's own tick, each channel from its own settings, and an explicit
     * choice from a person with the order open beats both. Nothing is sent
     * here: a job per message is queued, so a slow gateway never holds open
     * a courier's webhook or the ERP's API call.
     *
     * @return array{email: bool, channels: list<string>} what was queued
     */
    public static function announce(
        Order $order,
        ?OrderStatus $newStatus,
        ?string $oldStatusName,
        ?string $comment,
        NotifyChoice $choice,
        ?int $historyId = null,
    ): array {
        $email = $choice->email ?? (bool) ($newStatus?->emails_customer);

        $keys = [];
        foreach (self::channels() as $channel) {
            $wanted = $choice->channels === null
                ? $channel->wantsStatus($newStatus?->id)
                : in_array($channel->key(), $choice->channels, true);

            if ($wanted) {
                $keys[] = $channel->key();
            }
        }

        foreach ($email ? ['email'] : [] as $target) {
            SendOrderStatusNotification::dispatch($order->id, $target, $oldStatusName, $newStatus?->name, $comment, $historyId);
        }

        foreach ($keys as $key) {
            SendOrderStatusNotification::dispatch($order->id, $key, $oldStatusName, $newStatus?->name, $comment, $historyId);
        }

        return ['email' => $email, 'channels' => $keys];
    }
```

Import `App\Models\OrderStatus`, `App\Support\NotifyChoice` and `App\Jobs\SendOrderStatusNotification` at the top of the service.

The job:

```php
<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Services\OrderNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * One message, sent away from the request that caused it.
 *
 * A courier's webhook expects an answer in about three seconds and the ERP
 * waits on its API call: neither can afford to sit through a gateway's
 * timeout. A failed send retries here instead of being lost.
 */
class SendOrderStatusNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
        public string $target,
        public ?string $oldStatus,
        public ?string $newStatus,
        public ?string $comment,
        public ?int $historyId = null,
    ) {
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            return;
        }

        $result = $this->target === 'email'
            ? $this->sendEmail($order)
            : $this->sendChannel($order);

        $this->record($result);
    }

    /** @return array{sent: bool, reason: string|null} */
    private function sendEmail(Order $order): array
    {
        if (! $order->email) {
            return ['sent' => false, 'reason' => 'This order carries no email address.'];
        }

        $sent = OrderNotificationService::statusChanged($order, $this->oldStatus, $this->newStatus, $this->comment);

        return ['sent' => $sent, 'reason' => $sent ? null : 'The mailer refused the message.'];
    }

    /** @return array{sent: bool, reason: string|null} */
    private function sendChannel(Order $order): array
    {
        foreach (OrderNotificationService::channels() as $channel) {
            if ($channel->key() !== $this->target) {
                continue;
            }

            try {
                return $channel->statusChanged($order, $this->oldStatus, $this->newStatus, $this->comment);
            } catch (\Throwable $e) {
                // The contract says a channel never throws. This is the belt
                // for one that forgets: the order already moved.
                Log::error('Notification channel threw', ['channel' => $this->target, 'error' => $e->getMessage()]);

                return ['sent' => false, 'reason' => $e->getMessage()];
            }
        }

        return ['sent' => false, 'reason' => 'That way of telling the customer is no longer installed.'];
    }

    /** @param array{sent: bool, reason: string|null} $result */
    private function record(array $result): void
    {
        if (! $this->historyId) {
            return;
        }

        $history = OrderHistory::find($this->historyId);

        if (! $history) {
            return;
        }

        $history->update([
            'notifications' => ($history->notifications ?? []) + [$this->target => $result],
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Notifications/AnnouncementTest.php tests/Feature/Fulfillment/NotificationChannelTest.php`
Expected: PASS. The existing channel test still passes with `wantsStatus()` added to its SpyChannel.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/SendOrderStatusNotification.php database/migrations/2026_09_25_100002_add_notifications_to_order_histories.php app/Contracts/OrderNotificationChannel.php app/Services/OrderNotificationService.php app/Models/OrderHistory.php tests/Feature/Notifications/AnnouncementTest.php tests/Feature/Fulfillment/NotificationChannelTest.php
git commit -m "feat(orders): one place decides who hears about a status change"
```

---

### Task 4: The doorway announces, and only when something changed

**Files:**
- Modify: `app/Services/OrderStatusChanger.php`
- Test: `tests/Feature/Notifications/DoorwayTest.php`

**Interfaces:**
- Consumes: `NotifyChoice` (Task 2), `OrderNotificationService::announce()` (Task 3).
- Produces: `OrderStatusChanger::apply(Order $order, int $orderStatusId, ?string $comment = null, ?NotifyChoice $notify = null, ?int $createdBy = null): ?OrderStatus`. The fourth argument changes type from `bool` to `?NotifyChoice`; null means `NotifyChoice::follow()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Services\OrderNotificationService;
use App\Services\OrderStatusChanger;
use App\Support\NotifyChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DoorwayTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'emails_customer' => true,
        ]);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        Mail::fake();
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
    }

    public function test_moving_an_order_tells_the_customer_per_the_ticks(): void
    {
        $order = $this->order();

        OrderStatusChanger::apply($order, $this->shipped->id, 'On its way');

        $this->assertSame(1, RecordingChannel::$sends);
        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id);
    }

    /** Review Focus 1: the ERP and the courier poll both landing on Shipped. */
    public function test_applying_the_same_status_again_says_nothing(): void
    {
        $order = $this->order();
        OrderStatusChanger::apply($order, $this->shipped->id, 'On its way');
        RecordingChannel::reset();

        OrderStatusChanger::apply($order->fresh(), $this->shipped->id, 'Courier says picked up');

        $this->assertSame(0, RecordingChannel::$sends, 'The order was already Shipped. The customer was told once.');
    }

    public function test_a_silent_move_tells_nobody(): void
    {
        $order = $this->order();

        OrderStatusChanger::apply($order, $this->shipped->id, 'Internal move', NotifyChoice::silent());

        $this->assertSame(0, RecordingChannel::$sends);
    }

    public function test_the_history_row_records_what_was_decided(): void
    {
        $order = $this->order();

        OrderStatusChanger::apply($order, $this->shipped->id, 'On its way');

        $history = $order->fresh()->histories()->latest('id')->first();
        $this->assertTrue((bool) $history->notify_customer, 'The status emails, so the row says the customer was written to.');
        $this->assertTrue($history->notifications['recorder']['sent']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Notifications/DoorwayTest.php`
Expected: FAIL on the first test, because nothing is announced (0 sends) and on the type of the fourth argument.

- [ ] **Step 3: Write minimal implementation**

Replace the body of `OrderStatusChanger::apply()`:

```php
    public static function apply(Order $order, int $orderStatusId, ?string $comment = null, ?NotifyChoice $notify = null, ?int $createdBy = null): ?OrderStatus
    {
        $choice = $notify ?? NotifyChoice::follow();
        $oldStatusId = (int) $order->order_status_id;
        $oldStatusName = OrderStatus::find($oldStatusId)?->name;
        $newStatus = OrderStatus::find($orderStatusId);

        // Resolved before the row is written, so the row can say what was
        // decided rather than being updated a second time.
        $emails = $choice->email ?? (bool) ($newStatus?->emails_customer);

        $history = $order->histories()->create([
            'order_status_id' => $orderStatusId,
            'comment'         => $comment,
            'notify_customer' => $emails,
            'created_by'      => $createdBy,
        ]);
        $order->update(['order_status_id' => $orderStatusId]);
        // The status relation (if it was loaded) now describes the OLD status.
        $order->unsetRelation('status');

        if ($newStatus) {
            StockService::adjustForStatusChange($order, $newStatus);
        }

        // Nothing moved, so there is nothing to announce. This is what stops
        // the ERP setting Shipped and the courier poll mapping to Shipped ten
        // minutes later from telling the customer twice.
        if ($oldStatusId !== $orderStatusId) {
            OrderNotificationService::announce($order, $newStatus, $oldStatusName, $comment, $choice, $history->id);
        }

        return $newStatus;
    }
```

Import `App\Support\NotifyChoice` and `App\Services\OrderNotificationService`. Update the class docblock: it now announces, and the sentence "this method never sends mail" becomes "this method never sends anything itself: it announces, and the queue sends".

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Notifications/DoorwayTest.php`
Expected: PASS, 4 tests. Other suites will fail to compile until Task 5; that is expected and fixed there.

- [ ] **Step 5: Commit**

```bash
git add app/Services/OrderStatusChanger.php tests/Feature/Notifications/DoorwayTest.php
git commit -m "feat(orders): the one doorway announces every real status change"
```

---

### Task 5: The admin page passes its ticks, and stops sending twice

**Files:**
- Modify: `app/Http/Controllers/Admin/Sales/OrderController.php` (around lines 399-432, and the `addHistory` path around 160-185)
- Modify: `resources/views/admin/sales/orders/show.blade.php` (pre-tick the Notify box)
- Modify: `app/Services/CustomerOrderCancellation.php` (around line 98-107)
- Modify: `extensions/courier-fulfillment/src/FulfillmentService.php` (around lines 1105-1212)
- Test: `tests/Feature/Notifications/AdminOverrideTest.php`

**Interfaces:**
- Consumes: `OrderStatusChanger::apply()` with `NotifyChoice` (Task 4).
- Produces: no new API. Callers stop calling `statusChanged()` / `statusChangedVia()` themselves.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminOverrideTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'emails_customer' => true,
        ]);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        Mail::fake();
        $this->actingAs(User::factory()->create());
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
    }

    public function test_unticking_on_the_page_silences_that_one_change(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.history.store', $order), [
            'order_status_id' => $this->shipped->id,
            'comment' => 'Quietly moved',
            // notify_customer absent: the operator unticked it
        ])->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        $this->assertSame(0, RecordingChannel::$sends);
        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id);
    }

    public function test_ticking_a_channel_on_the_page_sends_it(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.history.store', $order), [
            'order_status_id' => $this->shipped->id,
            'comment' => 'On its way',
            'notify_customer' => '1',
            'channels' => ['recorder'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, RecordingChannel::$sends, 'Exactly once: the page must not send and then announce as well.');
    }
}
```

Note for the implementer: `admin.orders.history.store` is the order page's status-change submit (`OrderController::addHistory`, around line 160), and it does not handle channel ticks today. `OrderController::update` (around line 399) is the second place a status changes. Both get the same treatment, and `addHistory` gains `'channels' => ['array']` in its validation.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Notifications/AdminOverrideTest.php`
Expected: FAIL. The second test reports 2 sends, because the page announces through the doorway and then calls `statusChangedVia()` itself.

- [ ] **Step 3: Write minimal implementation**

In `OrderController`, at both places that change status, build the choice from the request and pass it in, then delete that method's own `OrderNotificationService::statusChanged(...)` and `statusChangedVia(...)` calls:

```php
$newStatus = OrderStatusChanger::apply(
    $order,
    (int) $validated['order_status_id'],
    $validated['comment'] ?? null,
    NotifyChoice::explicit(
        (bool) ($validated['notify_customer'] ?? false),
        $validated['channels'] ?? [],
    ),
    $validated['created_by'] ?? Auth::id(),
);
```

Keep the shipping-email side effect that fires when a tracking number arrives in the same submission; it is a different email and is not part of the status announcement.

In the view, the Notify box defaults from the status the operator is moving to. The form already knows the statuses; render the tick with the target status's flag, so Alpine can flip it as the select changes:

```blade
<input type="checkbox" name="notify_customer" value="1"
       x-bind:checked="emailsByStatus[statusId] ?? false"
       aria-label="Email the customer about this change">
```

with `emailsByStatus` built in the component's `x-data` from `@js($statuses->pluck('emails_customer', 'id'))`.

In `CustomerOrderCancellation`, replace the `true` argument with `NotifyChoice::emailAlways()` and delete the `OrderNotificationService::statusChanged(...)` call that follows it. The customer is always told their own cancellation worked; SMS follows the ticks, which is why Cancelled stays unticked.

In `FulfillmentService::applyBookedStatus()` and the booked-status path around line 1205, pass `NotifyChoice::follow()` (or `NotifyChoice::silent()` where the existing code deliberately passes `notify_customer => false` for an internal move) and delete its own `statusChanged()` and `statusChangedVia()` calls along with the `$channelKeys` computation that fed them.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Notifications tests/Feature/Fulfillment tests/Feature/Sales`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/Sales/OrderController.php resources/views/admin/sales/orders/show.blade.php app/Services/CustomerOrderCancellation.php extensions/courier-fulfillment/src/FulfillmentService.php tests/Feature/Notifications/AdminOverrideTest.php
git commit -m "feat(orders): the page states its choice, the doorway does the telling"
```

---

### Task 6: The ERP endpoint goes through the doorway

**Files:**
- Modify: `extensions/erp-sync/src/Http/Controllers/ErpOrderController.php` (`updateStatus`, around lines 237-265)
- Test: `tests/Feature/Api/ErpStatusNotificationTest.php`

**Interfaces:**
- Consumes: `OrderStatusChanger::apply()` with `NotifyChoice::follow()`.
- Produces: unchanged JSON response shape.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Store;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The path the business uses most. It told nobody and moved no stock.
 */
class ErpStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(ExtensionManager::class)->install('erp-sync');
        app(ExtensionManager::class)->bootEnabled();
        $this->apiKey('erp-test-token');
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2,
            'emails_customer' => true, 'deducts_stock' => true,
        ]);
        Mail::fake();
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
    }

    public function test_an_erp_status_update_emails_the_customer(): void
    {
        $order = $this->order();

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        Mail::assertSentCount(1);
    }

    public function test_an_erp_status_update_moves_stock(): void
    {
        $product = Product::create([
            'store_id' => 1, 'name' => 'Cable', 'slug' => 'cable',
            'price' => 100, 'quantity' => 5, 'track_stock' => true,
        ]);
        $order = $this->order();
        $order->products()->create([
            'product_id' => $product->id, 'name' => 'Cable', 'price' => 100, 'quantity' => 2, 'total' => 200,
        ]);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        $this->assertSame(3, $product->fresh()->quantity, 'Shipped deducts stock, whoever moved the order.');
    }

    public function test_setting_the_status_it_already_has_says_nothing(): void
    {
        $order = $this->order();
        $order->update(['order_status_id' => $this->shipped->id]);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        Mail::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Api/ErpStatusNotificationTest.php`
Expected: FAIL. No mail is sent and the stock stays at 5.

- [ ] **Step 3: Write minimal implementation**

Replace the hand-written status write in `updateStatus()`:

```php
        $newStatus = OrderStatusChanger::apply(
            $order,
            (int) $validated['status_id'],
            $validated['comment'] ?? 'Status updated via ERP API',
            // The store's ticks decide. The ERP deliberately cannot silence a
            // customer: staff should not be able to do that by accident.
            NotifyChoice::follow(),
        );
```

Import `App\Services\OrderStatusChanger` and `App\Support\NotifyChoice`, and drop the now-unused `OrderHistory` import if nothing else in the file uses it. The response block stays as it is; `$order->order_status_name` is read after the change.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Api/ErpStatusNotificationTest.php tests/Feature/Api`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add extensions/erp-sync/src/Http/Controllers/ErpOrderController.php tests/Feature/Api/ErpStatusNotificationTest.php
git commit -m "fix(erp): a status update tells the customer and moves stock"
```

---

### Task 7: The courier fetch goes through the doorway

**Files:**
- Modify: `extensions/courier-fulfillment/src/StatusMapper.php` (`apply()`, lines 44-88)
- Test: `tests/Feature/Fulfillment/CourierFetchNotificationTest.php`

**Interfaces:**
- Consumes: `OrderStatusChanger::apply()` with `NotifyChoice::follow()`.
- Produces: `StatusMapper::apply()` keeps its signature and its `bool` return.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Services\OrderNotificationService;
use Extensions\CourierFulfillment\StatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Notifications\RecordingChannel;
use Tests\TestCase;

/**
 * The QuadX case: the courier says picked up, the order becomes Shipped, and
 * Shipped is ticked to text. The text must go.
 */
class CourierFetchNotificationTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'emails_customer' => true,
        ]);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        Mail::fake();
    }

    public function test_a_fetched_courier_status_texts_the_customer(): void
    {
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
        $order->forceFill(['courier_provider' => 'quadx'])->save();
        // The map is a settings row keyed by the courier's own status, which
        // is what StatusMapper::currentMap() reads.
        Setting::create([
            'group' => 'courier',
            'key' => 'quadx_status_map',
            'value' => json_encode([
                'picked_up' => ['order_status_id' => $this->shipped->id, 'notify' => false],
            ]),
        ]);

        StatusMapper::apply($order, 'picked_up');

        $this->assertSame($this->shipped->id, $order->fresh()->order_status_id);
        $this->assertSame(1, RecordingChannel::$sends);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Fulfillment/CourierFetchNotificationTest.php`
Expected: FAIL with 0 sends. The status moves; nothing is announced.

- [ ] **Step 3: Write minimal implementation**

In `StatusMapper::apply()`, replace the hand-written update, history row and stock call with the doorway, keeping the comment builder:

```php
        $comment = CourierStatusLabel::sentence($order, (string) $courierStatus);

        \App\Services\OrderStatusChanger::apply(
            $order,
            (int) $target['order_status_id'],
            $comment,
            // The store's ticks decide, on the core status. The courier map's
            // own notify flag is no longer consulted; it retires in pass 2.
            \App\Support\NotifyChoice::follow(),
        );

        return true;
```

Delete the local `$order->update(...)`, the `OrderHistory::create(...)`, the `StockService::adjustForStatusChange(...)` call and the `if ($target['notify'])` block, since the doorway now does all four.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Fulfillment`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add extensions/courier-fulfillment/src/StatusMapper.php tests/Feature/Fulfillment/CourierFetchNotificationTest.php
git commit -m "fix(fulfillment): a fetched courier status tells the customer"
```

---

### Task 8: Checkout and the payment paths go through the doorway

**Files:**
- Modify: `app/Services/CheckoutService.php` (`confirmOrder()`, around lines 317-340)
- Modify: `app/Http/Controllers/Storefront/CheckoutController.php` (around lines 264-285)
- Modify: `extensions/payment-hitpay/src/Http/Controllers/HitPayWebhookController.php` (around lines 113-137)
- Test: `tests/Feature/Notifications/CheckoutAndPaymentTest.php`

**Interfaces:**
- Consumes: `OrderStatusChanger::apply()` with `NotifyChoice::emailHandledElsewhere()`.
- Produces: no API change. The order confirmation email these paths already send is untouched.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Services\CheckoutService;
use App\Services\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $pending;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->pending = OrderStatus::create([
            'store_id' => 1, 'name' => 'Pending', 'sort_order' => 1,
            'is_default' => true, 'emails_customer' => true,
        ]);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        Mail::fake();
    }

    public function test_a_placed_order_texts_when_its_first_status_is_ticked(): void
    {
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => null,
        ]);

        app(CheckoutService::class)->confirmOrder($order);

        $this->assertSame(1, RecordingChannel::$sends, 'Order placed is the order reaching its first status.');
    }

    public function test_the_status_email_does_not_duplicate_the_confirmation(): void
    {
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => null,
        ]);

        app(CheckoutService::class)->confirmOrder($order);

        // confirmOrder sends its own order confirmation; the status change
        // must not send a second email saying the same thing.
        $this->assertLessThanOrEqual(1, count(Mail::sent()),
            'The customer hears once about an order being placed.');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Notifications/CheckoutAndPaymentTest.php`
Expected: FAIL on the first test with 0 sends.

- [ ] **Step 3: Write minimal implementation**

In `CheckoutService::confirmOrder()`, replace the update, history row and stock call:

```php
        if ($status) {
            OrderStatusChanger::apply(
                $order,
                $status->id,
                'Order confirmed.',
                // The confirmation email is this method's own; a status email
                // would say the same thing twice in the same second. Channels
                // still follow the store's ticks, so a store that texts on
                // its first status does.
                NotifyChoice::emailHandledElsewhere(),
            );
        }
```

In `CheckoutController` (the HitPay return path) and in `HitPayWebhookController`, replace each `$order->update(['order_status_id' => ...])` plus its `OrderHistory::create(...)` and `StockService::adjustForStatusChange(...)` with the same call, keeping each path's existing comment text ("Payment confirmed via HitPay (return URL)." and "Payment confirmed via HitPay."), and keeping the confirmation and admin emails that follow. Both already guard against re-applying the status they hold, and the doorway guards again.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Notifications tests/Feature/Payments tests/Feature/Storefront`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CheckoutService.php app/Http/Controllers/Storefront/CheckoutController.php extensions/payment-hitpay/src/Http/Controllers/HitPayWebhookController.php tests/Feature/Notifications/CheckoutAndPaymentTest.php
git commit -m "feat(checkout): placing and paying for an order are status changes like any other"
```

---

### Task 9: UniSMS holds its own list of statuses

**Files:**
- Create: `extensions/sms-unisms/src/SmsStatusMap.php`
- Modify: `extensions/sms-unisms/src/SmsChannel.php` (add `wantsStatus()`)
- Modify: `extensions/sms-unisms/src/Http/Controllers/UniSmsController.php` (save the ticks)
- Modify: `extensions/sms-unisms/resources/views/index.blade.php` (a panel of statuses)
- Create: `database/migrations/2026_09_25_100003_carry_notify_lists_to_their_new_homes.php`
- Test: `tests/Feature/Notifications/SmsStatusMapTest.php`

**Interfaces:**
- Consumes: `OrderNotificationChannel::wantsStatus()` (Task 3).
- Produces: `SmsStatusMap::all(): list<int>`, `SmsStatusMap::texts(?int $statusId): bool`, `SmsStatusMap::put(array $ids): void`, stored as setting group `sms`, key `sms_status_ids`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\OrderStatus;
use App\Models\Store;
use Extensions\SmsUnisms\SmsChannel;
use Extensions\SmsUnisms\SmsStatusMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsStatusMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('sms-unisms');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_nothing_texts_until_a_status_is_ticked(): void
    {
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);

        $this->assertFalse(SmsStatusMap::texts($shipped->id));
        $this->assertFalse((new SmsChannel())->wantsStatus($shipped->id));
    }

    public function test_a_ticked_status_texts(): void
    {
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
        SmsStatusMap::put([$shipped->id]);

        $this->assertTrue(SmsStatusMap::texts($shipped->id));
        $this->assertTrue((new SmsChannel())->wantsStatus($shipped->id));
    }

    public function test_an_unknown_status_never_texts(): void
    {
        $this->assertFalse(SmsStatusMap::texts(null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Notifications/SmsStatusMapTest.php`
Expected: FAIL with "Class Extensions\SmsUnisms\SmsStatusMap not found".

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Extensions\SmsUnisms;

use App\Models\Setting;

/**
 * Which order statuses are worth paying to text about.
 *
 * Kept here rather than in core or in fulfillment because a text costs money
 * per message: the list of statuses worth that belongs beside the account
 * that pays for them, and it must keep working whoever moved the order.
 */
class SmsStatusMap
{
    public const KEY = 'sms_status_ids';

    private const GROUP = 'sms';

    /** @return list<int> */
    public static function all(): array
    {
        $raw = Setting::where('group', self::GROUP)->where('key', self::KEY)->value('value');

        if ($raw === null || $raw === '') {
            return [];
        }

        $ids = json_decode((string) $raw, true);

        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    public static function texts(?int $statusId): bool
    {
        return $statusId !== null && in_array((int) $statusId, self::all(), true);
    }

    /** @param list<int> $ids */
    public static function put(array $ids): void
    {
        Setting::updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode(array_values(array_unique(array_map('intval', $ids))))]
        );
    }
}
```

In `SmsChannel`, add:

```php
    /** The store's own list, kept in this extension's settings. */
    public function wantsStatus(?int $orderStatusId): bool
    {
        return SmsStatusMap::texts($orderStatusId);
    }
```

In `UniSmsController::index()`, pass the store's statuses and the current ticks to the view; in its save action accept `status_ids` as an array of integers and call `SmsStatusMap::put()`. In the view, add a panel listing each status with a checkbox, following the existing panels' markup.

The migration carries the two lists that exist today into their new homes, so nothing has to be re-ticked and no store silently goes quiet:

```php
<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasTable('order_statuses')) {
            return;
        }

        // Email: the fulfillment extension's list becomes a tick on each
        // status, where every path can read it.
        $emailIds = $this->read('notify_status_ids');
        if ($emailIds !== []) {
            DB::table('order_statuses')->whereIn('id', $emailIds)->update(['emails_customer' => true]);
        }

        // SMS: the same list, per channel, becomes the UniSMS extension's own.
        $smsIds = $this->read('notify_status_ids_sms');
        if ($smsIds !== [] && Setting::where('group', 'sms')->where('key', 'sms_status_ids')->doesntExist()) {
            Setting::create([
                'group' => 'sms',
                'key' => 'sms_status_ids',
                'value' => json_encode($smsIds),
            ]);
        }
    }

    public function down(): void
    {
        // Carrying choices forward is not reversible: the old rows are left
        // where they are, so rolling back loses nothing.
    }

    /** @return list<int> */
    private function read(string $key): array
    {
        $raw = DB::table('settings')->where('group', 'courier')->where('key', $key)->value('value');
        $ids = $raw ? json_decode((string) $raw, true) : [];

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Notifications/SmsStatusMapTest.php`
Expected: PASS, 3 tests.

- [ ] **Step 5: Commit**

```bash
git add extensions/sms-unisms/src/SmsStatusMap.php extensions/sms-unisms/src/SmsChannel.php extensions/sms-unisms/src/Http/Controllers/UniSmsController.php extensions/sms-unisms/resources/views/index.blade.php database/migrations/2026_09_25_100003_carry_notify_lists_to_their_new_homes.php tests/Feature/Notifications/SmsStatusMapTest.php
git commit -m "feat(sms): the statuses worth paying to text about live with the account that pays"
```

---

### Task 10: The message: a template, and never a link

**Files:**
- Modify: `extensions/sms-unisms/src/SmsChannel.php` (`message()`, `plain()`)
- Modify: `extensions/sms-unisms/src/UniSms.php` (template storage, URL guard in `send()`)
- Modify: `extensions/sms-unisms/resources/views/index.blade.php` (template field)
- Modify: `extensions/sms-unisms/src/Http/Controllers/UniSmsController.php` (save the template)
- Test: `tests/Feature/Notifications/SmsMessageTest.php`

**Interfaces:**
- Consumes: `SmsStatusMap` (Task 9).
- Produces: `UniSms::KEY_TEMPLATE = 'unisms_template'`, `UniSms::template(): string` (the saved template, or the seeded default), `UniSms::stripUrls(string $text): string`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use Extensions\SmsUnisms\UniSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsMessageTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('sms-unisms');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        config(['app.name' => 'VentaCart']);
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
        $this->order = Order::create([
            'store_id' => 1, 'first_name' => 'June', 'last_name' => 'Sillacos',
            'email' => 'june@example.test', 'phone' => '09171234567',
            'order_status_id' => $status->id,
        ]);
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);
    }

    public function test_the_seeded_template_is_the_owners_sentence(): void
    {
        $this->assertSame('{store_name}: Order #{order_id} is now {order_status}', UniSms::template());
    }

    public function test_the_template_is_filled_in(): void
    {
        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($this->order, 'Shipped', null);

        $this->assertSame('VentaCart: Order #' . $this->order->id . ' is now Shipped', $text);
    }

    /** Review Focus 4: a placeholder with nothing behind it prints nothing. */
    public function test_an_empty_placeholder_prints_nothing(): void
    {
        Setting::updateOrCreate(
            ['group' => 'sms', 'key' => UniSms::KEY_TEMPLATE],
            ['value' => 'Order #{order_id} tracking {tracking_number}']
        );

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($this->order, 'Shipped', null);

        $this->assertSame('Order #' . $this->order->id . ' tracking', trim($text));
        $this->assertStringNotContainsString('{tracking_number}', $text);
    }

    /** Review Focus 5: a link typed into the template never reaches a handset. */
    public function test_a_url_in_the_template_is_stripped(): void
    {
        Setting::updateOrCreate(
            ['group' => 'sms', 'key' => UniSms::KEY_TEMPLATE],
            ['value' => 'Track at https://gogoxpress.com/track/ABC123 now']
        );

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($this->order, 'Shipped', null);

        $this->assertStringNotContainsString('http', $text);
        $this->assertStringNotContainsString('gogoxpress.com', $text);
    }

    public function test_a_tracking_link_in_the_comment_becomes_its_number(): void
    {
        $comment = 'QuadX: <a href="https://gogoxpress.com/track/GGX1">GGX1</a>';

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($this->order, 'Shipped', $comment);

        $this->assertStringContainsString('GGX1', $text, 'The number is what a customer can use.');
        $this->assertStringNotContainsString('http', $text);
    }

    public function test_the_send_layer_refuses_to_carry_a_url(): void
    {
        UniSms::send('+639171234567', 'See https://example.com for details');

        Http::assertSent(function ($request) {
            return ! str_contains(json_encode($request->data()), 'http');
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Notifications/SmsMessageTest.php`
Expected: FAIL with "Call to undefined method ...::compose()" and no `UniSms::template()`.

- [ ] **Step 3: Write minimal implementation**

In `UniSms`, add the key, the default, the reader and the guard:

```php
    public const KEY_TEMPLATE = 'unisms_template';

    /** The owner's sentence, used until a merchant writes their own. */
    public const DEFAULT_TEMPLATE = '{store_name}: Order #{order_id} is now {order_status}';

    public static function template(): string
    {
        $saved = trim((string) Setting::where('group', 'sms')->where('key', self::KEY_TEMPLATE)->value('value'));

        return $saved !== '' ? $saved : self::DEFAULT_TEMPLATE;
    }

    /**
     * A text message carries no links.
     *
     * Philippine telcos drop messages containing a URL, so a link does not
     * merely annoy: it loses the message. The guard sits at the send layer as
     * well as in the composer, so a merchant typing a link into the template,
     * or any future caller, cannot break a store's deliverability.
     */
    public static function stripUrls(string $text): string
    {
        $text = preg_replace('~\b(?:https?://|www\.)\S+~i', '', $text) ?? $text;
        $text = preg_replace('~\b[a-z0-9-]+\.(?:com|net|org|ph|io|co)(?:/\S*)?~i', '', $text) ?? $text;

        return trim(preg_replace('/\s{2,}/', ' ', $text) ?? $text);
    }
```

Call it in `send()` on the content, before the length check.

In `SmsChannel`, rename `message()` to a public `compose()` so it can be tested directly, fill the template, and invert the anchor handling:

```php
    /**
     * What the text says.
     *
     * A text is not an email. No greeting, no signature, no marketing, and
     * never a link: the store, the order, the state it is now in, and the
     * tracking number if there is one.
     */
    public function compose(Order $order, ?string $newStatus, ?string $comment): string
    {
        $tracking = \App\Services\OrderNotificationService::trackingPlaceholders($order);

        $text = strtr(UniSms::template(), [
            '{store_name}' => (string) config('app.name'),
            '{order_id}' => (string) $order->id,
            '{order_status}' => $newStatus ?: 'updated',
            '{customer_name}' => trim((string) $order->full_name),
            '{tracking_number}' => trim(strip_tags((string) $tracking['tracking_number'])),
            '{courier_name}' => trim((string) $tracking['carrier_name']),
            '{comment}' => $this->plain((string) $comment),
        ]);

        return mb_substr(UniSms::stripUrls($text), 0, UniSms::MAX_LENGTH);
    }
```

and in `plain()`, replace the anchor with its TEXT rather than its href:

```php
        // An anchor becomes what it said, not where it pointed: the tracking
        // number is what a customer can give the courier, and a link would
        // stop the message reaching them at all.
        $text = preg_replace('/<a[^>]*>(.*?)<\/a>/is', '$1', $html) ?? $html;
```

Update `statusChanged()` to call `$this->compose(...)`. Add the template field to the settings view (a text input, with the placeholder list in its help slot) and save it in the controller alongside the status ticks.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Notifications`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add extensions/sms-unisms/src tests/Feature/Notifications/SmsMessageTest.php extensions/sms-unisms/resources/views/index.blade.php
git commit -m "feat(sms): the store writes the message, and it never carries a link"
```

---

### Task 11: The whole suite, and a real store

**Files:**
- Test: the whole suite
- Modify: `docs/superpowers/specs/2026-09-25-order-status-notifications-design.md` (mark pass 1 built)

- [ ] **Step 1: Run the full suite**

Run: `php artisan test`
Expected: PASS, with no fewer tests than before this plan started (1,423 passing, 2 skipped at the time of writing, plus the new ones).

- [ ] **Step 2: Drive a real order on the dev store**

Run the app (`php artisan serve --host=0.0.0.0 --port=8001`), then:
- place an order through checkout and confirm one confirmation email and, with the first status ticked in UniSMS, one text;
- move it to Shipped from the order page with the box unticked, and confirm nothing was sent;
- move it to Shipped with the box ticked, and confirm the email and the text;
- call the ERP endpoint against a test order and confirm the email, the text, and the stock movement.

UniSMS calls cost money. Either point the base URL at a stub or accept the charge for a handful of messages, and never send to a customer's real number from the dev store.

- [ ] **Step 3: Record what was built**

Change the spec's status line to `pass 1 built <date>, pass 2 outstanding` and list anything that turned out differently from the design.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/specs/2026-09-25-order-status-notifications-design.md
git commit -m "docs: pass 1 of the notification doorway is built"
```

---

## One change from the spec

The spec put the email tick on the core status in pass 2. It is in pass 1 here
(Task 1), because pass 1 promises that an ERP status update emails the
customer, and there is nothing for that path to read without it. What remains
in pass 2 is only deletion: the fulfillment extension's own list, its Notify
column, the courier map's notify flag, and the extension type for SMS
gateways. Pass 1 stops reading those and carries their saved values across
(Task 9's migration), so no store goes quiet in between.

## Deliberately not in pass 1

- Retiring `NotifyMap`, the Notify column on the fulfillment settings page, and the courier map's own notify flag. Pass 1 stops reading them and carries their values across; deleting the code and the UI is pass 2.
- The `notification` extension type for SMS gateways.
- Delivery confirmation from UniSMS.
- Notifications for changes that are not a status change, such as a tracking number added to an order that already shipped.
