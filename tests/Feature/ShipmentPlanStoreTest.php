<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShipmentPlanRecord;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\ShipmentPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentPlanStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(ShipmentPlanSeeder::class);
    }

    public function test_store_creates_shipment_plan(): void
    {
        $order = Order::query()->where('code', 'SO-2606-009')->first();
        $this->assertNotNull($order);

        $before = ShipmentPlanRecord::query()->count();

        $response = $this->post(route('shipment-plans.store', $order->id), [
            'planned_ship_date' => '2026-07-18',
            'confirmed_qty_m' => 30,
            'note' => '画面から登録',
        ]);

        $response->assertRedirect(route('orders.show', $order->id));
        $this->assertSame($before + 1, ShipmentPlanRecord::query()->count());
        $this->assertDatabaseHas('shipment_plans', [
            'order_id' => $order->id,
            'confirmed_qty_m' => 30,
            'note' => '画面から登録',
        ]);
    }

    public function test_store_rejects_over_remaining(): void
    {
        $order = Order::query()->where('code', 'SO-2606-006')->first();
        $this->assertNotNull($order);

        $before = ShipmentPlanRecord::query()->count();

        $response = $this->post(route('shipment-plans.store', $order->id), [
            'planned_ship_date' => '2026-06-30',
            'confirmed_qty_m' => 99999,
        ]);

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('error');
        $this->assertSame($before, ShipmentPlanRecord::query()->count());
    }
}
