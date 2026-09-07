<?php

namespace Tests\Feature;

use App\Models\Greige;
use App\Models\GreigeForecastManualAdjustment;
use App\Services\Inventory\GreigeMonthEndForecastEngine;
use App\Support\DemoData;
use App\Support\MasterCatalog;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GreigeForecastManualAdjustmentTest extends TestCase
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
        $ym = DemoData::CURRENT_YM;

        GreigeForecastManualAdjustment::add(
            'KB-A',
            $ym,
            12.5,
            'increase',
            '入荷遅延の見込み',
            'テスト担当'
        );

        $this->assertDatabaseHas('greige_forecast_manual_adjustments', [
            'greige_id' => Greige::findBySku('KB-A')?->id,
            'target_ym' => $ym,
            'adjustment_qty_m' => '12.50',
            'direction' => 'increase',
            'reason' => '入荷遅延の見込み',
            'created_by_name' => 'テスト担当',
        ]);
    }

    public function test_decrease_stores_negative_quantity(): void
    {
        $ym = DemoData::CURRENT_YM;

        GreigeForecastManualAdjustment::add(
            'KB-A',
            $ym,
            8,
            'decrease',
            '染機投入前倒し',
            'テスト担当'
        );

        $this->assertSame(-8.0, GreigeForecastManualAdjustment::totalFor('KB-A', $ym));
    }

    public function test_total_for_sums_only_matching_greige_and_month(): void
    {
        $ym = DemoData::CURRENT_YM;
        $otherYm = '2026-05';

        GreigeForecastManualAdjustment::add('KB-A', $ym, 10, 'increase', 'A', '担当');
        GreigeForecastManualAdjustment::add('KB-A', $ym, 3, 'increase', 'B', '担当');
        GreigeForecastManualAdjustment::add('KB-T', $ym, 99, 'increase', 'C', '担当');
        GreigeForecastManualAdjustment::add('KB-A', $otherYm, 50, 'increase', 'D', '担当');

        $this->assertSame(13.0, GreigeForecastManualAdjustment::totalFor('KB-A', $ym));
        $this->assertSame(99.0, GreigeForecastManualAdjustment::totalFor('KB-T', $ym));
        $this->assertSame(50.0, GreigeForecastManualAdjustment::totalFor('KB-A', $otherYm));
        $this->assertSame(0.0, GreigeForecastManualAdjustment::totalFor('NO-SKU', $ym));
    }

    public function test_history_for_returns_newest_first_with_created_by_alias(): void
    {
        $ym = DemoData::CURRENT_YM;

        GreigeForecastManualAdjustment::add('KB-A', $ym, 1, 'increase', '1件目', '担当A');
        GreigeForecastManualAdjustment::add('KB-A', $ym, 2, 'increase', '2件目', '担当B');

        $history = GreigeForecastManualAdjustment::historyFor('KB-A', $ym);

        $this->assertCount(2, $history);
        $this->assertSame('2件目', $history->first()->reason);
        $this->assertSame('担当B', $history->first()->created_by);
        $this->assertSame('KB-A', $history->first()->greige_sku);
    }

    public function test_manual_adjustment_affects_forecast_engine(): void
    {
        $greige = MasterCatalog::findGreige('KB-A');
        $ym = DemoData::CURRENT_YM;
        $monthEnd = GreigeMonthEndForecastEngine::monthEndDate($ym);

        $before = GreigeMonthEndForecastEngine::buildLine('KB-A', $greige, $ym, $monthEnd);

        GreigeForecastManualAdjustment::add(
            'KB-A',
            $ym,
            10,
            'increase',
            'テスト調整',
            'テスト担当'
        );

        $after = GreigeMonthEndForecastEngine::buildLine('KB-A', $greige, $ym, $monthEnd);

        $this->assertSame($before->auto_forecast_qty + 10, $after->forecast_qty);
        $this->assertSame(10.0, $after->manual_adjustment_qty);
        $this->assertCount(1, $after->manual_adjustments);
    }

    public function test_store_adjustment_via_http_persists_row(): void
    {
        $greige = MasterCatalog::findGreige('KB-A');
        $ym = DemoData::CURRENT_YM;

        $response = $this->post(route('inventory.greige-forecast.adjustments'), [
            'greige_sku' => 'KB-A',
            'target_ym' => $ym,
            'qty' => 50,
            'direction' => 'increase',
            'reason' => 'HTTP経由のテスト調整',
        ]);

        $response->assertRedirect(route('inventory.index', ['tab' => 'greige_forecast', 'ym' => $ym]));
        $this->assertDatabaseHas('greige_forecast_manual_adjustments', [
            'greige_id' => $greige->id,
            'target_ym' => $ym,
            'adjustment_qty_m' => '50.00',
            'reason' => 'HTTP経由のテスト調整',
        ]);
    }
}
