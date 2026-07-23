<?php

namespace Tests\Feature;

use App\Models\Greige;
use App\Models\Material;
use App\Models\Product;
use App\Support\DemoData;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
    }

    public function test_master_catalog_seeder_matches_demo_data(): void
    {
        $this->seedCatalog();

        $this->assertSame(DemoData::materials()->count(), Material::query()->count());
        $this->assertSame(DemoData::products()->count(), Product::query()->count());

        $material = Material::query()->where('sku', 'RM-001')->first();
        $this->assertNotNull($material);
        $this->assertSame('綿糸', $material->name);
        $this->assertSame('yarn', $material->type);

        $product = Product::query()->where('sku', 'FAB-A-BK')->first();
        $this->assertNotNull($product);
        $this->assertSame('ブラック', $product->color);
        $this->assertSame(50, $product->meters_per_tan);
        $this->assertSame(100, $product->stock_min_m);

        $greige = Greige::query()->where('sku', 'KB-A')->first();
        $this->assertNotNull($greige);
        $this->assertSame($greige->id, $product->greige_id);
    }

    public function test_product_display_object_resolves_greige_sku(): void
    {
        $this->seedCatalog();

        $product = Product::query()->where('sku', 'FAB-T-WH')->first();
        $this->assertNotNull($product);

        $display = $product->toDisplayObject();
        $this->assertSame('KB-T', $display->greige_sku);
        $this->assertSame('Tシャツ生機', $display->greige_name);
    }

    public function test_product_index_page_loads_from_database(): void
    {
        $this->seedCatalog();

        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('FAB-A-BK');
        $response->assertSee('ブラック');
    }

    public function test_product_find_display_returns_catalog_shape(): void
    {
        $this->seedCatalog();

        $display = Product::findDisplay(3);
        $this->assertNotNull($display);
        $this->assertSame(3, $display->id);
        $this->assertSame('FAB-T-WH', $display->sku);
        $this->assertSame('KB-T', $display->greige_sku);
        $this->assertSame(50, $display->meters_per_tan);
    }

    public function test_product_display_list_matches_database_count(): void
    {
        $this->seedCatalog();

        $this->assertSame(Product::query()->count(), Product::displayList()->count());
    }

    public function test_greige_display_helpers_resolve_by_sku_and_product(): void
    {
        $this->seedCatalog();

        $display = Greige::findDisplayBySku('KB-A');
        $this->assertNotNull($display);
        $this->assertSame('生機A', $display->name);
        $this->assertSame(100, $display->meters_per_tan);

        $greige = Greige::findByProductId(3);
        $this->assertNotNull($greige);
        $this->assertSame('KB-T', $greige->sku);

        $this->assertSame(Greige::query()->count(), Greige::displayList()->count());
    }

    public function test_material_display_helpers_filter_yarn_only(): void
    {
        $this->seedCatalog();

        $display = Material::findDisplay(1);
        $this->assertNotNull($display);
        $this->assertSame('RM-001', $display->sku);
        $this->assertSame('yarn', $display->type);

        $yarnMaterials = Material::yarnMaterials();
        $this->assertCount(2, $yarnMaterials);
        $this->assertTrue($yarnMaterials->every(fn ($row) => $row->type === 'yarn'));
        $this->assertTrue(Material::isYarn(1));
        $this->assertFalse(Material::isYarn(3));
    }

    public function test_product_category_options_are_distinct(): void
    {
        $this->seedCatalog();

        $categories = Product::categoryOptions();
        $this->assertGreaterThan(0, $categories->count());
        $this->assertSame(
            $categories->pluck('name')->unique()->count(),
            $categories->count(),
        );
    }
}
