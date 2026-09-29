<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderAllocation;
use App\Support\StockAllocation;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 受注引当の書き込み（DB 保存）が意図どおり動くかを Feature テストで確認する。
 *
 * StockAllocation クラスと受注画面の save-allocation ルートを対象とする。
 * 各テストは RefreshDatabase で空の DB にシードを流し、order_allocations 表の行を検証する。
 */
class StockAllocationWriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 引当なしの受注・発注までを DB に投入する（引当シードは含めない）。
     *
     * 処理の流れ:
     * 1. マスタ基盤 → 品番カタログ → 受注 → 発注の順に Seeder を実行する
     *
     * @return void PHPUnit のテスト用ヘルパーのため戻り値は使わない
     */
    private function seedOrdersOnly(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
    }

    /**
     * デモ用の受注引当行まで含めて DB を初期化する。
     *
     * seedOrdersOnly のあと OrderAllocationSeeder で order_allocations 表に行を入れる。
     *
     * @return void PHPUnit のテスト用ヘルパーのため戻り値は使わない
     */
    private function seedAllocations(): void
    {
        $this->seedOrdersOnly();
        $this->seed(OrderAllocationSeeder::class);
    }

    /**
     * 引当シード前は受注だけ存在し、引当表は空であることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_order_tables_ready_after_seed(): void
    {
        $this->seedOrdersOnly();

        $this->assertGreaterThan(0, Order::query()->count());
        $this->assertSame(0, OrderAllocation::query()->count());
    }

    /**
     * addLine で 1 行追加した引当が order_allocations 表に残ることを確認する。
     *
     * 品番 3・受注 10・現在庫引当（発注 ID なし）で 1.0 反を追加し、
     * qty_m が品番の換算（50m）と一致するかを見る。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_add_line_persists_to_database(): void
    {
        $this->seedOrdersOnly();

        StockAllocation::addLine(3, 10, 0, 1.0, StockAllocation::TYPE_STOCK);

        $row = OrderAllocation::query()
            ->where('order_id', 10)
            ->where('allocation_type', StockAllocation::TYPE_STOCK)
            ->whereNull('purchase_order_id')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(50.0, (float) $row->qty_m);
        $this->assertSame(1.0, (float) $row->qty_tan);
    }

    /**
     * saveFromTypedMaps で品番 3・受注 2 の引当を保存したとき DB の件数と合計 m が期待どおりか確認する。
     *
     * 引当シード済みの状態から、受注 2 分だけ typed マップを渡して保存する。
     * 品番 3 の行数が 2 件になり、受注 2 の現在庫・発注引当が各 50m になることを assert する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_save_from_typed_maps_replaces_product_rows_in_database(): void
    {
        $this->seedAllocations();

        StockAllocation::saveFromTypedMaps(3, [
            2 => [
                StockAllocation::TYPE_STOCK => [2 => 1.0],
                StockAllocation::TYPE_PO => [2 => 1.0],
            ],
        ]);

        $this->assertSame(
            2,
            OrderAllocation::query()->where('product_id', 3)->count()
        );
        $this->assertSame(
            50.0,
            StockAllocation::stockAllocatedForOrder(2)
        );
        $this->assertSame(
            50.0,
            StockAllocation::poAllocatedForOrder(2)
        );
    }

    /**
     * clearForOrder で指定受注の引当行だけが order_allocations から消えることを確認する。
     *
     * シード後に受注 2 の引当をすべて解除し、該当 order_id の行数と StockAllocation::get が 0 になるかを見る。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_clear_for_order_removes_rows_from_database(): void
    {
        $this->seedAllocations();

        StockAllocation::clearForOrder(2);

        $this->assertSame(0, OrderAllocation::query()->where('order_id', 2)->count());
        $this->assertSame(0.0, StockAllocation::get(2));
    }

    /**
     * removeLineFromOrder で受注 2 の発注引当 1 行だけ削除され、現在庫引当は残ることを確認する。
     *
     * 発注 ID 2・TYPE_PO の行を削除し、その行が null になり、
     * 現在庫 100m・発注引当 0m の合計状態になることを assert する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_remove_line_from_order_removes_single_row_from_database(): void
    {
        $this->seedAllocations();

        StockAllocation::removeLineFromOrder(2, 2, StockAllocation::TYPE_PO);

        $this->assertNull(
            OrderAllocation::query()
                ->where('order_id', 2)
                ->where('purchase_order_id', 2)
                ->where('allocation_type', StockAllocation::TYPE_PO)
                ->first()
        );
        $this->assertSame(100.0, StockAllocation::stockAllocatedForOrder(2));
        $this->assertSame(0.0, StockAllocation::poAllocatedForOrder(2));
    }

    /**
     * 0.25反の発注引当を有効な入力として受け付けることを確認する。
     *
     * 品番7・受注9に対し、発注9から0.25反を引き当てる入力を検証する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_validate_submission_accepts_quarter_tan_allocation(): void
    {
        $this->seedAllocations();

        $error = StockAllocation::validateSubmission(7, [
            9 => [
                StockAllocation::TYPE_PO => [9 => 0.25],
            ],
        ]);

        $this->assertNull($error);
    }

    /**
     * 0.25の倍数ではない0.3反を引当入力として拒否することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_validate_submission_rejects_allocation_outside_quarter_step(): void
    {
        $this->seedAllocations();

        $error = StockAllocation::validateSubmission(7, [
            9 => [
                StockAllocation::TYPE_PO => [9 => 0.3],
            ],
        ]);

        $this->assertSame(
            '受注 SO-2606-009 の引当反数は0.25反刻みで入力してください。',
            $error
        );
    }

    /**
     * 受注詳細の save-allocation POST が成功し、発注引当が DB に反映されることを確認する。
     *
     * SO-2606-009 に対し allocations フォーム形式で POST し、
     * リダイレクト・成功メッセージのあと poAllocatedForOrder が 100m になるかを見る。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_order_save_allocation_route_persists_to_database(): void
    {
        $this->seedAllocations();

        $order = Order::query()->where('code', 'SO-2606-009')->firstOrFail();

        $response = $this->post(route('orders.save-allocation', $order->id), [
            'allocations' => [
                $order->id => [
                    StockAllocation::TYPE_PO => ['9' => 2],
                ],
            ],
        ]);

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success', '引当を更新しました。');

        $this->assertSame(
            100.0,
            StockAllocation::poAllocatedForOrder($order->id)
        );
    }
}
