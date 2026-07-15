<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShipmentPlanRecord;
use App\Support\ShipmentPlan;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\ShipmentPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentPlanTest extends TestCase
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

    public function test_create_persists_plan_with_tan(): void
    {
        $order = Order::query()->where('code', 'SO-2606-010')->first();
        $this->assertNotNull($order);

        $display = ShipmentPlan::create([
            'order_id' => $order->id,
            'product_id' => $order->product_id,
            'planned_ship_date' => '2026-07-04',
            'confirmed_qty_m' => 50.0,
            'note' => 'テスト登録',
        ]);

        $this->assertDatabaseHas('shipment_plans', [
            'order_id' => $order->id,
            'confirmed_qty_m' => 50.0,
            'status' => ShipmentPlan::STATUS_CONFIRMED,
            'note' => 'テスト登録',
        ]);

        $record = ShipmentPlanRecord::query()->find($display->id);
        $this->assertNotNull($record);
        $this->assertGreaterThan(0, (float) $record->confirmed_qty_tan);
    }

    public function test_record_shipment_updates_status_to_completed(): void
    {
        $plan = ShipmentPlanRecord::query()->where('code', 'SP-2606-001')->first();
        $this->assertNotNull($plan);

        ShipmentPlan::recordShipment((int) $plan->order_id, 120.0, 2.0);

        $plan->refresh();
        $this->assertSame(ShipmentPlan::STATUS_COMPLETED, $plan->status);
        $this->assertSame(120.0, (float) $plan->shipped_qty_m);
        $this->assertSame(2.0, (float) $plan->shipped_qty_tan);
    }

    public function test_for_order_returns_display_objects(): void
    {
        $plans = ShipmentPlan::forOrder(2);

        $this->assertCount(2, $plans);
        $this->assertSame('SP-2606-005', $plans->first()->code);
        $this->assertSame('SO-2606-002', $plans->first()->order_code);
    }

    public function test_unshipped_qty_and_active_for_forecast(): void
    {
        $plan = ShipmentPlan::find(
            (int) ShipmentPlanRecord::query()->where('code', 'SP-2606-001')->value('id')
        );
        $this->assertNotNull($plan);

        $this->assertSame(120.0, ShipmentPlan::unshippedQty($plan));
        $this->assertTrue(ShipmentPlan::isActiveForForecast($plan));
    }
}
