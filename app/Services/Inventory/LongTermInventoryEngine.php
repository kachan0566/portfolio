<?php

namespace App\Services\Inventory;

use App\Support\MasterCatalog;

use App\Models\Product;
use App\Support\DemoData;
use App\Support\ProductStock;
use App\Support\FabricTanRoll;
use App\Support\ProductRoll;
use Illuminate\Support\Collection;

class LongTermInventoryEngine
{
    public const BUCKET_12_18 = '12-18';

    public const BUCKET_18_24 = '18-24';

    public const BUCKET_24_36 = '24-36';

    public const BUCKET_36_PLUS = '36+';

    public static function build(?string $asOfDate = null): object
    {
        FabricTanRoll::ensureBootstrapped();
        $asOfDate ??= DemoData::today();
        $ym = DemoData::CURRENT_YM;

        $lines = MasterCatalog::products()->map(function ($product) use ($asOfDate, $ym) {
            return self::buildLine((int) $product->id, $product, $asOfDate, $ym);
        })->filter(fn ($line) => $line->long_term_qty > 0 || $line->current_stock_qty > 0)
            ->values();

        $longTermLines = $lines->where('long_term_qty', '>', 0);

        return (object) [
            'as_of_date' => $asOfDate,
            'product_count' => $longTermLines->count(),
            'total_qty' => (float) $longTermLines->sum('long_term_qty'),
            'total_value' => (int) $longTermLines->sum('long_term_value'),
            'bucket_12_18_qty' => (float) $longTermLines->sum('bucket_12_18_qty'),
            'bucket_18_24_qty' => (float) $longTermLines->sum('bucket_18_24_qty'),
            'bucket_24_36_qty' => (float) $longTermLines->sum('bucket_24_36_qty'),
            'bucket_36_plus_qty' => (float) $longTermLines->sum('bucket_36_plus_qty'),
            'bucket_12_18_value' => (int) $longTermLines->sum('bucket_12_18_value'),
            'bucket_18_24_value' => (int) $longTermLines->sum('bucket_18_24_value'),
            'bucket_24_36_value' => (int) $longTermLines->sum('bucket_24_36_value'),
            'bucket_36_plus_value' => (int) $longTermLines->sum('bucket_36_plus_value'),
            'lines' => $lines,
        ];
    }

    public static function buildLine(int $productId, object $product, string $asOfDate, string $ym): object
    {
        $currentStock = (float) ProductStock::effectiveStock($productId);
        $lots = ProductRoll::inStockForProduct($productId)
            ->map(fn ($roll) => (object) [
                'id' => (int) $roll->id,
                'receiving_code' => $roll->code ?? null,
                'received_date' => (string) $roll->received_date,
                'received_qty_m' => (float) $roll->actual_qty_m,
                'remaining_qty_m' => (float) $roll->actual_qty_m,
            ]);
        $unitCost = DemoData::unitCost($productId, $ym);
        $unitCostInt = $unitCost !== null ? (int) round($unitCost) : null;

        $longTermQty = 0.0;
        $bucketQty = [
            self::BUCKET_12_18 => 0.0,
            self::BUCKET_18_24 => 0.0,
            self::BUCKET_24_36 => 0.0,
            self::BUCKET_36_PLUS => 0.0,
        ];

        $lotDetails = $lots->map(function ($lot) use ($asOfDate, &$longTermQty, &$bucketQty) {
            $age = FifoLotSimulator::ageInMonths((string) $lot->received_date, $asOfDate) ?? 0;
            $remaining = (float) $lot->remaining_qty_m;
            $isLongTerm = $age >= 12;

            if ($isLongTerm) {
                $longTermQty += $remaining;
                $bucket = self::bucketForAge($age);
                $bucketQty[$bucket] += $remaining;
            }

            return (object) [
                'id' => (int) $lot->id,
                'receiving_code' => $lot->receiving_code,
                'received_date' => $lot->received_date,
                'received_qty_m' => (float) $lot->received_qty_m,
                'remaining_qty_m' => $remaining,
                'age_months' => $age,
                'is_long_term' => $isLongTerm,
            ];
        });

        $oldestDate = FifoLotSimulator::oldestDate($lots);
        $oldestAge = FifoLotSimulator::ageInMonths($oldestDate, $asOfDate);
        $lastShipmentDate = DemoData::shipments()
            ->where('product_id', $productId)
            ->sortByDesc('date')
            ->first()?->date;

        $longTermValue = $unitCostInt !== null ? (int) round($longTermQty * $unitCostInt) : 0;

        return (object) [
            'product_id' => $productId,
            'sku' => $product->sku,
            'current_stock_qty' => $currentStock,
            'long_term_qty' => $longTermQty,
            'long_term_value' => $longTermValue,
            'oldest_received_date' => $oldestDate,
            'oldest_age_months' => $oldestAge,
            'last_shipment_date' => $lastShipmentDate,
            'age_bucket' => self::primaryBucket($oldestAge),
            'bucket_12_18_qty' => $bucketQty[self::BUCKET_12_18],
            'bucket_18_24_qty' => $bucketQty[self::BUCKET_18_24],
            'bucket_24_36_qty' => $bucketQty[self::BUCKET_24_36],
            'bucket_36_plus_qty' => $bucketQty[self::BUCKET_36_PLUS],
            'bucket_12_18_value' => $unitCostInt !== null ? (int) round($bucketQty[self::BUCKET_12_18] * $unitCostInt) : 0,
            'bucket_18_24_value' => $unitCostInt !== null ? (int) round($bucketQty[self::BUCKET_18_24] * $unitCostInt) : 0,
            'bucket_24_36_value' => $unitCostInt !== null ? (int) round($bucketQty[self::BUCKET_24_36] * $unitCostInt) : 0,
            'bucket_36_plus_value' => $unitCostInt !== null ? (int) round($bucketQty[self::BUCKET_36_PLUS] * $unitCostInt) : 0,
            'lot_details' => $lotDetails,
            'cost_calculable' => $unitCostInt !== null,
        ];
    }

    public static function bucketForAge(int $ageMonths): string
    {
        if ($ageMonths >= 36) {
            return self::BUCKET_36_PLUS;
        }
        if ($ageMonths >= 24) {
            return self::BUCKET_24_36;
        }
        if ($ageMonths >= 18) {
            return self::BUCKET_18_24;
        }

        return self::BUCKET_12_18;
    }

    public static function primaryBucket(?int $oldestAge): string
    {
        if ($oldestAge === null || $oldestAge < 12) {
            return '—';
        }

        return self::bucketForAge($oldestAge).'か月';
    }
}
