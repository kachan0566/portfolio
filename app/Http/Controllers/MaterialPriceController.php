<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreYarnPriceRequest;
use App\Http\Requests\UpdateYarnPriceRequest;
use App\Support\DemoData;
use App\Support\DemoOverlay;
use App\Support\ListSearch;
use Illuminate\Http\RedirectResponse;
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

    public function create(): View
    {
        return view('prices.create', [
            'materials' => DemoData::yarnMaterials(),
        ]);
    }

    public function store(StoreYarnPriceRequest $request): RedirectResponse
    {
        DemoOverlay::addYarnPrice(
            (int) $request->input('material_id'),
            (string) $request->input('ym'),
            (int) $request->input('price'),
        );

        return redirect()->route('prices.index')
            ->with('success', '糸価格を登録しました。');
    }

    public function edit(int $price): View
    {
        $row = DemoData::findYarnPrice($price) ?? abort(404);

        return view('prices.edit', [
            'price' => $row,
        ]);
    }

    public function update(UpdateYarnPriceRequest $request, int $price): RedirectResponse
    {
        $row = DemoData::findYarnPrice($price) ?? abort(404);

        DemoOverlay::updateYarnPrice($row->material_id, $row->ym, (int) $request->input('price'));

        return redirect()->route('prices.index')
            ->with('success', '糸価格を更新しました。');
    }
}
