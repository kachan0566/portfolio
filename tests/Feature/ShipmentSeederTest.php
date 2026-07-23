<?php

namespace Tests\Feature;

use App\Models\ProductRoll;
use App\Models\Shipment;
use App\Support\DemoData;
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

class ShipmentSeederTest extends TestCase
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

    public function test_seeder_creates_demo_shipments(): void
    {
        $this->assertSame(4, Shipment::query()->count());
        $this->assertDatabaseHas('shipments', ['code' => 'SH-2606-001']);

        $first = Shipment::query()->where('code', 'SH-2606-001')->first();
        $this->assertNotNull($first);
        $this->assertGreaterThan(0, (int) $first->qty_m);
        $this->assertGreaterThan(0, $first->allocations()->count());
    }

    public function test_seeder_marks_product_one_rolls_shipped(): void
    {
        $inStock = ProductRoll::query()
            ->where('product_id', 1)
            ->where('status', ProductRoll::STATUS_IN_STOCK)
            ->count();
        $shipped = ProductRoll::query()
            ->where('product_id', 1)
            ->where('status', ProductRoll::STATUS_SHIPPED)
            ->count();

        $this->assertSame(0, $inStock);
        $this->assertSame(4, $shipped);
    }

    public function test_display_list_returns_shipments_from_database(): void
    {
        $shipments = DemoData::shipments();

        $this->assertCount(4, $shipments);
        $this->assertSame('SH-2606-004', $shipments->first()->code);
        $this->assertSame('SO-2606-006', $shipments->first()->order_code);
    }
}
