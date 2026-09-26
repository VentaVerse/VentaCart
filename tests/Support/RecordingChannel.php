<?php

namespace Tests\Support;

use App\Contracts\OrderNotificationChannel;
use App\Models\Order;

/** A channel that records what it was asked to do. */
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

    public function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null, ?int $newStatusId = null): array
    {
        if (self::$throw) {
            throw new \RuntimeException('the gateway is down');
        }

        self::$sends++;

        return ['sent' => true, 'reason' => null];
    }
}
