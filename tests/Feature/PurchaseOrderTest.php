<?php

namespace Tests\Feature;

use App\Support\DemoData;
use App\Support\PurchaseOrderOverlay;
use App\Support\PurchaseOrderType;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PurchaseOrderOverlay::clear();
    }

    public function test_index_lists_three_purchase_types(): void
    {
        $response = $this->get(route('purchases.index'));

        $response->assertOk();
        $response->assertSee('糸発注');
        $response->assertSee('生機発注');
        $response->assertSee('製品発注');
        $response->assertSee('PO-Y-2606-001');
        $response->assertSee('PO-G-2606-001');
        $response->assertSee('PO-2606-001');
    }

    public function test_greige_create_form_shows_loss_preview_fields(): void
    {
        $response = $this->get(route('purchases.create', ['type' => 'greige']));

        $response->assertOk();
        $response->assertSee('必要糸量（プレビュー）');
        $response->assertSee('下書き保存');
    }

    public function test_store_greige_draft_persists_in_session(): void
    {
        $response = $this->post(route('purchases.store'), [
            'type' => PurchaseOrderType::GREIGE,
            'supplier_id' => 4,
            'ship_to_id' => 2,
            'greige_sku' => 'KB-A',
            'qty_tan' => 1,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
            'save_action' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertCount(1, PurchaseOrderOverlay::additions());

        $index = $this->get(route('purchases.index'));
        $index->assertSee('PO-G-');
        $index->assertSee('下書き');
    }
}
