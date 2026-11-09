<?php

namespace Tests\Unit;

use App\Models\Master\Greige;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Receiving\Receiving;
use App\Models\Receiving\ReceivingLine;
use App\Services\Inventory\GreigeDyeInput;
use App\Support\Inventory\GreigeRoll;
use App\Support\Inventory\GreigeSupply;
use App\Support\Purchase\PurchaseOrderStages;
use App\Support\Purchase\PurchaseOrderStatus;
use App\Support\Purchase\PurchaseOrderType;
use App\Support\Qty\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 製品発注の生機予約が、利用可能量から引かれることを確認する。
 */
class GreigeSupplyAllocationTest extends TestCase
{
    use RefreshDatabase;

    private const GREIGE_SKU = 'KB-A';

    private const PRODUCT_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        GreigeDyeInput::resetBootstrapForTesting();
    }

    /**
     * 製品2反（50m/反）の予約が、生機1反（100m/反）として計上されることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_allocated_greige_tan_converts_two_product_tan_to_one_greige_tan(): void
    {
        $this->createGreigePo(10.0);
        $this->createProductPo(2.0);

        $this->assertSame(100.0, GreigeSupply::allocatedGreigeMeters(self::GREIGE_SKU));
        $this->assertSame(1.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
    }

    /**
     * 生機10反を製品発注が上限ちょうど使うと、利用可能反が 0 になることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_available_tan_is_zero_when_product_po_uses_all_greige(): void
    {
        $this->createGreigePo(10.0);
        $this->createProductPo(20.0);

        $this->assertSame(10.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
        $this->assertSame(0.0, GreigeSupply::availableTan(self::GREIGE_SKU));
        $this->assertNotNull(GreigeSupply::shortageTan(self::PRODUCT_ID, 0.25));
    }

    /**
     * 編集中の製品発注は予約合計から除外し、自身の必要量では不足にならないことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_exclude_product_po_id_does_not_count_self(): void
    {
        $this->createGreigePo(10.0);
        $productPo = $this->createProductPo(20.0);

        $this->assertSame(0.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU, $productPo->id));
        $this->assertSame(10.0, GreigeSupply::availableTan(self::GREIGE_SKU, $productPo->id));
        $this->assertNull(GreigeSupply::shortageTan(self::PRODUCT_ID, 20.0, $productPo->id));
        $this->assertNotNull(GreigeSupply::shortageTan(self::PRODUCT_ID, 20.0, null));
    }

    /**
     * 下書きの製品発注は生機を予約せず、同じ量の発注済がまだ通ることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_draft_product_po_does_not_allocate(): void
    {
        $this->createGreigePo(10.0);
        $this->createProductPo(20.0, PurchaseOrderStatus::DRAFT);

        $this->assertSame(0.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
        $this->assertSame(10.0, GreigeSupply::availableTan(self::GREIGE_SKU));
        $this->assertTrue(GreigeSupply::canFulfillProductTan(self::PRODUCT_ID, 20.0));
    }

    /**
     * 染機投入済の製品発注は予約に含めず、在庫減と予約減が二重にならないことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_dyeing_product_po_does_not_allocate_or_double_subtract(): void
    {
        $greigePo = $this->createGreigePo(10.0, PurchaseOrderStatus::RECEIVED, 10.0);
        $this->createInStockRolls($greigePo, 10.0);
        $productPo = $this->createProductPo(8.0);
        $line = $productPo->primaryLine();
        $this->assertNotNull($line);

        $error = GreigeDyeInput::applyLineStageChange($line, PurchaseOrderStages::PRODUCT_DYEING);
        $this->assertNull($error);

        $stockTan = GreigeSupply::dyeFactoryTan(self::GREIGE_SKU);
        $this->assertGreaterThan(0.0, $stockTan);
        $this->assertLessThan(10.0, $stockTan);
        $this->assertSame(0.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
        $this->assertSame($stockTan, GreigeSupply::availableTan(self::GREIGE_SKU));
    }

    /**
     * 染工場在庫を使い切る量を予約した製品発注でも、染機投入できることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_dyeing_succeeds_when_product_po_reserved_all_stock(): void
    {
        $greigePo = $this->createGreigePo(10.0, PurchaseOrderStatus::RECEIVED, 10.0);
        $this->createInStockRolls($greigePo, 10.0);
        $productPo = $this->createProductPo(20.0);
        $line = $productPo->primaryLine();
        $this->assertNotNull($line);

        $error = GreigeDyeInput::applyLineStageChange($line, PurchaseOrderStages::PRODUCT_DYEING);

        $this->assertNull($error);
        $line->refresh();
        $this->assertSame(PurchaseOrderStages::PRODUCT_DYEING, $line->stage);
    }

    /**
     * キャンセル済みの製品発注は生機を予約せず、同じ量の新規発注がまだ通ることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_cancelled_product_po_does_not_allocate(): void
    {
        $this->createGreigePo(10.0);
        $this->createProductPo(20.0, PurchaseOrderStatus::CANCELLED);

        $this->assertSame(0.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
        $this->assertSame(10.0, GreigeSupply::availableTan(self::GREIGE_SKU));
        $this->assertTrue(GreigeSupply::canFulfillProductTan(self::PRODUCT_ID, 20.0));
    }

    /**
     * 入荷済み（残 0）の製品発注は、状態が発注済のままでも予約に入らないことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_fully_received_product_po_does_not_allocate(): void
    {
        $this->createGreigePo(10.0);
        $this->createProductPo(20.0, PurchaseOrderStatus::ORDERED, 20.0);

        $this->assertSame(0.0, GreigeSupply::allocatedGreigeMeters(self::GREIGE_SKU));
        $this->assertSame(0.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
        $this->assertSame(10.0, GreigeSupply::availableTan(self::GREIGE_SKU));
    }

    /**
     * 一部入荷の製品発注は、未入荷分だけを予約することを確認する。
     *
     * 製品 20反（1000m）のうち 10反（500m）が入荷済みなら、予約は 500m = 生機 5反。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_partially_received_product_po_allocates_remaining_only(): void
    {
        $this->createGreigePo(10.0);
        $this->createProductPo(20.0, PurchaseOrderStatus::PARTIAL, 10.0);

        $this->assertSame(500.0, GreigeSupply::allocatedGreigeMeters(self::GREIGE_SKU));
        $this->assertSame(5.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
        $this->assertSame(5.0, GreigeSupply::availableTan(self::GREIGE_SKU));
    }

    /**
     * KB-A 向けの製品発注予約が、別品番 KB-T の利用可能量を減らさないことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_kb_a_allocation_does_not_reduce_kb_t_availability(): void
    {
        $this->createGreigePo(10.0);
        $this->createProductPo(20.0);
        $this->createGreigePo(10.0, PurchaseOrderStatus::ORDERED, 0.0, 'KB-T');

        $this->assertSame(10.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
        $this->assertSame(0.0, GreigeSupply::allocatedGreigeTan('KB-T'));
        $this->assertSame(10.0, GreigeSupply::availableTan('KB-T'));
    }

    /**
     * 予約が供給を超えても、利用可能反はマイナスにならず 0 になることを確認する。
     *
     * 生機 10反に対し製品 40反（生機 20反相当）を予約する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_available_tan_is_zero_not_negative_when_allocation_exceeds_supply(): void
    {
        $this->createGreigePo(10.0);
        $this->createProductPo(40.0);

        $this->assertSame(20.0, GreigeSupply::allocatedGreigeTan(self::GREIGE_SKU));
        $this->assertSame(0.0, GreigeSupply::availableTan(self::GREIGE_SKU));
    }

    /**
     * 生機発注1件を作り、指定反数を発注残（または入荷済）として返す。
     *
     * @param  float  $tan  発注反数
     * @param  string  $status  発注状態
     * @param  float  $receivedTan  入荷済反数
     * @param  string  $greigeSku  生機 SKU。省略時は KB-A
     * @return PurchaseOrder  作成した生機発注
     */
    private function createGreigePo(
        float $tan,
        string $status = PurchaseOrderStatus::ORDERED,
        float $receivedTan = 0.0,
        string $greigeSku = self::GREIGE_SKU,
    ): PurchaseOrder {
        $greige = Greige::findBySku($greigeSku);
        $this->assertNotNull($greige);

        $po = PurchaseOrder::query()->create([
            'code' => 'PO-G-ALLOC-'.uniqid(),
            'type' => PurchaseOrderType::GREIGE,
            'status' => $status,
            'supplier_id' => 4,
            'ship_to_id' => 2,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);

        $meters = QtyHelper::metersFromTan($tan, null, true, $greigeSku);
        $receivedMeters = QtyHelper::metersFromTan($receivedTan, null, true, $greigeSku);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'greige_id' => $greige->id,
            'qty_tan' => $tan,
            'qty_meters' => $meters,
            'received_qty_tan' => $receivedTan,
            'received_qty_m' => $receivedMeters,
        ]);

        return $po->fresh(['lines']);
    }

    /**
     * 製品発注1件を作り、指定の製品反数で KB-A を予約する。
     *
     * @param  float  $tan  製品の発注反数
     * @param  string  $status  発注状態
     * @param  float  $receivedTan  入荷済の製品反数。残 0 や一部入荷の確認に使う
     * @return PurchaseOrder  作成した製品発注
     */
    private function createProductPo(
        float $tan,
        string $status = PurchaseOrderStatus::ORDERED,
        float $receivedTan = 0.0,
    ): PurchaseOrder {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-ALLOC-'.uniqid(),
            'type' => PurchaseOrderType::PRODUCT,
            'status' => $status,
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);

        $meters = QtyHelper::metersFromTan($tan, self::PRODUCT_ID);
        $receivedMeters = QtyHelper::metersFromTan($receivedTan, self::PRODUCT_ID);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'product_id' => self::PRODUCT_ID,
            'qty_tan' => $tan,
            'qty_meters' => $meters,
            'received_qty_tan' => $receivedTan,
            'received_qty_m' => $receivedMeters,
        ]);

        return $po->fresh(['lines']);
    }

    /**
     * 染工場在庫として数える生機反を、指定の生機発注へ登録する。
     *
     * @param  PurchaseOrder  $greigePo  入荷済みの生機発注
     * @param  float  $tan  在庫反数
     * @return void  反レコードを追加するため戻り値は使わない
     */
    private function createInStockRolls(PurchaseOrder $greigePo, float $tan): void
    {
        $poLine = $greigePo->primaryLine();
        $this->assertNotNull($poLine);

        $receiving = Receiving::query()->create([
            'code' => 'RCV-G-ALLOC-'.uniqid(),
            'received_date' => '2026-06-18',
        ]);
        $receivingLine = ReceivingLine::query()->create([
            'receiving_id' => $receiving->id,
            'purchase_order_line_id' => $poLine->id,
            'line_no' => 1,
            'qty_tan' => $tan,
            'qty_m' => QtyHelper::metersFromTan($tan, null, true, self::GREIGE_SKU),
        ]);

        GreigeRoll::create([
            'code' => 'G-ALLOC-ROLL-'.uniqid(),
            'greige_sku' => self::GREIGE_SKU,
            'purchase_order_id' => $greigePo->id,
            'receiving_line_id' => $receivingLine->id,
            'tan_qty' => $tan,
            'actual_qty_m' => QtyHelper::metersFromTan($tan, null, true, self::GREIGE_SKU),
            'nominal_meters' => QtyHelper::METERS_PER_TAN_GREIGE,
            'status' => GreigeRoll::STATUS_IN_STOCK,
            'received_date' => '2026-06-18',
        ]);
    }
}
