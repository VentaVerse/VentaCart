<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderSnapshotAccessorsTest extends TestCase
{
    public function test_payment_label_prefers_snapshot_title(): void
    {
        $order = new Order();
        $order->setRawAttributes(['payment_method' => 'bank_transfer_4', 'payment_method_title' => 'Maya']);
        $this->assertSame('Maya', $order->payment_method_label);
    }

    public function test_payment_label_falls_back_for_plain_keys_without_snapshot(): void
    {
        $order = new Order();
        $order->setRawAttributes(['payment_method' => 'cod', 'payment_method_title' => null]);
        $this->assertSame('Cash on Delivery', $order->payment_method_label);
    }

    public function test_tracking_link_prefers_stored_column(): void
    {
        $order = new Order();
        $order->setRawAttributes(['tracking_link' => 'https://track.example/ABC', 'tracking_number' => 'ABC']);
        $this->assertSame('https://track.example/ABC', $order->tracking_link);
    }

    public function test_tracking_link_null_when_no_snapshot_and_no_tracking_number(): void
    {
        $order = new Order();
        $order->setRawAttributes(['tracking_link' => null, 'tracking_number' => null]);
        $this->assertNull($order->tracking_link);
    }
}
