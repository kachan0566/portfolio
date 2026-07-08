<?php

namespace Tests\Unit;

use App\Models\Order;
use Tests\TestCase;

class OrderMetersOverriddenTest extends TestCase
{
    public function test_meters_mode_is_always_overridden(): void
    {
        $order = new Order([
            'order_qty_mode' => 'meters',
            'qty_tan' => 0,
            'qty_meters' => 150,
            'product_id' => 1,
        ]);

        $this->assertTrue($order->metersOverridden());
    }

    public function test_tan_mode_with_standard_conversion_is_not_overridden(): void
    {
        $order = new Order([
            'order_qty_mode' => 'tan',
            'qty_tan' => 2,
            'qty_meters' => 100,
            'product_id' => 1,
        ]);

        $this->assertFalse($order->metersOverridden());
    }

    public function test_tan_mode_with_non_standard_meters_is_overridden(): void
    {
        $order = new Order([
            'order_qty_mode' => 'tan',
            'qty_tan' => 2,
            'qty_meters' => 115,
            'product_id' => 1,
        ]);

        $this->assertTrue($order->metersOverridden());
    }
}
