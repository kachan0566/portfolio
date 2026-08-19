<?php

namespace Tests\Feature;

use App\Models\ForecastManualAdjustment;
use App\Models\MonthEndForecast;
use App\Services\Inventory\MonthEndForecastEngine;
use App\Support\DemoData;
use App\Support\MasterCatalog;
use App\Support\ShipmentPlan;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Database\Seeders\ShipmentPlanSeeder;
use Database\Seeders\ShipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(ReceivingSeeder::class);
        $this->seed(ShipmentPlanSeeder::class);
        $this->seed(ShipmentSeeder::class);
    }

    public function test_forecast_tab_renders(): void
    {
        $response = $this->get(route('inventory.index', ['tab' => 'forecast', 'ym' => DemoData::CURRENT_YM]));

        $response->assertOk();
        $response->assertSee('提出版として保存', false);
        $response->assertSee('手動調整の登録', false);
        $response->assertSee('品番別明細', false);
        $response->assertSee('検索', false);
    }

    public function test_forecast_defaults_to_the_current_business_month(): void
    {
        config()->set('business.fixed_date', '2026-08-19');

        $response = $this->get(route('inventory.index', ['tab' => 'forecast']));

        $response->assertOk();
        $response->assertViewHas('forecastYm', '2026-08');
    }

    public function test_forecast_keeps_an_explicitly_selected_past_month(): void
    {
        config()->set('business.fixed_date', '2026-08-19');

        $response = $this->get(route('inventory.index', [
            'tab' => 'forecast',
            'ym' => '2026-06',
        ]));

        $response->assertOk();
        $response->assertViewHas('forecastYm', '2026-06');
    }

    public function test_forecast_search_filters_by_sku(): void
    {
        $product = MasterCatalog::products()->firstWhere('sku', 'FAB-A-BK');

        $response = $this->get(route('inventory.index', [
            'tab' => 'forecast',
            'ym' => DemoData::CURRENT_YM,
            'sku' => 'FAB-A-BK',
        ]));

        $response->assertOk();
        $response->assertSee('code-cell t-strong">FAB-A-BK', false);
        $response->assertDontSee('code-cell t-strong">FAB-T-WH', false);
    }

    public function test_forecast_search_filters_by_warning_status(): void
    {
        $ym = DemoData::CURRENT_YM;
        $negativeLine = MonthEndForecastEngine::build($ym)->lines->first(fn ($line) => $line->is_negative);
        $this->assertNotNull($negativeLine, 'テスト用に在庫不足予想の品番が必要です。');

        $response = $this->get(route('inventory.index', [
            'tab' => 'forecast',
            'ym' => $ym,
            'status' => '在庫不足予想',
        ]));

        $response->assertOk();
        $response->assertSee($negativeLine->sku, false);
    }

    public function test_forecast_kpi_reflects_search(): void
    {
        $ym = DemoData::CURRENT_YM;
        $product = MasterCatalog::products()->firstWhere('sku', 'FAB-A-BK');
        $line = MonthEndForecastEngine::buildLine(
            (int) $product->id,
            $product,
            $ym,
            MonthEndForecastEngine::monthEndDate($ym)
        );

        $response = $this->get(route('inventory.index', [
            'tab' => 'forecast',
            'ym' => $ym,
            'sku' => 'FAB-A-BK',
        ]));

        $response->assertOk();
        $response->assertSee(number_format($line->forecast_qty).'m', false);
    }

    public function test_forecast_prev_month_diff_for_filtered_lines(): void
    {
        $ym = DemoData::CURRENT_YM;
        $product = MasterCatalog::products()->first();
        $line = MonthEndForecastEngine::buildLine(
            (int) $product->id,
            $product,
            $ym,
            MonthEndForecastEngine::monthEndDate($ym)
        );
        $summary = MonthEndForecastEngine::summarizeLines(collect([$line]), $ym);
        $prevValue = MonthEndForecastEngine::prevForecastValue((int) $product->id, $ym);
        $expectedDiff = ($line->cost_calculable ? (int) $line->forecast_value : 0) - $prevValue;

        $this->assertSame($expectedDiff, $summary->prev_month_diff);
    }

    public function test_forecast_detail_shows_unshipped_orders(): void
    {
        $product = MasterCatalog::products()->firstWhere('sku', 'FAB-T-WH');
        $orders = MonthEndForecastEngine::unshippedOrdersForProduct((int) $product->id);
        $this->assertNotEmpty($orders);

        $response = $this->get(route('inventory.forecast.show', [
            'product' => $product->id,
            'ym' => DemoData::CURRENT_YM,
        ]));

        $response->assertOk();
        $response->assertSee('未出荷受注一覧', false);
        $response->assertSee($orders->first()->code, false);
        $response->assertSee('手動調整', false);
    }

    public function test_forecast_adjustment_from_detail_redirects_back(): void
    {
        $product = MasterCatalog::products()->first();
        $ym = DemoData::CURRENT_YM;

        $response = $this->post(route('inventory.forecast.adjustments'), [
            'product_id' => $product->id,
            'target_ym' => $ym,
            'qty' => 5,
            'direction' => 'increase',
            'reason' => '詳細画面からのテスト調整',
            'redirect' => 'detail',
        ]);

        $response->assertRedirect(route('inventory.forecast.show', ['product' => $product->id, 'ym' => $ym]));
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
        $this->assertSame(MasterCatalog::products()->count(), $result->lines->count());
    }

    public function test_manual_adjustment_affects_forecast(): void
    {
        $product = MasterCatalog::products()->first();
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

        $snapshot = MonthEndForecast::saveSnapshot([
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
        $this->assertNotNull(MonthEndForecast::latestForMonth($ym));
        $this->assertDatabaseHas('month_end_forecasts', [
            'target_ym' => $ym,
            'version' => 1,
            'created_by_name' => 'テスト担当',
            'submission_status' => 'submitted',
        ]);
        $this->assertDatabaseHas('month_end_forecast_lines', [
            'month_end_forecast_id' => $snapshot->id,
            'product_id' => $result->lines->first()->product_id,
        ]);
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
        $product = MasterCatalog::products()->firstWhere('sku', 'FAB-A-BK');
        $line = MonthEndForecastEngine::buildLine(
            (int) $product->id,
            $product,
            DemoData::CURRENT_YM,
            MonthEndForecastEngine::monthEndDate(DemoData::CURRENT_YM)
        );

        $response = $this->get(route('inventory.forecast.show', [
            'product' => $product->id,
            'ym' => DemoData::CURRENT_YM,
        ]));

        $response->assertOk();
        $response->assertSee($product->sku, false);
        $response->assertSee('現在庫金額', false);
        $response->assertSee('現在庫数量', false);
        $response->assertSee('月末予想在庫', false);
        $response->assertSee(number_format($line->forecast_qty).'m', false);
        $response->assertDontSee('自動予想', false);
        if ($line->is_negative || $line->is_shortage) {
            $response->assertSee('在庫不足予想', false);
        }
        $response->assertSee('入荷予定', false);
        $response->assertSee('出荷見通し', false);
        $response->assertSee('売上見通しを編集', false);
    }

    public function test_forecast_detail_shows_submitted_badge_when_snapshot_exists(): void
    {
        $product = MasterCatalog::products()->first();
        $ym = DemoData::CURRENT_YM;
        $result = MonthEndForecastEngine::build($ym);

        MonthEndForecast::saveSnapshot([
            'target_ym' => $ym,
            'base_date' => '2026-06-20',
            'created_by' => 'テスト担当',
            'total_forecast_value' => $result->forecast_value,
            'total_long_term_value' => $result->long_term_value,
        ], []);

        $response = $this->get(route('inventory.forecast.show', [
            'product' => $product->id,
            'ym' => $ym,
        ]));

        $response->assertOk();
        $response->assertSee('提出済 Ver.1', false);
    }
}
