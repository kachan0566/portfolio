<?php

namespace App\Services\Receiving;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReceivingLine;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;

class PurchaseOrderLineReceiver
{
    public static function syncFromReceivingLine(ReceivingLine $line): void
    {
        $line->loadMissing('purchaseOrderLine.purchaseOrder');
        $poLine = $line->purchaseOrderLine;
        if ($poLine === null) {
            return;
        }

        self::syncPurchaseOrderLine($poLine);
        self::syncPurchaseOrderStatus($poLine->purchaseOrder);
    }

    public static function syncPurchaseOrderLine(PurchaseOrderLine $poLine): void
    {
        $poLine->loadMissing('purchaseOrder');
        $type = (string) ($poLine->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        $lines = ReceivingLine::query()
            ->where('purchase_order_line_id', $poLine->id)
            ->get();

        if ($type === PurchaseOrderType::YARN) {
            $poLine->update([
                'received_qty_kg' => round((float) $lines->sum(fn ($row) => (float) $row->qty_kg), 3),
            ]);

            return;
        }

        $receivedTan = QtyHelper::roundReceivingTan((float) $lines->sum(fn ($row) => (float) $row->qty_tan));
        $receivedM = (int) $lines->sum(fn ($row) => (int) $row->qty_m);

        $poLine->update([
            'received_qty_tan' => $receivedTan,
            'received_qty_m' => $receivedM,
        ]);
    }

    public static function syncPurchaseOrderStatus(?PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder === null) {
            return;
        }

        $purchaseOrder->load('lines');
        if ($purchaseOrder->lines->isEmpty()) {
            return;
        }

        if (in_array($purchaseOrder->status, [PurchaseOrderStatus::CANCELLED, PurchaseOrderStatus::DRAFT], true)) {
            return;
        }

        $type = (string) $purchaseOrder->type;
        $allComplete = true;
        $anyReceived = false;

        foreach ($purchaseOrder->lines as $line) {
            [$ordered, $received] = self::orderedAndReceived($type, $line);
            if ($received > 0.0001) {
                $anyReceived = true;
            }
            if ($received + 0.0001 < $ordered) {
                $allComplete = false;
            }
        }

        $nextStatus = match (true) {
            $allComplete && $anyReceived => PurchaseOrderStatus::RECEIVED,
            $anyReceived => PurchaseOrderStatus::PARTIAL,
            default => $purchaseOrder->status === PurchaseOrderStatus::PARTIAL
                ? PurchaseOrderStatus::ORDERED
                : $purchaseOrder->status,
        };

        if ($nextStatus !== $purchaseOrder->status) {
            $purchaseOrder->update(['status' => $nextStatus]);
        }
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function orderedAndReceived(string $type, PurchaseOrderLine $line): array
    {
        return match ($type) {
            PurchaseOrderType::YARN => [
                (float) ($line->qty_kg ?? 0),
                (float) ($line->received_qty_kg ?? 0),
            ],
            PurchaseOrderType::GREIGE => [
                (float) ($line->qty_meters ?? 0),
                (float) ($line->received_qty_m ?? 0),
            ],
            default => [
                (float) ($line->qty_meters ?? 0),
                (float) ($line->received_qty_m ?? 0),
            ],
        };
    }
}
