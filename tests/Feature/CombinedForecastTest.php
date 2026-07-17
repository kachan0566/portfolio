<?php

namespace Tests\Feature;

use App\Services\Inventory\CombinedMonthEndForecastEngine;
use App\Services\Inventory\ForecastSubmissionCoordinator;
use App\Services\Inventory\GreigeDyeInput;
use App\Services\Inventory\GreigeMonthEndForecastEngine;
use App\Services\Inventory\MonthEndForecastEngine;
use App\Support\CombinedForecastSnapshot;
use App\Support\DemoData;
use App\Support\FabricTanRoll;
use App\Support\ForecastSnapshot;
use App\Support\GreigeForecastManualAdjustment;
use App\Support\GreigeForecastSnapshot;
use App\Support\GreigeRoll;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombinedForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FabricTanRoll::resetBootstrap();
        GreigeDyeInput::resetBootstrapForTesting();
        GreigeRoll::resetCacheForTesting();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
        $this->resetJsonState('forecast_manual_adjustments.json');
        $this->resetJsonState('greige_forecast_manual_adjustments.json');
        $this->resetJsonState('month_end_forecast_snapshots.json');
        $this->resetJsonState('greige_month_end_forecast_snapshots.json');
        $this->resetJsonState('combined_month_end_forecast_snapshots.json');
        ForecastSnapshot::clearCache();
        GreigeForecastSnapshot::clearCache();
        CombinedForecastSnapshot::clearCache();
        GreigeForecastManualAdjustment::clearCache();
    }

    private function resetJsonState(string $file): void
    {
        $path = storage_path('app/'.$file);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function test_combined_forecast_tab_renders(): void
    {
        $response = $this->get(route('inventory.index', [
            'tab' => 'forecast_combined',
            'ym' => DemoData::CURRENT_YM,
        ]));

        $response->assertOk();
        $response->assertSee('合算（製品＋生機）', false);
        $response->assertSee('製品月末予想へ', false);
        $response->assertSee('生機月末予想へ', false);
        $response->assertSee('まとめて提出版として保存', false);
    }

    public function test_combined_forecast_value_sums_product_and_greige(): void
    {
        $ym = DemoData::CURRENT_YM;
        $combined = CombinedMonthEndForecastEngine::build($ym);
        $product = MonthEndForecastEngine::build($ym);
        $greige = GreigeMonthEndForecastEngine::build($ym);

        $productValue = (int) $product->lines->where('cost_calculable', true)->sum('forecast_value');
        $greigeValue = (int) $greige->lines->where('cost_calculable', true)->sum('forecast_value');

        $this->assertSame($productValue, $combined->product_forecast_value);
        $this->assertSame($greigeValue, $combined->greige_forecast_value);
        $this->assertSame($productValue + $greigeValue, $combined->forecast_value);
    }

    public function test_greige_manual_adjustment_affects_forecast(): void
    {
        $greige = DemoData::findGreige('KB-A');
        $ym = DemoData::CURRENT_YM;

        $before = GreigeMonthEndForecastEngine::buildLine(
            'KB-A',
            $greige,
            $ym,
            GreigeMonthEndForecastEngine::monthEndDate($ym)
        );

        GreigeForecastManualAdjustment::add(
            'KB-A',
            $ym,
            50,
            'increase',
            'テスト調整',
            'テスト担当'
        );

        $after = GreigeMonthEndForecastEngine::buildLine(
            'KB-A',
            $greige,
            $ym,
            GreigeMonthEndForecastEngine::monthEndDate($ym)
        );

        $this->assertSame($before->auto_forecast_qty + 50, $after->forecast_qty);
    }

    public function test_unified_snapshot_saves_all_three_with_same_version(): void
    {
        $ym = DemoData::CURRENT_YM;

        $result = ForecastSubmissionCoordinator::saveUnified($ym);

        $this->assertSame(1, $result->version);
        $this->assertSame(1, ForecastSnapshot::latestForMonth($ym)->version);
        $this->assertSame(1, GreigeForecastSnapshot::latestForMonth($ym)->version);
        $this->assertSame(1, CombinedForecastSnapshot::latestForMonth($ym)->version);
    }

    public function test_combined_tab_shows_submitted_badge_when_both_submitted(): void
    {
        $ym = DemoData::CURRENT_YM;
        ForecastSubmissionCoordinator::saveUnified($ym);

        $response = $this->get(route('inventory.index', [
            'tab' => 'forecast_combined',
            'ym' => $ym,
        ]));

        $response->assertOk();
        $response->assertSee('提出済 Ver.1', false);
    }

    public function test_combined_tab_hides_submitted_badge_when_only_product_submitted(): void
    {
        $ym = DemoData::CURRENT_YM;
        $product = MonthEndForecastEngine::build($ym);

        ForecastSnapshot::save([
            'target_ym' => $ym,
            'base_date' => '2026-06-20',
            'created_by' => 'テスト担当',
            'total_forecast_value' => $product->forecast_value,
            'total_long_term_value' => $product->long_term_value,
        ], []);

        $response = $this->get(route('inventory.index', [
            'tab' => 'forecast_combined',
            'ym' => $ym,
        ]));

        $response->assertOk();
        $response->assertDontSee('提出済 Ver.1', false);
    }
}
