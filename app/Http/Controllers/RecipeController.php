<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGreigeRecipeRequest;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateGreigeRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Support\DemoData;
use App\Support\DemoOverlay;
use App\Support\ListSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        $ym = DemoData::CURRENT_YM;
        $search = ListSearch::params($request);
        $tab = $request->query('tab', 'product');
        if (! in_array($tab, ['product', 'greige'], true)) {
            $tab = 'product';
        }

        $recipes = DemoData::products()
            ->filter(fn ($p) => DemoData::hasRecipe($p->id))
            ->mapWithKeys(function ($product) use ($ym) {
                $breakdown = DemoData::unitCostBreakdown($product->id, $ym);
                $profit = DemoData::unitProfitSummary($product->id, $ym);

                return [
                    $product->sku => (object) [
                        'product_id' => $product->id,
                        'breakdown' => $breakdown,
                        'profit' => $profit,
                    ],
                ];
            });

        if (ListSearch::isActive($search)) {
            $recipes = $recipes->filter(function ($recipe, $sku) use ($search) {
                if ($search['sku'] !== '') {
                    return str_contains(mb_strtolower($sku), mb_strtolower($search['sku']));
                }

                return true;
            });
        }

        $greigeRecipes = DemoData::greigeRecipes()->map(function ($recipe) use ($ym) {
            $breakdown = DemoData::greigeUnitCostBreakdown($recipe->greige_sku, $ym);

            return (object) array_merge((array) $recipe, [
                'cost_breakdown' => $breakdown,
            ]);
        });
        if ($search['sku'] !== '') {
            $needle = mb_strtolower($search['sku']);
            $greigeRecipes = $greigeRecipes->filter(
                fn ($r) => str_contains(mb_strtolower($r->greige_sku), $needle)
                    || str_contains(mb_strtolower($r->greige_name), $needle)
            );
        }

        $costWarnings = DemoData::collectCostWarnings(
            $recipes->pluck('product_id'),
            $ym
        );
        $greigeCostWarnings = DemoData::collectGreigeCostWarnings(
            $greigeRecipes->pluck('greige_sku'),
            $ym
        );

        return view('recipes.index', [
            'recipes' => $recipes,
            'greigeRecipes' => $greigeRecipes,
            'tab' => $tab,
            'ym' => $ym,
            'search' => $search,
            'costWarnings' => $costWarnings,
            'greigeCostWarnings' => $greigeCostWarnings,
        ]);
    }

    public function create(): View
    {
        $ym = DemoData::CURRENT_YM;
        $existingProductIds = collect(array_keys(DemoData::recipeData()));

        $products = DemoData::products()
            ->whereNotIn('id', $existingProductIds)
            ->map(function ($product) use ($ym) {
                $breakdown = DemoData::unitCostBreakdown($product->id, $ym);

                return (object) array_merge((array) $product, [
                    'greige_cost' => $breakdown->greige_cost,
                    'cost_calculable' => $breakdown->calculable,
                ]);
            })
            ->values();

        return view('recipes.create', [
            'products' => $products,
            'ym' => $ym,
        ]);
    }

    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $productId = (int) $request->input('product_id');
        DemoOverlay::saveRecipe($productId, self::productRecipePayload($request));
        DemoOverlay::saveProductPrice($productId, (int) $request->input('price'));

        return redirect()->route('recipes.index')
            ->with('success', '製品レシピを登録しました。');
    }

    public function edit(int $product): View
    {
        $ym = DemoData::CURRENT_YM;
        $productData = DemoData::findProduct($product) ?? abort(404);
        if (! DemoData::hasRecipe($product)) {
            abort(404);
        }

        $recipe = DemoData::recipeData()[$product];
        $greige = DemoData::findGreige($productData->greige_sku);
        $breakdown = DemoData::unitCostBreakdown($product, $ym);
        $profit = DemoData::unitProfitSummary($product, $ym);

        return view('recipes.edit', [
            'product' => $productData,
            'processingCost' => $recipe['processing_cost'],
            'price' => $productData->price,
            'greigeSku' => $productData->greige_sku,
            'greigeName' => $greige->name ?? null,
            'breakdown' => $breakdown,
            'profit' => $profit,
            'ym' => $ym,
            'costWarnings' => DemoData::costWarningMessages($product, $ym),
        ]);
    }

    public function update(UpdateRecipeRequest $request, int $product): RedirectResponse
    {
        DemoData::findProduct($product) ?? abort(404);
        if (! DemoData::hasRecipe($product)) {
            abort(404);
        }

        DemoOverlay::saveRecipe($product, self::productRecipePayload($request));
        DemoOverlay::saveProductPrice($product, (int) $request->input('price'));

        return redirect()->route('recipes.index')
            ->with('success', '製品レシピを更新しました。');
    }

    public function createGreige(): View
    {
        $existingSkus = collect(array_keys(DemoData::greigeRecipeData()));

        return view('recipes.greige-create', [
            'greiges' => DemoData::greiges()->whereNotIn('sku', $existingSkus)->values(),
            'materials' => DemoData::yarnMaterials(),
        ]);
    }

    public function storeGreige(StoreGreigeRecipeRequest $request): RedirectResponse
    {
        DemoOverlay::saveGreigeRecipe(
            (string) $request->input('greige_sku'),
            self::greigeRecipePayload($request)
        );

        return redirect()->route('recipes.index', ['tab' => 'greige'])
            ->with('success', '生機レシピを登録しました。');
    }

    public function editGreige(string $greigeSku): View
    {
        if (! DemoData::hasGreigeRecipe($greigeSku)) {
            abort(404);
        }

        $greige = DemoData::findGreige($greigeSku) ?? abort(404);
        $recipe = DemoData::greigeRecipeData()[$greigeSku];
        $lines = collect($recipe['lines'])->map(function ($line) {
            [$materialId, $qty] = $line;
            $material = DemoData::findMaterial($materialId);

            return (object) [
                'material_id' => $materialId,
                'qty' => $qty,
                'material_sku' => $material->sku,
                'material' => $material->name,
            ];
        });

        return view('recipes.greige-edit', [
            'greige' => $greige,
            'lines' => $lines,
            'lossRate' => $recipe['loss_rate'],
            'weavingCost' => (int) ($recipe['weaving_cost'] ?? 0),
            'materials' => DemoData::yarnMaterials(),
        ]);
    }

    public function updateGreige(UpdateGreigeRecipeRequest $request, string $greigeSku): RedirectResponse
    {
        if (! DemoData::hasGreigeRecipe($greigeSku)) {
            abort(404);
        }

        DemoOverlay::saveGreigeRecipe($greigeSku, self::greigeRecipePayload($request));

        return redirect()->route('recipes.index', ['tab' => 'greige'])
            ->with('success', '生機レシピを更新しました。');
    }

    /** @return array{processing_cost: int} */
    private static function productRecipePayload(StoreRecipeRequest|UpdateRecipeRequest $request): array
    {
        return [
            'processing_cost' => (int) $request->input('processing_cost'),
        ];
    }

    /** @return array{lines: list<array{0: int, 1: float}>, loss_rate: float, weaving_cost: int} */
    private static function greigeRecipePayload(StoreGreigeRecipeRequest|UpdateGreigeRecipeRequest $request): array
    {
        $lines = collect($request->input('lines', []))
            ->map(fn ($line) => [(int) $line['material_id'], (float) $line['qty']])
            ->values()
            ->all();

        return [
            'lines' => $lines,
            'loss_rate' => round((float) $request->input('loss_rate_percent', 0) / 100, 4),
            'weaving_cost' => (int) $request->input('weaving_cost'),
        ];
    }
}
