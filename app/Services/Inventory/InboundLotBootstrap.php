<?php

namespace App\Services\Inventory;

use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\InboundLot;
use App\Support\PurchaseOrderType;
use App\Support\ShipmentLotConsumption;

/**
 * 既存の入荷・出荷デモデータから入荷ロットを初期化する。
 */
class InboundLotBootstrap
{
    public static function run(): void
    {
        $lots = [];
        $consumptions = [];
        $nextLotId = 1;
        $nextConsumptionId = 1;

        $receivings = DemoData::receivings()
            ->concat(collect(DemoState::extraReceivings())->map(fn ($r) => (object) $r))
            ->filter(fn ($r) => ($r->po_type ?? PurchaseOrderType::PRODUCT) === PurchaseOrderType::PRODUCT)
            ->sortBy([
                ['date', 'asc'],
            ])
            ->values();

        foreach ($receivings as $receiving) {
            $qty = (float) ($receiving->qty ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $poId = isset($receiving->po_id) ? (int) $receiving->po_id : null;
            $lots[] = [
                'id' => $nextLotId++,
                'product_id' => (int) $receiving->product_id,
                'receiving_code' => $receiving->code ?? null,
                'received_date' => $receiving->date,
                'received_qty_m' => $qty,
                'remaining_qty_m' => $qty,
                'purchase_order_id' => $poId,
                'source_type' => InboundLot::SOURCE_RECEIVING,
            ];
        }

        $shipments = DemoData::shipments()->sortBy([
            ['date', 'asc'],
            ['id', 'asc'],
        ]);

        foreach ($shipments as $shipment) {
            $productId = (int) $shipment->product_id;
            $remaining = (float) $shipment->qty;

            foreach ($lots as &$lot) {
                if ($remaining <= 0) {
                    break;
                }
                if ((int) $lot['product_id'] !== $productId) {
                    continue;
                }
                if ((float) $lot['remaining_qty_m'] <= 0) {
                    continue;
                }

                $take = min((float) $lot['remaining_qty_m'], $remaining);
                $lot['remaining_qty_m'] = round((float) $lot['remaining_qty_m'] - $take, 2);
                $consumptions[] = [
                    'id' => $nextConsumptionId++,
                    'shipment_ref' => (int) $shipment->id,
                    'inbound_lot_id' => (int) $lot['id'],
                    'consumed_qty_m' => $take,
                    'note' => $shipment->code ?? null,
                    'created_at' => $shipment->date.'T00:00:00+09:00',
                ];
                $remaining -= $take;
            }
            unset($lot);
        }

        foreach (DemoData::products() as $product) {
            $productId = (int) $product->id;
            $stock = (float) DemoState::effectiveStock($productId);
            $lotTotal = (float) collect($lots)
                ->filter(fn ($l) => (int) $l['product_id'] === $productId)
                ->sum('remaining_qty_m');

            $diff = round($stock - $lotTotal, 2);
            if ($diff > 0) {
                $lots[] = [
                    'id' => $nextLotId++,
                    'product_id' => $productId,
                    'receiving_code' => null,
                    'received_date' => '2024-03-10',
                    'received_qty_m' => $diff,
                    'remaining_qty_m' => $diff,
                    'purchase_order_id' => null,
                    'source_type' => InboundLot::SOURCE_OPENING_BALANCE,
                ];
            }
        }

        foreach ($lots as &$lot) {
            if ((int) $lot['product_id'] === 1
                && (float) $lot['remaining_qty_m'] > 0
                && $lot['source_type'] === InboundLot::SOURCE_RECEIVING) {
                $lot['received_date'] = '2024-03-10';
                break;
            }
        }
        unset($lot);

        InboundLot::replaceAll($lots);
        ShipmentLotConsumption::replaceAll($consumptions);
    }
}
