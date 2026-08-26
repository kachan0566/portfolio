<?php

namespace Tests\Feature;

use App\Models\AllocationConversion;
use App\Models\PurchaseOrder;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationConversionAndPoLinkTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
    }

    public function test_link_purchase_updates_purchase_orders_order_id(): void
    {
        $this->seedBase();

        PurchaseOrder::query()->whereKey(7)->update(['order_id' => null]);
        $this->assertNull(PurchaseOrder::query()->find(7)?->order_id);

        $response = $this->post(route('orders.link-purchase', [7, 7]));

        $response->assertRedirect(route('orders.show', 7));
        $this->assertDatabaseHas('purchase_orders', [
            'id' => 7,
            'order_id' => 7,
        ]);
    }

    public function test_link_purchase_rejects_already_linked_po(): void
    {
        $this->seedBase();

        $response = $this->post(route('orders.link-purchase', [8, 7]));

        $response->assertRedirect(route('orders.show', 8));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('purchase_orders', [
            'id' => 7,
            'order_id' => 7,
        ]);
    }

    public function test_receiving_records_allocation_conversion_in_database(): void
    {
        $this->seedBase();

        $qty = 20;
        ReceivingRegistrar::register(
            7,
            '2026-07-10',
            PurchaseOrderType::PRODUCT,
            qtyTan: QtyHelper::tanCount($qty, 6),
            qtyMeters: $qty,
            rollLines: [['tan_qty' => QtyHelper::tanCount($qty, 6), 'actual_qty_m' => (float) $qty]],
        );

        $this->assertDatabaseCount('allocation_conversions', 1);

        $events = AllocationConversion::eventsForOrder(7);
        $this->assertCount(1, $events);
        $this->assertSame(7, $events[0]['po_id']);
        $this->assertSame(7, $events[0]['order_id']);
        $this->assertSame($qty, $events[0]['qty']);
        $this->assertNotSame('', $events[0]['at']);
    }

    public function test_events_for_product_filters_by_product_purchase_orders(): void
    {
        $this->seedBase();

        $qty = 20;
        ReceivingRegistrar::register(
            7,
            '2026-07-10',
            PurchaseOrderType::PRODUCT,
            qtyTan: QtyHelper::tanCount($qty, 6),
            qtyMeters: $qty,
            rollLines: [['tan_qty' => QtyHelper::tanCount($qty, 6), 'actual_qty_m' => (float) $qty]],
        );

        $product6Events = AllocationConversion::eventsForProduct(6);
        $product7Events = AllocationConversion::eventsForProduct(7);

        $this->assertCount(1, $product6Events);
        $this->assertCount(0, $product7Events);
    }
}
