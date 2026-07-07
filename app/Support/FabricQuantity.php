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
     * @return object{qty_tan: float, qty_meters: int, meters_overridden: bool}
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

        $metersInput = $qtyMeters !== null ? (int) round((float) $qtyMeters) : 0;
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
     * @return callable(float|int): float
     */
    private static function roundTanForContext(string $context): callable
    {
        return match ($context) {
            self::CONTEXT_ORDER, self::CONTEXT_PO, self::CONTEXT_SHIPMENT => fn (float|int $tan) => QtyHelper::roundIntegerTan($tan),
            self::CONTEXT_RECEIVING => fn (float|int $tan) => QtyHelper::roundReceivingTan($tan),
            default => fn (float|int $tan) => QtyHelper::roundTan($tan),
        };
    }

    public static function tanStepForContext(string $context): float
    {
        return match ($context) {
            self::CONTEXT_ORDER, self::CONTEXT_PO, self::CONTEXT_SHIPMENT => QtyHelper::ORDER_PO_TAN_STEP,
            self::CONTEXT_RECEIVING => QtyHelper::RECEIVING_TAN_STEP,
            default => QtyHelper::TAN_STEP,
        };
    }

    public static function isValidTanForContext(float|int $tan, string $context): bool
    {
        return match ($context) {
            self::CONTEXT_ORDER, self::CONTEXT_PO, self::CONTEXT_SHIPMENT => QtyHelper::isIntegerTan($tan),
            self::CONTEXT_RECEIVING => QtyHelper::isValidReceivingTanStep($tan),
            default => QtyHelper::isValidTanStep($tan),
        };
    }

    public static function metersFromRecord(
        object|array $record,
        ?int $productId = null,
        bool $isGreige = false,
        ?string $greigeSku = null,
    ): int {
        $row = (object) $record;

        if (isset($row->qty_meters) && (int) $row->qty_meters > 0) {
            return (int) $row->qty_meters;
        }

        if (isset($row->qty_tan) && (float) $row->qty_tan > 0) {
            return QtyHelper::metersFromTan(
                (float) $row->qty_tan,
                $productId ?? (isset($row->product_id) ? (int) $row->product_id : null),
                $isGreige,
                $greigeSku ?? ($row->greige_sku ?? null),
            );
        }

        return (int) ($row->qty ?? 0);
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
