<?php

namespace Tests\Unit;

use App\Support\DemoData;
use App\Support\DemoOverlay;
use Tests\TestCase;

class UnitProfitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DemoOverlay::clear();
    }

    public function test_unit_profit_summary_calculates_margin(): void
    {
        $summary = DemoData::unitProfitSummary(1, '2026-06');

        $this->assertTrue($summary->calculable);
        $this->assertSame(1728.0, $summary->unit_cost);
        $this->assertSame(1200, $summary->price);
        $this->assertSame(-528, $summary->profit);
        $this->assertSame(-44.0, $summary->margin_percent);
    }

    public function test_unit_profit_summary_respects_overrides(): void
    {
        $summary = DemoData::unitProfitSummary(1, '2026-06', 2000, 400);

        $this->assertSame(2000, $summary->price);
        $this->assertSame(400.0, $summary->processing_cost);
        $this->assertSame(1653.0, $summary->unit_cost);
        $this->assertSame(347, $summary->profit);
        $this->assertSame(17.3, $summary->margin_percent);
    }

    public function test_unit_profit_summary_is_null_when_cost_uncalculable(): void
    {
        $summary = DemoData::unitProfitSummary(4, '2026-06', 700);

        $this->assertFalse($summary->calculable);
        $this->assertNull($summary->unit_cost);
        $this->assertNull($summary->profit);
        $this->assertNull($summary->margin_percent);
    }

    public function test_save_product_price_updates_products_collection(): void
    {
        DemoOverlay::saveProductPrice(1, 1500);

        $product = DemoData::findProduct(1);
        $this->assertSame(1500, $product->price);

        $summary = DemoData::unitProfitSummary(1, '2026-06');
        $this->assertSame(1500, $summary->price);
        $this->assertSame(-228, $summary->profit);
    }
}
