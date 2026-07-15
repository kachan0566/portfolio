<?php

namespace Tests\Feature;

use App\Models\ShipmentPlanRecord;
use App\Support\ShipmentPlan;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\ShipmentPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentPlanSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedShipmentPlans(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(ShipmentPlanSeeder::class);
    }

    public function test_seeder_creates_demo_plans(): void
    {
        $this->seedShipmentPlans();

        $this->assertSame(count(ShipmentPlan::demoRows()), ShipmentPlanRecord::query()->count());

        $plan = ShipmentPlanRecord::query()->where('code', 'SP-2606-001')->first();
        $this->assertNotNull($plan);
        $this->assertSame(2, $plan->order_id);
        $this->assertSame(120.0, (float) $plan->confirmed_qty_m);
        $this->assertGreaterThan(0, (float) $plan->confirmed_qty_tan);
    }

    public function test_support_all_reads_from_database(): void
    {
        $this->seedShipmentPlans();

        $plans = ShipmentPlan::all();

        $this->assertCount(5, $plans);
        $this->assertSame('SP-2606-005', $plans[4]->code);
    }
}
