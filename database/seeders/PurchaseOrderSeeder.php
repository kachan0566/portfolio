<?php

namespace Database\Seeders;

use App\Models\Greige;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Support\DemoData;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Database\Seeder;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $greigeIdsBySku = Greige::query()->pluck('id', 'sku');

        foreach (DemoData::basePurchaseOrderRows() as $row) {
            $type = (string) $row['type'];

            PurchaseOrder::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'code' => $row['code'],
                    'type' => $type,
                    'status' => $row['status'],
                    'supplier_id' => $row['supplier_id'],
                    'ship_to_id' => $row['ship_to_id'],
                    'order_id' => $row['order_id'] ?? null,
                    'order_date' => $row['order_date'],
                    'due_date' => $row['due_date'],
                    'arrival_memo' => $row['arrival_memo'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $linePayload = [
                'purchase_order_id' => $row['id'],
                'line_no' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($type === PurchaseOrderType::YARN) {
                PurchaseOrderLine::query()->updateOrCreate(
                    ['purchase_order_id' => $row['id'], 'line_no' => 1],
                    array_merge($linePayload, [
                        'material_id' => $row['material_id'],
                        'qty_kg' => $row['qty_kg'],
                        'received_qty_kg' => $row['received_kg'] ?? 0,
                    ]),
                );
            } elseif ($type === PurchaseOrderType::GREIGE) {
                $sku = (string) $row['greige_sku'];
                $greigeId = $greigeIdsBySku->get($sku);
                if ($greigeId === null) {
                    continue;
                }

                $metersPerTan = (int) ($row['meters_per_tan'] ?? DemoData::METERS_PER_TAN_GREIGE);
                $qtyTan = (int) round((float) ($row['qty_tan'] ?? 0));
                $receivedM = (int) ($row['received'] ?? 0);
                $receivedTan = $metersPerTan > 0
                    ? round($receivedM / $metersPerTan, 2)
                    : 0.0;

                PurchaseOrderLine::query()->updateOrCreate(
                    ['purchase_order_id' => $row['id'], 'line_no' => 1],
                    array_merge($linePayload, [
                        'greige_id' => $greigeId,
                        'qty_tan' => $qtyTan,
                        'meters_per_tan' => $metersPerTan,
                        'qty_meters' => (int) ($row['qty_meters'] ?? 0),
                        'received_qty_tan' => $receivedTan,
                        'received_qty_m' => $receivedM,
                        'stage' => PurchaseOrderStages::normalizeGreigeManualStage($row['stage'] ?? null),
                    ]),
                );
            } else {
                $productId = (int) $row['product_id'];
                $qtyMeters = (int) ($row['qty_meters'] ?? 0);
                $qtyTan = isset($row['qty_tan']) && (float) $row['qty_tan'] > 0
                    ? (int) round((float) $row['qty_tan'])
                    : (int) QtyHelper::tanCount($qtyMeters, $productId);
                $receivedM = (int) ($row['received'] ?? 0);
                $product = Product::query()->find($productId);
                $perTan = (int) ($product?->meters_per_tan ?? DemoData::METERS_PER_TAN_PRODUCT);
                $receivedTan = $perTan > 0
                    ? round($receivedM / $perTan, 2)
                    : 0.0;

                PurchaseOrderLine::query()->updateOrCreate(
                    ['purchase_order_id' => $row['id'], 'line_no' => 1],
                    array_merge($linePayload, [
                        'product_id' => $productId,
                        'qty_tan' => $qtyTan,
                        'qty_meters' => $qtyMeters,
                        'received_qty_tan' => $receivedTan,
                        'received_qty_m' => $receivedM,
                        'stage' => PurchaseOrderStages::normalizeProductManualStage($row['stage'] ?? null),
                        'finish_date' => $row['finish_date'] ?? null,
                        'contact_date' => $row['contact_date'] ?? null,
                    ]),
                );
            }
        }
    }
}
