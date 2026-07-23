<?php

namespace Tests\Unit;

use App\Services\Inventory\ShipmentRollAllocator;
use App\Support\FabricTanRoll;
use App\Support\ProductRoll;
use Tests\TestCase;

class ShipmentRollAllocatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearRollStorage();
        FabricTanRoll::resetBootstrap();
        FabricTanRoll::ensureBootstrapped();
    }

    private function clearRollStorage(): void
    {
        foreach (['product_rolls.json', 'shipment_roll_allocations.json'] as $file) {
            $path = storage_path('app/'.$file);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_allocate_consumes_rolls_fifo(): void
    {
        $productId = 1;
        $before = ProductRoll::inStockForProduct($productId)->count();

        $result = ShipmentRollAllocator::allocate($productId, 1.0, 999, 'test');

        $this->assertGreaterThan(0, $result['allocated_tan']);
        $this->assertGreaterThan(0, $result['allocated_m']);
        $this->assertNotEmpty($result['roll_ids']);
        $this->assertLessThan($before, ProductRoll::inStockForProduct($productId)->count());
    }

    public function test_allocate_for_meters_ceil_until_required(): void
    {
        $productId = 1;
        $requiredM = 60.0;

        $result = ShipmentRollAllocator::allocateForMeters($productId, $requiredM, 1000, 'm-order');

        $this->assertGreaterThanOrEqual($requiredM, $result['allocated_m']);
        $this->assertGreaterThan(0, $result['allocated_tan']);
    }

    public function test_preview_fifo_returns_oldest_rolls_first(): void
    {
        $productId = 1;
        $fifo = ProductRoll::fifoInStock($productId)->take(2)->values();
        $preview = ShipmentRollAllocator::previewFifo($productId, 2.0);

        $this->assertNotEmpty($preview);
        if ($fifo->count() >= 2) {
            $this->assertSame((int) $fifo[0]->id, (int) $preview[0]->id);
        }
    }
}
