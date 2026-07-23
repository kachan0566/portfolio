<?php

namespace App\Support;

/**
 * 製品の有効在庫（反明細テーブル集計）。
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

    public static function effectiveStock(int $productId): int
    {
        $rollM = ProductRoll::stockMetersForProduct($productId);
        if ($rollM > 0) {
            return (int) round($rollM);
        }

        return QtyHelper::metersFromTan(self::effectiveStockTan($productId), $productId);
    }
}
