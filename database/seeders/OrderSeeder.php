<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Support\DemoData;
use App\Support\FabricQuantity;
use App\Support\QtyHelper;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $customerIdsByName = Customer::query()->pluck('id', 'name');

        foreach (DemoData::baseOrderRows() as $row) {
            $customerId = $row['customer_id']
                ?? $customerIdsByName->get($row['customer'] ?? '');

            if ($customerId === null) {
                continue;
            }

            $productId = (int) $row['product_id'];
            $mode = $row['order_qty_mode'] ?? 'tan';

            $qtyTan = $mode === 'tan'
                ? QtyHelper::roundIntegerTan((float) ($row['qty_tan'] ?? FabricQuantity::tanFromRecord($row, $productId)))
                : FabricQuantity::tanFromRecord($row, $productId);

            $qtyMeters = $mode === 'meters'
                ? (int) ($row['qty_meters'] ?? $row['qty'] ?? 0)
                : FabricQuantity::metersFromRecord(
                    ['qty_tan' => $qtyTan, 'qty_meters' => $row['qty_meters'] ?? null],
                    $productId,
                );

            $shippedM = (int) ($row['shipped_meters'] ?? $row['shipped'] ?? 0);
            $shippedTan = FabricQuantity::tanFromRecord(
                ['qty_tan' => $row['shipped_tan'] ?? null, 'qty' => $shippedM],
                $productId,
            );

            Order::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'code' => $row['code'],
                    'customer_id' => $customerId,
                    'product_id' => $productId,
                    'order_qty_mode' => $mode,
                    'qty_tan' => $mode === 'tan' ? (int) $qtyTan : 0,
                    'qty_meters' => $qtyMeters,
                    'shipped_qty_tan' => $shippedTan,
                    'shipped_qty_m' => $shippedM,
                    'order_date' => $row['order_date'],
                    'due_date' => $row['due_date'],
                    'planned_ship_date' => $row['planned_ship_date'] ?? null,
                    'ship_memo' => $row['ship_memo'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
