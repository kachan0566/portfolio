<?php

namespace App\Support\Qty;

use App\Models\Master\Greige;
use App\Models\Master\Product;

/**
 * 数量の表示・換算ヘルパー。
 *
 * 表示・入力は反数メイン。m は標準換算または上書き用。
 * Phase 2 以降、生地系の内部保存は反数（qty_tan）を正とする。
 */
class QtyHelper
{
    /** @var int 製品品番の標準：1反あたりのメートル数（マスタ未設定時のフォールバック参照用） */
    public const METERS_PER_TAN_PRODUCT = 50;

    /** @var int 生機品番の標準：1反あたりのメートル数（マスタ未設定時のフォールバック参照用） */
    public const METERS_PER_TAN_GREIGE = 100;

    /** @var float 受注・発注・入荷・引当・出荷で共通する反数の最小刻み */
    public const TAN_STEP = 0.25;

    /**
     * @deprecated TAN_STEP を使用
     * @var float 後方互換のため TAN_STEP と同じ 0.25
     */
    public const ORDER_PO_TAN_STEP = self::TAN_STEP;

    /**
     * @deprecated TAN_STEP を使用
     * @var float 後方互換のため TAN_STEP と同じ 0.25
     */
    public const RECEIVING_TAN_STEP = self::TAN_STEP;

    /** @var int 反数表示・丸め結果の小数桁数 */
    public const TAN_DECIMALS = 2;

    /** @var int 入荷画面などで参照する反数の小数桁数（現状 TAN_DECIMALS と同値） */
    public const RECEIVING_TAN_DECIMALS = 2;

    /**
     * 品番マスタから1反あたりの標準m数を取得する。
     *
     * @param  int|null  $productId  製品の場合に Product を検索する内部 ID
     * @param  bool  $isGreige  true のとき生機として $greigeSku で Greige を検索する
     * @param  string|null  $greigeSku  生機 SKU（$isGreige が true のとき必須）
     * @return int  マスタの meters_per_tan（整数）
     *
     * @throws \InvalidArgumentException  ID/SKU 未指定・マスタに存在しない場合
     */
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

    /**
     * 任意の刻み $step で反数を丸める（$step が 0 以下のときは 0.0）。
     *
     * @param  float|int  $tan  丸める反数
     * @param  float  $step  反数の刻み（例: 0.25）
     * @return float  刻みに合わせた反数
     */
    public static function roundTanWithStep(float|int $tan, float $step): float
    {
        if ($step <= 0) {
            return 0.0;
        }

        $steps = (int) round((float) $tan / $step);

        return round($steps * $step, self::decimalsForStep($step));
    }

    /**
     * 正の反数が指定刻み $step の倍数かを roundTanWithStep と比較して判定する。
     *
     * @param  float|int  $tan  判定する反数
     * @param  float  $step  反数の刻み
     * @return bool  倍数なら true（0 以下は false）
     */
    public static function isValidTanStepWith(float|int $tan, float $step): bool
    {
        if ((float) $tan <= 0) {
            return false;
        }

        return abs((float) $tan - self::roundTanWithStep($tan, $step)) < 0.0001;
    }

    /**
     * 刻み $step の小数桁数を求め、number_format の桁数に使う。
     *
     * @param  float  $step  反数の刻み
     * @return int  小数桁数（1 以上の整数刻みは 0）
     */
    private static function decimalsForStep(float $step): int
    {
        if ($step >= 1) {
            return 0;
        }

        $formatted = rtrim(rtrim(sprintf('%.10F', $step), '0'), '.');
        $dot = strpos($formatted, '.');

        return $dot === false ? 0 : strlen(substr($formatted, $dot + 1));
    }

    /**
     * 入荷用刻み RECEIVING_TAN_STEP で反数を丸める。
     *
     * @param  float|int  $tan  丸める反数
     * @return float  入荷刻みに合わせた反数
     */
    public static function roundReceivingTan(float|int $tan): float
    {
        return self::roundTanWithStep($tan, self::RECEIVING_TAN_STEP);
    }

    /**
     * 正の反数が入荷用刻み RECEIVING_TAN_STEP の倍数かを判定する。
     *
     * @param  float|int  $tan  判定する反数
     * @return bool  倍数なら true
     */
    public static function isValidReceivingTanStep(float|int $tan): bool
    {
        return self::isValidTanStepWith($tan, self::RECEIVING_TAN_STEP);
    }

    /**
     * m指定数量を満たすために必要な反数を切り上げ整数相当の float で返す（丸反出荷用）。
     *
     * @param  float|int  $meters  出荷対象の m 数
     * @param  int|null  $productId  製品の標準 m/反取得用 ID
     * @param  bool  $isGreige  生機として換算する場合は true
     * @param  string|null  $greigeSku  生機の標準 m/反取得用 SKU
     * @return float  必要反数（m または m/反 が 0 以下のとき 0.0）
     */
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
     * m 数を標準 m/反で割り、表示・見積用の反数（TAN_DECIMALS 桁、刻み丸めなし）を返す。
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

