<?php

namespace Tests\Unit;

use App\Support\DemoData;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderDisplay;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use Tests\TestCase;

class PurchaseOrderDisplayTest extends TestCase
{
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
