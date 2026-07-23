<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 反明細ファサード。内部は GreigeRoll / ProductRoll に委譲。
 *
 * @deprecated 新規コードは GreigeRoll / ProductRoll を直接使う。
 */
class FabricTanRoll
{
    public const STAGE_GREIGE_WIP = 'greige_wip';

    public const STAGE_PRODUCT = 'product_stock';

    public const STAGE_CONSUMED = 'consumed';

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $rolls = [];
        foreach (GreigeRoll::all() as $roll) {
            $rolls[] = self::fromGreigeRoll($roll);
        }
        foreach (ProductRoll::all() as $roll) {
            $rolls[] = self::fromProductRoll($roll);
        }

        usort($rolls, fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);

        return $rolls;
    }

    /** @return Collection<int, object> */
    public static function forPo(int $poId): Collection
    {
        return GreigeRoll::forPo($poId)
            ->map(fn ($roll) => (object) self::fromGreigeRoll((array) $roll))
            ->concat(
                ProductRoll::forPo($poId)->map(fn ($roll) => (object) self::fromProductRoll((array) $roll))
            )
            ->sortBy('id')
            ->values();
    }

    /** @return Collection<int, object> */
    public static function forGreigeSku(string $greigeSku, ?string $stage = self::STAGE_GREIGE_WIP): Collection
    {
        return collect(GreigeRoll::all())
            ->filter(function ($roll) use ($greigeSku, $stage) {
                if ((string) ($roll['greige_sku'] ?? '') !== $greigeSku) {
                    return false;
                }
                if ($stage === self::STAGE_CONSUMED) {
                    return (string) ($roll['status'] ?? '') === GreigeRoll::STATUS_CONSUMED;
                }
                if ($stage === self::STAGE_GREIGE_WIP) {
                    return in_array((string) ($roll['status'] ?? ''), [
                        GreigeRoll::STATUS_IN_STOCK,
                        GreigeRoll::STATUS_PARTIALLY_CONSUMED,
                    ], true);
                }

                return true;
            })
            ->sortBy('id')
            ->map(fn ($roll) => (object) self::fromGreigeRoll($roll))
            ->values();
    }

    /** @return Collection<int, object> */
    public static function forProduct(int $productId, ?string $stage = self::STAGE_PRODUCT): Collection
    {
        if ($stage === self::STAGE_PRODUCT) {
            return ProductRoll::inStockForProduct($productId)
                ->map(fn ($roll) => (object) self::fromProductRoll((array) $roll));
        }

        return collect(ProductRoll::all())
            ->filter(fn ($roll) => (int) ($roll['product_id'] ?? 0) === $productId)
            ->sortBy('id')
            ->map(fn ($roll) => (object) self::fromProductRoll($roll))
            ->values();
    }

    public static function actualMeters(object|array $roll): float
    {
        $row = (object) $roll;
        if (isset($row->actual_qty_m)) {
            return round((float) $row->actual_qty_m, 2);
        }
        if (isset($row->dyeing_meters) && $row->dyeing_meters !== null) {
            return round((float) $row->dyeing_meters, 2);
        }

        return round((float) ($row->weaving_meters ?? 0), 2);
    }

    public static function varianceMeters(object|array $roll): float
    {
        $row = (object) $roll;
        $nominal = (int) ($row->nominal_meters ?? 0);
        $actual = self::actualMeters($row);

        return round($actual - $nominal, 2);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): object
    {
        $stage = (string) ($attributes['stage'] ?? self::STAGE_GREIGE_WIP);

        if ($stage === self::STAGE_PRODUCT) {
            $roll = ProductRoll::create([
                'code' => $attributes['code'] ?? null,
                'product_id' => (int) ($attributes['product_id'] ?? 0),
                'parent_greige_roll_id' => $attributes['parent_roll_id'] ?? $attributes['parent_greige_roll_id'] ?? null,
                'purchase_order_id' => $attributes['po_id'] ?? $attributes['purchase_order_id'] ?? null,
                'receiving_id' => $attributes['receiving_id'] ?? null,
                'tan_qty' => (float) ($attributes['tan_qty'] ?? 1.0),
                'actual_qty_m' => (float) ($attributes['dyeing_meters'] ?? $attributes['actual_qty_m'] ?? 0),
                'nominal_meters' => (int) ($attributes['nominal_meters'] ?? DemoData::METERS_PER_TAN_PRODUCT),
                'received_date' => (string) ($attributes['measured_at'] ?? $attributes['received_date'] ?? date('Y-m-d')),
            ]);

            return (object) self::fromProductRoll((array) $roll);
        }

        $roll = GreigeRoll::create([
            'code' => $attributes['code'] ?? null,
            'greige_sku' => (string) ($attributes['greige_sku'] ?? ''),
            'purchase_order_id' => $attributes['po_id'] ?? $attributes['purchase_order_id'] ?? null,
            'receiving_id' => $attributes['receiving_id'] ?? null,
            'tan_qty' => (float) ($attributes['tan_qty'] ?? 1.0),
            'actual_qty_m' => (float) ($attributes['weaving_meters'] ?? $attributes['actual_qty_m'] ?? 0),
            'nominal_meters' => (int) ($attributes['nominal_meters'] ?? DemoData::METERS_PER_TAN_GREIGE),
            'status' => ($attributes['stage'] ?? '') === self::STAGE_CONSUMED
                ? GreigeRoll::STATUS_CONSUMED
                : GreigeRoll::STATUS_IN_STOCK,
            'received_date' => (string) ($attributes['measured_at'] ?? $attributes['received_date'] ?? date('Y-m-d')),
        ]);

        return (object) self::fromGreigeRoll((array) $roll);
    }

    /**
     * @param  list<array<string, mixed>>  $rolls
     */
    public static function replaceAll(array $rolls): void
    {
        $greigeRolls = [];
        $productRolls = [];

        foreach ($rolls as $roll) {
            $stage = (string) ($roll['stage'] ?? self::STAGE_GREIGE_WIP);
            if ($stage === self::STAGE_PRODUCT) {
                $productRolls[] = self::toProductRoll($roll);
            } else {
                $greigeRolls[] = self::toGreigeRoll($roll);
            }
        }

        GreigeRoll::replaceAll($greigeRolls);
        ProductRoll::replaceAll($productRolls);
    }

    /**
     * @param  array<string, mixed>  $roll
     * @return array<string, mixed>
     */
    private static function fromGreigeRoll(array $roll): array
    {
        $status = (string) ($roll['status'] ?? GreigeRoll::STATUS_IN_STOCK);
        $stage = match ($status) {
            GreigeRoll::STATUS_CONSUMED => self::STAGE_CONSUMED,
            default => self::STAGE_GREIGE_WIP,
        };

        return [
            'id' => (int) $roll['id'],
            'code' => (string) $roll['code'],
            'po_id' => (int) ($roll['purchase_order_id'] ?? 0),
            'greige_sku' => (string) $roll['greige_sku'],
            'product_id' => null,
            'parent_roll_id' => null,
            'stage' => $stage,
            'nominal_meters' => (int) $roll['nominal_meters'],
            'weaving_meters' => (float) $roll['actual_qty_m'],
            'dyeing_meters' => null,
            'tan_qty' => (float) $roll['tan_qty'],
            'actual_qty_m' => (float) $roll['actual_qty_m'],
            'measured_at' => (string) $roll['received_date'],
            'weaving_measured_at' => (string) $roll['received_date'],
            'dyeing_measured_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $roll
     * @return array<string, mixed>
     */
    private static function fromProductRoll(array $roll): array
    {
        $stage = (string) ($roll['status'] ?? ProductRoll::STATUS_IN_STOCK) === ProductRoll::STATUS_SHIPPED
            ? self::STAGE_CONSUMED
            : self::STAGE_PRODUCT;

        return [
            'id' => (int) $roll['id'],
            'code' => (string) $roll['code'],
            'po_id' => (int) ($roll['purchase_order_id'] ?? 0),
            'greige_sku' => '',
            'product_id' => (int) $roll['product_id'],
            'parent_roll_id' => $roll['parent_greige_roll_id'] ?? null,
            'stage' => $stage,
            'nominal_meters' => (int) $roll['nominal_meters'],
            'weaving_meters' => (float) $roll['actual_qty_m'],
            'dyeing_meters' => (float) $roll['actual_qty_m'],
            'tan_qty' => (float) $roll['tan_qty'],
            'actual_qty_m' => (float) $roll['actual_qty_m'],
            'measured_at' => (string) $roll['received_date'],
            'weaving_measured_at' => (string) $roll['received_date'],
            'dyeing_measured_at' => (string) $roll['received_date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $roll
     * @return array<string, mixed>
     */
    private static function toGreigeRoll(array $roll): array
    {
        return [
            'id' => (int) ($roll['id'] ?? 0),
            'code' => (string) ($roll['code'] ?? ''),
            'greige_sku' => (string) ($roll['greige_sku'] ?? ''),
            'purchase_order_id' => (int) ($roll['po_id'] ?? $roll['purchase_order_id'] ?? 0) ?: null,
            'tan_qty' => (float) ($roll['tan_qty'] ?? 1.0),
            'actual_qty_m' => (float) ($roll['weaving_meters'] ?? $roll['actual_qty_m'] ?? 0),
            'nominal_meters' => (int) ($roll['nominal_meters'] ?? DemoData::METERS_PER_TAN_GREIGE),
            'status' => ($roll['stage'] ?? '') === self::STAGE_CONSUMED
                ? GreigeRoll::STATUS_CONSUMED
                : GreigeRoll::STATUS_IN_STOCK,
            'received_date' => (string) ($roll['measured_at'] ?? $roll['received_date'] ?? date('Y-m-d')),
        ];
    }

    /**
     * @param  array<string, mixed>  $roll
     * @return array<string, mixed>
     */
    private static function toProductRoll(array $roll): array
    {
        return [
            'id' => (int) ($roll['id'] ?? 0),
            'code' => (string) ($roll['code'] ?? ''),
            'product_id' => (int) ($roll['product_id'] ?? 0),
            'parent_greige_roll_id' => $roll['parent_roll_id'] ?? $roll['parent_greige_roll_id'] ?? null,
            'purchase_order_id' => (int) ($roll['po_id'] ?? $roll['purchase_order_id'] ?? 0) ?: null,
            'tan_qty' => (float) ($roll['tan_qty'] ?? 1.0),
            'actual_qty_m' => (float) ($roll['dyeing_meters'] ?? $roll['actual_qty_m'] ?? 0),
            'nominal_meters' => (int) ($roll['nominal_meters'] ?? DemoData::METERS_PER_TAN_PRODUCT),
            'status' => ($roll['stage'] ?? '') === self::STAGE_CONSUMED
                ? ProductRoll::STATUS_SHIPPED
                : ProductRoll::STATUS_IN_STOCK,
            'received_date' => (string) ($roll['measured_at'] ?? $roll['received_date'] ?? date('Y-m-d')),
        ];
    }
}
