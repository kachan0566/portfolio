<?php

namespace App\Support;

/**
 * 送料（数量ベース: 円/m）。
 */
class FreightCalculator
{
    public const DEFAULT_RATE_PER_M = 50;

    public static function ratePerM(?int $productId = null): int
    {
        return self::DEFAULT_RATE_PER_M;
    }

    public static function forQty(float $qtyM, ?int $productId = null): int
    {
        if ($qtyM <= 0) {
            return 0;
        }

        return (int) round($qtyM * self::ratePerM($productId));
    }
}
