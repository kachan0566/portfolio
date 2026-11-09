<?php

namespace Tests\Feature\Purchase;

use App\Models\Master\Greige;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Support\Inventory\GreigeSupply;
use App\Support\Purchase\PurchaseOrderStatus;
use App\Support\Purchase\PurchaseOrderType;
use App\Support\Qty\QtyHelper;
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

    /**
     * 0.25反の生機発注を保存し、発注明細に小数反数が残ることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_greige_accepts_quarter_tan(): void
    {
        $response = $this->post(route('purchases.store'), [
            'type' => PurchaseOrderType::GREIGE,
            'supplier_id' => 4,
            'ship_to_id' => 2,
            'greige_sku' => 'KB-A',
            'qty_tan' => 0.25,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
            'save_action' => 'draft',
        ]);

        $response->assertSessionHasNoErrors();

        $line = PurchaseOrderLine::query()->latest('id')->firstOrFail();
        $this->assertSame(0.25, (float) $line->qty_tan);
        $this->assertSame(25.0, (float) $line->qty_meters);
    }

    /**
     * 0.25の倍数ではない0.3反を生機発注として保存しないことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_greige_rejects_tan_outside_quarter_step(): void
    {
        $before = PurchaseOrder::query()->count();

        $response = $this->post(route('purchases.store'), [
            'type' => PurchaseOrderType::GREIGE,
            'supplier_id' => 4,
            'ship_to_id' => 2,
            'greige_sku' => 'KB-A',
            'qty_tan' => 0.3,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
            'save_action' => 'draft',
        ]);

        $response->assertSessionHasErrors('lines.0.qty_tan');
        $this->assertSame($before, PurchaseOrder::query()->count());
    }

    /**
     * 生機反が足りない製品発注は保存せず、反数の不足エラーを発注反数欄へ返すことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_product_rejects_when_greige_tan_is_short(): void
    {
        $before = PurchaseOrder::query()->count();

        $response = $this->post(route('purchases.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
            'save_action' => 'ordered',
            'lines' => [
                ['product_id' => 6, 'qty_meters' => 500000],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.product_qty_tan');
        $message = (string) session('errors')->first('lines.0.product_qty_tan');
        $this->assertStringContainsString('反 不足しています', $message);
        $this->assertStringNotContainsString('m 不足しています', $message);
        $this->assertSame($before, PurchaseOrder::query()->count());
    }

    /**
     * 生機が足りる製品発注は保存され、発注件数が1件増えることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_product_succeeds_when_greige_tan_is_enough(): void
    {
        $greige = Greige::findBySku('KB-A');
        $this->assertNotNull($greige);

        $greigePo = PurchaseOrder::query()->create([
            'code' => 'PO-G-ENOUGH-001',
            'type' => PurchaseOrderType::GREIGE,
            'status' => PurchaseOrderStatus::ORDERED,
            'supplier_id' => 4,
            'ship_to_id' => 2,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $greigePo->id,
            'line_no' => 1,
            'greige_id' => $greige->id,
            'qty_tan' => 10,
            'qty_meters' => 1000,
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
        ]);

        $before = PurchaseOrder::query()->count();
        $response = $this->post(route('purchases.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
            'save_action' => 'ordered',
            'lines' => [
                ['product_id' => 1, 'qty_meters' => 12.5],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertSame($before + 1, PurchaseOrder::query()->count());
    }

    /**
     * 同じ生機を使い切る製品発注が既にあるとき、2件目の製品発注を保存しないことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_product_rejects_when_another_product_po_already_reserved_greige(): void
    {
        $greige = Greige::findBySku('KB-A');
        $this->assertNotNull($greige);

        $greigePo = PurchaseOrder::query()->create([
            'code' => 'PO-G-RESERVE-001',
            'type' => PurchaseOrderType::GREIGE,
            'status' => PurchaseOrderStatus::ORDERED,
            'supplier_id' => 4,
            'ship_to_id' => 2,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $greigePo->id,
            'line_no' => 1,
            'greige_id' => $greige->id,
            'qty_tan' => 10,
            'qty_meters' => 1000,
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
        ]);

        $productId = 1;
        $availableProductTan = QtyHelper::productTanFromGreigeTan(
            GreigeSupply::availableTan('KB-A'),
            $productId
        );
        $this->assertGreaterThan(0, $availableProductTan);

        $firstPo = PurchaseOrder::query()->create([
            'code' => 'PO-P-RESERVE-001',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => PurchaseOrderStatus::ORDERED,
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $firstPo->id,
            'line_no' => 1,
            'product_id' => $productId,
            'qty_tan' => $availableProductTan,
            'qty_meters' => QtyHelper::metersFromTan($availableProductTan, $productId),
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
        ]);

        $before = PurchaseOrder::query()->count();
        $response = $this->post(route('purchases.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
            'save_action' => 'ordered',
            'lines' => [
                ['product_id' => $productId, 'qty_meters' => 12.5],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.product_qty_tan');
        $this->assertSame($before, PurchaseOrder::query()->count());
    }

    /**
     * 生機を使い切っている製品発注でも、自分を除外した更新は不足にならないことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_update_product_does_not_treat_own_reservation_as_shortage(): void
    {
        $greige = Greige::findBySku('KB-A');
        $this->assertNotNull($greige);

        $greigePo = PurchaseOrder::query()->create([
            'code' => 'PO-G-UPDATE-001',
            'type' => PurchaseOrderType::GREIGE,
            'status' => PurchaseOrderStatus::ORDERED,
            'supplier_id' => 4,
            'ship_to_id' => 2,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $greigePo->id,
            'line_no' => 1,
            'greige_id' => $greige->id,
            'qty_tan' => 10,
            'qty_meters' => 1000,
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
        ]);

        $productId = 1;
        $availableProductTan = QtyHelper::productTanFromGreigeTan(
            GreigeSupply::availableTan('KB-A'),
            $productId
        );
        $this->assertGreaterThan(0, $availableProductTan);

        $productPo = PurchaseOrder::query()->create([
            'code' => 'PO-P-UPDATE-001',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => PurchaseOrderStatus::ORDERED,
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $productPo->id,
            'line_no' => 1,
            'product_id' => $productId,
            'qty_tan' => $availableProductTan,
            'qty_meters' => QtyHelper::metersFromTan($availableProductTan, $productId),
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
        ]);

        $response = $this->put(route('purchases.update', $productPo->id), [
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
            'status' => PurchaseOrderStatus::ORDERED,
            'arrival_memo' => '再保存',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('purchases.show', $productPo->id));
        $productPo->refresh();
        $this->assertSame(PurchaseOrderStatus::ORDERED, $productPo->status);
        $this->assertSame('再保存', $productPo->arrival_memo);
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
