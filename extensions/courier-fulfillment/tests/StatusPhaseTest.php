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
