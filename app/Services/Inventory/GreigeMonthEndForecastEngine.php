<?php

namespace App\Services\Inventory;

use App\Models\GreigeMonthEndForecast;
use App\Models\PurchaseOrder;
use App\Services\Sales\SalesRecognition;
use App\Support\DemoData;
use App\Support\GreigeForecastManualAdjustment;
use App\Support\GreigeRoll;
use App\Support\GreigeSupply;
use App\Support\MasterCatalog;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use Illuminate\Support\Collection;

/**
 * 染工場の生機在庫について、月末時点の数量・金額を予想する。
 *
 * 現在庫（in_stock）＋ 入荷予定（生機発注の finish_date）− 染機投入予定（製品発注の contact_date）
 */
class GreigeMonthEndForecastEngine
{
    public static function build(string $targetYm): object
    {
        $monthEnd = self::monthEndDate($targetYm);
        $lines = MasterCatalog::greiges()->map(function ($greige) use ($targetYm, $monthEnd) {
            return self::buildLine((string) $greige->sku, $greige, $targetYm, $monthEnd);
        })->values();

        $calculable = $lines->where('cost_calculable', true);

        return (object) [
            'target_ym' => $targetYm,
            'month_end_date' => $monthEnd,
            'current_stock_value' => (int) $calculable->sum('current_stock_value'),
            'inbound_scheduled_qty' => (float) $lines->sum('inbound_scheduled_qty'),
            'inbound_scheduled_value' => (int) $calculable->sum('inbound_scheduled_value'),
            'outbound_scheduled_qty' => (float) $lines->sum('outbound_scheduled_qty'),
            'outbound_scheduled_value' => (int) $calculable->sum('outbound_scheduled_value'),
            'forecast_qty' => (float) $lines->sum('forecast_qty'),
            'forecast_value' => (int) $calculable->sum('forecast_value'),
            'long_term_qty' => (float) $lines->sum('long_term_qty'),
            'long_term_value' => (int) $calculable->sum('long_term_value'),
            'prev_month_diff' => self::prevMonthDiff($targetYm, (int) $calculable->sum('forecast_value')),
            'uncosted_count' => $lines->where('cost_calculable', false)->count(),
            'shortage_count' => $lines->filter(fn ($l) => $l->is_negative)->count(),
            'lines' => $lines,
            'latest_snapshot' => GreigeMonthEndForecast::latestForMonth($targetYm),
        ];
    }

    public static function buildLine(string $greigeSku, object $greige, string $targetYm, ?string $monthEnd = null): object
    {
        $monthEnd ??= self::monthEndDate($targetYm);
        $currentStock = (float) GreigeRoll::stockMetersForSku($greigeSku);
        $inbound = self::inboundScheduled($greigeSku, $targetYm);
        $outbound = self::outboundScheduled($greigeSku, $targetYm);
        $manual = GreigeForecastManualAdjustment::totalFor($greigeSku, $targetYm);
        $autoForecast = $currentStock + $inbound['qty'] - $outbound['qty'];
        $forecastQty = round($autoForecast + $manual, 2);

        $unitCost = DemoData::greigeUnitCost($greigeSku, $targetYm);
        $costCalculable = $unitCost !== null;
        $unitCostInt = $costCalculable ? (int) round($unitCost) : null;

        $forecastValue = ($costCalculable && $forecastQty > 0)
            ? (int) round($forecastQty * $unitCostInt)
            : 0;

        $oldestDate = GreigeRoll::inStockForSku($greigeSku)
            ->pluck('received_date')
            ->filter()
            ->sort()
            ->first();
        $oldestAge = $oldestDate !== null
            ? FifoLotSimulator::ageInMonths((string) $oldestDate, $monthEnd)
            : null;
        $longTermQty = $oldestAge !== null && $oldestAge >= 12
            ? (float) GreigeRoll::inStockForSku($greigeSku)
                ->filter(fn ($roll) => FifoLotSimulator::ageInMonths((string) ($roll->received_date ?? ''), $monthEnd) >= 12)
                ->sum(fn ($roll) => (float) $roll->actual_qty_m)
            : 0.0;
        $longTermValue = $costCalculable ? (int) round($longTermQty * $unitCostInt) : 0;

        $warnings = [];
        if (! $costCalculable) {
            $warnings[] = '原価未登録';
        }
        if ($forecastQty < 0) {
            $warnings[] = '在庫不足予想';
        }

        return (object) [
            'greige_sku' => $greigeSku,
            'greige_name' => (string) ($greige->name ?? $greigeSku),
            'sku' => $greigeSku,
            'current_stock_qty' => $currentStock,
            'current_stock_value' => $costCalculable ? (int) round($currentStock * $unitCostInt) : 0,
            'inbound_scheduled_qty' => $inbound['qty'],
            'inbound_scheduled_value' => $costCalculable ? (int) round($inbound['qty'] * $unitCostInt) : 0,
            'outbound_scheduled_qty' => $outbound['qty'],
            'outbound_scheduled_value' => $costCalculable ? (int) round($outbound['qty'] * $unitCostInt) : 0,
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
            'manual_adjustments' => GreigeForecastManualAdjustment::historyFor($greigeSku, $targetYm),
            'warnings' => $warnings,
            'warning_text' => implode(' / ', $warnings),
            'is_negative' => $forecastQty < 0,
            'cost_calculable' => $costCalculable,
        ];
    }

