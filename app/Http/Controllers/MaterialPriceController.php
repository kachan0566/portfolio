<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\ListSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialPriceController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $prices = ListSearch::filter(DemoData::materialPrices(), $search, [
            'code_fields' => [],
            'sku_fields' => ['material_sku', 'material'],
        ]);

        $months = DemoData::materialPrices()->pluck('ym')->unique()->sort()->values();
        $matrix = DemoData::materialPrices()->groupBy('material')->map(function ($rows) {
            return $rows->keyBy('ym');
        });

        if (ListSearch::isActive($search)) {
            $materials = $prices->pluck('material')->unique();
            $matrix = $matrix->only($materials->all());
        }

        return view('prices.index', [
            'prices' => $prices,
            'months' => $months,
            'matrix' => $matrix,
            'search' => $search,
        ]);
    }
}
