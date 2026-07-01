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

    public function test_index_shows_arrival_inline_form(): void
    {
        $response = $this->get(route('purchases.index'));

        $response->assertOk();
        $response->assertSee('入荷予定日', false);
        $response->assertSee('name="expected_arrival_date"', false);
        $response->assertSee('name="arrival_memo"', false);
        $response->assertSee('染工場から6/16上がり連絡あり', false);
    }

    public function test_patch_arrival_persists_product_finish_date_and_memo(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-2606-002');
        $this->assertNotNull($po);

        $response = $this->patch(route('purchases.patch-arrival', $po->id), [
            'expected_arrival_date' => '2026-06-20',
            'arrival_memo' => 'テスト用メモ',
        ]);

        $response->assertRedirect(route('purchases.index'));
        $response->assertSessionHas('success');

        $overrides = PurchaseOrderOverlay::overrides((int) $po->id);
        $this->assertSame('2026-06-20', $overrides['finish_date']);
        $this->assertSame('テスト用メモ', $overrides['arrival_memo']);

        $index = $this->get(route('purchases.index'));
        $index->assertSee('value="2026-06-20"', false);
        $index->assertSee('テスト用メモ', false);
    }

    public function test_patch_arrival_persists_yarn_due_date_and_memo(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('type', PurchaseOrderType::YARN);
        $this->assertNotNull($po);

        $response = $this->patch(route('purchases.patch-arrival', $po->id), [
            'expected_arrival_date' => '2026-06-25',
            'arrival_memo' => '糸入荷メモ',
        ]);

        $response->assertRedirect(route('purchases.index'));

        $overrides = PurchaseOrderOverlay::overrides((int) $po->id);
        $this->assertSame('2026-06-25', $overrides['due_date']);
        $this->assertSame('糸入荷メモ', $overrides['arrival_memo']);
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
