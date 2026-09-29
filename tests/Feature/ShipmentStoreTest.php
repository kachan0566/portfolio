<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductRoll;
use App\Models\Shipment;
use App\Support\StockAllocation;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Database\Seeders\ShipmentPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentStoreTest extends TestCase
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
    }

    public function test_store_creates_shipment_and_reduces_stock(): void
    {
        $order = Order::query()->where('code', 'SO-2606-010')->first();
        $this->assertNotNull($order);

        $beforeStock = ProductRoll::query()
            ->where('product_id', $order->product_id)
            ->where('status', ProductRoll::STATUS_IN_STOCK)
            ->count();

        $this->assertGreaterThan(0, StockAllocation::shippableQty((int) $order->id));

        $response = $this->post(route('shipments.store'), [
            'order_id' => $order->id,
            'qty_tan' => 1,
        ]);

        $response->assertRedirect(route('shipments.index'));
        $response->assertSessionHas('success');

        $this->assertSame(1, Shipment::query()->count());
        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'product_id' => $order->product_id,
        ]);

        $order->refresh();
        $this->assertGreaterThan(0, (float) $order->shipped_qty_tan);

        $afterStock = ProductRoll::query()
            ->where('product_id', $order->product_id)
            ->where('status', ProductRoll::STATUS_IN_STOCK)
            ->count();

        $this->assertLessThan($beforeStock, $afterStock);
    }

    /**
     * 1反の在庫から0.25反だけを実測m指定で出荷し、残り0.75反と残mを在庫に残すことを確認する。
     *
     * 丸ごと出荷しない最後の反だけ `partial_actual_qty_m` で実測mを指定する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_quarter_tan_splits_roll_and_keeps_remainder_in_stock(): void
    {
        $order = Order::query()->where('code', 'SO-2606-002')->firstOrFail();
        $roll = ProductRoll::query()
            ->where('product_id', $order->product_id)
            ->where('status', ProductRoll::STATUS_IN_STOCK)
            ->where('tan_qty', '>=', 1)
            ->orderBy('received_date')
            ->orderBy('id')
            ->firstOrFail();

        $beforeMeters = (float) $roll->actual_qty_m;

        $response = $this->post(route('shipments.store'), [
            'order_id' => $order->id,
            'qty_tan' => 0.25,
            'partial_actual_qty_m' => 10,
        ]);

        $response->assertRedirect(route('shipments.index'));
        $response->assertSessionHas('success');

        $shipment = Shipment::query()->latest('id')->firstOrFail();
        $this->assertSame(0.25, (float) $shipment->qty_tan);
        $this->assertSame(10.0, (float) $shipment->qty_m);

        $roll->refresh();
        $this->assertSame(ProductRoll::STATUS_IN_STOCK, $roll->status);
        $this->assertSame(0.75, (float) $roll->tan_qty);
        $this->assertSame($beforeMeters - 10.0, (float) $roll->actual_qty_m);
    }

    /**
     * 0.25の倍数ではない0.3反を出荷せず、刻み違反を利用者へ伝えることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_rejects_shipment_outside_quarter_step(): void
    {
        $order = Order::query()->where('code', 'SO-2606-002')->firstOrFail();

        $response = $this->post(route('shipments.store'), [
            'order_id' => $order->id,
            'qty_tan' => 0.3,
            'partial_actual_qty_m' => 10,
        ]);

        $response->assertRedirect(route('shipments.create', ['order_id' => $order->id]));
        $response->assertSessionHas('error', '出荷反数は0.25反刻みで入力してください。');
        $this->assertSame(0, Shipment::query()->count());
    }
}
