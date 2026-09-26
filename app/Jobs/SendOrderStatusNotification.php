<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Services\OrderNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One message, sent away from the request that caused it.
 *
 * A courier's webhook expects an answer in about three seconds and the ERP
 * waits on its API call: neither can afford to sit through a gateway's
 * timeout. A failed send retries here instead of being lost.
 *
 * A mailer refusal or a channel throwing is caught in handle() below and
 * recorded, never rethrown, so those application-level failures are told
 * once and not retried: under the sync queue this test suite runs on, a
 * rethrow would bubble a dead gateway straight into the status change
 * itself, which nothing here is allowed to touch. $maxExceptions therefore
 * only covers infrastructure failures the queue worker itself detects (the
 * worker dying mid-job, a lost database connection), never a gateway that
 * answered "no".
 *
 * The one "no" that is not final is "not yet": a gateway that rate limits
 * (UniSMS allows one request every two seconds) answers a burst with
 * refusals that mean "come back shortly". Two things handle it. The job
 * asks for a limiter named after its target (order-notification:sms), which
 * the gateway's own extension defines, so core never names a gateway and a
 * target with no limiter defined passes straight through. And a channel
 * that still hears "slow down" says so with `retry`, and the job goes back
 * on the queue instead of writing a failure. Because every release uses up
 * an attempt, the job retries until a deadline (retryUntil) rather than for
 * a count, or a long burst would run out of tries while merely waiting.
 *
 * Implements ShouldQueueAfterCommit rather than the plain ShouldQueue: a
 * caller may dispatch this from inside a DB transaction (the HitPay paths
 * now claim the order under a row lock before calling apply()), and this
 * job's handle() reads the history row by id to record its result against.
 * Without deferring to the commit, the sync queue driver would run this
 * job, and its lookup of $this->historyId, before that row's insert had
 * committed. When no transaction is open, dispatch is immediate as before.
 */
class SendOrderStatusNotification implements ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Crashes (not refusals, not waits) tolerated before giving up. */
    public int $maxExceptions = 3;

    /** How long a text may wait its turn before it is no longer worth sending. */
    public const RETRY_WINDOW_MINUTES = 60;

    /** Used when a channel asks to retry without saying how soon. */
    public const DEFAULT_RETRY_SECONDS = 10;

    public function __construct(
        public int $orderId,
        public string $target,
        public ?string $oldStatus,
        public ?string $newStatus,
        public ?string $comment,
        public ?int $historyId = null,
        public ?int $newStatusId = null,
    ) {
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(self::RETRY_WINDOW_MINUTES);
    }

    /**
     * A limiter per target, defined (or not) by whoever owns that target.
     *
     * Not under the sync queue: there is no queue to wait on, and a
     * throttled sync job would simply be dropped. A store without a worker
     * sends immediately, as it always did, and a refusal is recorded.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        if ($this->job instanceof SyncJob) {
            return [];
        }

        return [new RateLimited('order-notification:' . $this->target)];
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

        // "Not yet" is not "no": back on the queue, and nothing recorded
        // until the message is really sent or really refused. Only when a
        // real queue can bring it back; a sync run records the refusal.
        if (($result['retry'] ?? false) === true && $this->job !== null && ! $this->job instanceof SyncJob) {
            $this->release((int) ($result['retry_after'] ?? self::DEFAULT_RETRY_SECONDS));

            return;
        }

        $this->record(['sent' => (bool) $result['sent'], 'reason' => $result['reason'] ?? null]);
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

    /** @return array{sent: bool, reason: string|null, retry?: bool, retry_after?: int} */
    private function sendChannel(Order $order): array
    {
        foreach (OrderNotificationService::channels() as $channel) {
            if ($channel->key() !== $this->target) {
                continue;
            }

            try {
                return $channel->statusChanged($order, $this->oldStatus, $this->newStatus, $this->comment, $this->newStatusId);
            } catch (\Throwable $e) {
                // The contract says a channel never throws. This is the belt
                // for one that forgets: the order already moved.
                Log::error('Notification channel threw', ['channel' => $this->target, 'error' => $e->getMessage()]);

                return ['sent' => false, 'reason' => $e->getMessage()];
            }
        }

        return ['sent' => false, 'reason' => 'That way of telling the customer is no longer installed.'];
    }

    /**
     * announce() dispatches one job per target (email, then each wanted
     * channel) against the same historyId. Under a real queue those jobs
     * can run on different workers at the same instant, and a plain
     * read-modify-write of the notifications column would let the slower
     * job's write clobber the faster job's key: last write wins on the
     * whole JSON blob, silently losing an entry. Locking the row for the
     * length of the transaction serialises the two writes instead.
     *
     * SQLite (the test database) ignores row locks entirely, so this only
     * protects the row on MySQL and MariaDB, which is what production
     * runs. The lock costs nothing extra there: it holds for the length of
     * one small UPDATE, not for however long the send itself took, because
     * the send already finished before record() is called.
     *
     * @param array{sent: bool, reason: string|null} $result
     */
    private function record(array $result): void
    {
        if (! $this->historyId) {
            return;
        }

        DB::transaction(function () use ($result): void {
            $history = OrderHistory::whereKey($this->historyId)->lockForUpdate()->first();

            if (! $history) {
                return;
            }

            $history->update([
                'notifications' => ($history->notifications ?? []) + [$this->target => $result],
            ]);
        });
    }
}
