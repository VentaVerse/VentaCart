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
