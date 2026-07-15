<?php

namespace Tests\Feature;

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
        $this->assertTrue(DemoData::usesShipmentDatabase());
        $this->assertSame(4, Shipment::query()->count());
    }
}
