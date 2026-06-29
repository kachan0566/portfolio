<?php

namespace Tests\Feature;

use App\Services\Inventory\MonthEndForecastEngine;
use App\Support\DemoData;
use App\Support\ForecastManualAdjustment;
use App\Support\ForecastSnapshot;
use App\Support\InboundLot;
use App\Support\ShipmentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryForecastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        InboundLot::resetBootstrap();
        $this->resetJsonState('forecast_manual_adjustments.json');
        $this->resetJsonState('month_end_forecast_snapshots.json');
        $this->resetJsonState('shipment_plans.json');
        \App\Support\ShipmentPlan::clearCache();
        \App\Support\ForecastManualAdjustment::clearCache();
        \App\Support\ForecastSnapshot::clearCache();
    }

    private function resetJsonState(string $file): void
    {
        $path = storage_path('app/'.$file);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function test_forecast_tab_renders(): void
    {
        $response = $this->get(route('inventory.index', ['tab' => 'forecast', 'ym' => DemoData::CURRENT_YM]));

        $response->assertOk();
        $response->assertSee('月末在庫予想', false);
        $response->assertSee('品番別明細', false);
        $response->assertSee('提出版として保存', false);
    }

    public function test_long_term_tab_renders(): void
    {
        $response = $this->get(route('inventory.index', ['tab' => 'long_term']));

        $response->assertOk();
        $response->assertSee('長期在庫', false);
        $response->assertSee('12〜18か月', false);
    }

    public function test_forecast_engine_builds_lines_for_all_products(): void
    {
        $result = MonthEndForecastEngine::build(DemoData::CURRENT_YM);

        $this->assertSame(DemoData::CURRENT_YM, $result->target_ym);
        $this->assertSame(DemoData::products()->count(), $result->lines->count());
    }

    public function test_manual_adjustment_affects_forecast(): void
    {
        $product = DemoData::products()->first();
        $ym = DemoData::CURRENT_YM;

        $before = MonthEndForecastEngine::buildLine(
            (int) $product->id,
            $product,
            $ym,
            MonthEndForecastEngine::monthEndDate($ym)
        );

        ForecastManualAdjustment::add(
            (int) $product->id,
            $ym,
            10,
            'increase',
            'テスト調整',
            'テスト担当'
        );

        $after = MonthEndForecastEngine::buildLine(
            (int) $product->id,
            $product,
            $ym,
            MonthEndForecastEngine::monthEndDate($ym)
        );

        $this->assertSame($before->auto_forecast_qty + 10, $after->forecast_qty);
    }

    public function test_snapshot_save_creates_version(): void
    {
        $ym = DemoData::CURRENT_YM;
        $result = MonthEndForecastEngine::build($ym);

        $snapshot = ForecastSnapshot::save([
            'target_ym' => $ym,
            'base_date' => '2026-06-20',
            'created_by' => 'テスト担当',
            'total_forecast_value' => $result->forecast_value,
            'total_long_term_value' => $result->long_term_value,
        ], $result->lines->take(1)->map(fn ($line) => [
            'product_id' => $line->product_id,
            'sku' => $line->sku,
            'forecast_qty' => $line->forecast_qty,
        ])->all());

        $this->assertSame(1, $snapshot->version);
        $this->assertNotNull(ForecastSnapshot::latestForMonth($ym));
    }

    public function test_shipment_plan_seeded_for_demo(): void
    {
        $plans = ShipmentPlan::all();

        $this->assertNotEmpty($plans);
    }

    public function test_forecast_csv_download(): void
    {
        $response = $this->get(route('inventory.forecast.csv', ['ym' => DemoData::CURRENT_YM]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_forecast_detail_page(): void
    {
        $product = DemoData::products()->first();

        $response = $this->get(route('inventory.forecast.show', [
            'product' => $product->id,
            'ym' => DemoData::CURRENT_YM,
        ]));

        $response->assertOk();
        $response->assertSee($product->sku, false);
        $response->assertSee('入荷予定', false);
    }
}
