<?php

namespace Tests\Unit;

use App\Support\DemoData;
use App\Support\MasterCatalog;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Database\Seeders\ShipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTrendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(CostFoundationSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
        $this->seed(ShipmentSeeder::class);
    }

    public function test_sales_trend_uses_shipment_data_for_current_month(): void
    {
        $trend = DemoData::salesTrend(DemoData::CURRENT_YM);
        $current = $trend->firstWhere('ym', DemoData::CURRENT_YM);

        $this->assertNotNull($current);
        $this->assertSame(
            DemoData::monthlySalesByProduct(DemoData::CURRENT_YM)->sum('sales'),
            $current->sales
        );
    }

    public function test_sales_trend_filters_by_product(): void
    {
        $trend = DemoData::salesTrend(DemoData::CURRENT_YM, 1);
        $current = $trend->firstWhere('ym', DemoData::CURRENT_YM);

        $this->assertNotNull($current);
        $this->assertSame(
            DemoData::monthlySalesByProduct(DemoData::CURRENT_YM)->where('product_id', 1)->sum('sales'),
            $current->sales
        );
    }

    public function test_monthly_sales_includes_master_price(): void
    {
        $row = DemoData::monthlySalesByProduct(DemoData::CURRENT_YM)->firstWhere('product_id', 1);

        $this->assertNotNull($row);
        $this->assertSame(MasterCatalog::findProduct(1)?->price, $row->price);
    }

    public function test_dashboard_trend_matches_sales_trend_from_shipments(): void
    {
        $currentYm = DemoData::CURRENT_YM;
        $expected = DemoData::salesTrend($currentYm);
        $dashboard = DemoData::dashboard();

        $this->assertCount(6, $dashboard['trend']);
        $this->assertSame(
            $expected->pluck('ym')->all(),
            $dashboard['trend']->pluck('ym')->all(),
        );

        $current = $dashboard['trend']->firstWhere('ym', $currentYm);
        $this->assertNotNull($current);
        $this->assertSame(
            DemoData::monthlySalesByProduct($currentYm)->sum('sales'),
            $current->sales,
        );
        $this->assertSame($expected->firstWhere('ym', $currentYm)?->sales, $current->sales);
    }
}
