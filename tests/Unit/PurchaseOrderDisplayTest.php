<?php

namespace Tests\Unit;

use App\Support\DemoData;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderType;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_received_product_shows_received_label(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-2606-001');
        $this->assertNotNull($po);
        $this->assertSame(PurchaseOrderStages::LABEL_RECEIVED, $po->stage);
    }

    public function test_partial_product_shows_in_stock_with_partial_suffix(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-2606-002');
        $this->assertNotNull($po);
        $this->assertSame(
            PurchaseOrderStages::PRODUCT_IN_STOCK.PurchaseOrderStages::PARTIAL_SUFFIX,
            $po->stage
        );
    }

    public function test_partial_yarn_shows_weaving_receipt_with_partial_suffix(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-Y-2606-002');
        $this->assertNotNull($po);
        $this->assertSame(
            PurchaseOrderStages::YARN_RECEIVED_AT_WEAVING.PurchaseOrderStages::PARTIAL_SUFFIX,
            $po->stage
        );
    }

    public function test_partial_greige_shows_shipped_with_partial_suffix(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-G-2606-002');
        $this->assertNotNull($po);
        $this->assertSame(
            PurchaseOrderStages::GREIGE_SHIPPED.PurchaseOrderStages::PARTIAL_SUFFIX,
            $po->stage
        );
    }

    public function test_ordered_yarn_shows_ordered_stage(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-Y-2606-001');
        $this->assertNotNull($po);
        $this->assertSame(PurchaseOrderStages::YARN_ORDERED, $po->stage);
    }

    public function test_greige_without_yarn_ready_shows_ordered(): void
    {
        $po = DemoData::purchaseOrders()->firstWhere('code', 'PO-G-2606-001');
        $this->assertNotNull($po);
        $this->assertSame(PurchaseOrderStages::GREIGE_ORDERED, $po->stage);
    }
}
