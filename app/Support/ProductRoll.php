<?php

namespace App\Support;

use App\Models\ProductRoll as ProductRollModel;
use App\Models\PurchaseOrderLine;
use App\Models\ReceivingLine;
use Illuminate\Support\Collection;

/**
 * 製品の物理反（1在庫単位 = 1レコード）。出荷は丸ごと。
 */
class ProductRoll
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_SHIPPED = 'shipped';

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return ProductRollModel::query()
            ->with(['receivingLine'])
            ->orderBy('id')
            ->get()
            ->map(fn (ProductRollModel $roll) => self::normalizeRoll($roll->toSupportArray()))
            ->values()
            ->all();
    }

    /** @return Collection<int, object> */
    public static function inStockForProduct(int $productId): Collection
    {
        return collect(self::all())
            ->filter(fn ($roll) => (int) ($roll['product_id'] ?? 0) === $productId
                && (string) ($roll['status'] ?? '') === self::STATUS_IN_STOCK)
            ->sortBy([
                ['received_date', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($roll) => (object) $roll)
            ->values();
    }

    /** @return Collection<int, object> */
    public static function fifoInStock(int $productId): Collection
    {
        return self::inStockForProduct($productId);
    }

    /** @return Collection<int, object> */
    public static function forPo(int $poId, ?string $status = null): Collection
    {
        return collect(self::all())
            ->filter(function ($roll) use ($poId, $status) {
                if ((int) ($roll['purchase_order_id'] ?? 0) !== $poId) {
                    return false;
                }
                if ($status === null) {
                    return true;
                }

                return (string) ($roll['status'] ?? '') === $status;
            })
            ->sortBy('id')
            ->map(fn ($roll) => (object) $roll)
            ->values();
    }

    public static function find(int $id): ?object
    {
        $roll = collect(self::all())->firstWhere('id', $id);

        return $roll !== null ? (object) $roll : null;
    }

    public static function stockTanForProduct(int $productId): float
    {
        return round((float) self::inStockForProduct($productId)->sum('tan_qty'), 2);
    }

    public static function stockMetersForProduct(int $productId): float
    {
        return round((float) self::inStockForProduct($productId)->sum('actual_qty_m'), 2);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): object
    {
        $receivingLineId = (int) ($attributes['receiving_line_id'] ?? 0);
        if ($receivingLineId <= 0) {
            $receivingLineId = self::resolveReceivingLineId(
                (int) ($attributes['receiving_id'] ?? 0),
                (int) ($attributes['purchase_order_id'] ?? 0),
            );
        }

        $roll = ProductRollModel::query()->create([
            'code' => (string) ($attributes['code'] ?? ''),
            'product_id' => (int) ($attributes['product_id'] ?? 0),
            'parent_greige_roll_id' => $attributes['parent_greige_roll_id'] ?? null,
            'purchase_order_id' => $attributes['purchase_order_id'] ?? null,
            'receiving_line_id' => $receivingLineId > 0 ? $receivingLineId : null,
            'tan_qty' => QtyHelper::roundReceivingTan((float) ($attributes['tan_qty'] ?? 1.0)),
            'actual_qty_m' => round((float) ($attributes['actual_qty_m'] ?? 0), 2),
            'nominal_meters' => (int) ($attributes['nominal_meters'] ?? DemoData::METERS_PER_TAN_PRODUCT),
            'status' => (string) ($attributes['status'] ?? self::STATUS_IN_STOCK),
            'received_date' => (string) ($attributes['received_date'] ?? date('Y-m-d')),
        ]);

        $roll->load('receivingLine');

        return (object) self::normalizeRoll($roll->toSupportArray());
    }

    public static function markShipped(int $id): ?object
    {
        return self::update($id, ['status' => self::STATUS_SHIPPED]);
    }

    public static function update(int $id, array $attributes): ?object
    {
        $roll = ProductRollModel::query()->with('receivingLine')->find($id);
        if ($roll === null) {
            return null;
        }

        $payload = [];
        foreach (['code', 'product_id', 'parent_greige_roll_id', 'purchase_order_id', 'tan_qty', 'actual_qty_m', 'nominal_meters', 'status', 'received_date'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $payload[$key] = $attributes[$key];
            }
        }

        if (isset($attributes['receiving_id']) || isset($attributes['purchase_order_id'])) {
            $receivingLineId = self::resolveReceivingLineId(
                (int) ($attributes['receiving_id'] ?? $roll->receivingLine?->receiving_id ?? 0),
                (int) ($attributes['purchase_order_id'] ?? $roll->purchase_order_id ?? 0),
            );
            if ($receivingLineId > 0) {
                $payload['receiving_line_id'] = $receivingLineId;
            }
        }

        $roll->update($payload);
        $roll->load('receivingLine');

        return (object) self::normalizeRoll($roll->fresh('receivingLine')->toSupportArray());
    }

    /**
     * @param  list<array<string, mixed>>  $rolls
     */
    public static function replaceAll(array $rolls): void
    {
        ProductRollModel::query()->delete();
        foreach (self::normalizeRolls($rolls) as $roll) {
            self::create($roll);
        }
    }

    /**
     * @param  list<array<mixed>>  $rolls
     * @return list<array<string, mixed>>
     */
    private static function normalizeRolls(array $rolls): array
    {
        return array_values(array_map(fn ($roll) => self::normalizeRoll((array) $roll), $rolls));
    }

    /**
     * @param  array<string, mixed>  $roll
     * @return array<string, mixed>
     */
    private static function normalizeRoll(array $roll): array
    {
        return [
            'id' => (int) ($roll['id'] ?? 0),
            'code' => (string) ($roll['code'] ?? ''),
            'product_id' => (int) ($roll['product_id'] ?? 0),
            'parent_greige_roll_id' => isset($roll['parent_greige_roll_id']) ? (int) $roll['parent_greige_roll_id'] : null,
            'purchase_order_id' => isset($roll['purchase_order_id']) ? (int) $roll['purchase_order_id'] : null,
            'receiving_id' => isset($roll['receiving_id']) ? (int) $roll['receiving_id'] : null,
            'tan_qty' => QtyHelper::roundReceivingTan((float) ($roll['tan_qty'] ?? 1.0)),
            'actual_qty_m' => round((float) ($roll['actual_qty_m'] ?? 0), 2),
            'nominal_meters' => (int) ($roll['nominal_meters'] ?? DemoData::METERS_PER_TAN_PRODUCT),
            'status' => (string) ($roll['status'] ?? self::STATUS_IN_STOCK),
            'received_date' => (string) ($roll['received_date'] ?? date('Y-m-d')),
        ];
    }

    private static function resolveReceivingLineId(int $receivingId, int $purchaseOrderId): int
    {
        if ($receivingId > 0) {
            $line = ReceivingLine::query()->where('receiving_id', $receivingId)->orderBy('line_no')->first();
            if ($line !== null) {
                return (int) $line->id;
            }
        }

        if ($purchaseOrderId > 0) {
            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $purchaseOrderId)
                ->orderBy('line_no')
                ->first();
            if ($poLine !== null) {
                $line = ReceivingLine::query()
                    ->where('purchase_order_line_id', $poLine->id)
                    ->orderBy('line_no')
                    ->first();
                if ($line !== null) {
                    return (int) $line->id;
                }
            }
        }

        return 0;
    }
}
