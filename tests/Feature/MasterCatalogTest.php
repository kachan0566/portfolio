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
}
