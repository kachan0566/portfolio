<?php

namespace Tests\Feature;

use App\Models\ForecastManualAdjustment;
use App\Services\Inventory\MonthEndForecastEngine;
use App\Support\DemoData;
use App\Support\MasterCatalog;
use App\Support\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastManualAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
    }

    public function test_add_persists_to_database(): void
    {
        $product = MasterCatalog::products()->first();
        $ym = DemoData::CURRENT_YM;

        ForecastManualAdjustment::add(
            (int) $product->id,
            $ym,
            12.5,
            'increase',
            '入荷遅延の見込み',
            'テスト担当'
        );

        $this->assertDatabaseHas('forecast_manual_adjustments', [
            'product_id' => $product->id,
            'target_ym' => $ym,
            'adjustment_qty_m' => '12.50',
            'direction' => 'increase',
            'reason' => '入荷遅延の見込み',
            'created_by_name' => 'テスト担当',
        ]);
    }

    public function test_decrease_stores_negative_quantity(): void
    {
        $product = MasterCatalog::products()->first();
        $ym = DemoData::CURRENT_YM;

        ForecastManualAdjustment::add(
            (int) $product->id,
            $ym,
            8,
            'decrease',
            '出荷前倒し',
            'テスト担当'
        );

        $this->assertSame(-8.0, ForecastManualAdjustment::totalFor((int) $product->id, $ym));
    }

    public function test_total_for_sums_only_matching_product_and_month(): void
    {
        $products = MasterCatalog::products()->take(2)->values();
        $ym = DemoData::CURRENT_YM;
        $otherYm = '2026-05';

        ForecastManualAdjustment::add((int) $products[0]->id, $ym, 10, 'increase', 'A', '担当');
        ForecastManualAdjustment::add((int) $products[0]->id, $ym, 3, 'increase', 'B', '担当');
        ForecastManualAdjustment::add((int) $products[1]->id, $ym, 99, 'increase', 'C', '担当');
        ForecastManualAdjustment::add((int) $products[0]->id, $otherYm, 50, 'increase', 'D', '担当');

        $this->assertSame(13.0, ForecastManualAdjustment::totalFor((int) $products[0]->id, $ym));
        $this->assertSame(99.0, ForecastManualAdjustment::totalFor((int) $products[1]->id, $ym));
        $this->assertSame(50.0, ForecastManualAdjustment::totalFor((int) $products[0]->id, $otherYm));
    }

    public function test_history_for_returns_newest_first_with_created_by_alias(): void
    {
        $product = MasterCatalog::products()->first();
        $ym = DemoData::CURRENT_YM;

        ForecastManualAdjustment::add((int) $product->id, $ym, 1, 'increase', '1件目', '担当A');
        ForecastManualAdjustment::add((int) $product->id, $ym, 2, 'increase', '2件目', '担当B');

        $history = ForecastManualAdjustment::historyFor((int) $product->id, $ym);

        $this->assertCount(2, $history);
        $this->assertSame('2件目', $history->first()->reason);
        $this->assertSame('担当B', $history->first()->created_by);
    }

    public function test_manual_adjustment_affects_forecast_engine(): void
    {
        $product = MasterCatalog::products()->first();
        $ym = DemoData::CURRENT_YM;
        $monthEnd = MonthEndForecastEngine::monthEndDate($ym);

        $before = MonthEndForecastEngine::buildLine((int) $product->id, $product, $ym, $monthEnd);

        ForecastManualAdjustment::add(
            (int) $product->id,
            $ym,
            10,
            'increase',
            'テスト調整',
            'テスト担当'
        );

        $after = MonthEndForecastEngine::buildLine((int) $product->id, $product, $ym, $monthEnd);

        $this->assertSame($before->auto_forecast_qty + 10, $after->forecast_qty);
        $this->assertSame(10.0, $after->manual_adjustment_qty);
        $this->assertCount(1, $after->manual_adjustments);
    }

    public function test_store_adjustment_via_http_persists_row(): void
    {
        $product = MasterCatalog::products()->first();
        $ym = DemoData::CURRENT_YM;
        $metersPerTan = QtyHelper::metersPerTan((int) $product->id);

        $response = $this->post(route('inventory.forecast.adjustments'), [
            'product_id' => $product->id,
            'target_ym' => $ym,
            'qty' => 5,
            'direction' => 'increase',
            'reason' => 'HTTP経由のテスト調整',
        ]);

        $expectedM = 5 * $metersPerTan;
        $response->assertRedirect(route('inventory.index', ['tab' => 'forecast', 'ym' => $ym]));
        $this->assertDatabaseHas('forecast_manual_adjustments', [
            'product_id' => $product->id,
            'target_ym' => $ym,
            'adjustment_qty_m' => number_format($expectedM, 2, '.', ''),
            'reason' => 'HTTP経由のテスト調整',
        ]);
    }

    public function test_store_adjustment_via_http_uses_qty_meters_when_provided(): void
    {
        $product = MasterCatalog::products()->first();
        $ym = DemoData::CURRENT_YM;

        $response = $this->post(route('inventory.forecast.adjustments'), [
            'product_id' => $product->id,
            'target_ym' => $ym,
            'qty' => 5,
            'qty_meters' => 123,
            'direction' => 'increase',
            'reason' => 'mで直接指定',
        ]);

        $response->assertRedirect(route('inventory.index', ['tab' => 'forecast', 'ym' => $ym]));
        $this->assertDatabaseHas('forecast_manual_adjustments', [
            'product_id' => $product->id,
            'target_ym' => $ym,
            'adjustment_qty_m' => '123.00',
            'reason' => 'mで直接指定',
        ]);
    }
}
