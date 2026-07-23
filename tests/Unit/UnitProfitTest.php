<?php

namespace Tests\Unit;

use App\Models\MaterialPrice;
use App\Models\Product;
use App\Support\DemoData;
use App\Support\MasterCatalog;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitProfitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(CostFoundationSeeder::class);
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

    public function test_product_price_update_reflects_in_profit_summary(): void
    {
        Product::query()->whereKey(1)->update(['price' => 1500]);

        $this->assertSame(1500, (int) Product::query()->find(1)?->price);
        $this->assertSame(1500, (int) MasterCatalog::findProduct(1)?->price);

        $summary = DemoData::unitProfitSummary(1, '2026-06', 1500);
        $this->assertSame(1500, $summary->price);
        $this->assertSame(-228, $summary->profit);
    }
}
