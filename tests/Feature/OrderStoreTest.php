<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Support\DemoData;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStoreTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrders(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(?int $customerId = null, ?int $productId = null): array
    {
        $customerId ??= Customer::query()->value('id');
        $productId ??= Product::query()->value('id');

        return [
            'customer_id' => $customerId,
            'product_id' => $productId,
            'order_qty_mode' => 'tan',
            'qty_tan' => 2,
            'order_date' => '2026-06-15',
            'due_date' => '2026-06-20',
            'ship_memo' => 'テスト受注',
        ];
    }

    public function test_store_creates_order_in_database(): void
    {
        $this->seedOrders();

        $before = Order::query()->count();

        $response = $this->post(route('orders.store'), $this->validPayload());

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame($before + 1, Order::query()->count());
        $response->assertRedirect(route('orders.show', $order->id));
        $this->get(route('orders.show', $order->id))->assertOk();
        $this->assertSame('SO-2606-011', $order->code);
        $this->assertSame(100, $order->qty_meters);
    }

    public function test_store_redirects_with_success_message(): void
    {
        $this->seedOrders();

        $response = $this->post(route('orders.store'), $this->validPayload());

        $order = Order::query()->latest('id')->first();
        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success', '受注を登録しました。在庫状況を確認してください。');
        $response->assertSessionHas('just_created', true);
    }

    public function test_update_persists_to_database(): void
    {
        $this->seedOrders();

        $order = Order::query()->where('code', 'SO-2606-010')->firstOrFail();

        $response = $this->put(route('orders.update', $order->id), [
            'customer_id' => $order->customer_id,
            'product_id' => $order->product_id,
            'order_qty_mode' => 'tan',
            'qty_tan' => 3,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-10',
            'ship_memo' => '更新テスト',
        ]);

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success', '受注を更新しました。');

        $order->refresh();
        $this->assertSame(3, $order->qty_tan);
        $this->assertSame(150, $order->qty_meters);
        $this->assertSame('2026-07-10', $order->due_date->toDateString());
        $this->assertSame('更新テスト', $order->ship_memo);
    }

    public function test_demo_data_orders_reads_from_database_after_seed(): void
    {
        $this->seedOrders();

        $this->assertSame(Order::query()->count(), DemoData::orders()->count());
    }
}
