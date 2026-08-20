<?php

namespace App\Services\Inventory;

use App\Models\CombinedMonthEndForecast;
use App\Models\GreigeMonthEndForecast;
use App\Models\MonthEndForecast;
use App\Support\MasterCatalog;

/**
 * 製品月末予想と生機月末予想を合算した閲覧用サマリ。
 */
class CombinedMonthEndForecastEngine
{
    public static function build(string $targetYm): object
    {
        $product = MonthEndForecastEngine::build($targetYm);
        $greige = GreigeMonthEndForecastEngine::build($targetYm);

        $productCalculable = $product->lines->where('cost_calculable', true);
        $greigeCalculable = $greige->lines->where('cost_calculable', true);

        $currentStockValue = (int) $productCalculable->sum('current_stock_value')
            + (int) $greigeCalculable->sum('current_stock_value');
        $forecastValue = (int) $productCalculable->sum('forecast_value')
            + (int) $greigeCalculable->sum('forecast_value');
        $prevMonthDiff = self::prevMonthDiff($targetYm, $forecastValue);

        $productSnapshot = MonthEndForecast::latestForMonth($targetYm);
        $greigeSnapshot = GreigeMonthEndForecast::latestForMonth($targetYm);
        $combinedSnapshot = CombinedMonthEndForecast::latestForMonth($targetYm);

        $bothSubmitted = $productSnapshot !== null && $greigeSnapshot !== null;
        $unifiedVersion = $bothSubmitted
            && (int) $productSnapshot->version === (int) $greigeSnapshot->version
            ? (int) $productSnapshot->version
            : null;

        return (object) [
            'target_ym' => $targetYm,
            'month_end_date' => MonthEndForecastEngine::monthEndDate($targetYm),
            'current_stock_value' => $currentStockValue,
            'forecast_value' => $forecastValue,
            'product_forecast_value' => (int) $productCalculable->sum('forecast_value'),
            'greige_forecast_value' => (int) $greigeCalculable->sum('forecast_value'),
            'prev_month_diff' => $prevMonthDiff,
            'both_submitted' => $bothSubmitted,
            'unified_version' => $unifiedVersion,
            'latest_combined_snapshot' => $combinedSnapshot,
            'product' => $product,
            'greige' => $greige,
            'product_summary' => self::sectionSummary($product),
            'greige_summary' => self::sectionSummary($greige, isGreige: true),
        ];
    }

    private static function sectionSummary(object $forecast, bool $isGreige = false): array
    {
        $calculable = $forecast->lines->where('cost_calculable', true);
        $outboundKey = $isGreige ? 'outbound_scheduled_qty' : 'outbound_confirmed_qty';
        $outboundValueKey = $isGreige ? 'outbound_scheduled_value' : 'outbound_confirmed_value';

        return [
            'current_stock_qty' => (float) $forecast->lines->sum('current_stock_qty'),
            'current_stock_value' => (int) $calculable->sum('current_stock_value'),
            'inbound_scheduled_qty' => (float) $forecast->lines->sum('inbound_scheduled_qty'),
            'inbound_scheduled_value' => (int) $calculable->sum('inbound_scheduled_value'),
            'outbound_qty' => (float) $forecast->lines->sum($outboundKey),
            'outbound_value' => (int) $calculable->sum($outboundValueKey),
            'forecast_qty' => (float) $forecast->lines->sum('forecast_qty'),
            'forecast_value' => (int) $calculable->sum('forecast_value'),
            'uncosted_count' => $forecast->lines->where('cost_calculable', false)->count(),
            'shortage_count' => $forecast->shortage_count,
        ];
    }

    private static function prevMonthDiff(string $targetYm, int $currentForecastValue): ?int
    {
        $prevYm = \DateTimeImmutable::createFromFormat('Y-m', $targetYm)?->modify('-1 month')->format('Y-m');
        if (! $prevYm) {
            return null;
        }

        $productMonthEnd = MonthEndForecastEngine::monthEndDate($prevYm);
        $productTotal = (int) MasterCatalog::products()->sum(function ($product) use ($prevYm, $productMonthEnd) {
            $line = MonthEndForecastEngine::buildLine((int) $product->id, $product, $prevYm, $productMonthEnd);

            return $line->cost_calculable ? $line->forecast_value : 0;
        });

        $greigeMonthEnd = GreigeMonthEndForecastEngine::monthEndDate($prevYm);
        $greigeTotal = (int) MasterCatalog::greiges()->sum(function ($greige) use ($prevYm, $greigeMonthEnd) {
            $line = GreigeMonthEndForecastEngine::buildLine((string) $greige->sku, $greige, $prevYm, $greigeMonthEnd);

            return $line->cost_calculable ? $line->forecast_value : 0;
        });

        return $currentForecastValue - ($productTotal + $greigeTotal);
    }
}
