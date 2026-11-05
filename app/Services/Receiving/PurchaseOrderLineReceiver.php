<?php

namespace App\Services\Receiving;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Receiving\ReceivingLine;
use App\Support\Purchase\PurchaseOrderStatus;
use App\Support\Purchase\PurchaseOrderType;
use App\Support\Qty\QtyHelper;

/**
 * 入荷実績を発注明細の累計数量と発注ステータスへ反映する。
 */
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
        $receivedM = round((float) $lines->sum(fn ($row) => (float) $row->qty_m), 2);

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
     * 発注種別に応じた発注数量と入荷済み数量を返す。
     *
     * 糸はkg、生機・製品は実測mの増減に左右されない反数を完了判定に使う。
     *
     * @param  string  $type  発注種別
     * @param  PurchaseOrderLine  $line  判定対象の発注明細
     * @return array{0: float, 1: float} 発注数量、入荷済み数量の順
     */
    private static function orderedAndReceived(string $type, PurchaseOrderLine $line): array
    {
        return match ($type) {
            PurchaseOrderType::YARN => [
                (float) ($line->qty_kg ?? 0),
                (float) ($line->received_qty_kg ?? 0),
            ],
            default => [
                $line->orderedTan(),
                $line->receivedTan(),
            ],
        };
    }
}
