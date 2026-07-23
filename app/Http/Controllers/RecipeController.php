<?php

namespace App\Http\Controllers;

use App\Support\MasterCatalog;

use App\Http\Requests\StoreGreigeRecipeRequest;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateGreigeRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Greige;
use App\Models\GreigeRecipe;
use App\Models\GreigeRecipeLine;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Support\DemoData;
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

        $recipes = MasterCatalog::products()
            ->filter(fn ($p) => ProductRecipe::existsForProduct((int) $p->id))
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

        $products = MasterCatalog::products()
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

        ProductRecipe::query()->updateOrCreate(
            ['product_id' => $productId],
            ['processing_cost' => (int) $request->input('processing_cost')],
        );
        Product::query()->whereKey($productId)->update([
            'price' => (int) $request->input('price'),
        ]);

        return redirect()->route('recipes.index')
            ->with('success', '製品レシピを登録しました。');
    }

    public function edit(int $product): View
    {
        $ym = DemoData::CURRENT_YM;
        $productData = MasterCatalog::findProductOrFail($product);
        if (! ProductRecipe::existsForProduct($product)) {
            abort(404);
        }

        $recipe = DemoData::recipeData()[$product];
        $greige = MasterCatalog::findGreige($productData->greige_sku);
        $breakdown = DemoData::unitCostBreakdown($product, $ym);
        $profit = DemoData::unitProfitSummary($product, $ym);

        return view('recipes.edit', [
            'product' => $productData,
            'processingCost' => $recipe['processing_cost'],
            'price' => $productData->price,
            'greigeSku' => $productData->greige_sku,
            'greigeName' => $greige?->name,
            'breakdown' => $breakdown,
            'profit' => $profit,
            'ym' => $ym,
            'costWarnings' => DemoData::costWarningMessages($product, $ym),
        ]);
    }

    public function update(UpdateRecipeRequest $request, int $product): RedirectResponse
    {
        MasterCatalog::findProductOrFail($product);
        if (! ProductRecipe::existsForProduct($product)) {
            abort(404);
        }

        ProductRecipe::query()->updateOrCreate(
            ['product_id' => $product],
            ['processing_cost' => (int) $request->input('processing_cost')],
        );
        Product::query()->whereKey($product)->update([
            'price' => (int) $request->input('price'),
        ]);

        return redirect()->route('recipes.index')
            ->with('success', '製品レシピを更新しました。');
    }

    public function createGreige(): View
    {
        $existingSkus = collect(array_keys(DemoData::greigeRecipeData()));

        return view('recipes.greige-create', [
            'greiges' => MasterCatalog::greiges()->whereNotIn('sku', $existingSkus)->values(),
            'materials' => MasterCatalog::yarnMaterials(),
        ]);
    }

    public function storeGreige(StoreGreigeRecipeRequest $request): RedirectResponse
    {
        $greigeSku = (string) $request->input('greige_sku');
        $payload = self::greigeRecipePayload($request);

        $greige = Greige::query()->where('sku', $greigeSku)->firstOrFail();
        $header = GreigeRecipe::query()->updateOrCreate(
            ['greige_id' => $greige->id],
            [
                'loss_rate' => $payload['loss_rate'],
                'weaving_cost' => $payload['weaving_cost'],
            ],
        );
        self::syncGreigeRecipeLines($header, $payload['lines']);

        return redirect()->route('recipes.index', ['tab' => 'greige'])
            ->with('success', '生機レシピを登録しました。');
    }

    public function editGreige(string $greigeSku): View
    {
        if (! GreigeRecipe::existsForSku($greigeSku)) {
            abort(404);
        }

        $greige = MasterCatalog::findGreige($greigeSku) ?? abort(404);
        $recipe = DemoData::greigeRecipeData()[$greigeSku];
        $lines = collect($recipe['lines'])->map(function ($line) {
            [$materialId, $qty] = $line;
            $material = MasterCatalog::findMaterial($materialId);

            return (object) [
                'material_id' => $materialId,
                'qty' => $qty,
                'material_sku' => $material?->sku ?? '',
                'material' => $material?->name ?? '',
            ];
        });

        return view('recipes.greige-edit', [
            'greige' => $greige,
            'lines' => $lines,
            'lossRate' => $recipe['loss_rate'],
            'weavingCost' => (int) ($recipe['weaving_cost'] ?? 0),
            'materials' => MasterCatalog::yarnMaterials(),
        ]);
    }

    public function updateGreige(UpdateGreigeRecipeRequest $request, string $greigeSku): RedirectResponse
    {
        if (! GreigeRecipe::existsForSku($greigeSku)) {
            abort(404);
        }

        $payload = self::greigeRecipePayload($request);

        $greige = Greige::query()->where('sku', $greigeSku)->firstOrFail();
        $header = GreigeRecipe::query()->updateOrCreate(
            ['greige_id' => $greige->id],
            [
                'loss_rate' => $payload['loss_rate'],
                'weaving_cost' => $payload['weaving_cost'],
            ],
        );
        self::syncGreigeRecipeLines($header, $payload['lines']);

        return redirect()->route('recipes.index', ['tab' => 'greige'])
            ->with('success', '生機レシピを更新しました。');
    }

    /**
     * @param  list<array{0: int, 1: float}>  $lines
     */
    private static function syncGreigeRecipeLines(GreigeRecipe $header, array $lines): void
    {
        $materialIds = [];
        foreach ($lines as [$materialId, $qtyPerM]) {
            $materialIds[] = (int) $materialId;
            GreigeRecipeLine::query()->updateOrCreate(
                [
                    'greige_recipe_id' => $header->id,
                    'material_id' => (int) $materialId,
                ],
                ['qty_per_m' => (float) $qtyPerM],
            );
        }

        GreigeRecipeLine::query()
            ->where('greige_recipe_id', $header->id)
            ->whereNotIn('material_id', $materialIds)
            ->delete();
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
