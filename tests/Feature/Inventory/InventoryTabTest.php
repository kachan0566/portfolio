<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\GreigeRoll as GreigeRollModel;
use App\Models\Inventory\ProductRoll as ProductRollModel;
use App\Support\Core\DemoData;
use App\Support\Inventory\GreigeInventory;
use App\Support\Inventory\GreigeRoll;
use App\Support\Purchase\PurchaseOrderType;
use App\Support\Qty\QtyHelper;
use App\Support\Yarn\YarnInventory;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTabTest extends TestCase
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
        $this->seed(CostFoundationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_inventory_has_yarn_tab(): void
    {
        $response = $this->get(route('inventory.index', ['tab' => 'yarn']));

        $response->assertOk();
        $response->assertSee('糸在庫');
        $response->assertSee('RM-001');
        $response->assertSee('利用可能');
        $response->assertSee('発注残');
    }

    public function test_greige_inventory_from_greige_po_receiving(): void
    {
        $entries = GreigeInventory::entries();

        $this->assertTrue($entries->contains(fn ($e) => $e->po_code === 'PO-G-2606-002'));
        // 染機投入済の既存明細へ bootstrap で生機が引当済みのため、物理在庫は 0m
        $this->assertSame(0, (int) GreigeRoll::stockMetersForSku('KB-T'));
    }

    public function test_yarn_inventory_shows_base_stock(): void
    {
        $this->assertSame(800.0, YarnInventory::effectiveStockKg(1));
        $this->assertSame(650.0, YarnInventory::effectiveStockKg(2)); // 500 + 入荷150
    }

    /**
     * 製品在庫一覧が物理反に保存された現在庫反数と実測mを表示することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_product_inventory_shows_roll_tan_and_actual_meters(): void
    {
        $roll = ProductRollModel::query()
            ->where('status', ProductRollModel::STATUS_IN_STOCK)
            ->firstOrFail();

        ProductRollModel::query()
            ->where('product_id', $roll->product_id)
            ->update(['status' => ProductRollModel::STATUS_SHIPPED]);
        ProductRollModel::query()->whereKey($roll->id)->update([
            'status' => ProductRollModel::STATUS_IN_STOCK,
            'tan_qty' => 0.25,
            'actual_qty_m' => 10.75,
        ]);

        $response = $this->get(route('inventory.index', ['tab' => 'product']));

        $response->assertOk();
        $response->assertSeeText('0.25反 / 10.75m');
    }

    /**
     * 生機在庫一覧が物理反に保存された現在庫反数と実測mを表示することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_greige_inventory_shows_roll_tan_and_actual_meters(): void
    {
        GreigeInventory::entries();
        $roll = GreigeRollModel::query()
            ->whereNotNull('purchase_order_id')
            ->firstOrFail();

        GreigeRollModel::query()
            ->where('purchase_order_id', $roll->purchase_order_id)
            ->update(['status' => GreigeRoll::STATUS_CONSUMED]);
        GreigeRollModel::query()->whereKey($roll->id)->update([
            'status' => GreigeRoll::STATUS_IN_STOCK,
            'tan_qty' => 0.25,
            'actual_qty_m' => 10.75,
        ]);

        $response = $this->get(route('inventory.index', ['tab' => 'greige']));

        $response->assertOk();
        $response->assertSeeText('0.25反 / 10.75m');
    }

}