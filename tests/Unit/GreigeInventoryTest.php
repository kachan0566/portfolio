<?php

namespace Tests\Unit;

use App\Support\GreigeInventory;
use App\Support\GreigeSupply;
use Tests\TestCase;

class GreigeInventoryTest extends TestCase
{
    public function test_entries_based_on_greige_po_received_qty(): void
    {
        $entry = GreigeInventory::entries()->firstWhere('po_code', 'PO-G-2606-002');

        $this->assertNotNull($entry);
        $this->assertSame('KB-T', $entry->greige_sku);
        $this->assertSame(200, $entry->qty_meters);
    }

    public function test_legacy_product_po_not_in_greige_entries(): void
    {
        $this->assertNull(
            GreigeInventory::entries()->firstWhere('po_code', 'PO-2606-003')
        );
    }

    public function test_greige_supply_uses_physical_and_on_order(): void
    {
        $sku = 'KB-T';
        $physical = GreigeSupply::dyeFactoryMeters($sku);
        $onOrder = GreigeSupply::greigePoRemainingMeters($sku);

        $this->assertSame(200, $physical);
        $this->assertGreaterThanOrEqual(0, $onOrder);
        $this->assertSame($physical + $onOrder, GreigeSupply::availableMeters($sku));
    }
}
