<?php

namespace Tests\Unit;

use App\Support\FabricQuantity;
use App\Support\MasterCatalog;
use App\Support\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabricQuantityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
    }

    /**
     * 受注の0.25反を丸めず、対応する12.5mまで正確に解決することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_resolve_order_context_keeps_quarter_tan_and_decimal_meters(): void
    {
        $resolved = FabricQuantity::resolve(0.25, null, 1, false, null, FabricQuantity::CONTEXT_ORDER);

        $this->assertSame(0.25, $resolved->qty_tan);
        $this->assertSame(12.5, $resolved->qty_meters);
    }

    /**
     * 受注・発注・入荷・出荷の全工程が同じ0.25反刻みを採用することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_all_fabric_contexts_accept_quarter_tan_and_reject_other_steps(): void
    {
        foreach ([
            FabricQuantity::CONTEXT_ORDER,
            FabricQuantity::CONTEXT_PO,
            FabricQuantity::CONTEXT_RECEIVING,
            FabricQuantity::CONTEXT_SHIPMENT,
        ] as $context) {
            $this->assertTrue(FabricQuantity::isValidTanForContext(1.25, $context));
            $this->assertFalse(FabricQuantity::isValidTanForContext(1.1, $context));
        }
    }

    public function test_resolve_receiving_context_uses_quarter_step(): void
    {
        $resolved = FabricQuantity::resolve(1.25, null, 1, false, null, FabricQuantity::CONTEXT_RECEIVING);

        $this->assertSame(1.25, $resolved->qty_tan);
    }

    /**
     * 反数指定では反数を正とし、換算mを小数型で返すことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_resolve_tan_is_canonical(): void
    {
        $resolved = FabricQuantity::resolve(2.4, null, 1);

        $this->assertSame(2.4, $resolved->qty_tan);
        $this->assertSame(120.0, $resolved->qty_meters);
        $this->assertFalse($resolved->meters_overridden);
    }

    /**
     * m指定値を整数へ変換せず、小数型の正本として保持することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_resolve_meter_override(): void
    {
        $resolved = FabricQuantity::resolve(2.4, 115, 1);

        $this->assertSame(2.4, $resolved->qty_tan);
        $this->assertSame(115.0, $resolved->qty_meters);
        $this->assertTrue($resolved->meters_overridden);
    }

    public function test_tan_from_record_prefers_qty_tan(): void
    {
        $tan = FabricQuantity::tanFromRecord(['qty_tan' => 1.5, 'qty' => 999], 1);

        $this->assertSame(1.5, $tan);
    }

    /**
     * レコードにm数がある場合は、反数から再計算せず小数型で返すことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_meters_from_record_prefers_qty_meters(): void
    {
        $meters = FabricQuantity::metersFromRecord(['qty_tan' => 2.0, 'qty_meters' => 115], 1);

        $this->assertSame(115.0, $meters);
    }

    /**
     * DBの標準m/反を使った換算結果を小数型で返すことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_meters_from_tan_uses_database_meters_per_tan(): void
    {
        $product = MasterCatalog::findProduct(1);

        $this->assertNotNull($product);
        $this->assertSame(50, $product->meters_per_tan);
        $this->assertSame(100.0, QtyHelper::metersFromTan(2.0, 1));
    }
}
