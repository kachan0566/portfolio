<?php

namespace Tests\Feature;

use App\Models\PurchaseOrderLine;
use App\Services\Inventory\GreigeDyeInput;
use App\Support\GreigeInventory;
use App\Support\GreigeRoll;
use App\Support\PurchaseOrderStages;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GreigeDyeInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function seedInventoryBase(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_bootstrap_moves_greige_to_in_dyeing_for_staged_product_lines(): void
    {
        $this->seedInventoryBase();

        $beforeStock = GreigeRoll::stockMetersForSku('KB-T');
        $this->assertGreaterThan(0, $beforeStock);

        GreigeDyeInput::bootstrapIfNeeded();

        $afterStock = GreigeRoll::stockMetersForSku('KB-T');
        $this->assertLessThan($beforeStock, $afterStock);

        $inDyeing = GreigeRoll::all();
        $this->assertTrue(
            collect($inDyeing)->contains(fn ($roll) => ($roll['status'] ?? '') === GreigeRoll::STATUS_IN_DYEING)
        );
    }

    public function test_apply_line_stage_change_reverts_greige_to_stock(): void
    {
        $this->seedInventoryBase();
        GreigeDyeInput::bootstrapIfNeeded();

        $line = PurchaseOrderLine::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('code', 'PO-2606-002'))
            ->where('stage', PurchaseOrderStages::PRODUCT_DYEING)
            ->first();
        $this->assertNotNull($line);
        $line->update(['received_qty_m' => 0]);
        $line->refresh();

        $stockBefore = GreigeRoll::stockMetersForSku(
            (string) ($line->product?->greige?->sku ?? '')
        );

        GreigeDyeInput::applyLineStageChange($line, null);

        $line->refresh();
        $this->assertNull($line->stage);
        $this->assertGreaterThan($stockBefore, GreigeRoll::stockMetersForSku(
            (string) ($line->product?->greige?->sku ?? '')
        ));
    }

    public function test_update_product_po_blocks_dyeing_when_greige_short(): void
    {
        $this->seedInventoryBase();
        GreigeDyeInput::bootstrapIfNeeded();

        $line = PurchaseOrderLine::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('code', 'PO-2606-009'))
            ->first();
        $this->assertNotNull($line);

        GreigeDyeInput::revertLine($line);
        $line->update(['stage' => null, 'received_qty_m' => 0]);
        $line->refresh();

        $greigeSku = (string) ($line->product?->greige?->sku ?? '');
        foreach (GreigeRoll::inStockForSku($greigeSku) as $roll) {
            GreigeRoll::update((int) $roll->id, ['status' => GreigeRoll::STATUS_IN_DYEING]);
        }

        $error = GreigeDyeInput::applyLineStageChange($line, PurchaseOrderStages::PRODUCT_DYEING);

        $this->assertNotNull($error);
        $this->assertStringContainsString('不足', $error);
    }

    public function test_greige_inventory_excludes_in_dyeing_rolls(): void
    {
        $this->seedInventoryBase();

        $inventoryBefore = GreigeInventory::totalMetersForSku('KB-T');

        GreigeDyeInput::bootstrapIfNeeded();

        $inventoryAfter = GreigeInventory::totalMetersForSku('KB-T');
        $this->assertLessThanOrEqual($inventoryBefore, $inventoryAfter);
    }
}
