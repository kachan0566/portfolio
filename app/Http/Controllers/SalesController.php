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
        $data = DemoData::dashboard();
        $search = ListSearch::params($request);
        $byProduct = ListSearch::filter(DemoData::monthlySalesByProduct(), $search, [
            'code_fields' => [],
            'sku_fields' => ['sku', 'product'],
        ]);

        return view('sales.index', [
            'byProduct' => $byProduct,
            'trend' => $data['trend'],
            'shipments' => DemoData::shipments(),
            'totalSales' => $byProduct->sum('sales'),
            'totalCost' => $byProduct->sum('cost'),
            'totalProfit' => $byProduct->sum('profit'),
            'ym' => DemoData::CURRENT_YM,
            'search' => $search,
        ]);
    }
}
