<?php

namespace App\Support;

use App\Models\Greige;
use App\Models\Product;

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

    /** 受注・発注・入荷・引当・出荷で共通する反数の最小刻み */
    public const TAN_STEP = 0.25;

    /** @deprecated TAN_STEPを使用。後方互換のため同じ0.25を返す */
    public const ORDER_PO_TAN_STEP = self::TAN_STEP;

    /** @deprecated TAN_STEPを使用。後方互換のため同じ0.25を返す */
    public const RECEIVING_TAN_STEP = self::TAN_STEP;

    public const TAN_DECIMALS = 2;

    public const RECEIVING_TAN_DECIMALS = 2;

    public static function metersPerTan(?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): int
    {
        if ($isGreige) {
            if ($greigeSku === null || $greigeSku === '') {
                throw new \InvalidArgumentException('生機SKUが指定されていないため、1反あたりのメートル数を取得できません。');
            }

            $greige = Greige::findBySku($greigeSku);
            if ($greige === null) {
                throw new \InvalidArgumentException("生機SKU {$greigeSku} がマスタに存在しません。");
            }

            return (int) $greige->meters_per_tan;
        }

        if ($productId === null || $productId <= 0) {
            throw new \InvalidArgumentException('製品IDが指定されていないため、1反あたりのメートル数を取得できません。');
        }

        $product = Product::query()->find($productId);
        if ($product === null) {
            throw new \InvalidArgumentException("製品ID {$productId} がマスタに存在しません。");
        }

        return (int) $product->meters_per_tan;
    }

    /**
     * 反数を全工程共通の0.25反刻みへ丸める。
     *
     * 入力検証では丸め前にisValidTanStepを使い、不正値を黙って補正しない。
     *
     * @param  float|int  $tan  丸める反数
     * @return float 0.25反刻みに丸めた反数
     */
    public static function roundTan(float|int $tan): float
    {
        $steps = round((float) $tan / self::TAN_STEP);

        return round($steps * self::TAN_STEP, self::TAN_DECIMALS);
    }

    /**
     * 正の反数が0.25の倍数かを判定する。
     *
     * @param  float|int  $tan  利用者が入力した丸め前の反数
     * @return bool 0.25の倍数ならtrue
     */
    public static function isValidTanStep(float|int $tan): bool
    {
        if ((float) $tan <= 0) {
            return false;
        }

        return abs((float) $tan - self::roundTan($tan)) < 0.0001;
    }

    public static function roundTanWithStep(float|int $tan, float $step): float
    {
        if ($step <= 0) {
            return 0.0;
        }

        $steps = (int) round((float) $tan / $step);

        return round($steps * $step, self::decimalsForStep($step));
    }

    public static function isValidTanStepWith(float|int $tan, float $step): bool
    {
        if ((float) $tan <= 0) {
            return false;
        }

        return abs((float) $tan - self::roundTanWithStep($tan, $step)) < 0.0001;
    }

    private static function decimalsForStep(float $step): int
    {
        if ($step >= 1) {
            return 0;
        }

        $formatted = rtrim(rtrim(sprintf('%.10F', $step), '0'), '.');
        $dot = strpos($formatted, '.');

        return $dot === false ? 0 : strlen(substr($formatted, $dot + 1));
    }

    public static function roundReceivingTan(float|int $tan): float
    {
        return self::roundTanWithStep($tan, self::RECEIVING_TAN_STEP);
    }

    public static function isValidReceivingTanStep(float|int $tan): bool
    {
        return self::isValidTanStepWith($tan, self::RECEIVING_TAN_STEP);
    }

    /** m受注の出荷：足りる反数を切り上げ（丸反出荷） */
    public static function tanCountCeilForShipment(
        float|int $meters,
        ?int $productId = null,
        bool $isGreige = false,
        ?string $greigeSku = null,
    ): float {
        $perTan = self::metersPerTan($productId, $isGreige, $greigeSku);
        if ($perTan <= 0 || (float) $meters <= 0) {
            return 0.0;
        }

        return (float) (int) ceil((float) $meters / $perTan);
    }

    /**
     * m数を表示・見積用の反数へ換算する。
     *
     * m指定受注や予測値は0.25反入力の対象外なので、0.25刻みへ丸めず小数2桁で返す。
     *
     * @param  float|int  $meters  換算元のm数
     * @param  int|null  $productId  製品の場合に標準m/反を取得する製品ID
     * @param  bool  $isGreige  生機として換算する場合はtrue
     * @param  string|null  $greigeSku  生機の場合に標準m/反を取得するSKU
     * @return float 表示・見積用の反数
     */
    public static function tanCount(float|int $meters, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): float
    {
        $perTan = self::metersPerTan($productId, $isGreige, $greigeSku);

        return $perTan > 0 ? round((float) $meters / $perTan, self::TAN_DECIMALS) : 0.0;
    }

    /**
     * 反数を品番の標準m/反で換算し、小数2桁のm数を返す。
     *
     * @param  float|int  $tan  換算する反数。入力値の刻み検証は呼び出し前に行う
     * @param  int|null  $productId  製品の場合に標準m/反を取得する製品ID
     * @param  bool  $isGreige  生機として換算する場合はtrue
     * @param  string|null  $greigeSku  生機の場合に標準m/反を取得するSKU
     * @return float 標準換算したm数（小数2桁）
     */
    public static function metersFromTan(float|int $tan, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): float
    {
        $perTan = self::metersPerTan($productId, $isGreige, $greigeSku);

        return round((float) $tan * $perTan, 2);
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

        return self::formatTanCount($tan).'反 / '.self::formatMeters($meters).'m';
    }

    /** 反数から表示（見込mは標準換算） */
    public static function formatFromTan(float|int $tan, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): string
    {
        $roundedTan = self::roundTan($tan);
        $meters = self::metersFromTan($roundedTan, $productId, $isGreige, $greigeSku);

        return self::formatTanCount($roundedTan).'反 / '.self::formatMeters($meters).'m';
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
            if (isset($row->qty_meters) && (float) $row->qty_meters > 0) {
                $total += (float) $row->qty_meters;
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
        return self::formatTanCount($totalTan).'反 / '.self::formatMeters($totalMeters).'m';
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
            $meters = self::sumMetersFromLines($lines, $qtyKey);

            return self::format($meters, $isGreige ? null : $productId, $isGreige);
        }

        $totalMeters = self::sumMetersFromLines($lines, $qtyKey);
        $totalTan = self::sumTanFromLines($lines, $qtyKey, $productIdKey, $isGreige, $greigeSkuKey);

        return self::formatAggregate($totalMeters, $totalTan);
    }

    /**
     * m数を小数2桁まで表示し、不要な末尾の0を省く。
     *
     * @param  float|int  $meters  表示するm数
     * @return string 桁区切り済みのm数（例: 1,250 / 12.5）
     */
    public static function formatMeters(float|int $meters): string
    {
        $formatted = number_format((float) $meters, 2);

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /** 生機反数 → 同長さの製品反数（例：生機1反100m → 製品2反） */
    public static function productTanFromGreigeMeters(float|int $greigeMeters, int $productId): float
    {
        return self::tanCount($greigeMeters, $productId, false);
    }

    /** 生機反数 → 同長さの製品反数（反数ベース換算） */
    public static function productTanFromGreigeTan(float|int $greigeTan, int $productId): float
    {
        $product = Product::query()->with('greige')->find($productId);
        if ($product === null) {
            throw new \InvalidArgumentException("製品ID {$productId} がマスタに存在しません。");
        }

        $greigeSku = $product->greige?->sku;
        if ($greigeSku === null || $greigeSku === '') {
            throw new \InvalidArgumentException("製品ID {$productId} に生機SKUが設定されていません。");
        }

        $meters = self::metersFromTan($greigeTan, null, true, $greigeSku);

        return self::tanCount($meters, $productId, false);
    }

    /** 反明細の表示（1反 / 実測m、標準との差分付き） */
    public static function formatRoll(object|array $roll, bool $isGreige = false, ?int $productId = null, ?string $greigeSku = null): string
    {
        $row = (object) $roll;
        $actual = FabricTanRoll::actualMeters($row);
        $nominal = (int) ($row->nominal_meters ?? self::metersPerTan($productId, $isGreige, $greigeSku));
        $tanQty = (float) ($row->tan_qty ?? 1.0);
        $variance = round($actual - ($nominal * $tanQty), 2);
        $base = QtyHelper::formatTanCount($tanQty).'反 / '.number_format($actual, 1).'m';

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
