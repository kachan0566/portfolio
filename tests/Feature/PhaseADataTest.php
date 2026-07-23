<?php

namespace Tests\Feature;

use App\Support\DemoData;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\ShipToType;
use App\Support\SupplierType;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseADataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
    }

    public function test_purchase_orders_have_three_types(): void
    {
        $orders = DemoData::purchaseOrders();

        $this->assertTrue($orders->contains(fn ($po) => $po->type === PurchaseOrderType::YARN));
        $this->assertTrue($orders->contains(fn ($po) => $po->type === PurchaseOrderType::GREIGE));
        $this->assertTrue($orders->contains(fn ($po) => $po->type === PurchaseOrderType::PRODUCT));
    }

    public function test_greige_purchase_order_has_yarn_requirements(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-G-2606-001');
        $this->assertNotNull($po);
        $this->assertSame(PurchaseOrderType::GREIGE, $po->type);
        $this->assertNotEmpty($po->yarn_requirements);
    }

    public function test_suppliers_filtered_by_purchase_type(): void
    {
        $yarnSuppliers = DemoData::suppliersForPurchaseType(PurchaseOrderType::YARN);
        $this->assertTrue($yarnSuppliers->every(fn ($s) => $s->type === SupplierType::SPINNING));

        $greigeSuppliers = DemoData::suppliersForPurchaseType(PurchaseOrderType::GREIGE);
        $this->assertTrue($greigeSuppliers->every(fn ($s) => $s->type === SupplierType::WEAVING));
    }

    public function test_ship_tos_filtered_by_purchase_type(): void
    {
        $yarnDest = DemoData::shipTosForPurchaseType(PurchaseOrderType::YARN);
        $this->assertTrue($yarnDest->every(fn ($s) => $s->type === ShipToType::WEAVING));

        $productDest = DemoData::shipTosForPurchaseType(PurchaseOrderType::PRODUCT);
        $this->assertTrue(
            $productDest->every(fn ($s) => in_array($s->type, [ShipToType::DYEING, ShipToType::WAREHOUSE], true))
        );
    }

    public function test_product_purchase_order_shows_received_label_when_complete(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-2606-001');
        $this->assertSame(PurchaseOrderType::PRODUCT, $po->type);
        $this->assertSame(PurchaseOrderStatus::RECEIVED, $po->status);
        $this->assertSame('入荷完了', $po->stage);
    }
}