    /**
     * 反数を桁区切り表示し、末尾の不要な 0 を省く。
     *
     * @param  float|int  $tan  表示する反数
     * @param  int|null  $decimals  小数桁（null のとき TAN_DECIMALS）
     * @return string  表示用文字列（単位「反」は付けない）
     */
    public static function formatTanCount(float|int $tan, ?int $decimals = null): string
    {
        $decimals ??= self::TAN_DECIMALS;
        $formatted = number_format((float) $tan, $decimals);

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * m 数から「反数 / m数」形式の表示文字列を返す（反メイン・m サブ）。
     *
     * @param  float|int  $meters  表示元の m 数
     * @param  int|null  $productId  換算用の製品 ID
     * @param  bool  $isGreige  生機として換算する場合は true
     * @param  string|null  $greigeSku  生機 SKU
     * @return string  例: 「2.4反 / 120m」
     */
    public static function format(float|int $meters, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): string
    {
        $tan = self::tanCount($meters, $productId, $isGreige, $greigeSku);

        return self::formatTanCount($tan).'反 / '.self::formatMeters($meters).'m';
    }

    /**
     * 反数入力から「反数 / 標準換算 m 数」形式の表示文字列を返す。
     *
     * @param  float|int  $tan  表示する反数（内部で TAN_STEP 丸め）
     * @param  int|null  $productId  標準 m/反取得用の製品 ID
     * @param  bool  $isGreige  生機として換算する場合は true
     * @param  string|null  $greigeSku  生機 SKU
     * @return string  例: 「2.5反 / 125m」
     */
    public static function formatFromTan(float|int $tan, ?int $productId = null, bool $isGreige = false, ?string $greigeSku = null): string
    {
        $roundedTan = self::roundTan($tan);
        $meters = self::metersFromTan($roundedTan, $productId, $isGreige, $greigeSku);

        return self::formatTanCount($roundedTan).'反 / '.self::formatMeters($meters).'m';
    }

    /**
     * 明細行ごとに品番の m/反で反数へ換算し、合計反数を返す（複数品番集計用）。
     *
     * @param  iterable<int|string, object|array<string, mixed>>  $lines  qty_tan または $qtyKey の m 数を持つ行
     * @param  string  $qtyKey  m 数が入るプロパティ名（qty_tan 優先時は未使用）
     * @param  string  $productIdKey  行オブジェクト上の製品 ID キー
     * @param  bool  $isGreige  生機明細として扱う場合は true
     * @param  string|null  $greigeSkuKey  生機 SKU のキー名（null なら SKU なし）
     * @return float  合計反数（TAN_DECIMALS 桁で丸め）
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
     * 明細行から m 数を合算する（qty_meters・qty_tan・$qtyKey の優先順で読む）。
     *
     * @param  iterable<int|string, object|array<string, mixed>>  $lines  数量を持つ明細行
     * @param  string  $qtyKey  フォールバックの m 数キー
     * @param  string  $productIdKey  qty_tan 換算時の製品 ID キー
     * @param  bool  $isGreige  生機明細として qty_tan を換算する場合は true
     * @param  string|null  $greigeSkuKey  生機 SKU キー名
     * @return float  合計 m 数
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

    /**
     * 合計反数と合計 m 数から「反 / m」形式の表示文字列を返す。
     *
     * @param  float  $totalMeters  合計 m 数
     * @param  float  $totalTan  合計反数
     * @return string  例: 「10反 / 500m」
     */
    public static function formatAggregate(float $totalMeters, float $totalTan): string
    {
        return self::formatTanCount($totalTan).'反 / '.self::formatMeters($totalMeters).'m';
    }

    /**
     * 明細から数量表示を組み立てる（単一品番は format、複数品番は合算後 formatAggregate）。
     *
     * @param  iterable<int|string, object|array<string, mixed>>  $lines  明細行
     * @param  string  $qtyKey  m 数キー（sumMetersFromLines 等に渡す）
     * @param  int|null  $productId  指定時は単一品番としてその ID で format する
     * @param  string  $productIdKey  複数品番時の製品 ID キー
     * @param  bool  $isGreige  生機明細として集計する場合は true
     * @param  string|null  $greigeSkuKey  生機 SKU キー名
     * @return string  「反 / m」形式の表示文字列
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
     * m 数を小数 2 桁・桁区切りで表示し、末尾の不要な 0 を省く。
     *
     * @param  float|int  $meters  表示する m 数
     * @return string  桁区切り済み m 数（単位「m」は付けない）
     */
    public static function formatMeters(float|int $meters): string
    {
        $formatted = number_format((float) $meters, 2);

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * 生機側の m 長を、指定製品の標準 m/反で割った製品反数を返す。
     *
     * @param  float|int  $greigeMeters  生機換算の m 長
     * @param  int  $productId  製品マスタ ID
     * @return float  製品反数（tanCount と同じ丸め規則）
     */
    public static function productTanFromGreigeMeters(float|int $greigeMeters, int $productId): float
    {
        return self::tanCount($greigeMeters, $productId, false);
    }

    /**
     * 生機反数を製品に紐づく生機 SKU 経由で m に換算し、同長の製品反数を返す。
     *
     * @param  float|int  $greigeTan  生機反数
     * @param  int  $productId  製品マスタ ID（紐づく生機 SKU を参照）
     * @return float  同長換算後の製品反数
     *
     * @throws \InvalidArgumentException  製品不存在・生機 SKU 未設定
     */
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

    /**
     * 反ロール1件の表示文字列を返す（反数 / 実測 m、標準 m との差が大きいとき注記付き）。
     *
     * @param  object|array<string, mixed>  $roll  tan_qty・nominal_meters 等を持つ反明細
     * @param  bool  $isGreige  標準 m/反取得を生機として行う場合は true
     * @param  int|null  $productId  製品の標準 m/反取得用 ID
     * @param  string|null  $greigeSku  生機 SKU
     * @return string  画面表示用1行
     */
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

    /**
     * 標準 m との差分（m）を符号付きで表示する（微小差は「±0」）。
     *
     * @param  float  $variance  実測と標準の差（m）
     * @return string  例: 「+1.2m」「±0」
     */
    public static function formatVariance(float $variance): string
    {
        if (abs($variance) < 0.05) {
            return '±0';
        }

        $sign = $variance > 0 ? '+' : '';

        return $sign.number_format($variance, 1).'m';
    }
}
