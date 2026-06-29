<?php

namespace App\Services\Inventory;

use App\Support\InboundLot;
use Illuminate\Support\Collection;

/**
 * 月末予想時点の入荷ロットを FIFO でシミュレーションする。
 */
class FifoLotSimulator
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function simulate(
        int $productId,
        string $asOfDate,
        float $additionalInboundM,
        string $additionalInboundDate,
        float $additionalOutboundM,
    ): array {
        $lots = InboundLot::withRemaining($productId)
            ->map(fn ($lot) => [
                'id' => (int) $lot->id,
                'product_id' => (int) $lot->product_id,
                'receiving_code' => $lot->receiving_code,
                'received_date' => (string) $lot->received_date,
                'received_qty_m' => (float) $lot->received_qty_m,
                'remaining_qty_m' => (float) $lot->remaining_qty_m,
                'purchase_order_id' => $lot->purchase_order_id,
                'source_type' => (string) $lot->source_type,
            ])
            ->values()
            ->all();

        self::consumeFifo($lots, $additionalOutboundM);

        if ($additionalInboundM > 0) {
            $lots[] = [
                'id' => 900000 + count($lots),
                'product_id' => $productId,
                'receiving_code' => null,
                'received_date' => $additionalInboundDate,
                'received_qty_m' => $additionalInboundM,
                'remaining_qty_m' => $additionalInboundM,
                'purchase_order_id' => null,
                'source_type' => 'forecast_inbound',
            ];
        }

        return $lots;
    }

    /**
     * @param  list<array<string, mixed>>  $lots
     */
    public static function longTermQty(array $lots, string $asOfDate, int $months = 12): float
    {
        $cutoff = \DateTimeImmutable::createFromFormat('Y-m-d', $asOfDate);
        if (! $cutoff) {
            return 0.0;
        }
        $threshold = $cutoff->modify("-{$months} months")->format('Y-m-d');

        return (float) collect($lots)
            ->filter(fn ($lot) => (float) ($lot['remaining_qty_m'] ?? 0) > 0)
            ->filter(fn ($lot) => ($lot['received_date'] ?? '') <= $threshold)
            ->sum('remaining_qty_m');
    }

    public static function oldestDate(Collection $lots): ?string
    {
        $dates = $lots
            ->filter(fn ($lot) => (float) ($lot->remaining_qty_m ?? $lot->remaining_qty_m ?? 0) > 0)
            ->pluck('received_date')
            ->filter()
            ->sort()
            ->values();

        return $dates->isNotEmpty() ? (string) $dates->first() : null;
    }

    public static function ageInMonths(?string $receivedDate, string $asOfDate): ?int
    {
        if ($receivedDate === null || $receivedDate === '') {
            return null;
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', $receivedDate);
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', $asOfDate);
        if (! $from || ! $to) {
            return null;
        }

        $months = ((int) $to->format('Y') - (int) $from->format('Y')) * 12
            + ((int) $to->format('m') - (int) $from->format('m'));

        if ((int) $to->format('d') > (int) $from->format('d')) {
            $months++;
        }

        return max(0, $months);
    }

    /**
     * @param  list<array<string, mixed>>  $lots
     */
    private static function consumeFifo(array &$lots, float $qtyM): void
    {
        if ($qtyM <= 0) {
            return;
        }

        $remaining = $qtyM;
        usort($lots, fn ($a, $b) => [$a['received_date'], $a['id']] <=> [$b['received_date'], $b['id']]);

        foreach ($lots as &$lot) {
            if ($remaining <= 0) {
                break;
            }
            $lotRemaining = (float) $lot['remaining_qty_m'];
            if ($lotRemaining <= 0) {
                continue;
            }
            $take = min($lotRemaining, $remaining);
            $lot['remaining_qty_m'] = round($lotRemaining - $take, 2);
            $remaining -= $take;
        }
        unset($lot);
    }
}
