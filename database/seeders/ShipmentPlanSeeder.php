<?php

namespace Database\Seeders;

use App\Models\ShipmentPlanRecord;
use App\Models\User;
use App\Support\ShipmentPlan;
use Illuminate\Database\Seeder;

class ShipmentPlanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $createdBy = User::query()->value('id');

        foreach (ShipmentPlan::demoRows() as $row) {
            $productId = (int) $row['product_id'];
            $confirmedM = (float) $row['confirmed_qty_m'];
            $shippedM = (float) $row['shipped_qty_m'];

            ShipmentPlanRecord::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'code' => $row['code'],
                    'order_id' => $row['order_id'],
                    'product_id' => $productId,
                    'planned_ship_date' => $row['planned_ship_date'],
                    'confirmed_qty_m' => $confirmedM,
                    'confirmed_qty_tan' => ShipmentPlanRecord::tanFromMeters($confirmedM, $productId),
                    'shipped_qty_m' => $shippedM,
                    'shipped_qty_tan' => $shippedM > 0
                        ? ShipmentPlanRecord::tanFromMeters($shippedM, $productId)
                        : 0.0,
                    'status' => $row['status'],
                    'note' => $row['note'] ?? '',
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
