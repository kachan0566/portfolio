<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Support\DemoData;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrders(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
    }

    public function test_order_seeder_matches_demo_base_rows(): void
    {
        $this->seedOrders();

        $this->assertSame(DemoData::baseOrderRows()->count(), Order::query()->count());

        $order = Order::query()->where('code', 'SO-2606-001')->first();
        $this->assertNotNull($order);
        $this->assertSame('東レ商事', $order->customer->name);
        $this->assertSame('FAB-A-BK', $order->product->sku);
        $this->assertSame('tan', $order->order_qty_mode);
        $this->assertSame(120, $order->shipped_qty_m);
    }

    public function test_order_display_object_matches_demo_shape(): void
    {
        $this->seedOrders();

        $dbOrder = Order::query()->where('code', 'SO-2606-002')->first();
        $demoOrder = DemoData::orders()->firstWhere('code', 'SO-2606-002');

        $this->assertNotNull($dbOrder);
        $this->assertNotNull($demoOrder);

        $display = $dbOrder->toDisplayObject();
        $this->assertSame($demoOrder->customer, $display->customer);
        $this->assertSame($demoOrder->sku, $display->sku);
        $this->assertSame($demoOrder->qty, $display->qty);
        $this->assertSame($demoOrder->status, $display->status);
    }

    public function test_order_index_page_loads_from_database(): void
    {
        $this->seedOrders();

        $response = $this->get(route('orders.index'));

        $response->assertOk();
        $response->assertSee('SO-2606-001');
        $response->assertSee('東レ商事');
    }

    public function test_customer_show_lists_orders_by_customer_id(): void
    {
        $this->seedOrders();

        $customer = Customer::query()->where('name', '東レ商事')->first();
        $this->assertNotNull($customer);

        $response = $this->get(route('customers.show', $customer->id));

        $response->assertOk();
        $response->assertSee('SO-2606-001');
        $response->assertSee('SO-2606-005');
        $response->assertSee('SO-2606-009');
    }
}
