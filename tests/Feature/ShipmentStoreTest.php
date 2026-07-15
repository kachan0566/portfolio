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
        $order = Order::query()->where('code', 'SO-2606-008')->first();
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
}
