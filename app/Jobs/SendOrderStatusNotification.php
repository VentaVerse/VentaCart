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
 * itself, which nothing here is allowed to touch. $tries therefore only
 * covers infrastructure failures the queue worker itself detects (the
 * worker dying mid-job, a lost database connection), never a gateway that
 * answered "no".
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
