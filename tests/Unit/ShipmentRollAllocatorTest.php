<?php

namespace Tests\Unit;

use App\Models\Shipment;
use App\Services\Inventory\ShipmentRollAllocator;
use App\Support\ProductRoll;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentRollAllocatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_allocate_consumes_rolls_fifo(): void
    {
        $productId = 1;
        $shipmentId = $this->createTestShipment($productId)->id;
        $before = ProductRoll::inStockForProduct($productId)->count();

        $result = ShipmentRollAllocator::allocate($productId, 1.0, $shipmentId, 'test');

        $this->assertGreaterThan(0, $result['allocated_tan']);
        $this->assertGreaterThan(0, $result['allocated_m']);
        $this->assertNotEmpty($result['roll_ids']);
        $this->assertLessThan($before, ProductRoll::inStockForProduct($productId)->count());
    }

    public function test_allocate_for_meters_ceil_until_required(): void
    {
        $productId = 1;
        $requiredM = 60.0;
        $shipmentId = $this->createTestShipment($productId)->id;

        $result = ShipmentRollAllocator::allocateForMeters($productId, $requiredM, $shipmentId, 'm-order');

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

    private function createTestShipment(int $productId): Shipment
    {
        return Shipment::query()->create([
            'code' => 'SH-TEST-'.uniqid(),
            'order_id' => 1,
            'product_id' => $productId,
            'qty_tan' => 1,
            'qty_m' => 50,
            'shipped_date' => '2026-06-15',
            'ship_to_name' => 'テスト出荷先',
        ]);
    }
}
