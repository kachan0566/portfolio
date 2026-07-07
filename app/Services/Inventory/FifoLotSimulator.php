<?php

namespace App\Services\Inventory;

use App\Support\ProductRoll;
use Illuminate\Support\Collection;

/**
 * 月末予想時点の製品反を FIFO でシミュレーションする。
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
        $lots = ProductRoll::inStockForProduct($productId)
            ->map(fn ($roll) => [
                'id' => (int) $roll->id,
                'product_id' => (int) $roll->product_id,
                'receiving_code' => null,
                'received_date' => (string) $roll->received_date,
                'received_qty_m' => (float) $roll->actual_qty_m,
                'remaining_qty_m' => (float) $roll->actual_qty_m,
                'tan_qty' => (float) $roll->tan_qty,
                'purchase_order_id' => $roll->purchase_order_id,
                'source_type' => 'product_roll',
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
                'tan_qty' => 1.0,
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
            ->filter(fn ($lot) => self::lotFloat($lot, 'remaining_qty_m') > 0)
            ->filter(fn ($lot) => self::lotString($lot, 'received_date') <= $threshold)
            ->sum(fn ($lot) => self::lotFloat($lot, 'remaining_qty_m'));
    }

    public static function oldestDate(Collection $lots): ?string
    {
        $dates = $lots
            ->filter(fn ($lot) => self::lotFloat($lot, 'remaining_qty_m') > 0)
            ->map(fn ($lot) => self::lotString($lot, 'received_date'))
            ->filter()
            ->sort()
            ->values();

        return $dates->isNotEmpty() ? (string) $dates->first() : null;
    }

    /**
     * @param  array<string, mixed>|object  $lot
     */
    private static function lotFloat(array|object $lot, string $key): float
    {
        if (is_array($lot)) {
            return (float) ($lot[$key] ?? 0);
        }

        return (float) ($lot->{$key} ?? 0);
    }

    /**
     * @param  array<string, mixed>|object  $lot
     */
    private static function lotString(array|object $lot, string $key): string
    {
        if (is_array($lot)) {
            return (string) ($lot[$key] ?? '');
        }

        return (string) ($lot->{$key} ?? '');
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
     * 丸反単位で FIFO 消費をシミュレートする。
     *
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
            $lot['remaining_qty_m'] = 0.0;
            $remaining -= $lotRemaining;
        }
        unset($lot);
    }
}
