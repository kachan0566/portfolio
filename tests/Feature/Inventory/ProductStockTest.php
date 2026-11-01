<?php

namespace Tests\Feature\Inventory;

use App\Models\Receiving\ReceivingLine;
use App\Models\Shipment\Shipment;
use App\Support\Inventory\ProductStock;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Database\Seeders\ShipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockTest extends TestCase
{
    use RefreshDatabase;

    private function seedTransactionalData(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(ReceivingSeeder::class);
        $this->seed(ShipmentSeeder::class);
    }

    public function test_stock_movements_returns_empty_without_seed(): void
    {
        $movements = ProductStock::stockMovements();

        $this->assertTrue($movements->isEmpty());
    }

    public function test_stock_movements_builds_from_receivings_and_shipments(): void
    {
        $this->seedTransactionalData();

        $movements = ProductStock::stockMovements();

        // 製品入荷3件 + 期首繰越1件 + 出荷4件
        $this->assertSame(8, $movements->count());
        $this->assertSame(4, $movements->where('type', '入庫')->count());
        $this->assertSame(4, $movements->where('type', '出庫')->count());
        $this->assertTrue($movements->every(fn ($m) => $m->sku !== ''));
        $this->assertTrue($movements->contains(fn ($m) => $m->sku === 'FAB-A-BK'));
        $inboundNotes = $movements->where('type', '入庫')->pluck('note')->all();
        $this->assertContains('入荷 RC-2606-001', $inboundNotes);
        $this->assertContains('入荷 RC-OPEN-002', $inboundNotes);

        $rc001 = $movements->first(fn ($m) => $m->note === '入荷 RC-2606-001');
        $this->assertNotNull($rc001);
        $this->assertSame(200.0, (float) $rc001->qty);

        $sh001 = $movements->first(fn ($m) => $m->note === '出荷 SH-2606-001');
        $this->assertNotNull($sh001);
        $shipment = Shipment::query()->where('code', 'SH-2606-001')->first();
        $this->assertNotNull($shipment);
        $this->assertEqualsWithDelta((float) $shipment->qty_m, (float) $sh001->qty, 0.001);
    }

    /**
     * 入出庫履歴が入荷明細・出荷実績に保存された反数と実測mを保持することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_stock_movements_keep_actual_tan_and_meters(): void
    {
        $this->seedTransactionalData();

        $receivingLine = ReceivingLine::query()
            ->whereHas('receiving', fn ($query) => $query->where('code', 'RC-2606-001'))
            ->firstOrFail();
        $receivingLine->update([
            'qty_tan' => 2.00,
            'qty_m' => 105.75,
        ]);

        $shipment = Shipment::query()->where('code', 'SH-2606-001')->firstOrFail();
        $shipment->update([
            'qty_tan' => 0.25,
            'qty_m' => 10.75,
        ]);

        $movements = ProductStock::stockMovements();
        $inbound = $movements->firstWhere('note', '入荷 RC-2606-001');
        $outbound = $movements->firstWhere('note', '出荷 SH-2606-001');

        $this->assertNotNull($inbound);
        $this->assertSame(2.0, (float) $inbound->qty_tan);
        $this->assertSame(105.75, (float) $inbound->qty);
        $this->assertNotNull($outbound);
        $this->assertSame(0.25, (float) $outbound->qty_tan);
        $this->assertSame(10.75, (float) $outbound->qty);
    }
}
