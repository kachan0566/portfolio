<?php

namespace Tests\Feature;

use App\Models\OrderAllocation;
use App\Support\DemoData;
use App\Support\StockAllocation;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAllocationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StockAllocation::resetCacheForTesting();
    }

    private function seedAllocations(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
    }

    public function test_order_allocation_seeder_matches_demo_base_rows(): void
    {
        $this->seedAllocations();

        $this->assertSame(DemoData::baseAllocationRows()->count(), OrderAllocation::query()->count());

        $row = OrderAllocation::query()
            ->where('order_id', 2)
            ->where('allocation_type', OrderAllocation::TYPE_PO)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(2, $row->purchase_order_id);
        $this->assertSame(2.4, (float) $row->qty_tan);
        $this->assertSame(120, $row->qty_m);
    }

    public function test_stock_allocation_reads_from_database_after_seed(): void
    {
        $this->seedAllocations();

        $this->assertTrue(DemoData::usesOrderAllocationDatabase());
        $this->assertSame(9, count(StockAllocation::allLines()));
        $this->assertSame(120, StockAllocation::poAllocatedForOrder(2));
        $this->assertSame(80, StockAllocation::stockAllocatedForOrder(2));
    }

    public function test_order_show_page_reflects_seeded_allocations(): void
    {
        $this->seedAllocations();

        $response = $this->get(route('orders.show', 10));

        $response->assertOk();
        $response->assertSee('SO-2606-010');
        $response->assertSee('引当');
    }
}
