<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\ListSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $ym = $this->resolveYm($request);
        $search = ListSearch::params($request);
        $selectedProductId = $request->filled('product_id') ? (int) $request->query('product_id') : null;

        $allByProduct = DemoData::monthlySalesByProduct($ym);
        $byProduct = ListSearch::filter($allByProduct, $search, [
            'code_fields' => [],
            'sku_fields' => ['sku', 'product'],
        ]);

        $kpiRows = $selectedProductId !== null
            ? $allByProduct->where('product_id', $selectedProductId)
            : $allByProduct;
        $calculableRows = $kpiRows->where('cost_calculable', true);

        $selectedProduct = $selectedProductId !== null
            ? DemoData::findProduct($selectedProductId)
            : null;

        return view('sales.index', [
            'byProduct' => $byProduct,
            'allByProduct' => $allByProduct,
            'trend' => DemoData::salesTrend($ym, $selectedProductId),
            'totalSales' => $kpiRows->sum('sales'),
            'totalCost' => $calculableRows->sum('cost'),
            'totalProfit' => $calculableRows->sum('profit'),
            'hasUncalculableCost' => $kpiRows->contains(fn ($row) => ! $row->cost_calculable),
            'costWarnings' => DemoData::collectCostWarnings(
                $allByProduct->where('cost_calculable', false)->pluck('product_id'),
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
