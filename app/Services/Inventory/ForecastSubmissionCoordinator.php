<?php

namespace App\Services\Inventory;

use App\Support\CombinedForecastSnapshot;
use App\Support\ForecastSnapshot;
use App\Support\GreigeForecastSnapshot;

/**
 * 製品・生機・合算の提出版を同一バージョンで保存する。
 */
class ForecastSubmissionCoordinator
{
    private const CREATED_BY = '木村 勝也';

    /**
     * @return object{version: int, product: object, greige: object, combined: object}
     */
    public static function saveUnified(string $targetYm): object
    {
        $product = MonthEndForecastEngine::build($targetYm);
        $greige = GreigeMonthEndForecastEngine::build($targetYm);
        $combined = CombinedMonthEndForecastEngine::build($targetYm);

        $version = max(
            ForecastSnapshot::maxVersionForMonth($targetYm),
            GreigeForecastSnapshot::maxVersionForMonth($targetYm),
            CombinedForecastSnapshot::maxVersionForMonth($targetYm),
        ) + 1;

        $productSnapshot = ForecastSnapshot::saveWithVersion([
            'target_ym' => $targetYm,
            'base_date' => date('Y-m-d'),
            'created_by' => self::CREATED_BY,
            'total_forecast_value' => $product->forecast_value,
            'total_long_term_value' => $product->long_term_value,
        ], self::productLinesForSnapshot($product), $version);

        $greigeSnapshot = GreigeForecastSnapshot::saveWithVersion([
            'target_ym' => $targetYm,
            'base_date' => date('Y-m-d'),
            'created_by' => self::CREATED_BY,
            'total_forecast_value' => $greige->forecast_value,
            'total_long_term_value' => $greige->long_term_value,
        ], self::greigeLinesForSnapshot($greige), $version);

        $combinedSnapshot = CombinedForecastSnapshot::saveWithVersion([
            'target_ym' => $targetYm,
            'base_date' => date('Y-m-d'),
            'created_by' => self::CREATED_BY,
            'total_forecast_value' => $combined->forecast_value,
            'total_current_stock_value' => $combined->current_stock_value,
            'product_forecast_value' => $combined->product_forecast_value,
            'greige_forecast_value' => $combined->greige_forecast_value,
            'product_summary' => $combined->product_summary,
            'greige_summary' => $combined->greige_summary,
        ], $version);

        return (object) [
            'version' => $version,
            'product' => $productSnapshot,
            'greige' => $greigeSnapshot,
            'combined' => $combinedSnapshot,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function productLinesForSnapshot(object $product): array
    {
        return $product->lines->map(fn ($line) => [
            'product_id' => $line->product_id,
            'sku' => $line->sku,
            'current_stock_qty' => $line->current_stock_qty,
            'inbound_scheduled_qty' => $line->inbound_scheduled_qty,
            'outbound_confirmed_qty' => $line->outbound_confirmed_qty,
            'manual_adjustment_qty' => $line->manual_adjustment_qty,
            'forecast_qty' => $line->forecast_qty,
            'unit_cost' => $line->unit_cost,
            'forecast_value' => $line->forecast_value,
            'long_term_qty' => $line->long_term_qty,
            'long_term_value' => $line->long_term_value,
            'oldest_received_date' => $line->oldest_received_date,
            'oldest_age_months' => $line->oldest_age_months,
            'note' => $line->note,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function greigeLinesForSnapshot(object $greige): array
    {
        return $greige->lines->map(fn ($line) => [
            'greige_sku' => $line->greige_sku,
            'sku' => $line->sku,
            'current_stock_qty' => $line->current_stock_qty,
            'inbound_scheduled_qty' => $line->inbound_scheduled_qty,
            'outbound_scheduled_qty' => $line->outbound_scheduled_qty,
            'manual_adjustment_qty' => $line->manual_adjustment_qty,
            'forecast_qty' => $line->forecast_qty,
            'unit_cost' => $line->unit_cost,
            'forecast_value' => $line->forecast_value,
            'long_term_qty' => $line->long_term_qty,
            'long_term_value' => $line->long_term_value,
            'oldest_received_date' => $line->oldest_received_date,
            'oldest_age_months' => $line->oldest_age_months,
        ])->all();
    }
}
