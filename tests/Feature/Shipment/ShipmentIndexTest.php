<?php

namespace Tests\Feature\Shipment;

use App\Models\Shipment\Shipment;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Database\Seeders\ShipmentPlanSeeder;
use Database\Seeders\ShipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentIndexTest extends TestCase
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
        $this->seed(ShipmentPlanSeeder::class);
        $this->seed(ShipmentSeeder::class);
    }

    public function test_shipments_index_uses_database(): void
    {
        $response = $this->get(route('shipments.index'));

        $response->assertOk();
        $response->assertSee('SH-2606-001', false);
        $this->assertSame(4, Shipment::query()->count());
    }

    /**
     * 出荷履歴が実測mから反数を再計算せず、DBに保存された実績反数と実測mを表示することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_shipments_index_shows_stored_tan_and_actual_meters(): void
    {
        $shipment = Shipment::query()->where('code', 'SH-2606-001')->firstOrFail();
        $shipment->update([
            'qty_tan' => 0.25,
            'qty_m' => 10.75,
        ]);

        $response = $this->get(route('shipments.index'));

        $response->assertOk();
        $response->assertSeeText('0.25反 / 10.75m');
    }
}
