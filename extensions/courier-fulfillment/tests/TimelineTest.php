<?php
namespace Extensions\CourierFulfillment\Tests;

use Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment;
use PHPUnit\Framework\TestCase;

class TimelineTest extends TestCase
{
    public function test_sorts_events_chronologically_and_drops_metadata(): void
    {
        $tat = [
            'out_for_delivery' => 300, 'for_pickup' => 100, 'picked_up' => 200,
            'sla_start' => 50, 'first_pickup_at' => 10, 'first_estimated_delivery_date' => 999,
        ];
        $t = QuadXFulfillment::timelineFromTat($tat);
        $this->assertSame(['for_pickup', 'picked_up', 'out_for_delivery'], array_column($t, 'status'));
        $this->assertSame([100, 200, 300], array_column($t, 'at'));
    }

    public function test_null_and_empty_are_empty(): void
    {
        $this->assertSame([], QuadXFulfillment::timelineFromTat(null));
        $this->assertSame([], QuadXFulfillment::timelineFromTat([]));
    }
}
