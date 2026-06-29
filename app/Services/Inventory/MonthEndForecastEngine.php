<?php

namespace App\Services\Inventory;

use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\ForecastManualAdjustment;
use App\Support\ForecastSnapshot;
use App\Support\InboundLot;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\ShipmentPlan;
use Illuminate\Support\Collection;

class MonthEndForecastEngine
{
    public static function build(string $targetYm): object
    {
        InboundLot::ensureBootstrapped();

        $monthEnd = self::monthEndDate($targetYm);
        $lines = DemoData::products()->map(function ($product) use ($targetYm, $monthEnd) {
            return self::buildLine((int) $product->id, $product, $targetYm, $monthEnd);
        })->values();

        $calculable = $lines->where('cost_calculable', true);

        return (object) [
            'target_ym' => $targetYm,
            'month_end_date' => $monthEnd,
            'current_stock_value' => (int) $calculable->sum('current_stock_value'),
            'inbound_scheduled_qty' => (float) $lines->sum('inbound_scheduled_qty'),
            'inbound_scheduled_value' => (int) $calculable->sum('inbound_scheduled_value'),
            'outbound_confirmed_qty' => (float) $lines->sum('outbound_confirmed_qty'),
            'outbound_confirmed_value' => (int) $calculable->sum('outbound_confirmed_value'),
            'forecast_qty' => (float) $lines->sum('forecast_qty'),
            'forecast_value' => (int) $calculable->sum('forecast_value'),
            'long_term_qty' => (float) $lines->sum('long_term_qty'),
            'long_term_value' => (int) $calculable->sum('long_term_value'),
            'prev_month_diff' => self::prevMonthDiff($targetYm, (int) $calculable->sum('forecast_value')),
            'uncosted_count' => $lines->where('cost_calculable', false)->count(),
            'shortage_count' => $lines->filter(fn ($l) => $l->is_shortage || $l->is_negative)->count(),
            'lines' => $lines,
            'latest_snapshot' => ForecastSnapshot::latestForMonth($targetYm),
        ];
    }

    public static function buildLine(int $productId, object $product, string $targetYm, ?string $monthEnd = null): object
    {
        $monthEnd ??= self::monthEndDate($targetYm);
        $currentStock = (float) DemoState::effectiveStock($productId);
        $inbound = self::inboundScheduled($productId, $monthEnd);
        $outbound = self::outboundConfirmed($productId, $monthEnd);
        $manual = ForecastManualAdjustment::totalFor($productId, $targetYm);
        $autoForecast = $currentStock + $inbound['qty'] - $outbound['qty'];
        $forecastQty = round($autoForecast + $manual, 2);

        $unitCost = DemoData::unitCost($productId, $targetYm);
        $costCalculable = $unitCost !== null;
        $unitCostInt = $costCalculable ? (int) round($unitCost) : null;

        $forecastValue = ($costCalculable && $forecastQty > 0)
            ? (int) round($forecastQty * $unitCostInt)
            : 0;

        $simulatedLots = FifoLotSimulator::simulate(
            $productId,
            $monthEnd,
            $inbound['qty'],
            $monthEnd,
            $outbound['qty']
        );
        $longTermQty = FifoLotSimulator::longTermQty($simulatedLots, $monthEnd);
        $longTermValue = $costCalculable ? (int) round($longTermQty * $unitCostInt) : 0;

        $oldestDate = FifoLotSimulator::oldestDate(collect($simulatedLots)->map(fn ($l) => (object) $l));
        $oldestAge = FifoLotSimulator::ageInMonths($oldestDate, $monthEnd);

        $warnings = [];
        if (! $costCalculable) {
            $warnings[] = '原価未登録';
        }
        if ($forecastQty < 0) {
            $warnings[] = '在庫不足予想';
        } elseif ($forecastQty < (float) $product->stock_min) {
            $warnings[] = '安全在庫割れ';
        }

        return (object) [
            'product_id' => $productId,
            'sku' => $product->sku,
            'stock_min' => (float) $product->stock_min,
            'current_stock_qty' => $currentStock,
            'current_stock_value' => $costCalculable ? (int) round($currentStock * $unitCostInt) : 0,
            'inbound_scheduled_qty' => $inbound['qty'],
            'inbound_scheduled_value' => $costCalculable ? (int) round($inbound['qty'] * $unitCostInt) : 0,
            'outbound_confirmed_qty' => $outbound['qty'],
            'outbound_confirmed_value' => $costCalculable ? (int) round($outbound['qty'] * $unitCostInt) : 0,
            'manual_adjustment_qty' => $manual,
            'auto_forecast_qty' => round($autoForecast, 2),
            'forecast_qty' => $forecastQty,
            'unit_cost' => $unitCostInt,
            'forecast_value' => $forecastValue,
            'long_term_qty' => $longTermQty,
            'long_term_value' => $longTermValue,
            'oldest_received_date' => $oldestDate,
            'oldest_age_months' => $oldestAge,
            'inbound_details' => $inbound['details'],
            'outbound_details' => $outbound['details'],
            'lot_details' => collect($simulatedLots)->map(fn ($l) => (object) $l)->values(),
            'manual_adjustments' => ForecastManualAdjustment::historyFor($productId, $targetYm),
            'warnings' => $warnings,
            'warning_text' => implode(' / ', $warnings),
            'is_shortage' => $forecastQty < (float) $product->stock_min,
            'is_negative' => $forecastQty < 0,
            'cost_calculable' => $costCalculable,
            'note' => '',
        ];
    }

