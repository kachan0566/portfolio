<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderAllocation;
use App\Support\StockAllocation;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAllocationWriteTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrdersOnly(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
    }

    private function seedAllocations(): void
    {
        $this->seedOrdersOnly();
        $this->seed(OrderAllocationSeeder::class);
    }

    public function test_order_tables_ready_after_seed(): void
    {
        $this->seedOrdersOnly();

        $this->assertGreaterThan(0, Order::query()->count());
        $this->assertSame(0, OrderAllocation::query()->count());
    }

    public function test_add_line_persists_to_database(): void
    {
        $this->seedOrdersOnly();

        StockAllocation::addLine(3, 10, 0, 1.0, StockAllocation::TYPE_STOCK);

        $row = OrderAllocation::query()
            ->where('order_id', 10)
            ->where('allocation_type', StockAllocation::TYPE_STOCK)
            ->whereNull('purchase_order_id')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(50, $row->qty_m);
        $this->assertSame(1.0, (float) $row->qty_tan);
    }

    public function test_save_from_typed_maps_replaces_product_rows_in_database(): void
    {
        $this->seedAllocations();

        StockAllocation::saveFromTypedMaps(3, [
            2 => [
                StockAllocation::TYPE_STOCK => [2 => 1.0],
                StockAllocation::TYPE_PO => [2 => 1.0],
            ],
        ]);

        $this->assertSame(
            2,
            OrderAllocation::query()->where('product_id', 3)->count()
        );
        $this->assertSame(
            50,
            StockAllocation::stockAllocatedForOrder(2)
        );
        $this->assertSame(
            50,
            StockAllocation::poAllocatedForOrder(2)
        );
    }

    public function test_clear_for_order_removes_rows_from_database(): void
    {
        $this->seedAllocations();

        StockAllocation::clearForOrder(2);

        $this->assertSame(0, OrderAllocation::query()->where('order_id', 2)->count());
        $this->assertSame(0, StockAllocation::get(2));
    }

    public function test_remove_line_from_order_removes_single_row_from_database(): void
    {
        $this->seedAllocations();

        StockAllocation::removeLineFromOrder(2, 2, StockAllocation::TYPE_PO);

        $this->assertNull(
            OrderAllocation::query()
                ->where('order_id', 2)
                ->where('purchase_order_id', 2)
                ->where('allocation_type', StockAllocation::TYPE_PO)
                ->first()
        );
        $this->assertSame(80, StockAllocation::stockAllocatedForOrder(2));
        $this->assertSame(0, StockAllocation::poAllocatedForOrder(2));
    }

    public function test_order_save_allocation_route_persists_to_database(): void
    {
        $this->seedAllocations();

        $order = Order::query()->where('code', 'SO-2606-003')->firstOrFail();

        $response = $this->post(route('orders.save-allocation', $order->id), [
            'allocations' => [
                $order->id => [
                    StockAllocation::TYPE_PO => ['3' => 2],
                ],
            ],
        ]);

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success', '引当を更新しました。');

        $this->assertSame(
            100,
            StockAllocation::poAllocatedForOrder($order->id)
        );
    }
}
