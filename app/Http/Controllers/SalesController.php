<?php

namespace App\Http\Controllers;

use App\Services\Sales\SalesForecastEngine;
use App\Support\DemoData;
use App\Support\ListSearch;
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

        $forecast = SalesForecastEngine::build($ym);
        $allByProduct = DemoData::monthlySalesByProduct($ym);

        if ($tab === 'forecast') {
            $activeForecastLines = $forecast->lines->filter(
                fn ($line) => $line->has_forecast_activity || $line->actual_qty > 0
            );
            $forecastLines = ListSearch::filter($activeForecastLines, $search, [
                'code_fields' => [],
                'sku_fields' => ['sku'],
            ]);
        } else {
            $byProduct = ListSearch::filter($allByProduct, $search, [
                'code_fields' => [],
                'sku_fields' => ['sku', 'product'],
            ]);
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
            ? DemoData::findProduct($selectedProductId)
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
            'forecastTrend' => SalesForecastEngine::forecastTrend($ym, $selectedProductId),
            'forecastComparison' => SalesForecastEngine::buildComparison($ym),
            'costWarnings' => DemoData::collectCostWarnings(
                $forecast->lines->where('cost_calculable', false)->pluck('product_id'),
                $ym
            ),
            'ym' => $ym,
            'monthOptions' => DemoData::salesMonthOptions(),
            'selectedProductId' => $selectedProductId,
            'selectedProduct' => $selectedProduct,
            'search' => $search,
        ]);
    }

    private function resolveYm(Request $request): string
    {
        $ym = (string) $request->query('ym', DemoData::CURRENT_YM);

        return DemoData::isValidSalesMonth($ym) ? $ym : DemoData::CURRENT_YM;
    }
}
