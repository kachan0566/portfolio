<?php

namespace Tests\Feature;

use App\Models\GreigeRecipe;
use App\Support\DemoData;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GreigeRecipeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(CostFoundationSeeder::class);
    }

    public function test_index_shows_greige_recipe_tab(): void
    {
        $response = $this->get(route('recipes.index', ['tab' => 'greige']));

        $response->assertOk();
        $response->assertSee('生機レシピ');
        $response->assertSee('KB-A');
        $response->assertSee('ロス率');
        $response->assertSee('織賃');
        $response->assertSee('生機単価');
    }

    public function test_greige_create_form(): void
    {
        $response = $this->get(route('recipes.greige.create'));

        $response->assertOk();
        $response->assertSee('ロス率（%）');
        $response->assertSee('織賃（円/m）');
        $response->assertSee('KB-C');
        $response->assertDontSee('KB-A');
    }

    public function test_store_greige_recipe_persists_to_database(): void
    {
        $response = $this->post(route('recipes.greige.store'), [
            'greige_sku' => 'KB-C',
            'loss_rate_percent' => 4,
            'weaving_cost' => 110,
            'lines' => [
                ['material_id' => 2, 'qty' => 1.5],
            ],
        ]);

        $response->assertRedirect(route('recipes.index', ['tab' => 'greige']));
        $this->assertTrue(DemoData::hasGreigeRecipe('KB-C'));
        $this->assertDatabaseHas('greige_recipes', ['weaving_cost' => 110]);

        $requirements = DemoData::greigeYarnRequirements('KB-C', 100);
        $this->assertCount(1, $requirements);
        $this->assertSame(156.0, $requirements[0]->required_kg);
    }

    public function test_edit_greige_recipe(): void
    {
        $response = $this->get(route('recipes.greige.edit', 'KB-A'));

        $response->assertOk();
        $response->assertSee('KB-A');
        $response->assertSee('value="3"', false);
        $response->assertSee('value="120"', false);
    }

    public function test_greige_unit_cost_includes_weaving_and_loss(): void
    {
        $ym = DemoData::CURRENT_YM;
        $breakdown = DemoData::greigeUnitCostBreakdown('KB-A', $ym);

        $this->assertTrue($breakdown->calculable);
        $this->assertSame(120.0, $breakdown->weaving_cost);
        // 2.0 kg/m × (1 + 3%) × 550 円/kg + 織賃 120 円/m
        $this->assertSame(1133.0, $breakdown->yarn_cost);
        $this->assertSame(1253.0, $breakdown->total);
        $this->assertSame(1253.0, DemoData::greigeUnitCost('KB-A', $ym));
    }
}