    /**
     * @return array{qty: float, details: Collection<int, object>}
     */
    public static function inboundScheduled(int $productId, string $monthEnd): array
    {
        $details = DemoData::purchaseOrders()
            ->filter(fn ($po) => ($po->type ?? '') === PurchaseOrderType::PRODUCT)
            ->filter(fn ($po) => (int) $po->product_id === $productId)
            ->filter(fn ($po) => ($po->status ?? '') !== PurchaseOrderStatus::CANCELLED)
            ->filter(function ($po) use ($monthEnd) {
                $finish = (string) ($po->finish_date ?? '');
                return $finish !== '' && $finish <= $monthEnd;
            })
            ->map(function ($po) {
                $remaining = (float) DemoState::poRemaining((int) $po->id);

                return (object) [
                    'po_id' => (int) $po->id,
                    'po_code' => $po->code,
                    'finish_date' => $po->finish_date,
                    'qty_meters' => (float) $po->qty_meters,
                    'received_qty' => (float) DemoState::effectiveReceivedQty((int) $po->id),
                    'remaining_qty' => $remaining,
                ];
            })
            ->filter(fn ($row) => $row->remaining_qty > 0)
            ->values();

        return [
            'qty' => (float) $details->sum('remaining_qty'),
            'details' => $details,
        ];
    }

    /**
     * @return array{qty: float, details: Collection<int, object>}
     */
    public static function outboundConfirmed(int $productId, string $monthEnd): array
    {
        $details = collect(ShipmentPlan::all())
            ->map(fn ($p) => ShipmentPlan::enrich((object) $p))
            ->filter(fn ($p) => (int) $p->product_id === $productId)
            ->filter(fn ($p) => ShipmentPlan::isActiveForForecast($p))
            ->filter(fn ($p) => (string) $p->planned_ship_date <= $monthEnd)
            ->values();

        return [
            'qty' => (float) $details->sum(fn ($p) => ShipmentPlan::unshippedQty($p)),
            'details' => $details,
        ];
    }

    public static function monthEndDate(string $targetYm): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m', $targetYm);

        return $dt ? $dt->modify('last day of this month')->format('Y-m-d') : $targetYm.'-30';
    }

    private static function prevMonthDiff(string $targetYm, int $currentForecastValue): ?int
    {
        $prev = ForecastSnapshot::previousMonthSubmittedTotal($targetYm);
        if ($prev !== null) {
            return $currentForecastValue - $prev;
        }

        $prevYm = \DateTimeImmutable::createFromFormat('Y-m', $targetYm)?->modify('-1 month')->format('Y-m');
        if (! $prevYm) {
            return null;
        }

        $monthEnd = self::monthEndDate($prevYm);
        $prevTotal = (int) DemoData::products()->sum(function ($product) use ($prevYm, $monthEnd) {
            $line = self::buildLine((int) $product->id, $product, $prevYm, $monthEnd);

            return $line->cost_calculable ? $line->forecast_value : 0;
        });

        return $currentForecastValue - $prevTotal;
    }
}
