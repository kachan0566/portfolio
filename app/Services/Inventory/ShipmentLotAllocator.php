<?php

namespace App\Services\Inventory;

use App\Support\InboundLot;
use App\Support\ShipmentLotConsumption;

/**
 * 出荷時の FIFO ロット消化。
 */
class ShipmentLotAllocator
{
    public static function consume(int $productId, float $qtyM, int $shipmentRef, ?string $note = null): float
    {
        if ($qtyM <= 0) {
            return 0.0;
        }

        $remaining = $qtyM;
        $lots = InboundLot::withRemaining($productId)->sortBy([
            ['received_date', 'asc'],
            ['id', 'asc'],
        ]);

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = InboundLot::consume((int) $lot->id, $remaining);
            if ($take > 0) {
                ShipmentLotConsumption::record($shipmentRef, (int) $lot->id, $take, $note);
                $remaining -= $take;
            }
        }

        return $qtyM - $remaining;
    }
}
