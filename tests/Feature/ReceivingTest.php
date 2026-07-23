<?php

namespace Tests\Feature;

use App\Models\ReceivingLine;
use App\Models\YarnStockMovement;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\YarnMovementType;
use App\Support\DemoState;
use App\Support\FabricTanRoll;
use App\Support\GreigeInventory;
use App\Support\ProductRoll as ProductRollSupport;
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

class ReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProductRollSupport::resetCacheForTesting();
        FabricTanRoll::resetBootstrap();
        $this->seedBase();
    }

    private function seedBase(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(CostFoundationSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_receiving_index_shows_three_types(): void
    {
        $response = $this->get(route('receivings.index'));

        $response->assertOk();
        $response->assertSee('糸発注');
        $response->assertSee('生機発注');
        $response->assertSee('RC-2606-005');
        $response->assertSee('RC-2606-002');
    }

    public function test_yarn_receiving_registers_movements_and_po_line(): void
    {
        $before = YarnInventory::effectiveStockKg(1);

        $result = ReceivingRegistrar::register(
            10,
            '2026-06-26',
            PurchaseOrderType::YARN,
            qtyKg: 100.0,
        );

        // 織工場入荷は入庫と消費が同量で相殺され、在庫合計は変わらない
        $this->assertSame($before, YarnInventory::effectiveStockKg(1));

        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', $result['code']))
            ->first();
        $this->assertNotNull($line);
        $this->assertSame(100.0, (float) $line->qty_kg);

        $this->assertDatabaseHas('yarn_stock_movements', [
            'material_id' => 1,
            'movement_type' => YarnMovementType::RECEIVING,
            'qty_kg' => 100.0,
        ]);
        $this->assertDatabaseHas('yarn_stock_movements', [
            'material_id' => 1,
            'movement_type' => YarnMovementType::CONSUMPTION,
            'qty_kg' => -100.0,
        ]);
    }

    public function test_greige_receiving_shows_in_inventory(): void
    {
        $before = GreigeInventory::totalMetersForSku('KB-A');
        $remaining = (int) floor(DemoState::poRemaining(4));
        $qty = min(150, max(1, $remaining));

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::GREIGE,
            'po_id' => 4,
            'qty_tan' => QtyHelper::tanCount($qty, null, true, 'KB-A'),
            'qty_meters' => $qty,
            'date' => '2026-06-26',
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + $qty, GreigeInventory::totalMetersForSku('KB-A'));
    }

    public function test_product_receiving_increases_stock(): void
    {
        $before = DemoState::effectiveStock(6);
        $remaining = (int) floor(DemoState::poRemaining(7));
        $qty = min(20, max(1, $remaining));

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'po_id' => 7,
            'qty_tan' => QtyHelper::tanCount($qty, 6),
            'qty_meters' => $qty,
            'date' => '2026-06-26',
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + $qty, DemoState::effectiveStock(6));
    }
}