    /**
     * @return array{qty: float, details: Collection<int, object>}
     */
    public static function inboundScheduled(string $greigeSku, string $targetYm): array
    {
        $details = DemoData::purchaseOrders()
            ->filter(fn ($po) => ($po->type ?? '') === PurchaseOrderType::GREIGE)
            ->filter(fn ($po) => self::greigeSkuForPo($po) === $greigeSku)
            ->filter(fn ($po) => PurchaseOrderStatus::isActive($po->status ?? ''))
            ->filter(fn ($po) => SalesRecognition::countsPoForInboundMonth($po, $targetYm))
            ->map(function ($po) {
                $remaining = (float) PurchaseOrder::remainingQtyFor((int) $po->id, $po);

                return (object) [
                    'po_id' => (int) $po->id,
                    'po_code' => $po->code,
                    'finish_date' => DemoData::expectedArrivalDate($po),
                    'forecast_qty' => $remaining,
                ];
            })
            ->filter(fn ($row) => $row->forecast_qty > 0.0001)
            ->values();

        return [
            'qty' => (float) $details->sum('forecast_qty'),
            'details' => $details,
        ];
    }

    /**
     * 染機投入予定（製品発注の contact_date ベース。未投入分のみ）。
     *
     * @return array{qty: float, details: Collection<int, object>}
     */
    public static function outboundScheduled(string $greigeSku, string $targetYm): array
    {
        $monthEnd = self::monthEndDate($targetYm);

        $details = DemoData::purchaseOrderIndexRows()
            ->filter(fn ($row) => ($row->type ?? '') === PurchaseOrderType::PRODUCT)
            ->filter(fn ($row) => PurchaseOrderStatus::isActive($row->status ?? ''))
            ->filter(function ($row) use ($greigeSku) {
                $productId = (int) ($row->product_id ?? 0);

                return GreigeSupply::greigeSkuForProduct($productId) === $greigeSku;
            })
            ->filter(fn ($row) => ($row->manual_stage ?? '') !== PurchaseOrderStages::PRODUCT_DYEING)
            ->map(function ($row) {
                $ordered = (float) ($row->qty_meters ?? $row->qty ?? 0);
                $received = (float) ($row->received ?? 0);

                return (object) [
                    'purchase_order_line_id' => (int) ($row->purchase_order_line_id ?? 0),
                    'po_id' => (int) $row->id,
                    'po_code' => $row->code,
                    'product_sku' => (string) ($row->sku ?? ''),
                    'contact_date' => (string) ($row->contact_date ?? ''),
                    'remaining_qty' => max(0.0, $ordered - $received),
                ];
            })
            ->filter(fn ($row) => $row->remaining_qty > 0.0001)
            ->filter(fn ($row) => $row->contact_date !== '' && $row->contact_date <= $monthEnd)
            ->map(function ($row) {
                $row->forecast_qty = $row->remaining_qty;

                return $row;
            })
            ->values();

        return [
            'qty' => (float) $details->sum('forecast_qty'),
            'details' => $details,
        ];
    }

