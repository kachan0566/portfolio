<?php

namespace App\Support;

use App\Models\ReceivingLine;
use App\Models\Shipment;
use Illuminate\Support\Collection;

/**
 * 製品の有効在庫（反明細テーブル集計）と入出庫履歴。
 */
class ProductStock
{
    public static function effectiveStockTan(int $productId): float
    {
        $rollTan = ProductRoll::stockTanForProduct($productId);
        if ($rollTan > 0) {
            return $rollTan;
        }

        return 0.0;
    }

    public static function effectiveStock(int $productId): float
    {
        return self::inStockRollTotals($productId)->meters;
    }

    /**
     * 在庫反明細（in_stock）の反数・実測m合計。在庫数量の正は反明細の足し算。
     *
     * @return object{tan: float, meters: float}
     */
    public static function inStockRollTotals(int $productId): object
    {
        return (object) [
            'tan' => ProductRoll::stockTanForProduct($productId),
            'meters' => ProductRoll::stockMetersForProduct($productId),
        ];
    }

    /**
     * 指定発注に紐づく在庫反明細（in_stock）の合計。
     *
     * @return object{tan: float, meters: float}
     */
    public static function inStockRollTotalsForPo(int $poId): object
    {
        $rolls = ProductRoll::forPo($poId, ProductRoll::STATUS_IN_STOCK);

        return (object) [
            'tan' => round((float) $rolls->sum('tan_qty'), 2),
            'meters' => round((float) $rolls->sum('actual_qty_m'), 2),
        ];
    }

    /**
     * 製品入出庫履歴（製品発注の入荷 + 出荷実績から組み立て）。
     *
     * @return Collection<int, object{
     *     date: string,
     *     product_id: int,
     *     type: string,
     *     qty: float,
     *     note: string,
     *     product: string,
     *     sku: string,
     *     unit: string
     * }>
     */
    public static function stockMovements(): Collection
    {
        return self::inboundMovements()
            ->concat(self::outboundMovements())
            ->sortByDesc(fn (object $movement) => $movement->date.'-'.$movement->sort_key)
            ->values()
            ->map(function (object $movement) {
                unset($movement->sort_key);

                return $movement;
            });
    }

    /** @return Collection<int, object> */
    private static function inboundMovements(): Collection
    {
        return ReceivingLine::query()
            ->with([
                'receiving',
                'purchaseOrderLine.purchaseOrder',
                'purchaseOrderLine.product',
            ])
            ->whereHas(
                'purchaseOrderLine.purchaseOrder',
                fn ($query) => $query->where('type', PurchaseOrderType::PRODUCT),
            )
            ->get()
            ->map(function (ReceivingLine $line) {
                $receiving = $line->receiving;
                $poLine = $line->purchaseOrderLine;
                $productId = (int) ($poLine?->product_id ?? 0);
                if ($productId <= 0 || $receiving === null) {
                    return null;
                }

                $product = MasterCatalog::findProduct($productId);
                if ($product === null) {
                    return null;
                }

                $qtyM = (float) ($line->qty_m ?? 0);
                if ($qtyM <= 0) {
                    return null;
                }

                return (object) [
                    'date' => $receiving->received_date?->toDateString() ?? '',
                    'product_id' => $productId,
                    'type' => '入庫',
                    'qty' => $qtyM,
                    'note' => '入荷 '.(string) $receiving->code,
                    'product' => $product->sku,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'sort_key' => sprintf('1-%06d', $line->id),
                ];
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, object> */
    private static function outboundMovements(): Collection
    {
        return Shipment::query()
            ->orderByDesc('shipped_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Shipment $shipment) {
                $productId = (int) $shipment->product_id;
                if ($productId <= 0) {
                    return null;
                }

                $product = MasterCatalog::findProduct($productId);
                if ($product === null) {
                    return null;
                }

                $qtyM = (float) $shipment->qty_m;
                if ($qtyM <= 0) {
                    return null;
                }

                return (object) [
                    'date' => $shipment->shipped_date?->toDateString() ?? '',
                    'product_id' => $productId,
                    'type' => '出庫',
                    'qty' => $qtyM,
                    'note' => '出荷 '.(string) $shipment->code,
                    'product' => $product->sku,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'sort_key' => sprintf('0-%06d', $shipment->id),
                ];
            })
            ->filter()
            ->values();
    }
}
