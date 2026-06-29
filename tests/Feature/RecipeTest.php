<?php

namespace Tests\Feature;

use App\Support\DemoData;
use App\Support\DemoOverlay;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DemoOverlay::clear();
    }

    public function test_index_shows_cost_breakdown_sections(): void
    {
        $response = $this->get(route('recipes.index'));

        $response->assertOk();
        $response->assertSee('生機単価');
        $response->assertSee('染色加工料');
        $response->assertSee('製造コスト合計');
        $response->assertSee('販売価格');
        $response->assertSee('粗利');
        $response->assertSee('粗利率');
        $response->assertSee('算出不可（生機レシピ未登録）');
    }

    public function test_index_shows_profit_badges_for_product_with_recipe(): void
    {
        $response = $this->get(route('recipes.index'));

        $response->assertOk();
        $response->assertSee('FAB-A-BK');
        $response->assertSee('販売価格 1,200 円/m');
        $response->assertSee('粗利 -528 円/m · -44%');
    }

    public function test_index_reflects_saved_price_on_badges(): void
    {
        DemoOverlay::saveProductPrice(1, 2500);

        $response = $this->get(route('recipes.index'));

        $response->assertOk();
        $response->assertSee('販売価格 2,500 円/m');
        $response->assertSee('粗利 772 円/m · 30.9%');
    }

    public function test_create_form_has_dyeing_cost_field(): void
    {
        $response = $this->get(route('recipes.create'));

        $response->assertOk();
        $response->assertSee('染色加工料（円/m）');
        $response->assertSee('生機レシピから参照');
        $response->assertDontSee('RM-001');
    }

    public function test_edit_form_has_dyeing_cost_field(): void
    {
        $response = $this->get(route('recipes.edit', 1));

        $response->assertOk();
        $response->assertSee('染色加工料（円/m）');
        $response->assertSee('販売価格（円/m）');
        $response->assertSee('コスト・粗利サマリー');
        $response->assertSee('粗利率');
        $response->assertSee('KB-A');
        $response->assertSee('value="475"', false);
        $response->assertSee('value="1200"', false);
    }

    public function test_update_saves_recipe_and_price_overlay(): void
    {
        $response = $this->put(route('recipes.update', 1), [
            'processing_cost' => 500,
            'price' => 2500,
        ]);

        $response->assertRedirect(route('recipes.index'));
        $this->assertSame(500, DemoData::processingCost(1));
        $this->assertSame(2500, DemoData::findProduct(1)->price);

        $breakdown = DemoData::unitCostBreakdown(1, '2026-06');
        $this->assertSame(500.0, $breakdown->processing_cost);
        $this->assertSame(1753.0, $breakdown->total);

        $summary = DemoData::unitProfitSummary(1, '2026-06');
        $this->assertSame(747, $summary->profit);
        $this->assertSame(29.9, $summary->margin_percent);
    }
}
