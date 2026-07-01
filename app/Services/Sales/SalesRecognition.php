<?php

namespace App\Services\Sales;

use App\Support\DemoData;

/**
 * 売上計上月の判定（Phase 1: 出荷予定日ベース。将来は到着日を追加）。
 */
class SalesRecognition
{
    public static function monthEndDate(string $targetYm): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m', $targetYm);

        return $dt ? $dt->modify('last day of this month')->format('Y-m-d') : $targetYm.'-30';
    }

    public static function countsOrderForSalesMonth(object $order, string $targetYm): bool
    {
        $shipDate = (string) ($order->planned_ship_date ?? '');
        if ($shipDate === '') {
            return false;
        }

        // Phase 2: customer_arrival_date が翌月なら false
        return $shipDate <= self::monthEndDate($targetYm);
    }

    public static function countsPoForInboundMonth(object $po, string $targetYm): bool
    {
        $arrival = DemoData::expectedArrivalDate($po);
        if ($arrival === '') {
            return false;
        }

        return $arrival <= self::monthEndDate($targetYm);
    }
}
