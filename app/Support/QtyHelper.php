<?php

namespace App\Support;

/**
 * メートル数量を「2.40反 / 120m」形式で表示・換算するヘルパー。
 * メートルが正（ソース・オブ・トゥルース）、反数は換算値。
 */
class QtyHelper
{
    /** 製品品番の標準：1反あたりのメートル数 */
    public const METERS_PER_TAN_PRODUCT = 50;

    /** 生機品番の標準：1反あたりのメートル数 */
    public const METERS_PER_TAN_GREIGE = 100;

    public const TAN_DECIMALS = 2;

    public static function metersPerTan(?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): int
    {
        if ($isGreige) {
            if ($greigeSku !== null) {
                $greige = DemoData::findGreige($greigeSku);
                if ($greige !== null && isset($greige->meters_per_tan)) {
                    return (int) $greige->meters_per_tan;
                }
            }

            return self::METERS_PER_TAN_GREIGE;
        }

        if ($productId !== null) {
            $product = DemoData::findProduct($productId);
            if ($product !== null && isset($product->meters_per_tan)) {
                return (int) $product->meters_per_tan;
            }
        }

        return self::METERS_PER_TAN_PRODUCT;
    }

    public static function tanCount(float|int $meters, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): float
    {
        $perTan = self::metersPerTan($productId, $isGreige, $greigeSku);

        return $perTan > 0 ? round((float) $meters / $perTan, self::TAN_DECIMALS) : 0.0;
    }

    public static function metersFromTan(float|int $tan, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): int
    {
        $perTan = self::metersPerTan($productId, $isGreige, $greigeSku);

        return (int) round((float) $tan * $perTan);
    }

    public static function formatTanCount(float|int $tan, ?int $decimals = null): string
    {
        $decimals ??= self::TAN_DECIMALS;
        $formatted = number_format((float) $tan, $decimals);

        return rtrim(rtrim($formatted, '0'), '.');
    }

    public static function format(float|int $meters, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): string
    {
        $tan = self::tanCount($meters, $productId, $isGreige, $greigeSku);

        return self::formatTanCount($tan).'反 / '.number_format((int) $meters).'m';
    }

    /** 生機反数 → 同長さの製品反数（例：生機1反100m → 製品2反） */
    public static function productTanFromGreigeMeters(float|int $greigeMeters, int $productId): float
    {
        return self::tanCount($greigeMeters, $productId, false);
    }

    /** 生機反数 → 同長さの製品反数（反数ベース換算） */
    public static function productTanFromGreigeTan(float|int $greigeTan, int $productId): float
    {
        $greigeSku = DemoData::findProduct($productId)?->greige_sku;
        $meters = self::metersFromTan($greigeTan, null, true, $greigeSku);

        return self::tanCount($meters, $productId, false);
    }
}
