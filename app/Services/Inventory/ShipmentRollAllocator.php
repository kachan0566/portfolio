<?php

namespace App\Services\Inventory;

use App\Support\ProductRoll;
use App\Support\QtyHelper;
use App\Support\ShipmentRollAllocation;

/**
 * 出荷時の FIFO 反丸ごと割当。
 */
class ShipmentRollAllocator
{
    /**
     * @return array{allocated_tan: float, allocated_m: float, roll_ids: list<int>}
     */
    public static function allocate(int $productId, float $qtyTan, int $shipmentRef, ?string $note = null): array
    {
        if ($qtyTan <= 0) {
            return ['allocated_tan' => 0.0, 'allocated_m' => 0.0, 'roll_ids' => []];
        }

        $targetTan = QtyHelper::roundReceivingTan($qtyTan);
        $remainingTan = $targetTan;
        $allocatedM = 0.0;
        $rollIds = [];

        $rolls = ProductRoll::fifoInStock($productId);

        foreach ($rolls as $roll) {
            if ($remainingTan <= 0.0001) {
                break;
            }

            $rollTan = (float) $roll->tan_qty;
            $rollM = (float) $roll->actual_qty_m;

            ProductRoll::markShipped((int) $roll->id);
            ShipmentRollAllocation::record(
                $shipmentRef,
                (int) $roll->id,
                $rollTan,
                $rollM,
                $note,
            );

            $rollIds[] = (int) $roll->id;
            $remainingTan = round($remainingTan - $rollTan, 2);
            $allocatedM += $rollM;
        }

        return [
            'allocated_tan' => round($targetTan - max(0, $remainingTan), 2),
            'allocated_m' => round($allocatedM, 2),
            'roll_ids' => $rollIds,
        ];
    }

    /**
     * m受注向け：必要m以上になるまで反を FIFO で足す。
     *
     * @return array{allocated_tan: float, allocated_m: float, roll_ids: list<int>}
     */
    public static function allocateForMeters(int $productId, float $requiredMeters, int $shipmentRef, ?string $note = null): array
    {
        if ($requiredMeters <= 0) {
            return ['allocated_tan' => 0.0, 'allocated_m' => 0.0, 'roll_ids' => []];
        }

        $allocatedM = 0.0;
        $allocatedTan = 0.0;
        $rollIds = [];

        $rolls = ProductRoll::fifoInStock($productId);

        foreach ($rolls as $roll) {
            if ($allocatedM >= $requiredMeters - 0.001) {
                break;
            }

            $rollTan = (float) $roll->tan_qty;
            $rollM = (float) $roll->actual_qty_m;

            ProductRoll::markShipped((int) $roll->id);
            ShipmentRollAllocation::record(
                $shipmentRef,
                (int) $roll->id,
                $rollTan,
                $rollM,
                $note,
            );

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
        $remainingTan = QtyHelper::roundReceivingTan($qtyTan);
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
}
