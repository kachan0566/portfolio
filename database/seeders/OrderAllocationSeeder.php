<?php

namespace Database\Seeders;

use App\Models\OrderAllocation;
use App\Support\DemoData;
use App\Support\QtyHelper;
use Illuminate\Database\Seeder;

class OrderAllocationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (DemoData::baseAllocationRows() as $row) {
            $productId = (int) $row['product_id'];
            $qtyTan = QtyHelper::roundTan((float) $row['qty_tan']);

            OrderAllocation::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'order_id' => (int) $row['order_id'],
                    'product_id' => $productId,
                    'purchase_order_id' => $row['purchase_order_id'] ?? null,
                    'allocation_type' => (string) $row['allocation_type'],
                    'qty_tan' => $qtyTan,
                    'qty_m' => QtyHelper::metersFromTan($qtyTan, $productId),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
