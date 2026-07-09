<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Support\DemoData;
use App\Support\PurchaseOrderType;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedPurchaseOrders(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
    }

    public function test_purchase_order_seeder_matches_demo_base_rows(): void
    {
        $this->seedPurchaseOrders();

        $this->assertSame(DemoData::basePurchaseOrderRows()->count(), PurchaseOrder::query()->count());

        $po = PurchaseOrder::query()->where('code', 'PO-2606-001')->with('lines')->first();
        $this->assertNotNull($po);
        $this->assertSame(PurchaseOrderType::PRODUCT, $po->type);
        $this->assertSame(1, $po->order_id);
        $this->assertSame(200, $po->primaryLine()?->qty_meters);
        $this->assertSame(200, $po->primaryLine()?->received_qty_m);
    }

    public function test_purchase_order_display_object_matches_demo_shape(): void
    {
        $this->seedPurchaseOrders();

        $dbPo = PurchaseOrder::query()->where('code', 'PO-G-2606-001')->first();
        $demoPo = DemoData::basePurchaseOrderRows()->firstWhere('code', 'PO-G-2606-001');

        $this->assertNotNull($dbPo);
        $this->assertNotNull($demoPo);

        $display = $dbPo->toDisplayObject();
        $this->assertSame(PurchaseOrderType::GREIGE, $display->type);
        $this->assertSame('KB-A', $display->sku);
        $this->assertSame(500, $display->qty_meters);
        $this->assertNotEmpty($display->yarn_requirements);
    }

    public function test_purchase_index_page_loads_from_database(): void
    {
        $this->seedPurchaseOrders();

        $response = $this->get(route('purchases.index'));

        $response->assertOk();
        $response->assertSee('PO-2606-001');
        $response->assertSee('PO-Y-2606-001');
        $response->assertSee('PO-G-2606-001');
    }
}
