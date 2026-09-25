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
