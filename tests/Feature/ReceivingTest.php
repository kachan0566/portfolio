<?php

namespace Tests\Feature;

use App\Support\DemoData;
use App\Support\DemoOverlay;
use App\Support\DemoState;
use App\Support\FabricTanRoll;
use App\Support\GreigeInventory;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use App\Support\YarnInventory;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DemoOverlay::clear();
        $this->clearDemoStorage();
        FabricTanRoll::resetBootstrap();
    }

    private function clearDemoStorage(): void
    {
        foreach (glob(storage_path('app/*_state.json')) ?: [] as $file) {
            @unlink($file);
        }
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

    public function test_yarn_receiving_increases_stock(): void
    {
        $before = YarnInventory::effectiveStockKg(1);

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::YARN,
            'po_id' => 10,
            'qty' => 100,
            'date' => '2026-06-26',
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + 100.0, YarnInventory::effectiveStockKg(1));
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
