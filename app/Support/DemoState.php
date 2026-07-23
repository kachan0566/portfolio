<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\Schema;

/**
 * 在庫・入荷・出荷の読み取り窓口。DB を正とし、未移行環境では DemoData の固定値へフォールバックする。
 */
class DemoState
{
    public static function effectiveReceived(int $poId): int
    {
        return (int) floor(self::effectiveReceivedQty($poId));
    }

    public static function effectiveReceivedQty(int $poId, ?object $po = null): float
    {
        if (DemoData::usesPurchaseOrderDatabase()) {
            $model = PurchaseOrder::query()->with('lines')->find($poId);
            if ($model !== null) {
                return self::receivedQtyFromPurchaseOrder($model);
            }
        }

        $po ??= self::findBasePurchase($poId);
        if (! $po) {
            return 0.0;
        }

        return (float) DemoData::purchaseOrderReceivedQty($po);
    }

    private static function receivedQtyFromPurchaseOrder(PurchaseOrder $purchaseOrder): float
    {
        $type = (string) $purchaseOrder->type;

        return match ($type) {
            PurchaseOrderType::YARN => (float) $purchaseOrder->lines->sum(
                fn ($line) => (float) ($line->received_qty_kg ?? 0),
            ),
            default => (float) $purchaseOrder->lines->sum(
                fn ($line) => (int) ($line->received_qty_m ?? 0),
            ),
        };
    }

    public static function poRemaining(int $poId): float
    {
        if (DemoData::usesPurchaseOrderDatabase()) {
            $model = PurchaseOrder::query()->with('lines')->find($poId);
            if ($model !== null) {
                $ordered = match ((string) $model->type) {
                    PurchaseOrderType::YARN => (float) $model->lines->sum(
                        fn ($line) => (float) ($line->qty_kg ?? 0),
                    ),
                    default => (float) $model->lines->sum(
                        fn ($line) => (int) ($line->qty_meters ?? 0),
                    ),
                };

                return max(0.0, $ordered - self::effectiveReceivedQty($poId, $model));
            }
        }

        $po = self::findBasePurchase($poId);
        if (! $po) {
            return 0.0;
        }

        $ordered = match ($po->type ?? PurchaseOrderType::PRODUCT) {
            PurchaseOrderType::YARN => DemoData::purchaseOrderOrderedQty($po),
            default => DemoData::purchaseOrderOrderedMeters($po),
        };

        return max(0.0, $ordered - self::effectiveReceivedQty($poId, $po));
    }

    public static function poLineRemaining(int $poLineId): float
    {
        if (DemoData::usesPurchaseOrderDatabase()) {
            $line = PurchaseOrderLine::query()->with('purchaseOrder')->find($poLineId);
            if ($line !== null) {
                return max(0.0, $line->orderedQty() - $line->receivedQty());
            }
        }

        return 0.0;
    }

    public static function effectiveStockTan(int $productId): float
    {
        FabricTanRoll::ensureBootstrapped();
        $rollTan = ProductRoll::stockTanForProduct($productId);
        if ($rollTan > 0) {
            return $rollTan;
        }

        if (DemoData::usesProductRollDatabase()) {
            return 0.0;
        }

        $product = DemoData::products()->firstWhere('id', $productId);
        if (! $product) {
            return 0.0;
        }

        return max(0.0, QtyHelper::roundReceivingTan(
            (float) ($product->stock_tan ?? QtyHelper::tanCount((int) ($product->stock ?? 0), $productId))
        ));
    }

    public static function effectiveStock(int $productId): int
    {
        FabricTanRoll::ensureBootstrapped();
        $rollM = ProductRoll::stockMetersForProduct($productId);
        if ($rollM > 0) {
            return (int) round($rollM);
        }

        return QtyHelper::metersFromTan(self::effectiveStockTan($productId), $productId);
    }

    public static function effectiveShippedTan(int $orderId): float
    {
        if (DemoData::usesShipmentDatabase()) {
            $order = Order::query()->find($orderId);

            return max(0.0, QtyHelper::roundReceivingTan((float) ($order?->shipped_qty_tan ?? 0)));
        }

        $row = DemoData::findBaseOrder($orderId);
        if (! $row) {
            return 0.0;
        }

        $productId = (int) $row['product_id'];

        return max(0.0, QtyHelper::roundReceivingTan(
            isset($row['shipped_tan'])
                ? (float) $row['shipped_tan']
                : QtyHelper::roundIntegerTan(QtyHelper::tanCount((int) ($row['shipped'] ?? 0), $productId))
        ));
    }

