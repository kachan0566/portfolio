<?php

namespace Tests\Feature;

use App\Support\DemoData;
use App\Support\GreigeInventory;
use App\Support\GreigeRoll;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use App\Support\YarnInventory;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTabTest extends TestCase
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
        $this->seed(CostFoundationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }
    public function test_inventory_has_yarn_tab(): void
    {
        $response = $this->get(route('inventory.index', ['tab' => 'yarn']));

        $response->assertOk();
        $response->assertSee('糸在庫');
        $response->assertSee('RM-001');
        $response->assertSee('利用可能');
        $response->assertSee('発注残');
    }

    public function test_greige_inventory_from_greige_po_receiving(): void
    {
        $entries = GreigeInventory::entries();

        $this->assertTrue($entries->contains(fn ($e) => $e->po_code === 'PO-G-2606-002'));
        // 染機投入済の既存明細へ bootstrap で生機が引当済みのため、物理在庫は 0m
        $this->assertSame(0, (int) GreigeRoll::stockMetersForSku('KB-T'));
    }

    public function test_yarn_inventory_shows_base_stock(): void
    {
        $this->assertSame(800.0, YarnInventory::effectiveStockKg(1));
        $this->assertSame(500.0, YarnInventory::effectiveStockKg(2));
    }
}
