<?php

namespace Tests\Feature;

use App\Models\OrderAllocation;
use App\Support\ProductStock;
use App\Support\StockAllocation;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Database\Seeders\ShipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 現在庫引当の未割当・検証が反明細ベースであることを確認する。
 */
class RollBasedStockAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(ReceivingSeeder::class);
        $this->seed(ShipmentSeeder::class);
    }

    public function test_po_2606_002_unallocated_follows_in_stock_roll_not_standard_tan_conversion(): void
    {
        $productId = 3;
        $poId = 2;

        $rolls = ProductStock::inStockRollTotalsForPo($poId);
        $this->assertSame(1.0, $rolls->tan);
        $this->assertSame(49.0, $rolls->meters);

        $unallocated = StockAllocation::unallocatedStockQuantityFromPo($productId, $poId);
        $this->assertSame(1.0, $unallocated->tan);
        $this->assertSame(49.0, $unallocated->meters);

        $options = StockAllocation::poOptionsForProduct($productId);
        $stockOpt = $options['stock']->firstWhere('code', 'PO-2606-002');
        $this->assertNotNull($stockOpt);
        $this->assertSame(49.0, $stockOpt->qty);
        $this->assertSame(1.0, $stockOpt->qty_tan);
        $this->assertStringContainsString('1反 / 49m', $stockOpt->label);
        $this->assertStringNotContainsString('0.98', $stockOpt->label);
    }

    public function test_one_tan_stock_allocation_passes_when_roll_is_one_tan_forty_nine_meters(): void
    {
        $productId = 3;
        $orderId = 10;
        $poId = 2;

        $error = StockAllocation::validateSubmission($productId, [
            $orderId => [
                StockAllocation::TYPE_STOCK => [$poId => 1.0],
            ],
        ]);

        $this->assertNull($error);
    }

    public function test_one_point_two_five_tan_stock_allocation_fails_against_roll_tan(): void
    {
        $productId = 3;
        $orderId = 10;
        $poId = 2;

        $error = StockAllocation::validateSubmission($productId, [
            $orderId => [
                StockAllocation::TYPE_STOCK => [$poId => 1.25],
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('在庫反', $error);
    }

    public function test_save_one_tan_stock_allocation_persists_roll_actual_meters_not_standard_fifty(): void
    {
        $productId = 3;
        $orderId = 10;
        $poId = 2;

        StockAllocation::saveFromTypedMaps($productId, [
            $orderId => [
                StockAllocation::TYPE_STOCK => [$poId => 1.0],
            ],
        ]);

        $row = OrderAllocation::query()
            ->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->where('purchase_order_id', $poId)
            ->where('allocation_type', StockAllocation::TYPE_STOCK)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(1.0, (float) $row->qty_tan);
        $this->assertSame(49.0, (float) $row->qty_m);
        $this->assertSame(49.0, StockAllocation::stockAllocatedForOrder($orderId));
    }
}
