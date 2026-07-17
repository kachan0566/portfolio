<?php

namespace Tests\Feature;

use App\Services\Inventory\GreigeDyeInput;
use App\Services\Inventory\GreigeMonthEndForecastEngine;
use App\Support\DemoData;
use App\Support\FabricTanRoll;
use App\Support\GreigeRoll;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GreigeForecastTest extends TestCase
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
    }

    public function test_greige_forecast_tab_renders(): void
    {
        $response = $this->get(route('inventory.index', [
            'tab' => 'greige_forecast',
            'ym' => DemoData::CURRENT_YM,
        ]));

        $response->assertOk();
        $response->assertSee('生機月末予想', false);
        $response->assertSee('品番別明細', false);
        $response->assertSee('KB-T', false);
        $response->assertSee('染機投入予定', false);
        $response->assertSee('提出版として保存', false);
        $response->assertSee('手動調整の登録', false);
    }

    public function test_greige_forecast_includes_inbound_from_greige_po(): void
    {
        $ym = DemoData::CURRENT_YM;
        $line = GreigeMonthEndForecastEngine::buildLine(
            'KB-A',
            DemoData::findGreige('KB-A'),
            $ym,
            GreigeMonthEndForecastEngine::monthEndDate($ym)
        );

        $this->assertGreaterThan(0, $line->inbound_scheduled_qty);
    }

    public function test_greige_forecast_formula_is_current_plus_inbound_minus_outbound(): void
    {
        $ym = DemoData::CURRENT_YM;
        $line = GreigeMonthEndForecastEngine::buildLine(
            'KB-T',
            DemoData::findGreige('KB-T'),
            $ym,
            GreigeMonthEndForecastEngine::monthEndDate($ym)
        );

        $expected = round(
            $line->current_stock_qty + $line->inbound_scheduled_qty - $line->outbound_scheduled_qty,
            2
        );
        $this->assertSame($expected, $line->forecast_qty);

        // デモの製品発注は染機投入済が多く、在庫から既に外れているため outbound は 0 になりうる
        $this->assertGreaterThanOrEqual(0, $line->outbound_scheduled_qty);
    }

    public function test_greige_forecast_excludes_already_dyeing_allocated_stock_from_current(): void
    {
        GreigeDyeInput::bootstrapIfNeeded();

        $ym = DemoData::CURRENT_YM;
        $line = GreigeMonthEndForecastEngine::buildLine(
            'KB-T',
            DemoData::findGreige('KB-T'),
            $ym,
            GreigeMonthEndForecastEngine::monthEndDate($ym)
        );

        $physicalStock = GreigeRoll::stockMetersForSku('KB-T');
        $this->assertSame((float) $physicalStock, $line->current_stock_qty);
    }

    public function test_greige_forecast_value_uses_greige_unit_cost(): void
    {
        $ym = DemoData::CURRENT_YM;
        $line = GreigeMonthEndForecastEngine::buildLine(
            'KB-A',
            DemoData::findGreige('KB-A'),
            $ym,
            GreigeMonthEndForecastEngine::monthEndDate($ym)
        );

        $this->assertTrue($line->cost_calculable);
        $unitCost = DemoData::greigeUnitCost('KB-A', $ym);
        $this->assertNotNull($unitCost);
        if ($line->forecast_qty > 0) {
            $this->assertSame((int) round($line->forecast_qty * $unitCost), $line->forecast_value);
        }
    }

    public function test_greige_forecast_search_filters_by_sku(): void
    {
        $response = $this->get(route('inventory.index', [
            'tab' => 'greige_forecast',
            'ym' => DemoData::CURRENT_YM,
            'sku' => 'KB-A',
        ]));

        $response->assertOk();
        $response->assertSee('KB-A', false);
        $response->assertDontSee('code-cell t-strong">KB-T', false);
    }
}
