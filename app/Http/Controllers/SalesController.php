<?php

namespace App\Http\Controllers;

use App\Services\Sales\SalesForecastEngine;
use App\Support\BusinessDate;
use App\Support\DemoData;
use App\Support\ListSearch;
use App\Support\MasterCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $ym = $this->resolveYm($request);
        $tab = $request->query('tab', 'actual') === 'forecast' ? 'forecast' : 'actual';
        $search = ListSearch::params($request);
        $selectedProductId = $request->filled('product_id') ? (int) $request->query('product_id') : null;

        $allByProduct = DemoData::monthlySalesByProduct($ym);

        if ($tab === 'forecast') {
            $forecast = SalesForecastEngine::build($ym);
            $activeForecastLines = $forecast->lines->filter(
                fn ($line) => $line->has_forecast_activity || $line->actual_qty > 0
            );
            $forecastLines = ListSearch::filter($activeForecastLines, $search, [
                'code_fields' => [],
                'sku_fields' => ['sku'],
            ]);
            $forecastTrend = SalesForecastEngine::forecastTrend($ym, $selectedProductId, forecast: $forecast);
            $forecastComparison = SalesForecastEngine::buildComparison($ym, $forecast);
            $costWarnings = DemoData::collectCostWarnings(
                $forecast->lines->where('cost_calculable', false)->pluck('product_id'),
                $ym
            );
        } else {
            $forecast = SalesForecastEngine::buildForActualTab($ym);
            $byProduct = ListSearch::filter($allByProduct, $search, [
                'code_fields' => [],
                'sku_fields' => ['sku', 'product'],
            ]);
            $forecastTrend = collect();
            $forecastComparison = null;
            $costWarnings = DemoData::collectCostWarnings(
                $allByProduct->where('cost_calculable', false)->pluck('product_id'),
                $ym
            );
        }

        $kpiRows = $selectedProductId !== null
            ? $allByProduct->where('product_id', $selectedProductId)
            : $allByProduct;
        $calculableRows = $kpiRows->where('cost_calculable', true);

        $forecastKpiRows = $selectedProductId !== null
            ? $forecast->lines->where('product_id', $selectedProductId)
            : $forecast->lines;
        $forecastCalculable = $forecastKpiRows->where('cost_calculable', true);

        $selectedProduct = $selectedProductId !== null
            ? MasterCatalog::findProduct($selectedProductId)
            : null;

        $forecastLineMap = $forecast->lines->keyBy('product_id');

        return view('sales.index', [
            'tab' => $tab,
            'forecast' => $forecast,
            'forecastLines' => $forecastLines ?? collect(),
            'forecastLineMap' => $forecastLineMap,
            'byProduct' => $byProduct ?? collect(),
            'allByProduct' => $allByProduct,
            'trend' => DemoData::salesTrend($ym, $selectedProductId),
            'totalSales' => $kpiRows->sum('sales'),
            'totalCost' => $calculableRows->sum('cost'),
            'totalProfit' => $calculableRows->sum('profit'),
            'hasUncalculableCost' => $kpiRows->contains(fn ($row) => ! $row->cost_calculable),
            'forecastRemainingQty' => (float) $forecastKpiRows->sum('forecast_remaining_qty'),
            'forecastRemainingSales' => (int) $forecastKpiRows->sum('forecast_remaining_sales'),
            'forecastRemainingProfit' => (int) $forecastCalculable->sum('forecast_remaining_profit'),
            'forecastTrend' => $forecastTrend,
            'forecastComparison' => $forecastComparison,
            'costWarnings' => $costWarnings,
            'ym' => $ym,
            'monthOptions' => DemoData::salesMonthOptions(),
            'selectedProductId' => $selectedProductId,
            'selectedProduct' => $selectedProduct,
            'search' => $search,
        ]);
    }

    private function resolveYm(Request $request): string
    {
        $currentYm = BusinessDate::currentYm();
        $ym = (string) $request->query('ym', $currentYm);

        return DemoData::isValidSalesMonth($ym) ? $ym : $currentYm;
    }
}
