<?php

namespace Tests\Unit;

use App\Support\DemoData;
use Tests\TestCase;

class SalesTrendTest extends TestCase
{
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
        $this->assertSame(DemoData::findProduct(1)->price, $row->price);
    }
}
