<?php

namespace Tests\Unit;

use App\Models\MaterialPrice;
use App\Support\DemoData;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(CostFoundationSeeder::class);
    }

    public function test_unit_cost_breakdown_uses_greige_cost_plus_dyeing(): void
    {
        $breakdown = DemoData::unitCostBreakdown(1, '2026-06');

        $this->assertTrue($breakdown->calculable);
        $this->assertSame('KB-A', $breakdown->greige_sku);
        $this->assertSame(1253.0, $breakdown->greige_cost);
        $this->assertSame(475.0, $breakdown->processing_cost);
        $this->assertSame(1728.0, $breakdown->total);
    }

    public function test_unit_cost_breakdown_with_multiple_yarn_greige_lines(): void
    {
        $breakdown = DemoData::unitCostBreakdown(2, '2026-06');

        $this->assertTrue($breakdown->calculable);
        $this->assertSame('KB-B', $breakdown->greige_sku);
        $this->assertSame(1341.75, $breakdown->greige_cost);
        $this->assertSame(520.0, $breakdown->processing_cost);
        $this->assertSame(1861.75, $breakdown->total);
    }

    public function test_missing_greige_recipe_makes_cost_uncalculable(): void
    {
        $breakdown = DemoData::unitCostBreakdown(4, '2026-06');

        $this->assertFalse($breakdown->calculable);
        $this->assertTrue($breakdown->missing_greige_recipe);
        $this->assertSame('KB-C', $breakdown->greige_sku);
        $this->assertNull($breakdown->greige_cost);
        $this->assertNull($breakdown->total);
        $this->assertNull(DemoData::unitCost(4, '2026-06'));

        $warnings = DemoData::costWarningMessages(4, '2026-06');
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('生機レシピ', $warnings[0]);
    }

    public function test_missing_yarn_price_makes_cost_uncalculable_without_zero_fallback(): void
    {
        $breakdown = DemoData::unitCostBreakdown(1, '2026-07');

        $this->assertFalse($breakdown->calculable);
        $this->assertNull($breakdown->greige_cost);
        $this->assertNull($breakdown->total);
        $this->assertCount(1, $breakdown->missing_yarns);
        $this->assertNull(DemoData::unitCost(1, '2026-07'));
    }

    public function test_yarn_materials_returns_only_yarn_ids(): void
    {
        $ids = DemoData::yarnMaterials()->pluck('id')->all();

        $this->assertSame([1, 2], $ids);
    }

    public function test_yarn_price_returns_null_for_non_yarn_material(): void
    {
        $this->assertNull(DemoData::yarnPrice(3, '2026-06'));
    }

    public function test_monthly_sales_marks_uncalculable_rows(): void
    {
        MaterialPrice::query()->create([
            'material_id' => 1,
            'ym' => '2026-07',
            'unit_price' => 600,
        ]);

        $rows = DemoData::monthlySalesByProduct();
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertTrue($row->cost_calculable);
            $this->assertNotNull($row->cost);
            $this->assertNotNull($row->profit);
        }
    }

    public function test_dashboard_returns_profit_and_cost_flags(): void
    {
        $dashboard = DemoData::dashboard();
        $warnings = DemoData::collectCostWarnings(
            DemoData::products()->pluck('id'),
            DemoData::CURRENT_YM
        );

        $this->assertNotEmpty($warnings);
        $this->assertTrue(collect($warnings)->contains(fn ($w) => str_contains($w, 'KB-C')));
        $this->assertArrayHasKey('profit', $dashboard);
        $this->assertArrayHasKey('cost', $dashboard);
        $this->assertIsInt($dashboard['profit']);
    }
}
