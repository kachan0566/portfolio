<?php

namespace Tests\Feature;

use App\Models\MaterialPrice;
use App\Support\DemoData;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YarnPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(CostFoundationSeeder::class);
    }

    public function test_index_shows_yarn_price_screen(): void
    {
        $response = $this->get(route('prices.index'));

        $response->assertOk();
        $response->assertSee('月別糸価格');
        $response->assertSee('綿糸');
        $response->assertSee('ポリエステル糸');
        $response->assertDontSee('染料');
        $response->assertDontSee('仕上げ剤');
    }

    public function test_store_validates_and_persists_yarn_price_to_database(): void
    {
        $response = $this->post(route('prices.store'), [
            'material_id' => 1,
            'ym' => '2026-07',
            'price' => 600,
        ]);

        $response->assertRedirect(route('prices.index'));
        $this->assertSame(600, DemoData::yarnPrice(1, '2026-07'));
        $this->assertDatabaseHas('material_prices', [
            'material_id' => 1,
            'ym' => '2026-07',
            'unit_price' => 600,
        ]);

        $index = $this->get(route('prices.index'));
        $index->assertSee('2026-07');
        $index->assertSee('600');
    }

    public function test_store_rejects_duplicate_yarn_price(): void
    {
        $response = $this->post(route('prices.store'), [
            'material_id' => 1,
            'ym' => '2026-06',
            'price' => 999,
        ]);

        $response->assertSessionHasErrors('ym');
    }

    public function test_update_changes_existing_yarn_price(): void
    {
        $row = DemoData::materialPrices()->firstWhere('ym', '2026-06');

        $response = $this->put(route('prices.update', $row->id), [
            'price' => 580,
        ]);

        $response->assertRedirect(route('prices.index'));
        $this->assertSame(580, DemoData::yarnPrice($row->material_id, $row->ym));
        $this->assertDatabaseHas('material_prices', [
            'material_id' => $row->material_id,
            'ym' => $row->ym,
            'unit_price' => 580,
        ]);
    }
}
