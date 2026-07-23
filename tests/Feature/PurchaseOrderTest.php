<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Support\PurchaseOrderType;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(CostFoundationSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
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

    public function test_patch_arrival_persists_greige_finish_date_without_changing_due_date(): void
    {
        $po = PurchaseOrder::query()->where('code', 'PO-G-2606-001')->first();
        $this->assertNotNull($po);
        $originalDueDate = $po->due_date?->toDateString();

        $response = $this->patch(route('purchases.patch-arrival', $po->id), [
            'expected_arrival_date' => '2026-06-24',
            'arrival_memo' => '染工場入荷見込み',
        ]);

        $response->assertRedirect(route('purchases.index'));
        $response->assertSessionHas('success');

        $po->refresh()->load('lines');
        $this->assertSame($originalDueDate, $po->due_date?->toDateString());
        $this->assertSame('2026-06-24', $po->primaryLine()?->finish_date?->toDateString());
        $this->assertSame('染工場入荷見込み', $po->arrival_memo);
    }

    public function test_patch_arrival_persists_product_finish_date_and_memo(): void
    {
        $po = PurchaseOrder::query()->where('code', 'PO-2606-002')->first();
        $this->assertNotNull($po);

        $response = $this->patch(route('purchases.patch-arrival', $po->id), [
            'expected_arrival_date' => '2026-06-20',
            'arrival_memo' => 'テスト用メモ',
        ]);

        $response->assertRedirect(route('purchases.index'));
        $response->assertSessionHas('success');

        $po->refresh()->load('lines');
        $this->assertSame('テスト用メモ', $po->arrival_memo);
        $this->assertSame('2026-06-20', $po->primaryLine()?->finish_date?->toDateString());

        $index = $this->get(route('purchases.index'));
        $index->assertSee('value="2026-06-20"', false);
        $index->assertSee('テスト用メモ', false);
    }

    public function test_patch_arrival_persists_yarn_due_date_and_memo(): void
    {
        $po = PurchaseOrder::query()->where('type', PurchaseOrderType::YARN)->first();
        $this->assertNotNull($po);

        $response = $this->patch(route('purchases.patch-arrival', $po->id), [
            'expected_arrival_date' => '2026-06-25',
            'arrival_memo' => '糸入荷メモ',
        ]);

        $response->assertRedirect(route('purchases.index'));

        $po->refresh();
        $this->assertSame('2026-06-25', $po->due_date?->toDateString());
        $this->assertSame('糸入荷メモ', $po->arrival_memo);
    }

    public function test_greige_create_form_shows_loss_preview_fields(): void
    {
        $response = $this->get(route('purchases.create', ['type' => 'greige']));

        $response->assertOk();
        $response->assertSee('必要糸量（プレビュー・合計）');
        $response->assertSee('下書き保存');
    }

    public function test_store_greige_draft_persists_in_database(): void
    {
        $before = PurchaseOrder::query()->count();

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
        $this->assertSame($before + 1, PurchaseOrder::query()->count());

        $index = $this->get(route('purchases.index'));
        $index->assertSee('PO-G-');
        $index->assertSee('下書き');
    }

    public function test_update_greige_persists_finish_date_separately_from_due_date(): void
    {
        $po = PurchaseOrder::query()->where('code', 'PO-G-2606-003')->first();
        $this->assertNotNull($po);
        $line = $po->primaryLine();
        $this->assertNotNull($line);

        $response = $this->put(route('purchases.update', $po->id), [
            'supplier_id' => $po->supplier_id,
            'ship_to_id' => $po->ship_to_id,
            'order_date' => $po->order_date?->toDateString(),
            'due_date' => '2026-07-10',
            'status' => $po->status,
            'finish_date' => '2026-07-03',
            'arrival_memo' => '入荷予定更新',
        ]);

        $response->assertRedirect(route('purchases.show', $po->id));

        $po->refresh()->load('lines');
        $this->assertSame('2026-07-10', $po->due_date?->toDateString());
        $this->assertSame('2026-07-03', $po->primaryLine()?->finish_date?->toDateString());
        $this->assertSame('入荷予定更新', $po->arrival_memo);
    }
}
