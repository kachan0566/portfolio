<?php

namespace Tests\Feature;

use App\Models\SalesForecast;
use App\Models\SalesForecastLine;
use App\Services\Inventory\MonthEndForecastEngine;
use App\Services\Sales\SalesForecastEngine;
use App\Support\DemoData;
use App\Support\SalesForecastSourceType;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\ShipmentPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(ShipmentPlanSeeder::class);
    }

    public function test_sales_screen_has_actual_and_forecast_tabs(): void
    {
        $response = $this->get(route('sales.index', ['ym' => DemoData::CURRENT_YM]));

        $response->assertOk();
        $response->assertSee('実績', false);
        $response->assertSee('見通し', false);
        $response->assertSee('今月の進捗', false);
        $response->assertDontSee('送料（見通し込み）', false);
    }

    public function test_forecast_tab_renders_kpi_chart_and_table(): void
    {
        $response = $this->get(route('sales.index', [
            'tab' => 'forecast',
            'ym' => DemoData::CURRENT_YM,
        ]));

        $response->assertOk();
        $response->assertSee('見通し売上', false);
        $response->assertSee('見通し出荷数量', false);
        $response->assertSee('見通し粗利', false);
        $response->assertSee('見通し粗利率', false);
        $response->assertSee('品番別 見通し', false);
        $response->assertSee('見通し出荷総量', false);
        $response->assertSee('forecastSalesChart', false);
        $response->assertSee('詳細', false);
        $response->assertSee('実績と見通し', false);
        $response->assertSee('提出版として保存', false);
        $response->assertSee('CSV出力', false);
        $response->assertDontSee('今月見通し送料', false);
    }

    public function test_forecast_trend_uses_forecast_for_current_month(): void
    {
        $ym = DemoData::CURRENT_YM;
        $trend = SalesForecastEngine::forecastTrend($ym);
        $current = $trend->firstWhere('ym', $ym);
        $forecast = SalesForecastEngine::build($ym);

        $this->assertNotNull($current);
        $this->assertTrue($current->is_forecast);
        $this->assertSame((int) $forecast->total_sales, $current->sales);
        $this->assertSame((int) $forecast->total_profit, $current->profit);

        $prevYm = $trend->where('ym', '!=', $ym)->last();
        $this->assertNotNull($prevYm);
        $this->assertFalse($prevYm->is_forecast);
    }

    public function test_forecast_detail_renders_pairs_and_future_section(): void
    {
        $product = DemoData::products()->firstWhere('sku', 'FAB-T-WH');
        $this->assertNotNull($product);

        $response = $this->get(route('sales.forecast.show', [
            'product' => $product->id,
            'ym' => DemoData::CURRENT_YM,
        ]));

        $response->assertOk();
        $response->assertSee('今月計上の見込み', false);
        $response->assertSee('今月出荷見通し', false);
        $response->assertSee('入荷予定日', false);
        $response->assertSee('この品番の見通しを保存', false);
        $response->assertDontSee('見通し送料', false);
    }

    public function test_saving_forecast_lines_persists_custom_outbound_qty(): void
    {
        $line = SalesForecastEngine::build(DemoData::CURRENT_YM)->lines
            ->first(fn ($l) => $l->forecast_remaining_qty > 0);
        $this->assertNotNull($line, '見通し対象の品番が必要です。');

        $detail = SalesForecastEngine::buildDetail(
            $line->product_id,
            DemoData::findProduct($line->product_id),
            DemoData::CURRENT_YM
        );
        $pair = $detail->pairs->first(fn ($p) => $p->order_id !== null);
        $this->assertNotNull($pair);

        $customQty = max(1.0, round($pair->default_outbound_qty - 10, 2));

        $response = $this->post(route('sales.forecast.store', $line->product_id), [
            'target_ym' => DemoData::CURRENT_YM,
            'outbound_'.$pair->order_id => $customQty,
        ]);

        $response->assertRedirect(route('sales.forecast.show', [
            'product' => $line->product_id,
            'ym' => DemoData::CURRENT_YM,
        ]));

        $saved = SalesForecastLine::findDraftQty(
            $line->product_id,
            DemoData::CURRENT_YM,
            SalesForecastSourceType::ORDER,
            (int) $pair->order_id
        );
        $this->assertSame($customQty, $saved);
    }

    public function test_reset_clears_saved_forecast_lines(): void
    {
        $productId = 3;
        SalesForecastLine::saveDraftForProduct($productId, DemoData::CURRENT_YM, [
            [
                'source_type' => SalesForecastSourceType::ORDER,
                'source_id' => 2,
                'forecast_qty_m' => 50,
            ],
        ]);

        $response = $this->post(route('sales.forecast.reset', $productId), [
            'ym' => DemoData::CURRENT_YM,
        ]);

        $response->assertRedirect();
        $this->assertNull(SalesForecastLine::findDraftQty(
            $productId,
            DemoData::CURRENT_YM,
            SalesForecastSourceType::ORDER,
            2
        ));
    }

    public function test_future_orders_are_excluded_from_current_month_forecast(): void
    {
        $order = DemoData::orders()->firstWhere('code', 'SO-2606-007');
        $this->assertNotNull($order);
        $this->assertSame(6, (int) $order->product_id);

        $default = SalesForecastEngine::defaultOutboundQty((int) $order->id, DemoData::CURRENT_YM);
        $this->assertSame(0.0, $default);
    }

    public function test_sales_forecast_save_updates_month_end_inventory_forecast(): void
    {
        $ym = DemoData::CURRENT_YM;
        $line = SalesForecastEngine::build($ym)->lines
            ->first(fn ($l) => $l->forecast_remaining_qty > 0);
        $this->assertNotNull($line, '見通し対象の品番が必要です。');

        $product = DemoData::findProduct($line->product_id);
        $before = MonthEndForecastEngine::buildLine(
            $line->product_id,
            $product,
            $ym
        );

        $detail = SalesForecastEngine::buildDetail($line->product_id, $product, $ym);
        $pair = $detail->pairs->first(fn ($p) => $p->order_id !== null);
        $this->assertNotNull($pair);

        $reducedQty = max(0.0, round($pair->default_outbound_qty - 20, 2));

        $this->post(route('sales.forecast.store', $line->product_id), [
            'target_ym' => $ym,
            'outbound_'.$pair->order_id => $reducedQty,
        ])->assertRedirect();

        $after = MonthEndForecastEngine::buildLine(
            $line->product_id,
            $product,
            $ym
        );

        $this->assertSame($reducedQty, $after->outbound_confirmed_qty);
        $this->assertGreaterThan($before->forecast_qty, $after->forecast_qty);
    }

    public function test_sales_forecast_detail_shows_inventory_impact(): void
    {
        $product = DemoData::products()->firstWhere('sku', 'FAB-T-WH');
        $response = $this->get(route('sales.forecast.show', [
            'product' => $product->id,
            'ym' => DemoData::CURRENT_YM,
        ]));

        $response->assertOk();
        $response->assertSee('月末在庫予想への影響', false);
        $response->assertSee('月末在庫予想を見る', false);
    }

    public function test_forecast_snapshot_save_creates_version(): void
    {
        $ym = DemoData::CURRENT_YM;
        $result = SalesForecastEngine::build($ym);

        $snapshot = SalesForecastEngine::submitSnapshot(
            $ym,
            'テスト担当',
            '2026-06-20',
            $result->total_sales,
            $result->total_qty,
            $result->total_profit,
            SalesForecastEngine::snapshotLinePayloads($ym),
        );

        $this->assertSame(1, $snapshot->version);
        $this->assertNotNull(SalesForecastEngine::latestSnapshotForMonth($ym));
    }

    public function test_forecast_snapshot_via_http_redirects_with_success(): void
    {
        $response = $this->post(route('sales.forecast.snapshot'), [
            'ym' => DemoData::CURRENT_YM,
        ]);

        $response->assertRedirect(route('sales.index', [
            'tab' => 'forecast',
            'ym' => DemoData::CURRENT_YM,
        ]));
        $response->assertSessionHas('success');
    }

    public function test_forecast_csv_download(): void
    {
        $response = $this->get(route('sales.forecast.csv', ['ym' => DemoData::CURRENT_YM]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_forecast_tab_shows_submit_and_comparison_ui(): void
    {
        $ym = DemoData::CURRENT_YM;
        $result = SalesForecastEngine::build($ym);
        SalesForecastEngine::submitSnapshot(
            $ym,
            'テスト担当',
            '2026-06-01',
            $result->total_sales - 10000,
            $result->total_qty,
            $result->total_profit,
            SalesForecastEngine::snapshotLinePayloads($ym),
        );

        $response = $this->get(route('sales.index', ['tab' => 'forecast', 'ym' => $ym]));

        $response->assertOk();
        $response->assertSee('提出版として保存', false);
        $response->assertSee('CSV出力', false);
        $response->assertSee('提出済 Ver.1', false);
        $response->assertSee('実績と見通し', false);
        $response->assertSee('提出版との差分', false);
    }

    public function test_build_comparison_detects_snapshot_diff(): void
    {
        $ym = DemoData::CURRENT_YM;
        $current = SalesForecastEngine::build($ym);

        SalesForecastEngine::submitSnapshot(
            $ym,
            'テスト担当',
            '2026-06-01',
            $current->total_sales - 50000,
            $current->total_qty,
            $current->total_profit,
            SalesForecastEngine::snapshotLinePayloads($ym),
        );

        $comparison = SalesForecastEngine::buildComparison($ym);

        $this->assertTrue($comparison->has_snapshot);
        $this->assertSame(50000, $comparison->snapshot_vs_current->diff_sales);
    }

    public function test_submit_does_not_delete_draft_lines(): void
    {
        $ym = DemoData::CURRENT_YM;
        SalesForecastLine::saveDraftForProduct(3, $ym, [
            [
                'source_type' => SalesForecastSourceType::ORDER,
                'source_id' => 2,
                'forecast_qty_m' => 50,
            ],
        ]);

        $result = SalesForecastEngine::build($ym);
        SalesForecastEngine::submitSnapshot(
            $ym,
            'テスト担当',
            '2026-06-01',
            $result->total_sales,
            $result->total_qty,
            $result->total_profit,
            SalesForecastEngine::snapshotLinePayloads($ym),
        );

        $this->assertSame(50.0, SalesForecastLine::findDraftQty(
            3,
            $ym,
            SalesForecastSourceType::ORDER,
            2
        ));
        $this->assertSame(1, SalesForecast::latestSubmittedForMonth($ym)?->version);
    }
}
