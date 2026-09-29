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

    /**
     * 受注登録で反数と換算mがDBへ保存されることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
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
        $this->assertSame(100.0, (float) $order->qty_meters);
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

    /**
     * 0.25反の受注を保存し、製品50m/反から換算した12.5mを正確に保持することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_accepts_quarter_tan_and_keeps_decimal_meters(): void
    {
        $this->seedOrders();

        $payload = $this->validPayload();
        $payload['qty_tan'] = 0.25;

        $response = $this->post(route('orders.store'), $payload);

        $response->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(0.25, (float) $order->qty_tan);
        $this->assertSame(12.5, (float) $order->qty_meters);
    }

    /**
     * 0.25の倍数ではない0.3反を受注として保存しないことを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_store_rejects_tan_outside_quarter_step(): void
    {
        $this->seedOrders();
        $before = Order::query()->count();

        $payload = $this->validPayload();
        $payload['qty_tan'] = 0.3;

        $response = $this->post(route('orders.store'), $payload);

        $response->assertSessionHasErrors('qty_tan');
        $this->assertSame($before, Order::query()->count());
    }

    /**
     * 受注更新で小数対応列と日付・メモが正しく更新されることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
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
        $this->assertSame(3.0, (float) $order->qty_tan);
        $this->assertSame(150.0, (float) $order->qty_meters);
        $this->assertSame('2026-07-10', $order->due_date->toDateString());
        $this->assertSame('更新テスト', $order->ship_memo);
    }

    public function test_demo_data_orders_reads_from_database_after_seed(): void
    {
        $this->seedOrders();

        $this->assertSame(Order::query()->count(), DemoData::orders()->count());
    }
}
