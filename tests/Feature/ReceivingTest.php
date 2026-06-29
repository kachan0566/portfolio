<?php

namespace Tests\Feature;

use App\Support\DemoData;
use App\Support\DemoOverlay;
use App\Support\DemoState;
use App\Support\GreigeInventory;
use App\Support\PurchaseOrderType;
use App\Support\YarnInventory;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DemoOverlay::clear();
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

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::GREIGE,
            'po_id' => 4,
            'qty' => 150,
            'date' => '2026-06-26',
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + 150, GreigeInventory::totalMetersForSku('KB-A'));
    }

    public function test_product_receiving_increases_stock(): void
    {
        $before = DemoState::effectiveStock(6);

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'po_id' => 7,
            'qty' => 20,
            'date' => '2026-06-26',
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + 20, DemoState::effectiveStock(6));
    }
}
