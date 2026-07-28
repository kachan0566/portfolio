<?php

namespace Tests\Unit;

use App\Support\FabricQuantity;
use App\Support\MasterCatalog;
use App\Support\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabricQuantityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
    }

    public function test_resolve_order_context_requires_integer_tan(): void
    {
        $resolved = FabricQuantity::resolve(2, null, 1, false, null, FabricQuantity::CONTEXT_ORDER);

        $this->assertSame(2.0, $resolved->qty_tan);
        $this->assertSame(100, $resolved->qty_meters);
    }

    public function test_resolve_receiving_context_uses_quarter_step(): void
    {
        $resolved = FabricQuantity::resolve(1.25, null, 1, false, null, FabricQuantity::CONTEXT_RECEIVING);

        $this->assertSame(1.25, $resolved->qty_tan);
    }

    public function test_resolve_tan_is_canonical(): void
    {
        $resolved = FabricQuantity::resolve(2.4, null, 1);

        $this->assertSame(2.4, $resolved->qty_tan);
        $this->assertSame(120, $resolved->qty_meters);
        $this->assertFalse($resolved->meters_overridden);
    }

    public function test_resolve_meter_override(): void
    {
        $resolved = FabricQuantity::resolve(2.4, 115, 1);

        $this->assertSame(2.4, $resolved->qty_tan);
        $this->assertSame(115, $resolved->qty_meters);
        $this->assertTrue($resolved->meters_overridden);
    }

    public function test_tan_from_record_prefers_qty_tan(): void
    {
        $tan = FabricQuantity::tanFromRecord(['qty_tan' => 1.5, 'qty' => 999], 1);

        $this->assertSame(1.5, $tan);
    }

    public function test_meters_from_record_prefers_qty_meters(): void
    {
        $meters = FabricQuantity::metersFromRecord(['qty_tan' => 2.0, 'qty_meters' => 115], 1);

        $this->assertSame(115, $meters);
    }

    public function test_meters_from_tan_uses_database_meters_per_tan(): void
    {
        $product = MasterCatalog::findProduct(1);

        $this->assertNotNull($product);
        $this->assertSame(50, $product->meters_per_tan);
        $this->assertSame(100, QtyHelper::metersFromTan(2.0, 1));
    }
}
