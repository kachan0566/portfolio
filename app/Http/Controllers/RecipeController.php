<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\ListSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        $ym = DemoData::CURRENT_YM;
        $search = ListSearch::params($request);

        $recipes = DemoData::recipes()->groupBy('sku')->map(function ($items) use ($ym) {
            return (object) [
                'product_id' => $items->first()->product_id,
                'rows' => $this->displayRecipeRows($items, $ym),
            ];
        });

        if (ListSearch::isActive($search)) {
            $recipes = $recipes->filter(function ($rows, $sku) use ($search) {
                if ($search['sku'] !== '') {
                    return str_contains(mb_strtolower($sku), mb_strtolower($search['sku']));
                }

                return true;
            });
        }

        $unitCosts = DemoData::products()->mapWithKeys(fn ($p) => [
            $p->sku => DemoData::unitCost($p->id, $ym),
        ]);

        return view('recipes.index', [
            'recipes' => $recipes,
            'unitCosts' => $unitCosts,
            'ym' => $ym,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('recipes.create', [
            'products' => DemoData::products(),
            'materials' => DemoData::materials(),
        ]);
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('recipes.index')
            ->with('success', 'レシピを登録しました。（テストデータのため保存はされません）');
    }

    public function edit(int $product): View
    {
        $productData = DemoData::findProduct($product) ?? abort(404);
        $items = DemoData::recipes()->where('product_id', $product);

        if ($items->isEmpty()) {
            abort(404);
        }

        return view('recipes.edit', [
            'product' => $productData,
            'items' => $items,
            'materials' => DemoData::materials(),
        ]);
    }

    public function update(int $product): RedirectResponse
    {
        DemoData::findProduct($product) ?? abort(404);

        return redirect()->route('recipes.index')
            ->with('success', 'レシピを更新しました。（テストデータのため保存はされません）');
    }

    /**
     * レシピ表示用に行を整形する。
     * 染料（id:3）・仕上げ剤（id:4）は「加工料」として1行にまとめる。
     */
    private function displayRecipeRows(Collection $items, string $ym): Collection
    {
        $rows = collect();
        $processingAmount = 0;
        $processingDetail = [];

        foreach ($items as $item) {
            if (in_array($item->material_id, [3, 4], true)) {
                $price = DemoData::materialPrice($item->material_id, $ym);
                $processingAmount += $item->qty * $price;
                $processingDetail[] = $item->material_sku . ' ' . rtrim(rtrim(number_format($item->qty, 2), '0'), '.') . $item->unit;
                continue;
            }

            $price = DemoData::materialPrice($item->material_id, $ym);
            $rows->push((object) [
                'label' => $item->material_sku,
                'sub' => null,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'price' => $price,
                'amount' => $item->qty * $price,
            ]);
        }

        if ($processingAmount > 0) {
            $rows->push((object) [
                'label' => '加工料',
                'sub' => implode(' ＋ ', $processingDetail),
                'qty' => null,
                'unit' => '',
                'price' => null,
                'amount' => $processingAmount,
            ]);
        }

        return $rows;
    }
}
