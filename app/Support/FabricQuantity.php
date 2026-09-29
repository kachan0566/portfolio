<?php

namespace App\Support;

/**
 * 生地系数量の正規化。反数（qty_tan）を正とし、m は標準換算または上書き値。
 */
class FabricQuantity
{
    public const CONTEXT_DEFAULT = 'default';

    public const CONTEXT_ORDER = 'order';

    public const CONTEXT_RECEIVING = 'receiving';

    public const CONTEXT_SHIPMENT = 'shipment';

    public const CONTEXT_PO = 'po';

    /**
     * 入力された反数またはm数を、用途ごとの正規化済み数量へ解決する。
     *
     * 反数入力は0.25反刻み、m指定は小数2桁を維持する。
     *
     * @param  float|int|null  $qtyTan  入力反数。反数指定時は事前に刻みを検証する
     * @param  float|int|null  $qtyMeters  入力m数。m指定受注ではこの値を正とする
     * @param  int|null  $productId  製品の標準m/反を取得するID
     * @param  bool  $isGreige  生機数量の場合はtrue
     * @param  string|null  $greigeSku  生機の標準m/反を取得するSKU
     * @param  string  $context  受注・発注・入荷・出荷などの利用場面
     * @return object{qty_tan: float, qty_meters: float, meters_overridden: bool} 解決済み数量
     */
    public static function resolve(
        float|int|null $qtyTan,
        float|int|null $qtyMeters,
        ?int $productId = null,
        bool $isGreige = false,
        ?string $greigeSku = null,
        string $context = self::CONTEXT_DEFAULT,
    ): object {
        $roundTan = self::roundTanForContext($context);

        $tan = $qtyTan !== null && (float) $qtyTan > 0
            ? $roundTan($qtyTan)
            : 0.0;

        $metersInput = $qtyMeters !== null ? round((float) $qtyMeters, 2) : 0.0;
        $nominalMeters = $tan > 0
            ? QtyHelper::metersFromTan($tan, $productId, $isGreige, $greigeSku)
            : 0;

        if ($metersInput > 0 && ($tan <= 0 || $metersInput !== $nominalMeters)) {
            $resolvedTan = $tan > 0
                ? $tan
                : QtyHelper::tanCount($metersInput, $productId, $isGreige, $greigeSku);

            return (object) [
                'qty_tan' => $roundTan($resolvedTan),
                'qty_meters' => $metersInput,
                'meters_overridden' => $tan <= 0 || $metersInput !== $nominalMeters,
            ];
        }

        return (object) [
            'qty_tan' => $tan,
            'qty_meters' => $nominalMeters,
            'meters_overridden' => false,
        ];
    }

    /**
     * 利用場面ごとの反数丸め処理を返す。
     *
     * m指定用のdefaultだけは参考反数を小数2桁で保持し、反数入力の各工程は0.25刻みにする。
     *
     * @param  string  $context  数量を使用する場面
     * @return callable(float|int): float 反数を正規化する関数
     */
    private static function roundTanForContext(string $context): callable
    {
        return match ($context) {
            self::CONTEXT_ORDER,
            self::CONTEXT_PO,
            self::CONTEXT_RECEIVING,
            self::CONTEXT_SHIPMENT => fn (float|int $tan) => QtyHelper::roundTan($tan),
            default => fn (float|int $tan) => round((float) $tan, QtyHelper::TAN_DECIMALS),
        };
    }

    /**
     * 利用場面で許可する反数刻みを返す。
     *
     * @param  string  $context  数量を使用する場面
     * @return float 全工程共通の0.25反
     */
    public static function tanStepForContext(string $context): float
    {
        return QtyHelper::TAN_STEP;
    }

    /**
     * 入力反数が利用場面共通の0.25反刻みかを判定する。
     *
     * @param  float|int  $tan  丸め前の入力反数
     * @param  string  $context  数量を使用する場面
     * @return bool 0.25の倍数ならtrue
     */
    public static function isValidTanForContext(float|int $tan, string $context): bool
    {
        return QtyHelper::isValidTanStep($tan);
    }

    /**
     * レコードに保存されたm数を優先し、なければ反数から小数2桁で換算する。
     *
     * @param  object|array<string, mixed>  $record  qty_meters・qty_tan・qtyを持つ数量レコード
     * @param  int|null  $productId  製品の標準m/反を取得するID
     * @param  bool  $isGreige  生機数量の場合はtrue
     * @param  string|null  $greigeSku  生機の標準m/反を取得するSKU
     * @return float 保存値または標準換算したm数（小数2桁）
     */
    public static function metersFromRecord(
        object|array $record,
        ?int $productId = null,
        bool $isGreige = false,
        ?string $greigeSku = null,
    ): float {
        $row = (object) $record;

        if (isset($row->qty_meters) && (float) $row->qty_meters > 0) {
            return round((float) $row->qty_meters, 2);
        }

        if (isset($row->qty_tan) && (float) $row->qty_tan > 0) {
            return QtyHelper::metersFromTan(
                (float) $row->qty_tan,
                $productId ?? (isset($row->product_id) ? (int) $row->product_id : null),
                $isGreige,
                $greigeSku ?? ($row->greige_sku ?? null),
            );
        }

        return round((float) ($row->qty ?? 0), 2);
    }

    public static function tanFromRecord(
        object|array $record,
        ?int $productId = null,
        bool $isGreige = false,
        ?string $greigeSku = null,
    ): float {
        $row = (object) $record;

        if (isset($row->qty_tan) && (float) $row->qty_tan > 0) {
            return QtyHelper::roundTan((float) $row->qty_tan);
        }

        $meters = self::metersFromRecord($row, $productId, $isGreige, $greigeSku);
        if ($meters <= 0) {
            return 0.0;
        }

        return QtyHelper::tanCount(
            $meters,
            $productId ?? (isset($row->product_id) ? (int) $row->product_id : null),
            $isGreige,
            $greigeSku ?? ($row->greige_sku ?? null),
        );
    }
}