    public static function effectiveShippedM(int $orderId): int
    {
        if (DemoData::usesShipmentDatabase()) {
            $order = Order::query()->find($orderId);

            return max(0, (int) ($order?->shipped_qty_m ?? 0));
        }

        $row = DemoData::findBaseOrder($orderId);
        if (! $row) {
            return 0;
        }

        return max(0, (int) ($row['shipped_meters'] ?? $row['shipped'] ?? 0));
    }

    public static function effectiveShipped(int $orderId): int
    {
        return self::effectiveShippedM($orderId);
    }

    public static function orderRemainingTan(int $orderId): float
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return 0.0;
        }

        if (($order->order_qty_mode ?? 'tan') === 'meters') {
            $remainingM = self::orderRemainingM($orderId);
            if ($remainingM <= 0) {
                return 0.0;
            }

            return QtyHelper::tanCountCeilForShipment($remainingM, (int) $order->product_id);
        }

        $qtyTan = (float) ($order->qty_tan ?? QtyHelper::roundIntegerTan(
            QtyHelper::tanCount((int) $order->qty, (int) $order->product_id)
        ));

        return max(0.0, QtyHelper::roundReceivingTan($qtyTan - self::effectiveShippedTan($orderId)));
    }

    public static function orderRemainingM(int $orderId): int
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return 0;
        }

        if (($order->order_qty_mode ?? 'tan') === 'meters') {
            $qtyM = (int) ($order->qty_meters ?? $order->qty ?? 0);

            return max(0, $qtyM - self::effectiveShippedM($orderId));
        }

        return QtyHelper::metersFromTan(self::orderRemainingTan($orderId), (int) $order->product_id);
    }

    public static function orderRemaining(int $orderId): int
    {
        return self::orderRemainingM($orderId);
    }

    public static function applyShipment(int $orderId, int $productId, float $qtyTan, ?int $qtyMeters = null): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        $order = DemoData::orders()->firstWhere('id', $orderId);
        $isMetersOrder = ($order->order_qty_mode ?? 'tan') === 'meters';

        \App\Services\Shipment\ShipmentRegistrar::register(
            $orderId,
            $qtyTan,
            $isMetersOrder ? ($qtyMeters ?? self::orderRemainingM($orderId)) : null,
            DemoData::today(),
            null,
            null,
        );
    }

    public static function poHasReceived(int $poId): bool
    {
        return self::effectiveReceived($poId) > 0;
    }

    public static function poHasRemaining(int $poId): bool
    {
        return self::poRemaining($poId) > 0;
    }

    public static function effectivePoStage(int $poId): string
    {
        if (Schema::hasTable('purchase_orders')) {
            $po = PurchaseOrder::query()
                ->with(['lines'])
                ->find($poId);

            if ($po !== null) {
                $detail = $po->primaryLine();
                $raw = match ($po->type) {
                    PurchaseOrderType::GREIGE => $detail?->stage,
                    PurchaseOrderType::PRODUCT => $detail?->stage,
                    default => null,
                };

                return match ($po->type) {
                    PurchaseOrderType::PRODUCT => PurchaseOrderStages::normalizeProductManualStage($raw),
                    PurchaseOrderType::GREIGE => PurchaseOrderStages::normalizeGreigeManualStage($raw) ?? '',
                    default => '',
                };
            }
        }

        $row = DemoData::basePurchaseOrderRows()->firstWhere('id', $poId);
        if ($row !== null) {
            $row = array_merge($row, PurchaseOrderOverlay::overrides($poId));
        } else {
            $row = collect(PurchaseOrderOverlay::additions())->firstWhere('id', $poId);
        }

        if ($row === null) {
            return '';
        }

        $raw = (string) ($row['stage'] ?? '');
        $type = (string) ($row['type'] ?? '');

        return match ($type) {
            PurchaseOrderType::PRODUCT => PurchaseOrderStages::normalizeProductManualStage($raw !== '' ? $raw : null),
            PurchaseOrderType::GREIGE => PurchaseOrderStages::normalizeGreigeManualStage($raw !== '' ? $raw : null) ?? '',
            default => '',
        };
    }

    private static function findBasePurchase(int $poId): ?object
    {
        $row = DemoData::basePurchaseOrderRows()->firstWhere('id', $poId);
        if ($row) {
            $merged = array_merge($row, PurchaseOrderOverlay::overrides($poId));

            return DemoData::enrichPurchaseOrder($merged);
        }
        foreach (PurchaseOrderOverlay::additions() as $addition) {
            if ((int) ($addition['id'] ?? 0) === $poId) {
                return DemoData::enrichPurchaseOrder($addition);
            }
        }

        return null;
    }
}