    /**
     * @param  Collection<int, object>  $lines
     */
    public static function summarizeLines(Collection $lines, string $targetYm): object
    {
        $monthEnd = self::monthEndDate($targetYm);
        $calculable = $lines->where('cost_calculable', true);

        return (object) [
            'target_ym' => $targetYm,
            'month_end_date' => $monthEnd,
            'current_stock_qty' => (float) $lines->sum('current_stock_qty'),
            'current_stock_value' => (int) $calculable->sum('current_stock_value'),
            'inbound_scheduled_qty' => (float) $lines->sum('inbound_scheduled_qty'),
            'inbound_scheduled_value' => (int) $calculable->sum('inbound_scheduled_value'),
            'outbound_scheduled_qty' => (float) $lines->sum('outbound_scheduled_qty'),
            'outbound_scheduled_value' => (int) $calculable->sum('outbound_scheduled_value'),
            'forecast_qty' => (float) $lines->sum('forecast_qty'),
            'forecast_value' => (int) $calculable->sum('forecast_value'),
            'long_term_qty' => (float) $lines->sum('long_term_qty'),
            'long_term_value' => (int) $calculable->sum('long_term_value'),
            'prev_month_diff' => self::prevMonthDiffForLines($lines, $targetYm),
            'uncosted_count' => $lines->where('cost_calculable', false)->count(),
            'shortage_count' => $lines->filter(fn ($l) => $l->is_negative)->count(),
            'latest_snapshot' => GreigeMonthEndForecast::latestForMonth($targetYm),
        ];
    }

    public static function monthEndDate(string $targetYm): string
    {
        return SalesRecognition::monthEndDate($targetYm);
    }

    private static function greigeSkuForPo(object $po): string
    {
        return (string) ($po->greige_sku ?? $po->sku ?? '');
    }

    /**
     * @param  Collection<int, object>  $lines
     */
    private static function prevMonthDiffForLines(Collection $lines, string $targetYm): ?int
    {
        if ($lines->isEmpty()) {
            return null;
        }

        $currentTotal = (int) $lines->where('cost_calculable', true)->sum('forecast_value');
        $prevTotal = (int) $lines->sum(fn ($line) => self::prevForecastValue((string) $line->greige_sku, $targetYm));

        return $currentTotal - $prevTotal;
    }

    private static function prevForecastValue(string $greigeSku, string $targetYm): int
    {
        $prevYm = \DateTimeImmutable::createFromFormat('Y-m', $targetYm)?->modify('-1 month')->format('Y-m');
        if (! $prevYm) {
            return 0;
        }

        $greige = MasterCatalog::findGreige($greigeSku);
        if ($greige === null) {
            return 0;
        }

        $line = self::buildLine($greigeSku, $greige, $prevYm, self::monthEndDate($prevYm));

        return $line->cost_calculable ? (int) $line->forecast_value : 0;
    }

    private static function prevMonthDiff(string $targetYm, int $currentForecastValue): ?int
    {
        $prevYm = \DateTimeImmutable::createFromFormat('Y-m', $targetYm)?->modify('-1 month')->format('Y-m');
        if (! $prevYm) {
            return null;
        }

        $monthEnd = self::monthEndDate($prevYm);
        $prevTotal = (int) MasterCatalog::greiges()->sum(function ($greige) use ($prevYm, $monthEnd) {
            $line = self::buildLine((string) $greige->sku, $greige, $prevYm, $monthEnd);

            return $line->cost_calculable ? $line->forecast_value : 0;
        });

        return $currentForecastValue - $prevTotal;
    }
}
