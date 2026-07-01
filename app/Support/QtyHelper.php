<?php

namespace App\Support;

/**
 * 数量の表示・換算ヘルパー。
 *
 * 表示・入力は反数メイン。m は標準換算または上書き用。
 * Phase 2 以降、生地系の内部保存は反数（qty_tan）を正とする。
 */
class QtyHelper
{
    /** 製品品番の標準：1反あたりのメートル数 */
    public const METERS_PER_TAN_PRODUCT = 50;

    /** 生機品番の標準：1反あたりのメートル数 */
    public const METERS_PER_TAN_GREIGE = 100;

    /** 反数の最小刻み（0.05反まで） */
    public const TAN_STEP = 0.05;

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

    public static function roundTan(float|int $tan): float
    {
        $steps = round((float) $tan / self::TAN_STEP);

        return round($steps * self::TAN_STEP, self::TAN_DECIMALS);
    }

    public static function isValidTanStep(float|int $tan): bool
    {
        if ((float) $tan <= 0) {
            return false;
        }

        return abs((float) $tan - self::roundTan($tan)) < 0.0001;
    }

    public static function tanCount(float|int $meters, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): float
    {
        $perTan = self::metersPerTan($productId, $isGreige, $greigeSku);

        return $perTan > 0 ? self::roundTan((float) $meters / $perTan) : 0.0;
    }

    public static function metersFromTan(float|int $tan, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): int
    {
        $perTan = self::metersPerTan($productId, $isGreige, $greigeSku);
        $roundedTan = self::roundTan($tan);

        return (int) round($roundedTan * $perTan);
    }

    public static function formatTanCount(float|int $tan, ?int $decimals = null): string
    {
        $decimals ??= self::TAN_DECIMALS;
        $formatted = number_format((float) $tan, $decimals);

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /** 「2.4反 / 120m」形式（反メイン・mサブ） */
    public static function format(float|int $meters, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): string
    {
        $tan = self::tanCount($meters, $productId, $isGreige, $greigeSku);

        return self::formatTanCount($tan).'反 / '.number_format((int) $meters).'m';
    }

    /** 反数から表示（見込mは標準換算） */
    public static function formatFromTan(float|int $tan, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): string
    {
        $roundedTan = self::roundTan($tan);
        $meters = self::metersFromTan($roundedTan, $productId, $isGreige, $greigeSku);

        return self::formatTanCount($roundedTan).'反 / '.number_format($meters).'m';
    }

    /**
     * 品番ごとに換算した反数の合計（全品番集計用）。
     *
     * @param  iterable<int|string, object|array<string, mixed>>  $lines
     */
    public static function sumTanFromLines(
        iterable $lines,
        string $qtyKey,
        string $productIdKey = 'product_id',
        bool $isGreige = false,
        ?string $greigeSkuKey = null,
    ): float {
        $totalTan = 0.0;

        foreach ($lines as $line) {
            $row = (object) $line;
            $productId = $isGreige ? null : (int) ($row->{$productIdKey} ?? 0);
            $greigeSku = $greigeSkuKey !== null ? (string) ($row->{$greigeSkuKey} ?? '') : null;

            if (isset($row->qty_tan) && (float) $row->qty_tan > 0) {
                $totalTan += self::roundTan((float) $row->qty_tan);
            } else {
                $meters = (float) ($row->{$qtyKey} ?? 0);
                $totalTan += self::tanCount(
                    $meters,
                    $productId,
                    $isGreige,
                    $greigeSku !== '' ? $greigeSku : null,
                );
            }
        }

        return round($totalTan, self::TAN_DECIMALS);
    }

    /**
     * @param  iterable<int|string, object|array<string, mixed>>  $lines
     */
    public static function sumMetersFromLines(
        iterable $lines,
        string $qtyKey,
        string $productIdKey = 'product_id',
        bool $isGreige = false,
        ?string $greigeSkuKey = null,
    ): float {
        $total = 0.0;
        foreach ($lines as $line) {
            $row = (object) $line;
            if (isset($row->qty_meters) && (int) $row->qty_meters > 0) {
                $total += (int) $row->qty_meters;
            } elseif (isset($row->qty_tan) && (float) $row->qty_tan > 0) {
                $productId = $isGreige ? null : (int) ($row->{$productIdKey} ?? 0);
                $greigeSku = $greigeSkuKey !== null ? (string) ($row->{$greigeSkuKey} ?? '') : null;
                $total += self::metersFromTan(
                    (float) $row->qty_tan,
                    $productId,
                    $isGreige,
                    $greigeSku !== '' ? $greigeSku : null,
                );
            } else {
                $total += (float) ($row->{$qtyKey} ?? 0);
            }
        }

        return $total;
    }

    public static function formatAggregate(float $totalMeters, float $totalTan): string
    {
        return self::formatTanCount($totalTan).'反 / '.number_format((int) round($totalMeters)).'m';
    }

    /**
     * 単一品番ならその品番で、複数品番なら反数合算＋m合算で表示。
     *
     * @param  iterable<int|string, object|array<string, mixed>>  $lines
     */
    public static function formatAggregateFromLines(
        iterable $lines,
        string $qtyKey,
        ?int $productId = null,
        string $productIdKey = 'product_id',
        bool $isGreige = false,
        ?string $greigeSkuKey = null,
    ): string {
        if ($productId !== null) {
            $meters = (int) round(self::sumMetersFromLines($lines, $qtyKey));

            return self::format($meters, $isGreige ? null : $productId, $isGreige);
        }

        $totalMeters = self::sumMetersFromLines($lines, $qtyKey);
        $totalTan = self::sumTanFromLines($lines, $qtyKey, $productIdKey, $isGreige, $greigeSkuKey);

        return self::formatAggregate($totalMeters, $totalTan);
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

    /** 反明細の表示（1反 / 実測m、標準との差分付き） */
    public static function formatRoll(object|array $roll, bool $isGreige = false, ?int $productId = null, ?string $greigeSku = null): string
    {
        $row = (object) $roll;
        $actual = FabricTanRoll::actualMeters($row);
        $nominal = (int) ($row->nominal_meters ?? self::metersPerTan($productId, $isGreige, $greigeSku));
        $variance = round($actual - $nominal, 2);
        $base = '1反 / '.number_format($actual, 1).'m';

        if (abs($variance) < 0.05) {
            return $base;
        }

        $sign = $variance > 0 ? '+' : '';

        return $base.'（標準'.$nominal.'m '.$sign.number_format($variance, 1).'）';
    }

    public static function formatVariance(float $variance): string
    {
        if (abs($variance) < 0.05) {
            return '±0';
        }

        $sign = $variance > 0 ? '+' : '';

        return $sign.number_format($variance, 1).'m';
    }
}
