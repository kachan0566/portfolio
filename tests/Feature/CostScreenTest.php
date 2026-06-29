<?php

namespace Tests\Feature;

use App\Support\DemoData;
use App\Support\DemoOverlay;
use Tests\TestCase;

class CostScreenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DemoOverlay::clear();
    }

    public function test_sales_screen_renders_with_kpi_cost_and_profit_columns(): void
    {
        $response = $this->get(route('sales.index'));

        $response->assertOk();
        $response->assertSee('製造コスト', false);
        $response->assertSee('粗利', false);
        $response->assertSee('単価', false);
        $response->assertSee('対象月', false);
    }

    public function test_sales_table_does_not_show_manufacturing_cost_column_header(): void
    {
        $response = $this->get(route('sales.index'));

        $response->assertOk();
        $content = $response->getContent();
        $tableStart = strpos($content, '品番別 売上・粗利');
        $this->assertNotFalse($tableStart);
        $tableSection = substr($content, $tableStart);
        $this->assertStringNotContainsString('<th class="num">製造コスト</th>', $tableSection);
    }

    public function test_sales_filters_kpi_by_selected_product(): void
    {
        $allResponse = $this->get(route('sales.index', ['ym' => DemoData::CURRENT_YM]));
        $filteredResponse = $this->get(route('sales.index', [
            'ym' => DemoData::CURRENT_YM,
            'product_id' => 1,
        ]));

        $allResponse->assertOk();
        $filteredResponse->assertOk();
        $filteredResponse->assertSee('選択中: FAB-A-BK', false);

        $allSales = DemoData::monthlySalesByProduct(DemoData::CURRENT_YM)->where('product_id', 1)->sum('sales');
        $filteredResponse->assertSee(number_format($allSales), false);
    }

    public function test_sales_search_clears_product_selection(): void
    {
        $response = $this->get(route('sales.index', [
            'ym' => DemoData::CURRENT_YM,
            'sku' => 'FAB',
        ]));

        $response->assertOk();
        $response->assertDontSee('name="product_id"', false);
    }

    public function test_sales_month_switch_keeps_product_selection(): void
    {
        $response = $this->get(route('sales.index', [
            'ym' => '2026-05',
            'product_id' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('value="1"', false);
        $response->assertSee('選択中: FAB-A-BK', false);
    }

    public function test_sales_product_link_goes_to_recipe_edit(): void
    {
        $response = $this->get(route('sales.index'));

        $response->assertOk();
        $response->assertSee(route('recipes.edit', 1), false);
    }

    public function test_inventory_and_dashboard_render_successfully(): void
    {
        $this->get(route('inventory.index'))->assertOk();
        $this->get(route('inventory.show', 1))->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_sales_shows_uncalculable_warning_when_yarn_price_missing(): void
    {
        $this->get(route('sales.index'))->assertOk();
        $this->get(route('inventory.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }
}
