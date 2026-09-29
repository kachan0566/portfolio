<?php

namespace App\Services\Inventory;

use App\Models\ProductRoll as ProductRollModel;
use App\Models\ShipmentRollAllocation;
use App\Support\ProductRoll;
use App\Support\QtyHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 出荷時の FIFO 反割当。丸ごと消費と、最後の反だけの分割出荷に対応する。
 */
class ShipmentRollAllocator
{
    /**
     * 指定反数を FIFO で割り当てる。最後の反が分割になる場合は実測mが必須。
     *
     * @return array{allocated_tan: float, allocated_m: float, roll_ids: list<int>}
     */
    public static function allocate(
        int $productId,
        float $qtyTan,
        int $shipmentId,
        ?string $note = null,
        ?float $partialActualMeters = null,
    ): array {
        if ($qtyTan <= 0) {
            return ['allocated_tan' => 0.0, 'allocated_m' => 0.0, 'roll_ids' => []];
        }

        $targetTan = QtyHelper::roundTan($qtyTan);
        $remainingTan = $targetTan;
        $allocatedM = 0.0;
        $rollIds = [];

        foreach (self::fifoInStock($productId) as $roll) {
            if ($remainingTan <= 0.0001) {
                break;
            }

            $rollId = (int) $roll->id;
            $rollTan = (float) $roll->tan_qty;
            $rollM = (float) $roll->actual_qty_m;

            if ($remainingTan + 0.0001 >= $rollTan) {
                self::recordAllocation($shipmentId, $rollId, $rollTan, $rollM, $note);
                ProductRoll::markShipped($rollId);

                $rollIds[] = $rollId;
                $remainingTan = round($remainingTan - $rollTan, 2);
                $allocatedM += $rollM;

                continue;
            }

            $useTan = QtyHelper::roundTan($remainingTan);
            if ($partialActualMeters === null || $partialActualMeters <= 0) {
                throw new \RuntimeException('分割出荷には、分割する反の出荷実測m（1m単位）を入力してください。');
            }

            $consumeM = round($partialActualMeters, 2);
            if ($consumeM > $rollM + 0.0001) {
                throw new \RuntimeException('出荷実測mが在庫反の実測mを超えています。');
            }

            self::recordAllocation($shipmentId, $rollId, $useTan, $consumeM, $note);
            ProductRoll::update($rollId, [
                'tan_qty' => round($rollTan - $useTan, 2),
                'actual_qty_m' => round($rollM - $consumeM, 2),
                'status' => ProductRoll::STATUS_IN_STOCK,
            ]);

            $rollIds[] = $rollId;
            $allocatedM += $consumeM;
            $remainingTan = 0.0;
        }

        if ($remainingTan > 0.0001) {
            throw new \RuntimeException('出荷できる在庫反がありません。');
        }

        return [
            'allocated_tan' => $targetTan,
            'allocated_m' => round($allocatedM, 2),
            'roll_ids' => $rollIds,
        ];
    }

    /**
     * 最後の反を分割して出荷する場合に、実測m入力が必要かどうか。
     */
    public static function requiresPartialActualMeters(int $productId, float $qtyTan): bool
    {
        $remainingTan = QtyHelper::roundTan($qtyTan);
        if ($remainingTan <= 0) {
            return false;
        }

        foreach (ProductRoll::fifoInStock($productId) as $roll) {
            if ($remainingTan <= 0.0001) {
                break;
            }

            $rollTan = (float) $roll->tan_qty;
            if ($remainingTan + 0.0001 < $rollTan) {
                return true;
            }

            $remainingTan = round($remainingTan - $rollTan, 2);
        }

        return false;
    }

    /**
     * m受注向け：必要m以上になるまで反を FIFO で足す。
     *
     * @return array{allocated_tan: float, allocated_m: float, roll_ids: list<int>}
     */
    public static function allocateForMeters(int $productId, float $requiredMeters, int $shipmentId, ?string $note = null): array
    {
        if ($requiredMeters <= 0) {
            return ['allocated_tan' => 0.0, 'allocated_m' => 0.0, 'roll_ids' => []];
        }

        $allocatedM = 0.0;
        $allocatedTan = 0.0;
        $rollIds = [];

        foreach (self::fifoInStock($productId) as $roll) {
            if ($allocatedM >= $requiredMeters - 0.001) {
                break;
            }

            $rollTan = (float) $roll->tan_qty;
            $rollM = (float) $roll->actual_qty_m;

            self::recordAllocation($shipmentId, (int) $roll->id, $rollTan, $rollM, $note);
            ProductRoll::markShipped((int) $roll->id);

            $rollIds[] = (int) $roll->id;
            $allocatedTan += $rollTan;
            $allocatedM += $rollM;
        }

        return [
            'allocated_tan' => round($allocatedTan, 2),
            'allocated_m' => round($allocatedM, 2),
            'roll_ids' => $rollIds,
        ];
    }

    /**
     * @return list<object>
     */
    public static function previewFifo(int $productId, float $qtyTan): array
    {
        $remainingTan = QtyHelper::roundTan($qtyTan);
        $preview = [];

        foreach (ProductRoll::fifoInStock($productId) as $roll) {
            if ($remainingTan <= 0.0001) {
                break;
            }
            $preview[] = $roll;
            $remainingTan = round($remainingTan - (float) $roll->tan_qty, 2);
        }

        return $preview;
    }

    /** @return Collection<int, object> */
    private static function fifoInStock(int $productId): Collection
    {
        if (DB::transactionLevel() > 0) {
            return ProductRollModel::query()
                ->where('product_id', $productId)
                ->where('status', ProductRollModel::STATUS_IN_STOCK)
                ->orderBy('received_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->map(fn (ProductRollModel $roll) => (object) $roll->toSupportArray())
                ->values();
        }

        return ProductRoll::fifoInStock($productId);
    }

    private static function recordAllocation(
        int $shipmentId,
        int $productRollId,
        float $consumedTanQty,
        float $consumedQtyM,
        ?string $note,
    ): void {
        ShipmentRollAllocation::query()->create([
            'shipment_id' => $shipmentId,
            'product_roll_id' => $productRollId,
            'consumed_tan_qty' => QtyHelper::roundTan($consumedTanQty),
            'consumed_qty_m' => round($consumedQtyM, 2),
            'note' => $note,
        ]);
    }
}
