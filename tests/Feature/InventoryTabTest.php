<?php

namespace Tests\Feature;

use App\Support\GreigeInventory;
use App\Support\YarnInventory;
use Tests\TestCase;

class InventoryTabTest extends TestCase
{
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
        $this->assertSame(200, GreigeInventory::totalMetersForSku('KB-T'));
    }

    public function test_yarn_inventory_shows_base_stock(): void
    {
        $this->assertSame(800.0, YarnInventory::effectiveStockKg(1));
        $this->assertSame(650.0, YarnInventory::effectiveStockKg(2));
    }
}
