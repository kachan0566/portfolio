<?php

namespace App\Support;

use App\Models\ShipmentPlanRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 出荷予定／出荷確定（DB 正）。
 */
class ShipmentPlan
{
    public const STATUS_CONFIRMED = ShipmentPlanRecord::STATUS_CONFIRMED;

    public const STATUS_PARTIAL = ShipmentPlanRecord::STATUS_PARTIAL;

    public const STATUS_COMPLETED = ShipmentPlanRecord::STATUS_COMPLETED;

    public const STATUS_CANCELLED = ShipmentPlanRecord::STATUS_CANCELLED;

    /**
     * @return list<array<string, mixed>>
     */
    public static function demoRows(): array
    {
        return [
            ['id' => 1, 'code' => 'SP-2606-001', 'order_id' => 2, 'product_id' => 3, 'planned_ship_date' => '2026-06-17', 'confirmed_qty_m' => 120, 'shipped_qty_m' => 0, 'status' => self::STATUS_CONFIRMED, 'note' => '分納2回目'],
            ['id' => 2, 'code' => 'SP-2606-002', 'order_id' => 3, 'product_id' => 5, 'planned_ship_date' => '2026-06-19', 'confirmed_qty_m' => 90, 'shipped_qty_m' => 0, 'status' => self::STATUS_CONFIRMED, 'note' => ''],
            ['id' => 3, 'code' => 'SP-2606-003', 'order_id' => 5, 'product_id' => 4, 'planned_ship_date' => '2026-06-24', 'confirmed_qty_m' => 150, 'shipped_qty_m' => 0, 'status' => self::STATUS_CONFIRMED, 'note' => '入荷待ち'],
            ['id' => 4, 'code' => 'SP-2606-004', 'order_id' => 6, 'product_id' => 1, 'planned_ship_date' => '2026-06-27', 'confirmed_qty_m' => 60, 'shipped_qty_m' => 0, 'status' => self::STATUS_CONFIRMED, 'note' => '残量出荷'],
            ['id' => 5, 'code' => 'SP-2606-005', 'order_id' => 2, 'product_id' => 3, 'planned_ship_date' => '2026-06-14', 'confirmed_qty_m' => 80, 'shipped_qty_m' => 80, 'status' => self::STATUS_COMPLETED, 'note' => '1回目出荷済'],
        ];
    }

    /**
     * @return list<object>
     */
    public static function all(): array
    {
        return self::baseQuery()
            ->orderBy('id')
            ->get()
            ->map(fn (ShipmentPlanRecord $plan) => $plan->toDisplayObject())
            ->all();
    }

    /**
     * @return Collection<int, object>
     */
    public static function forProduct(int $productId): Collection
    {
        return self::baseQuery()
            ->where('product_id', $productId)
            ->orderBy('planned_ship_date')
            ->orderBy('id')
            ->get()
            ->map(fn (ShipmentPlanRecord $plan) => $plan->toDisplayObject())
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public static function forOrder(int $orderId): Collection
    {
        return self::baseQuery()
            ->where('order_id', $orderId)
            ->orderBy('planned_ship_date')
            ->orderBy('id')
            ->get()
            ->map(fn (ShipmentPlanRecord $plan) => $plan->toDisplayObject())
            ->values();
    }

    public static function find(int $id): ?object
    {
        $plan = self::baseQuery()->find($id);

        return $plan?->toDisplayObject();
    }

    public static function unshippedQty(object $plan): float
    {
        return max(0.0, round((float) $plan->confirmed_qty_m - (float) $plan->shipped_qty_m, 2));
    }

    public static function isActiveForForecast(object $plan): bool
    {
        return in_array($plan->status, [self::STATUS_CONFIRMED, self::STATUS_PARTIAL], true)
            && self::unshippedQty($plan) > 0;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): object
    {
        $productId = (int) ($attributes['product_id'] ?? 0);
        $confirmedM = round((float) ($attributes['confirmed_qty_m'] ?? 0), 2);
        $shippedM = round((float) ($attributes['shipped_qty_m'] ?? 0), 2);

        $plan = ShipmentPlanRecord::query()->create([
            'code' => (string) ($attributes['code'] ?? self::nextCode()),
            'order_id' => (int) ($attributes['order_id'] ?? 0),
            'product_id' => $productId,
            'planned_ship_date' => (string) ($attributes['planned_ship_date'] ?? date('Y-m-d')),
            'confirmed_qty_m' => $confirmedM,
            'confirmed_qty_tan' => ShipmentPlanRecord::tanFromMeters($confirmedM, $productId),
            'shipped_qty_m' => min($shippedM, $confirmedM),
            'shipped_qty_tan' => $shippedM > 0
                ? ShipmentPlanRecord::tanFromMeters($shippedM, $productId)
                : 0.0,
            'status' => (string) ($attributes['status'] ?? self::STATUS_CONFIRMED),
            'note' => (string) ($attributes['note'] ?? ''),
            'created_by' => $attributes['created_by'] ?? null,
        ]);

        return $plan->fresh(['order', 'product'])?->toDisplayObject()
            ?? $plan->toDisplayObject();
    }

    public static function recordShipment(int $orderId, float $qtyM, ?float $qtyTan = null): void
    {
        if ($qtyM <= 0) {
            return;
        }

        DB::transaction(function () use ($orderId, $qtyM, $qtyTan) {
            $remaining = $qtyM;
            $tanToApply = ($qtyTan !== null && $qtyTan > 0) ? $qtyTan : null;

            $plans = ShipmentPlanRecord::query()
                ->where('order_id', $orderId)
                ->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_PARTIAL])
                ->orderBy('planned_ship_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($plans as $plan) {
                if ($remaining <= 0) {
                    break;
                }

                $unshipped = max(0.0, (float) $plan->confirmed_qty_m - (float) $plan->shipped_qty_m);
                if ($unshipped <= 0) {
                    continue;
                }

                $take = min($unshipped, $remaining);
                $plan->shipped_qty_m = round((float) $plan->shipped_qty_m + $take, 2);

                if ($tanToApply !== null) {
                    $plan->shipped_qty_tan = round((float) $plan->shipped_qty_tan + $tanToApply, 2);
                    $tanToApply = null;
                }

                $plan->status = $plan->shipped_qty_m >= (float) $plan->confirmed_qty_m
                    ? self::STATUS_COMPLETED
                    : self::STATUS_PARTIAL;
                $plan->save();

                $remaining -= $take;
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    public static function replaceAll(array $plans): void
    {
        DB::transaction(function () use ($plans) {
            ShipmentPlanRecord::query()->delete();

            foreach ($plans as $row) {
                self::create($row);
            }
        });
    }

    public static function clearCache(): void
    {
        // DB 正のためキャッシュなし（テスト互換用に残す）
    }

    public static function enrich(object $plan): object
    {
        if (isset($plan->order_code, $plan->sku, $plan->unshipped_qty_m, $plan->status_label)) {
            return $plan;
        }

        $record = ShipmentPlanRecord::query()
            ->with(['order', 'product'])
            ->find((int) ($plan->id ?? 0));

        return $record?->toDisplayObject() ?? $plan;
    }

    private static function baseQuery()
    {
        return ShipmentPlanRecord::query()->with(['order', 'product']);
    }

    private static function nextCode(): string
    {
        $prefix = 'SP-'.date('ym').'-';
        $seq = ShipmentPlanRecord::query()
            ->where('code', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
