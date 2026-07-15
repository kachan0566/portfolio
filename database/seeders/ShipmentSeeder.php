<?php

namespace Database\Seeders;

use App\Models\ProductRoll;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Receiving;
use App\Models\ReceivingLine;
use App\Models\Shipment;
use App\Models\ShipTo;
use App\Models\Supplier;
use App\Services\Fabric\TanRollRecorder;
use App\Services\Shipment\ShipmentRegistrar;
use App\Support\DemoData;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $this->seedOpeningStockForProduct2($now);

        $rows = collect(ShipmentRegistrar::demoRows())
            ->sortBy([
                ['shipped_date', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        foreach ($rows as $row) {
            $qtyM = (int) $row['qty_m'];
            $productId = (int) $row['product_id'];

            $shipment = Shipment::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'code' => $row['code'],
                    'order_id' => $row['order_id'],
                    'product_id' => $productId,
                    'qty_tan' => QtyHelper::tanCount($qtyM, $productId),
                    'qty_m' => $qtyM,
                    'shipped_date' => $row['shipped_date'],
                    'ship_to_name' => $row['ship_to_name'] ?? null,
                    'note' => $row['note'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            ShipmentRegistrar::replayDemoShipment($shipment->fresh(), $qtyM);
        }
    }

    private function seedOpeningStockForProduct2(\Illuminate\Support\Carbon $now): void
    {
        if (ProductRoll::query()->where('product_id', 2)->exists()) {
            return;
        }

        $supplierId = Supplier::query()->value('id');
        $shipToId = ShipTo::query()->value('id');

        $po = PurchaseOrder::query()->firstOrCreate(
            ['code' => 'PO-OPEN-002'],
            [
                'type' => PurchaseOrderType::PRODUCT,
                'status' => PurchaseOrderStatus::RECEIVED,
                'supplier_id' => $supplierId,
                'ship_to_id' => $shipToId,
                'order_date' => '2026-06-01',
                'due_date' => '2026-06-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $poLine = PurchaseOrderLine::query()->firstOrCreate(
            ['purchase_order_id' => $po->id, 'line_no' => 1],
            [
                'product_id' => 2,
                'qty_tan' => 2,
                'qty_meters' => 60,
                'received_qty_tan' => 2,
                'received_qty_m' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $receiving = Receiving::query()->firstOrCreate(
            ['code' => 'RC-OPEN-002'],
            [
                'received_date' => '2026-06-01',
                'note' => '期首繰越（デモ）',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $receivingLine = ReceivingLine::query()->firstOrCreate(
            ['receiving_id' => $receiving->id, 'line_no' => 1],
            [
                'purchase_order_line_id' => $poLine->id,
                'qty_tan' => 2,
                'qty_m' => 60,
                'qty_kg' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $perRoll = TanRollRecorder::distributeMeters(60, 2);
        $nominal = DemoData::METERS_PER_TAN_PRODUCT;

        foreach ($perRoll as $index => $actual) {
            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            ProductRoll::query()->firstOrCreate(
                ['code' => 'FAB-B-NV-OPENING-'.$seq],
                [
                    'product_id' => 2,
                    'purchase_order_id' => $po->id,
                    'receiving_line_id' => $receivingLine->id,
                    'tan_qty' => 1.0,
                    'actual_qty_m' => $actual,
                    'nominal_meters' => $nominal,
                    'status' => ProductRoll::STATUS_IN_STOCK,
                    'received_date' => '2026-06-01',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
