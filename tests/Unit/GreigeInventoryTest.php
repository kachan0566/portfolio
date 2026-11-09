<?php

namespace Tests\Unit;

use App\Support\Inventory\GreigeInventory;
use App\Support\Inventory\GreigeSupply;
use App\Support\Qty\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GreigeInventoryTest extends TestCase
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

    public function test_entries_based_on_greige_po_received_qty(): void
    {
        $entry = GreigeInventory::entries()->firstWhere('po_code', 'PO-G-2606-002');

        $this->assertNotNull($entry);
        $this->assertSame('KB-T', $entry->greige_sku);
        $this->assertSame(200.0, (float) $entry->qty_meters);
    }

    public function test_legacy_product_po_not_in_greige_entries(): void
    {
        $this->assertNull(
            GreigeInventory::entries()->firstWhere('po_code', 'PO-2606-003')
        );
    }

    /**
     * 利用可能な生機mが、染工場在庫m + 生機発注残m − 製品発注予約m であることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_greige_supply_uses_physical_on_order_minus_allocated(): void
    {
        $sku = 'KB-T';
        $physical = GreigeSupply::dyeFactoryMeters($sku);
        $onOrder = GreigeSupply::greigePoRemainingMeters($sku);
        $allocated = GreigeSupply::allocatedGreigeMeters($sku);

        $this->assertSame(200.0, $physical);
        $this->assertGreaterThanOrEqual(0, $onOrder);
        $this->assertGreaterThanOrEqual(0, $allocated);
        $this->assertSame(
            max(0.0, round($physical + $onOrder - $allocated, 2)),
            GreigeSupply::availableMeters($sku)
        );
    }

    /**
     * 利用可能な生機反が、染工場在庫反 + 生機発注残反 − 製品発注予約反 であることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_greige_supply_available_tan_is_physical_plus_on_order_minus_allocated(): void
    {
        $sku = 'KB-T';
        $physical = GreigeSupply::dyeFactoryTan($sku);
        $onOrder = GreigeSupply::greigePoRemainingTan($sku);
        $allocated = GreigeSupply::allocatedGreigeTan($sku);

        $this->assertGreaterThanOrEqual(0, $physical);
        $this->assertGreaterThanOrEqual(0, $onOrder);
        $this->assertGreaterThanOrEqual(0, $allocated);
        $this->assertSame(
            max(0.0, round($physical + $onOrder - $allocated, QtyHelper::TAN_DECIMALS)),
            GreigeSupply::availableTan($sku)
        );
    }

    /**
     * 生機100m/反・製品50m/反のとき、生機1反を製品2反として足りると判定することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_shortage_tan_converts_greige_tan_to_product_tan(): void
    {
        $productId = 1;
        $this->assertSame('KB-A', GreigeSupply::greigeSkuForProduct($productId));
        $this->assertSame(2.0, QtyHelper::productTanFromGreigeTan(1.0, $productId));

        $availableGreigeTan = GreigeSupply::availableTan('KB-A');
        $availableProductTan = QtyHelper::productTanFromGreigeTan($availableGreigeTan, $productId);
        $this->assertSame(
            round($availableGreigeTan * 2, QtyHelper::TAN_DECIMALS),
            $availableProductTan
        );

        if ($availableProductTan > 0) {
            $this->assertNull(GreigeSupply::shortageTan($productId, $availableProductTan));
            $this->assertTrue(GreigeSupply::canFulfillProductTan($productId, $availableProductTan));
        }
    }

    /**
     * 製品反が足りないとき、不足メッセージが m ではなく反数で出ることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_shortage_tan_message_uses_tan_not_meters(): void
    {
        $productId = 1;
        $availableProductTan = QtyHelper::productTanFromGreigeTan(
            GreigeSupply::availableTan('KB-A'),
            $productId
        );
        $requiredTan = $availableProductTan + 0.25;

        $message = GreigeSupply::shortageTan($productId, $requiredTan);

        $this->assertNotNull($message);
        $this->assertStringContainsString('反 不足しています', $message);
        $this->assertStringContainsString('必要 '.QtyHelper::formatTanCount($requiredTan).'反', $message);
        $this->assertStringContainsString('利用可能 '.QtyHelper::formatTanCount($availableProductTan).'反', $message);
        $this->assertStringNotContainsString('m 不足しています', $message);
        $this->assertFalse(GreigeSupply::canFulfillProductTan($productId, $requiredTan));
    }
}
