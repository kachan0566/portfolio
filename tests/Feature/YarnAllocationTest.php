<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\YarnAllocation;
use App\Support\PurchaseOrderType;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YarnAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_foundation_seeder_creates_greige_yarn_allocations(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(CostFoundationSeeder::class);

        $greigePoCount = PurchaseOrder::query()
            ->where('type', PurchaseOrderType::GREIGE)
            ->count();

        $this->assertGreaterThan(0, $greigePoCount);
        $this->assertGreaterThan(0, YarnAllocation::query()->count());
    }
}
