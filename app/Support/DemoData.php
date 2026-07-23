<?php

namespace App\Support;

use App\Models\Greige;
use App\Models\Material;
use App\Models\GreigeRecipe;
use App\Models\MaterialPrice;
use App\Models\Order;
use App\Models\OrderAllocation;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\ShipTo;
use App\Models\Supplier;
use App\Models\YarnStockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * フロント確認用のテストデータ置き場。
 *
 * データベースには接続せず、ここで定義した固定データ（テストデータ）を
 * 各画面に渡す。実データに差し替えるときは、各メソッドの中身を
 * Eloquent などの取得処理へ置き換えればよい。
 */
class DemoData
{
    /** 「今月」として扱う年月（テスト用に固定） */
    public const CURRENT_YM = '2026-06';

    /** 受注登録デモの遷移先（本日受付・在庫十分で即引当可能なシナリオ） */
    public const DEMO_NEW_ORDER_ID = 8;

    /** 大型受注デモの遷移先（FAB-T-BK・在庫大幅不足で追加発注が必要なシナリオ） */
    public const DEMO_LARGE_ORDER_ID = 9;

    /** 製品品番の標準：1反あたりのメートル数 */
    public const METERS_PER_TAN_PRODUCT = 50;

    /** 生機品番の標準：1反あたりのメートル数 */
    public const METERS_PER_TAN_GREIGE = 100;

    /** @var array<string, bool> */
    private static array $databaseUsageCache = [];

    /** @internal テスト用に DB 利用判定のキャッシュを破棄する */
    public static function resetDatabaseUsageCacheForTesting(): void
    {
        self::$databaseUsageCache = [];
    }

    private static function cachedDatabaseUsage(string $key, callable $resolver): bool
    {
        if (array_key_exists($key, self::$databaseUsageCache)) {
            return self::$databaseUsageCache[$key];
        }

        try {
            return self::$databaseUsageCache[$key] = (bool) $resolver();
        } catch (\Throwable) {
            return self::$databaseUsageCache[$key] = false;
        }
    }

    /** カテゴリ一覧 */
    public static function categories(): Collection
    {
        return collect([
            (object) ['id' => 1, 'name' => '生地'],
            (object) ['id' => 2, 'name' => '糸'],
            (object) ['id' => 3, 'name' => '製品'],
        ]);
    }

    /** 生機品番（親品番）一覧 */
    public static function greiges(): Collection
    {
        return collect([
            (object) ['id' => 1, 'sku' => 'KB-A', 'name' => '生機A',        'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
            (object) ['id' => 2, 'sku' => 'KB-B', 'name' => '生機B',        'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
            (object) ['id' => 3, 'sku' => 'KB-T', 'name' => 'Tシャツ生機',  'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
            (object) ['id' => 4, 'sku' => 'KB-C', 'name' => '裏地C生機',    'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
            (object) ['id' => 5, 'sku' => 'KB-D', 'name' => 'デニム生機',   'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
        ]);
    }

    /** 製品品番（子品番）一覧。1つの生機品番に複数の製品品番（カラー違い）がぶら下がる */
    public static function products(): Collection
    {
        $rows = [
            (object) ['id' => 1, 'sku' => 'FAB-A-BK', 'greige_sku' => 'KB-A', 'greige_name' => '生機A',       'color' => 'ブラック',   'price' => 1200, 'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 40,  'stock_min' => 100],
            (object) ['id' => 2, 'sku' => 'FAB-B-NV', 'greige_sku' => 'KB-B', 'greige_name' => '生機B',       'color' => 'ネイビー',   'price' => 1500, 'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 0,   'stock_min' => 100],
            (object) ['id' => 3, 'sku' => 'FAB-T-WH', 'greige_sku' => 'KB-T', 'greige_name' => 'Tシャツ生機', 'color' => 'ホワイト',   'price' => 900,  'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 70,  'stock_min' => 150],
            (object) ['id' => 4, 'sku' => 'LIN-C-BE', 'greige_sku' => 'KB-C', 'greige_name' => '裏地C生機',   'color' => 'ベージュ',   'price' => 700,  'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 0,   'stock_min' => 80],
            (object) ['id' => 5, 'sku' => 'DEN-D-IN', 'greige_sku' => 'KB-D', 'greige_name' => 'デニム生機',   'color' => 'インディゴ', 'price' => 1800, 'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 0,   'stock_min' => 100],
            (object) ['id' => 6, 'sku' => 'FAB-A-WH', 'greige_sku' => 'KB-A', 'greige_name' => '生機A',       'color' => 'ホワイト',   'price' => 1250, 'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 0,   'stock_min' => 80],
            (object) ['id' => 7, 'sku' => 'FAB-T-BK', 'greige_sku' => 'KB-T', 'greige_name' => 'Tシャツ生機', 'color' => 'ブラック',   'price' => 950,  'category' => '生地', 'unit' => '反', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 120,  'stock_min' => 150],
        ];

        $priceUpdates = DemoOverlay::productPriceUpdates();

        return collect($rows)->map(function ($p) use ($priceUpdates) {
            if (isset($priceUpdates[$p->id])) {
                $p->price = $priceUpdates[$p->id];
            }

            $perTan = $p->meters_per_tan ?? self::METERS_PER_TAN_PRODUCT;
            $p->stock_tan = isset($p->stock_tan)
                ? QtyHelper::roundTan((float) $p->stock_tan)
                : ($perTan > 0
                    ? QtyHelper::roundTan((int) ($p->stock ?? 0) / $perTan)
                    : 0.0);
            $p->stock = $perTan > 0
                ? (int) round($p->stock_tan * $perTan)
                : (int) ($p->stock ?? 0);
            $p->stock_min_tan = $perTan > 0
                ? round($p->stock_min / $perTan, QtyHelper::TAN_DECIMALS)
                : 0.0;

            return $p;
        });
    }

    public static function findProduct(int $id): ?object
    {
        return self::products()->firstWhere('id', $id);
    }

    public static function findGreige(string $sku): ?object
    {
        return self::greiges()->firstWhere('sku', $sku);
    }

    public static function findGreigeByProductId(int $productId): ?object
    {
        $product = self::products()->firstWhere('id', $productId);
        if ($product === null) {
            return null;
        }

        return self::greiges()->firstWhere('sku', $product->greige_sku);
    }

    /** 原材料一覧 */
    public static function materials(): Collection
    {
        return collect([
            (object) ['id' => 1, 'sku' => 'RM-001', 'name' => '綿糸',         'unit' => 'kg', 'type' => 'yarn'],
            (object) ['id' => 2, 'sku' => 'RM-002', 'name' => 'ポリエステル糸', 'unit' => 'kg', 'type' => 'yarn'],
            (object) ['id' => 3, 'sku' => 'RM-003', 'name' => '染料',         'unit' => 'kg', 'type' => 'dye'],
            (object) ['id' => 4, 'sku' => 'RM-004', 'name' => '仕上げ剤',      'unit' => 'L',  'type' => 'finishing'],
        ]);
    }

    public static function findMaterial(int $id): ?object
    {
        return self::materials()->firstWhere('id', $id);
    }

    /** 糸のみの原材料一覧 */
    public static function yarnMaterials(): Collection
    {
        return self::materials()->where('type', 'yarn')->values();
    }

    public static function isYarnMaterial(int $materialId): bool
    {
        $material = self::findMaterial($materialId);

        return $material?->type === 'yarn';
    }

    /** @return array<int, array{processing_cost: int}> */
    private static function baseRecipeData(): array
    {
        return [
            1 => ['processing_cost' => 475],
            2 => ['processing_cost' => 520],
            3 => ['processing_cost' => 170],
            4 => ['processing_cost' => 260],
            5 => ['processing_cost' => 778],
            6 => ['processing_cost' => 475],
            7 => ['processing_cost' => 170],
        ];
    }

    /** @return array<int, array{processing_cost: int}> */
    public static function baseRecipeDataForSeed(): array
    {
        return self::baseRecipeData();
    }

    /** @return array<int, array{processing_cost: int}> */
    public static function recipeData(): array
    {
        if (self::usesRecipeDatabase()) {
            $result = [];
            foreach (ProductRecipe::query()->get() as $row) {
                $result[(int) $row->product_id] = [
                    'processing_cost' => (int) $row->processing_cost,
                ];
            }

            return $result;
        }

        return array_replace_recursive(self::baseRecipeData(), DemoOverlay::recipeOverrides());
    }

    public static function processingCost(int $productId): int
    {
        return self::recipeData()[$productId]['processing_cost'] ?? 0;
    }

    public static function hasRecipe(int $productId): bool
    {
        return array_key_exists($productId, self::recipeData());
    }

    /** @return array<string, array{lines: list<array{0: int, 1: float}>, loss_rate: float, weaving_cost: int}> */
    private static function baseGreigeRecipeData(): array
    {
        return [
            'KB-A' => ['lines' => [[1, 2.0]], 'loss_rate' => 0.03, 'weaving_cost' => 120],
            'KB-B' => ['lines' => [[1, 1.5], [2, 1.0]], 'loss_rate' => 0.05, 'weaving_cost' => 150],
            'KB-T' => ['lines' => [[1, 1.8]], 'loss_rate' => 0.03, 'weaving_cost' => 100],
            'KB-D' => ['lines' => [[1, 2.5]], 'loss_rate' => 0.04, 'weaving_cost' => 180],
        ];
    }

    /** @return array<string, array{lines: list<array{0: int, 1: float}>, loss_rate: float, weaving_cost: int}> */
    public static function baseGreigeRecipeDataForSeed(): array
    {
        return self::baseGreigeRecipeData();
    }

    /** @return array<string, array{lines: list<array{0: int, 1: float}>, loss_rate: float, weaving_cost: int}> */
    public static function greigeRecipeData(): array
    {
        if (self::usesRecipeDatabase()) {
            $result = [];
            foreach (GreigeRecipe::query()->with(['greige', 'lines'])->get() as $header) {
                $sku = $header->greige?->sku;
                if ($sku === null) {
                    continue;
                }

                $lines = [];
                foreach ($header->lines as $line) {
                    $lines[] = [(int) $line->material_id, (float) $line->qty_per_m];
                }

                $result[$sku] = [
                    'lines' => $lines,
                    'loss_rate' => (float) $header->loss_rate,
                    'weaving_cost' => (int) $header->weaving_cost,
                ];
            }

            return $result;
        }

        return array_replace_recursive(self::baseGreigeRecipeData(), DemoOverlay::greigeRecipeOverrides());
    }

    public static function hasGreigeRecipe(string $greigeSku): bool
    {
        return array_key_exists($greigeSku, self::greigeRecipeData());
    }

    public static function greigeLossRate(string $greigeSku): float
    {
        return (float) (self::greigeRecipeData()[$greigeSku]['loss_rate'] ?? 0.0);
    }

    /**
     * 生機発注向け：総m数から必要糸量（kg）を算出（ロス率込み）。
     *
     * @return list<object{material_id: int, material_sku: string, material: string, qty_per_m: float, required_kg: float}>
     */
    public static function greigeYarnRequirements(string $greigeSku, float|int $qtyMeters): array
    {
        $recipe = self::greigeRecipeData()[$greigeSku] ?? null;
        if ($recipe === null || $qtyMeters <= 0) {
            return [];
        }

        $lossMultiplier = 1.0 + self::greigeLossRate($greigeSku);
        $meters = (float) $qtyMeters;
        $result = [];

        foreach ($recipe['lines'] as [$materialId, $qtyPerM]) {
            $material = self::findMaterial($materialId);
            if ($material === null) {
                continue;
            }
            $result[] = (object) [
                'material_id' => $materialId,
                'material_sku' => $material->sku,
                'material' => $material->name,
                'qty_per_m' => (float) $qtyPerM,
                'required_kg' => round((float) $qtyPerM * $meters * $lossMultiplier, 2),
            ];
        }

        return $result;
    }

    /** 生機レシピ一覧（画面表示用） */
    public static function greigeRecipes(): Collection
    {
        $result = collect();
        foreach (self::greigeRecipeData() as $greigeSku => $recipe) {
            $greige = self::findGreige($greigeSku);
            if ($greige === null) {
                continue;
            }
            $yarnLines = [];
            foreach ($recipe['lines'] as [$materialId, $qty]) {
                $material = self::findMaterial($materialId);
                $yarnLines[] = (object) [
                    'material_id' => $materialId,
                    'material_sku' => $material->sku,
                    'material' => $material->name,
                    'qty' => $qty,
                    'unit' => 'kg/m',
                ];
            }
            $result->push((object) [
                'greige_sku' => $greigeSku,
                'greige_name' => $greige->name,
                'loss_rate' => $recipe['loss_rate'],
                'weaving_cost' => (int) ($recipe['weaving_cost'] ?? 0),
                'yarn_lines' => $yarnLines,
            ]);
        }

        return $result->sortBy('greige_sku')->values();
    }

    /** 生機1mあたりの単価内訳（糸原価はロス率込み） */
    public static function greigeUnitCostBreakdown(string $greigeSku, string $ym): object
    {
        $recipe = self::greigeRecipeData()[$greigeSku] ?? null;
        if ($recipe === null) {
            return (object) [
                'calculable' => false,
                'yarn_lines' => [],
                'yarn_cost' => null,
                'weaving_cost' => 0.0,
                'loss_rate' => 0.0,
                'total' => null,
                'missing_yarns' => [],
            ];
        }

        $lossRate = (float) ($recipe['loss_rate'] ?? 0);
        $lossMultiplier = 1.0 + $lossRate;
        $weavingCost = (float) ($recipe['weaving_cost'] ?? 0);
        $yarnLines = [];
        $missingYarns = [];
        $yarnCost = 0.0;
        $calculable = true;

        foreach ($recipe['lines'] as [$materialId, $qty]) {
            $material = self::findMaterial($materialId);
            $price = self::yarnPrice($materialId, $ym);
            $missing = $price === null;
            $effectiveQty = (float) $qty * $lossMultiplier;

            if ($missing) {
                $calculable = false;
                $missingYarns[] = (object) [
                    'material_id' => $materialId,
                    'material_sku' => $material->sku,
                    'material' => $material->name,
                    'ym' => $ym,
                ];
            } else {
                $yarnCost += $effectiveQty * $price;
            }

            $yarnLines[] = (object) [
                'label' => $material->sku,
                'sub' => $material->name,
                'qty' => (float) $qty,
                'effective_qty' => $effectiveQty,
                'unit' => 'kg/m',
                'price' => $price,
                'amount' => $missing ? null : $effectiveQty * $price,
                'missing' => $missing,
            ];
        }

        return (object) [
            'calculable' => $calculable,
            'yarn_lines' => $yarnLines,
            'yarn_cost' => $calculable ? $yarnCost : null,
            'weaving_cost' => $weavingCost,
            'loss_rate' => $lossRate,
            'total' => $calculable ? $yarnCost + $weavingCost : null,
            'missing_yarns' => $missingYarns,
        ];
    }

    public static function greigeUnitCost(string $greigeSku, string $ym): ?float
    {
        $breakdown = self::greigeUnitCostBreakdown($greigeSku, $ym);

        return $breakdown->calculable ? $breakdown->total : null;
    }

    /** @return list<string> */
    public static function greigeCostWarningMessages(string $greigeSku, string $ym): array
    {
        $breakdown = self::greigeUnitCostBreakdown($greigeSku, $ym);
        $messages = [];

        foreach ($breakdown->missing_yarns as $yarn) {
            $messages[] = "{$yarn->material}（{$yarn->material_sku}）の {$ym} 単価が未登録のため、生機単価を算出できません。";
        }

        return $messages;
    }

    /** @return list<string> */
    public static function collectGreigeCostWarnings(iterable $greigeSkus, string $ym): array
    {
        $messages = [];
        foreach ($greigeSkus as $greigeSku) {
            foreach (self::greigeCostWarningMessages((string) $greigeSku, $ym) as $message) {
                if (! in_array($message, $messages, true)) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /** 糸の初期在庫（kg）。Phase B 以降で DemoState と連動 */
    public static function yarnStockBase(): array
    {
        return [
            1 => 800.0,
            2 => 500.0,
        ];
    }

    public static function yarnStockKg(int $materialId): float
    {
        return (float) (self::yarnStockBase()[$materialId] ?? 0.0);
    }

    /** 出荷先マスタ */
    public static function shipTos(): Collection
    {
        return collect([
            (object) ['id' => 1, 'name' => '第一織工場',     'type' => ShipToType::WEAVING],
            (object) ['id' => 2, 'name' => '中央染工場',     'type' => ShipToType::DYEING],
            (object) ['id' => 3, 'name' => '第二織工場',     'type' => ShipToType::WEAVING],
            (object) ['id' => 4, 'name' => '本社倉庫',       'type' => ShipToType::WAREHOUSE],
            (object) ['id' => 5, 'name' => '関西染工場',     'type' => ShipToType::DYEING],
        ]);
    }

    public static function findShipTo(int $id): ?object
    {
        return self::shipTos()->firstWhere('id', $id);
    }

    /** @return Collection<int, object> */
    public static function shipTosForPurchaseType(string $type): Collection
    {
        $allowed = PurchaseOrderType::shipToTypesFor($type);

        return self::shipTos()->whereIn('type', $allowed)->values();
    }

    /** @return list<array{material_id: int, prices: array<string, int>}> */
    public static function baseMaterialPriceRowsForSeed(): array
    {
        return [
            ['material_id' => 1, 'prices' => ['2026-04' => 480, '2026-05' => 500, '2026-06' => 550]],
            ['material_id' => 2, 'prices' => ['2026-04' => 300, '2026-05' => 320, '2026-06' => 310]],
        ];
    }

    /** 月別糸価格 */
    public static function materialPrices(): Collection
    {
        if (self::usesMaterialPriceDatabase()) {
            return MaterialPrice::query()
                ->with('material')
                ->orderBy('material_id')
                ->orderBy('ym')
                ->get()
                ->map(function (MaterialPrice $row) {
                    $material = $row->material;

                    return (object) [
                        'id' => (int) $row->id,
                        'material_id' => (int) $row->material_id,
                        'material_sku' => $material?->sku ?? '',
                        'material' => $material?->name ?? '',
                        'unit' => 'kg',
                        'ym' => (string) $row->ym,
                        'price' => (int) $row->unit_price,
                    ];
                });
        }

        $rows = self::baseMaterialPriceRowsForSeed();

        $priceMap = [];
        foreach ($rows as $row) {
            foreach ($row['prices'] as $ym => $price) {
                $priceMap[DemoOverlay::yarnPriceKey($row['material_id'], $ym)] = $price;
            }
        }

        foreach (DemoOverlay::yarnPriceUpdates() as $key => $price) {
            $priceMap[$key] = $price;
        }

        foreach (DemoOverlay::yarnPriceAdditions() as $addition) {
            $priceMap[DemoOverlay::yarnPriceKey($addition['material_id'], $addition['ym'])] = $addition['price'];
        }

        $result = collect();
        $id = 1;
        foreach ($priceMap as $key => $price) {
            [$materialId, $ym] = explode('|', $key, 2);
            $material = self::findMaterial((int) $materialId);
            if (! $material || ! self::isYarnMaterial((int) $materialId)) {
                continue;
            }
            $result->push((object) [
                'id' => $id++,
                'material_id' => (int) $materialId,
                'material_sku' => $material->sku,
                'material' => $material->name,
                'unit' => 'kg',
                'ym' => $ym,
                'price' => $price,
            ]);
        }

        return $result->sortBy(['material_id', 'ym'])->values()->map(function ($row, $index) {
            $row->id = $index + 1;

            return $row;
        });
    }

    public static function findYarnPrice(int $id): ?object
    {
        if (self::usesMaterialPriceDatabase()) {
            $row = MaterialPrice::query()->with('material')->find($id);
            if ($row === null) {
                return null;
            }
            $material = $row->material;

            return (object) [
                'id' => (int) $row->id,
                'material_id' => (int) $row->material_id,
                'material_sku' => $material?->sku ?? '',
                'material' => $material?->name ?? '',
                'unit' => 'kg',
                'ym' => (string) $row->ym,
                'price' => (int) $row->unit_price,
            ];
        }

        return self::materialPrices()->firstWhere('id', $id);
    }

    public static function hasYarnPrice(int $materialId, string $ym): bool
    {
        return self::yarnPrice($materialId, $ym) !== null;
    }

    /** 指定糸・年月の単価を取得（未登録時は null） */
    public static function yarnPrice(int $materialId, string $ym): ?int
    {
        if (! self::isYarnMaterial($materialId)) {
            return null;
        }

        $row = self::materialPrices()
            ->where('material_id', $materialId)
            ->firstWhere('ym', $ym);

        return $row?->price;
    }

    /** 商品レシピ一覧（染色加工料のみ） */
    public static function recipes(): Collection
    {
        $result = collect();
        foreach (self::recipeData() as $productId => $recipe) {
            $product = self::findProduct($productId);
            if ($product === null) {
                continue;
            }
            $result->push((object) [
                'product_id' => $productId,
                'product' => $product->sku,
                'sku' => $product->sku,
                'greige_sku' => $product->greige_sku,
                'processing_cost' => $recipe['processing_cost'],
            ]);
        }

        return $result;
    }

    /** 商品1mあたりの製造コスト内訳（生機単価＋染色加工料） */
    public static function unitCostBreakdown(int $productId, string $ym): object
    {
        $recipe = self::recipeData()[$productId] ?? null;
        $product = self::findProduct($productId);
        $processingCost = (float) ($recipe['processing_cost'] ?? 0);
        $greigeSku = $product->greige_sku ?? null;
        $greige = $greigeSku !== null ? self::findGreige($greigeSku) : null;
        $missingGreigeRecipe = $greigeSku === null || ! self::hasGreigeRecipe($greigeSku);

        $greigeBreakdown = (! $missingGreigeRecipe && $greigeSku !== null)
            ? self::greigeUnitCostBreakdown($greigeSku, $ym)
            : null;

        $greigeCost = ($greigeBreakdown !== null && $greigeBreakdown->calculable)
            ? $greigeBreakdown->total
            : null;

        $calculable = $greigeCost !== null;

        return (object) [
            'calculable' => $calculable,
            'greige_sku' => $greigeSku,
            'greige_name' => $greige->name ?? null,
            'greige_cost' => $greigeCost,
            'processing_cost' => $processingCost,
            'total' => $calculable ? $greigeCost + $processingCost : null,
            'missing_greige_recipe' => $missingGreigeRecipe,
            'missing_yarns' => $greigeBreakdown->missing_yarns ?? [],
        ];
    }

    /** 商品1単位あたりの製造コスト（算出不可時は null） */
    public static function unitCost(int $productId, string $ym): ?float
    {
        $breakdown = self::unitCostBreakdown($productId, $ym);

        return $breakdown->calculable ? $breakdown->total : null;
    }

    /**
     * 1mあたりの粗利サマリー（販売価格・染色加工料の上書きに対応）。
     *
     * @return object{
     *     calculable: bool,
     *     unit_cost: ?float,
     *     price: int,
     *     profit: ?int,
     *     margin_percent: ?float,
     *     greige_cost: ?float,
     *     processing_cost: float,
     * }
     */
    public static function unitProfitSummary(
        int $productId,
        string $ym,
        ?int $priceOverride = null,
        ?int $processingCostOverride = null,
    ): object {
        $breakdown = self::unitCostBreakdown($productId, $ym);
        $processingCost = $processingCostOverride !== null
            ? (float) $processingCostOverride
            : $breakdown->processing_cost;
        $unitCost = $breakdown->calculable && $breakdown->greige_cost !== null
            ? $breakdown->greige_cost + $processingCost
            : null;

        $product = self::findProduct($productId);
        $price = $priceOverride ?? (int) ($product->price ?? 0);
        $profit = $unitCost !== null ? $price - $unitCost : null;
        $marginPercent = ($profit !== null && $price > 0)
            ? round($profit / $price * 100, 1)
            : null;

        return (object) [
            'calculable' => $breakdown->calculable,
            'unit_cost' => $unitCost,
            'price' => $price,
            'profit' => $profit !== null ? (int) round($profit) : null,
            'margin_percent' => $marginPercent,
            'greige_cost' => $breakdown->greige_cost,
            'processing_cost' => $processingCost,
        ];
    }

    /** 製造コスト算出不可の警告メッセージ一覧 */
    public static function costWarningMessages(int $productId, string $ym): array
    {
        $breakdown = self::unitCostBreakdown($productId, $ym);
        $messages = [];

        if ($breakdown->missing_greige_recipe && $breakdown->greige_sku !== null) {
            $label = $breakdown->greige_name !== null
                ? "{$breakdown->greige_name}（{$breakdown->greige_sku}）"
                : $breakdown->greige_sku;
            $messages[] = "{$label} の生機レシピが未登録のため、製造コストを算出できません。生機レシピタブから登録してください。";
        }

        foreach ($breakdown->missing_yarns as $yarn) {
            $messages[] = "{$yarn->material}（{$yarn->material_sku}）の {$ym} 単価が未登録のため、製造コストを算出できません。";
        }

        return $messages;
    }

    /** 複数品番の製造コスト警告をまとめて返す */
    public static function collectCostWarnings(iterable $productIds, string $ym): array
    {
        $messages = [];
        foreach ($productIds as $productId) {
            foreach (self::costWarningMessages((int) $productId, $ym) as $message) {
                if (! in_array($message, $messages, true)) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /** 得意先 */
    public static function customers(): Collection
    {
        return collect([
            (object) ['id' => 1, 'name' => '東レ商事',        'contact' => '田中 一郎', 'tel' => '03-1111-2222'],
            (object) ['id' => 2, 'name' => 'アパレル東京',    'contact' => '佐藤 花子', 'tel' => '03-3333-4444'],
            (object) ['id' => 3, 'name' => '西日本繊維',      'contact' => '鈴木 次郎', 'tel' => '06-5555-6666'],
            (object) ['id' => 4, 'name' => 'ユニフォーム製作所', 'contact' => '高橋 三郎', 'tel' => '052-777-8888'],
        ]);
    }

    /** 仕入先 */
    public static function suppliers(): Collection
    {
        return collect([
            (object) ['id' => 1, 'name' => '紡績ワークス',    'contact' => '伊藤 健', 'tel' => '03-9999-0000', 'type' => SupplierType::SPINNING],
            (object) ['id' => 2, 'name' => 'ケミカル商会',    'contact' => '渡辺 茜', 'tel' => '06-1212-3434', 'type' => SupplierType::CHEMICAL],
            (object) ['id' => 3, 'name' => '染料センター',    'contact' => '山本 武', 'tel' => '075-5656-7878', 'type' => SupplierType::DYE],
            (object) ['id' => 4, 'name' => '東日本織編',      'contact' => '中村 誠', 'tel' => '03-8888-1111', 'type' => SupplierType::WEAVING],
            (object) ['id' => 5, 'name' => '関西織編工業',    'contact' => '小林 美咲', 'tel' => '06-7777-2222', 'type' => SupplierType::WEAVING],
            (object) ['id' => 6, 'name' => '中央染色加工',    'contact' => '加藤 翔', 'tel' => '052-6666-3333', 'type' => SupplierType::DYEING],
            (object) ['id' => 7, 'name' => '関西染色加工',    'contact' => '松本 優', 'tel' => '06-5555-4444', 'type' => SupplierType::DYEING],
        ]);
    }

    public static function findSupplier(int $id): ?object
    {
        return self::suppliers()->firstWhere('id', $id);
    }

    /** @return Collection<int, object> */
    public static function suppliersForPurchaseType(string $type): Collection
    {
        $allowed = PurchaseOrderType::supplierTypesFor($type);

        return self::suppliers()->whereIn('type', $allowed)->values();
    }

    /** 受注の生データ（DemoState 参照なし。循環回避用） */
    public static function baseOrderRows(): Collection
    {
        $rows = [
            ['id' => 1, 'code' => 'SO-2606-001', 'customer' => '東レ商事',        'product_id' => 1, 'qty' => 120, 'shipped' => 120, 'order_date' => '2026-06-02', 'due_date' => '2026-06-12', 'planned_ship_date' => '2026-06-11', 'ship_memo' => '6/11 全量出荷済み'],
            ['id' => 2, 'code' => 'SO-2606-002', 'customer' => 'アパレル東京',    'product_id' => 3, 'qty' => 200, 'shipped' => 80,  'order_date' => '2026-06-03', 'due_date' => '2026-06-18', 'planned_ship_date' => '2026-06-17', 'ship_memo' => '残120は6/17午前に分納予定'],
            ['id' => 3, 'code' => 'SO-2606-003', 'customer' => '西日本繊維',      'product_id' => 5, 'qty' => 90,  'shipped' => 0,   'order_date' => '2026-06-05', 'due_date' => '2026-06-20', 'planned_ship_date' => '2026-06-19', 'ship_memo' => '在庫確保のうえ6/19出荷予定'],
            ['id' => 4, 'code' => 'SO-2606-004', 'customer' => 'ユニフォーム製作所', 'product_id' => 2, 'qty' => 60,  'shipped' => 60,  'order_date' => '2026-06-06', 'due_date' => '2026-06-15', 'planned_ship_date' => '2026-06-12', 'ship_memo' => '6/12 出荷完了'],
            ['id' => 5, 'code' => 'SO-2606-005', 'customer' => '東レ商事',        'product_id' => 4, 'qty' => 150, 'shipped' => 0,   'order_date' => '2026-06-08', 'due_date' => '2026-06-25', 'planned_ship_date' => '2026-06-24', 'ship_memo' => '入荷待ち。6/24までに出荷予定'],
            ['id' => 6, 'code' => 'SO-2606-006', 'customer' => 'アパレル東京',    'product_id' => 1, 'qty' => 100, 'shipped' => 40,  'order_date' => '2026-06-10', 'due_date' => '2026-06-28', 'planned_ship_date' => '2026-06-27', 'ship_memo' => '残60を6/27に出荷予定'],
            ['id' => 7, 'code' => 'SO-2606-007', 'customer' => '西日本繊維',      'product_id' => 6, 'qty' => 180, 'shipped' => 0,   'order_date' => '2026-06-25', 'due_date' => '2026-07-03', 'planned_ship_date' => '2026-07-02', 'ship_memo' => '本日受付。在庫140mを引当予定。不足40mは PO-2606-007 で追加発注済み'],
            ['id' => 8, 'code' => 'SO-2606-008', 'customer' => 'アパレル東京',    'product_id' => 7, 'qty' => 80,  'shipped' => 0,   'order_date' => '2026-06-25', 'due_date' => '2026-07-08', 'planned_ship_date' => '2026-07-07', 'ship_memo' => '本日受付。在庫不足のため PO-2606-008 を追加手配済み'],
            ['id' => 9, 'code' => 'SO-2606-009', 'customer' => '東レ商事',        'product_id' => 7, 'qty' => 500, 'shipped' => 0,   'order_date' => '2026-06-25', 'due_date' => '2026-07-20', 'planned_ship_date' => '2026-07-18', 'ship_memo' => '本日受付。大型案件500m。在庫不足のため PO-2606-009 を追加手配済み'],
            ['id' => 10, 'code' => 'SO-2606-010', 'customer' => 'ユニフォーム製作所', 'product_id' => 3, 'qty' => 50, 'shipped' => 0, 'order_date' => '2026-06-25', 'due_date' => '2026-07-05', 'planned_ship_date' => '2026-07-04', 'ship_memo' => '本日受付。在庫70mから全量引当可能'],
        ];

        foreach (OrderOverlay::additions() as $addition) {
            $rows[] = $addition;
        }

        return collect($rows)->map(function ($r) {
            return array_merge($r, OrderOverlay::overrides((int) $r['id']));
        });
    }

    /** @return array<string, mixed>|null */
    public static function findBaseOrder(int $id): ?array
    {
        $row = self::baseOrderRows()->firstWhere('id', $id);

        return is_array($row) ? $row : null;
    }

    /** 受注一覧 */
    public static function orders(): Collection
    {
        if (self::usesOrderDatabase()) {
            return Order::displayList();
        }

        return self::baseOrderRows()->map(function ($r) {
            $product = self::findProduct($r['product_id']);
            $r['product'] = $product->sku;
            $r['sku'] = $product->sku;
            $r['color'] = $product->color;
            $r['unit'] = $product->unit;
            $r['order_qty_mode'] = $r['order_qty_mode'] ?? 'tan';
            $r['qty_tan'] = ($r['order_qty_mode'] ?? 'tan') === 'tan'
                ? QtyHelper::roundIntegerTan((float) ($r['qty_tan'] ?? FabricQuantity::tanFromRecord($r, (int) $r['product_id'])))
                : FabricQuantity::tanFromRecord($r, (int) $r['product_id']);
            $r['shipped_tan'] = FabricQuantity::tanFromRecord(
                ['qty_tan' => $r['shipped_tan'] ?? null, 'qty' => $r['shipped'] ?? 0],
                (int) $r['product_id'],
            );
            $r['qty_meters'] = ($r['order_qty_mode'] ?? 'tan') === 'meters'
                ? (int) ($r['qty_meters'] ?? $r['qty'] ?? 0)
                : FabricQuantity::metersFromRecord(
                    ['qty_tan' => $r['qty_tan'], 'qty_meters' => $r['qty_meters'] ?? null],
                    (int) $r['product_id'],
                );
            $r['shipped_meters'] = (int) DemoState::effectiveShippedM((int) $r['id']);
            $r['qty'] = $r['qty_meters'];
            $r['shipped'] = $r['shipped_meters'];
            $r['status'] = self::orderProgressStatus($r);
            $r['is_new_today'] = $r['order_date'] === self::today();

            return (object) $r;
        });
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public static function orderProgressStatus(array $order): string
    {
        $mode = $order['order_qty_mode'] ?? 'tan';
        if ($mode === 'meters') {
            $qty = (int) ($order['qty_meters'] ?? $order['qty'] ?? 0);
            $shipped = (int) DemoState::effectiveShippedM((int) ($order['id'] ?? 0));

            return self::progressStatus($shipped, $qty, '受注');
        }

        $qtyTan = (float) ($order['qty_tan'] ?? 0);
        $shippedTan = DemoState::effectiveShippedTan((int) ($order['id'] ?? 0));
        if ($shippedTan <= 0) {
            return '未出荷';
        }

        return $shippedTan + 0.0001 >= $qtyTan ? '出荷済' : '一部出荷';
    }

    /** デモ上の「今日」の日付（受注日の基準） */
    public static function today(): string
    {
        return self::CURRENT_YM.'-25';
    }

    /** 受注日の新しい順に並べた一覧 */
    public static function recentOrders(int $limit = 6): Collection
    {
        return self::orders()
            ->sortBy([
                ['order_date', 'desc'],
                ['id', 'desc'],
            ])
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function basePurchaseOrderRows(): Collection
    {
        return collect([
            // --- 製品発注 ---
            [
                'id' => 1, 'code' => 'PO-2606-001', 'type' => PurchaseOrderType::PRODUCT,
                'status' => PurchaseOrderStatus::RECEIVED, 'order_id' => 1,
                'supplier_id' => 6, 'ship_to_id' => 4,
                'product_id' => 1, 'qty_meters' => 200, 'received' => 200,
                'order_date' => '2026-06-01', 'due_date' => '2026-06-08',
                'stage' => '染機投入済', 'finish_date' => '2026-06-07', 'contact_date' => '2026-06-06',
            ],
            [
                'id' => 2, 'code' => 'PO-2606-002', 'type' => PurchaseOrderType::PRODUCT,
                'status' => PurchaseOrderStatus::PARTIAL, 'order_id' => 2,
                'supplier_id' => 6, 'ship_to_id' => 4,
                'product_id' => 3, 'qty_meters' => 300, 'received' => 150,
                'order_date' => '2026-06-03', 'due_date' => '2026-06-14',
                'stage' => '染機投入済', 'finish_date' => '2026-06-16', 'contact_date' => '2026-06-15',
                'arrival_memo' => '染工場から6/16上がり連絡あり',
            ],
            [
                'id' => 3, 'code' => 'PO-2606-003', 'type' => PurchaseOrderType::PRODUCT,
                'status' => PurchaseOrderStatus::ORDERED, 'order_id' => 3,
                'supplier_id' => 7, 'ship_to_id' => 4,
                'product_id' => 5, 'qty_meters' => 120, 'received' => 0,
                'order_date' => '2026-06-05', 'due_date' => '2026-06-19',
                'stage' => '染機投入済', 'finish_date' => '2026-06-22', 'contact_date' => '2026-06-20',
                'arrival_memo' => '染工場に確認済み。6/22入荷見込み',
            ],
            // --- 生機発注 ---
            [
                'id' => 4, 'code' => 'PO-G-2606-001', 'type' => PurchaseOrderType::GREIGE,
                'status' => PurchaseOrderStatus::ORDERED, 'order_id' => null,
                'supplier_id' => 4, 'ship_to_id' => 2,
                'greige_sku' => 'KB-A', 'qty_tan' => 5.0, 'meters_per_tan' => 100, 'qty_meters' => 500,
                'received' => 0,
                'order_date' => '2026-06-18', 'due_date' => '2026-06-28',
                'finish_date' => '2026-06-25',
            ],
            [
                'id' => 5, 'code' => 'PO-G-2606-002', 'type' => PurchaseOrderType::GREIGE,
                'status' => PurchaseOrderStatus::PARTIAL, 'order_id' => null,
                'supplier_id' => 5, 'ship_to_id' => 5,
                'greige_sku' => 'KB-T', 'qty_tan' => 4.0, 'meters_per_tan' => 100, 'qty_meters' => 400,
                'received' => 200,
                'order_date' => '2026-06-10', 'due_date' => '2026-06-22',
                'finish_date' => '2026-06-18',
            ],
            [
                'id' => 6, 'code' => 'PO-G-2606-003', 'type' => PurchaseOrderType::GREIGE,
                'status' => PurchaseOrderStatus::DRAFT, 'order_id' => null,
                'supplier_id' => 4, 'ship_to_id' => 2,
                'greige_sku' => 'KB-B', 'qty_tan' => 4.0, 'meters_per_tan' => 100, 'qty_meters' => 400,
                'received' => 0,
                'order_date' => '2026-06-25', 'due_date' => '2026-07-05',
                'finish_date' => '2026-07-02',
            ],
            // --- 製品発注（受注連動） ---
            [
                'id' => 7, 'code' => 'PO-2606-007', 'type' => PurchaseOrderType::PRODUCT,
                'status' => PurchaseOrderStatus::ORDERED, 'order_id' => 7,
                'supplier_id' => 6, 'ship_to_id' => 4,
                'product_id' => 6, 'qty_meters' => 40, 'received' => 0,
                'order_date' => '2026-06-25', 'due_date' => '2026-07-01',
                'stage' => '染機投入済', 'finish_date' => '2026-07-02', 'contact_date' => '2026-06-30',
                'arrival_memo' => '不足分40mの追加手配。7/2上がり予定',
            ],
            [
                'id' => 8, 'code' => 'PO-2606-008', 'type' => PurchaseOrderType::PRODUCT,
                'status' => PurchaseOrderStatus::PARTIAL, 'order_id' => 8,
                'supplier_id' => 6, 'ship_to_id' => 4,
                'product_id' => 7, 'qty_meters' => 200, 'received' => 120,
                'order_date' => '2026-06-25', 'due_date' => '2026-07-05',
                'stage' => '染機投入済', 'finish_date' => '2026-07-06', 'contact_date' => '2026-07-04',
            ],
            [
                'id' => 9, 'code' => 'PO-2606-009', 'type' => PurchaseOrderType::PRODUCT,
                'status' => PurchaseOrderStatus::ORDERED, 'order_id' => 9,
                'supplier_id' => 6, 'ship_to_id' => 4,
                'product_id' => 7, 'qty_meters' => 500, 'received' => 0,
                'order_date' => '2026-06-25', 'due_date' => '2026-07-18',
                'stage' => '染機投入済', 'finish_date' => '2026-07-19', 'contact_date' => '2026-07-17',
            ],
            // --- 糸発注 ---
            [
                'id' => 10, 'code' => 'PO-Y-2606-001', 'type' => PurchaseOrderType::YARN,
                'status' => PurchaseOrderStatus::ORDERED, 'order_id' => null,
                'supplier_id' => 1, 'ship_to_id' => 1,
                'material_id' => 1, 'qty_kg' => 500.0, 'received_kg' => 0.0,
                'order_date' => '2026-06-20', 'due_date' => '2026-06-28',
            ],
            [
                'id' => 11, 'code' => 'PO-Y-2606-002', 'type' => PurchaseOrderType::YARN,
                'status' => PurchaseOrderStatus::PARTIAL, 'order_id' => null,
                'supplier_id' => 1, 'ship_to_id' => 3,
                'material_id' => 2, 'qty_kg' => 300.0, 'received_kg' => 150.0,
                'order_date' => '2026-06-12', 'due_date' => '2026-06-20',
            ],
        ]);
    }

    /** 受注引当の生データ（DemoState / StockAllocation 参照なし） */
    public static function baseAllocationRows(): Collection
    {
        return collect([
            // SO-2606-001: 全量出荷済み。PO-001 入荷分から在庫引当
            [
                'id' => 1, 'order_id' => 1, 'product_id' => 1,
                'purchase_order_id' => 1, 'allocation_type' => OrderAllocation::TYPE_STOCK,
                'qty_tan' => 2.4,
            ],
            // SO-2606-002: 一部出荷。在庫80m + 発注残120m
            [
                'id' => 2, 'order_id' => 2, 'product_id' => 3,
                'purchase_order_id' => 2, 'allocation_type' => OrderAllocation::TYPE_STOCK,
                'qty_tan' => 1.6,
            ],
            [
                'id' => 3, 'order_id' => 2, 'product_id' => 3,
                'purchase_order_id' => 2, 'allocation_type' => OrderAllocation::TYPE_PO,
                'qty_tan' => 2.4,
            ],
            // SO-2606-003: 入荷待ち。発注引当のみ
            [
                'id' => 4, 'order_id' => 3, 'product_id' => 5,
                'purchase_order_id' => 3, 'allocation_type' => OrderAllocation::TYPE_PO,
                'qty_tan' => 1.8,
            ],
            // SO-2606-007: 在庫140m + PO-007 から40m
            [
                'id' => 5, 'order_id' => 7, 'product_id' => 6,
                'purchase_order_id' => null, 'allocation_type' => OrderAllocation::TYPE_STOCK,
                'qty_tan' => 2.8,
            ],
            [
                'id' => 6, 'order_id' => 7, 'product_id' => 6,
                'purchase_order_id' => 7, 'allocation_type' => OrderAllocation::TYPE_PO,
                'qty_tan' => 0.8,
            ],
            // SO-2606-008: PO-008 入荷分から在庫引当
            [
                'id' => 7, 'order_id' => 8, 'product_id' => 7,
                'purchase_order_id' => 8, 'allocation_type' => OrderAllocation::TYPE_STOCK,
                'qty_tan' => 1.6,
            ],
            // SO-2606-009: 大型案件。発注引当のみ
            [
                'id' => 8, 'order_id' => 9, 'product_id' => 7,
                'purchase_order_id' => 9, 'allocation_type' => OrderAllocation::TYPE_PO,
                'qty_tan' => 10.0,
            ],
            // SO-2606-010: 在庫から全量引当可能
            [
                'id' => 9, 'order_id' => 10, 'product_id' => 3,
                'purchase_order_id' => null, 'allocation_type' => OrderAllocation::TYPE_STOCK,
                'qty_tan' => 1.0,
            ],
        ]);
    }

    /** 発注一覧（糸・生機・製品の3種別） */
    public static function purchaseOrders(): Collection
    {
        if (self::usesPurchaseOrderDatabase()) {
            return PurchaseOrder::displayList();
        }

        $rows = self::basePurchaseOrderRows()->all();

        foreach (PurchaseOrderOverlay::additions() as $addition) {
            $rows[] = $addition;
        }

        return collect($rows)->map(function ($r) {
            $overrides = PurchaseOrderOverlay::overrides((int) $r['id']);
            if (! empty($overrides)) {
                $r = array_merge($r, $overrides);
            }

            return self::enrichPurchaseOrder($r);
        });
    }

    public static function usesPurchaseOrderDatabase(): bool
    {
        return self::cachedDatabaseUsage('purchase_orders', fn () => Schema::hasTable('purchase_orders')
            && Schema::hasTable('purchase_order_lines')
            && PurchaseOrder::query()->exists());
    }

    public static function usesOrderDatabase(): bool
    {
        return self::cachedDatabaseUsage('orders', fn () => Schema::hasTable('orders')
            && Order::query()->exists());
    }

    public static function usesOrderAllocationDatabase(): bool
    {
        return self::cachedDatabaseUsage('order_allocations', fn () => Schema::hasTable('order_allocations')
            && self::usesOrderDatabase());
    }

    public static function purchaseOrdersOfType(string $type): Collection
    {
        return self::purchaseOrders()->where('type', $type)->values();
    }

    /** 発注一覧用（明細行単位） */
    public static function purchaseOrderIndexRows(): Collection
    {
        if (self::usesPurchaseOrderDatabase()) {
            return PurchaseOrder::displayLineList();
        }

        return self::purchaseOrders()->flatMap(function ($po) {
            $lineCount = max(1, (int) ($po->line_count ?? 1));
            $lineStage = (string) ($po->stage ?? PurchaseOrderDisplay::label($po));

            return [(object) array_merge((array) $po, [
                'line_no' => 1,
                'line_count' => $lineCount,
                'purchase_order_line_id' => null,
                'line_stage' => $lineStage,
                'stage' => $lineStage,
            ])];
        })->values();
    }

    /** @param  array<string, mixed>  $row */
    public static function enrichPurchaseOrder(array $row): object
    {
        $type = $row['type'] ?? PurchaseOrderType::PRODUCT;
        $status = $row['status'] ?? PurchaseOrderStatus::ORDERED;
        $supplier = self::findSupplier((int) ($row['supplier_id'] ?? 0));
        $shipTo = self::findShipTo((int) ($row['ship_to_id'] ?? 0));

        $row['type'] = $type;
        $row['type_label'] = PurchaseOrderType::label($type);
        $row['status'] = $status;
        $row['status_label'] = PurchaseOrderStatus::label($type, $status);
        $row['supplier'] = $supplier?->name ?? '—';
        $row['supplier_type'] = $supplier?->type;
        $row['ship_to'] = $shipTo?->name ?? '—';
        $row['ship_to_type'] = $shipTo?->type;
        $row['eta'] = $row['due_date'] ?? $row['eta'] ?? null;
        $row['arrival_memo'] = (string) ($row['arrival_memo'] ?? '');

        $linkedOrderId = PurchaseOrderLink::orderIdForPurchase((int) $row['id'], $row['order_id'] ?? null);
        $row['order_id'] = $linkedOrderId;
        $linkedOrder = $linkedOrderId ? self::findBaseOrder($linkedOrderId) : null;
        $row['order_code'] = $linkedOrder['code'] ?? null;
        $row['customer'] = $linkedOrder['customer'] ?? null;

        if ($type === PurchaseOrderType::YARN) {
            $material = self::findMaterial((int) ($row['material_id'] ?? 0));
            $row['sku'] = $material?->sku ?? '—';
            $row['product'] = $material?->name ?? '—';
            $row['unit'] = 'kg';
            $row['qty'] = (float) ($row['qty_kg'] ?? 0);
            $row['received'] = (float) ($row['received_kg'] ?? 0);
        } elseif ($type === PurchaseOrderType::GREIGE) {
            $greige = self::findGreige((string) ($row['greige_sku'] ?? ''));
            $row['sku'] = $greige?->sku ?? ($row['greige_sku'] ?? '—');
            $row['product'] = $greige?->name ?? '—';
            $row['unit'] = '反';
            $row['qty_meters'] = (int) ($row['qty_meters'] ?? 0);
            $row['qty'] = $row['qty_meters'];
            $row['qty_tan'] = (float) ($row['qty_tan'] ?? 0);
            $row['meters_per_tan'] = (int) ($row['meters_per_tan'] ?? self::METERS_PER_TAN_GREIGE);
            $row['received'] = (int) ($row['received'] ?? 0);
            $row['yarn_requirements'] = self::greigeYarnRequirements($row['sku'], $row['qty_meters']);
            $row['manual_stage'] = DemoState::effectivePoStage((int) $row['id'])
                ?: PurchaseOrderStages::normalizeGreigeManualStage($row['stage'] ?? null);
            $row['finish_date'] = $row['finish_date'] ?? $row['due_date'] ?? null;
        } else {
            $product = self::findProduct((int) ($row['product_id'] ?? 0));
            $row['product_id'] = (int) ($row['product_id'] ?? 0);
            $row['product'] = $product?->sku ?? '—';
            $row['sku'] = $product?->sku ?? '—';
            $row['unit'] = $product?->unit ?? '反';
            $row['qty_meters'] = (int) ($row['qty_meters'] ?? $row['qty'] ?? 0);
            $row['qty_tan'] = isset($row['qty_tan']) && (float) $row['qty_tan'] > 0
                ? QtyHelper::roundTan((float) $row['qty_tan'])
                : QtyHelper::tanCount($row['qty_meters'], (int) ($row['product_id'] ?? 0));
            if ($row['qty_meters'] <= 0 && $row['qty_tan'] > 0) {
                $row['qty_meters'] = QtyHelper::metersFromTan($row['qty_tan'], (int) ($row['product_id'] ?? 0));
            }
            $row['qty'] = $row['qty_meters'];
            $row['received'] = (int) ($row['received'] ?? 0);
            $row['manual_stage'] = DemoState::effectivePoStage((int) $row['id'])
                ?: PurchaseOrderStages::normalizeProductManualStage($row['stage'] ?? null);
        }

        $po = (object) $row;
        $row['stage'] = PurchaseOrderDisplay::label($po);
        $row['progress'] = PurchaseOrderDisplay::progressPercent($po);

        return (object) $row;
    }

    public static function expectedArrivalDate(object $po): string
    {
        return match ($po->type ?? PurchaseOrderType::PRODUCT) {
            PurchaseOrderType::PRODUCT, PurchaseOrderType::GREIGE => (string) (
                $po->finish_date ?? $po->due_date ?? $po->eta ?? ''
            ),
            default => (string) ($po->eta ?? $po->due_date ?? ''),
        };
    }

    public static function purchaseOrderOrderedQty(object $po): float
    {
        return match ($po->type ?? PurchaseOrderType::PRODUCT) {
            PurchaseOrderType::YARN => (float) ($po->qty_kg ?? $po->qty ?? 0),
            PurchaseOrderType::GREIGE => (float) ($po->qty_tan ?? QtyHelper::tanCount(
                (int) ($po->qty_meters ?? $po->qty ?? 0),
                null,
                true,
                (string) ($po->greige_sku ?? $po->sku ?? ''),
            )),
            default => (float) ($po->qty_tan ?? QtyHelper::tanCount(
                (int) ($po->qty_meters ?? $po->qty ?? 0),
                (int) ($po->product_id ?? 0),
            )),
        };
    }

    public static function purchaseOrderOrderedMeters(object $po): int
    {
        return match ($po->type ?? PurchaseOrderType::PRODUCT) {
            PurchaseOrderType::YARN => 0,
            PurchaseOrderType::GREIGE => (int) ($po->qty_meters ?? QtyHelper::metersFromTan(
                (float) ($po->qty_tan ?? 0),
                null,
                true,
                (string) ($po->greige_sku ?? $po->sku ?? ''),
            )),
            default => (int) ($po->qty_meters ?? QtyHelper::metersFromTan(
                (float) ($po->qty_tan ?? 0),
                (int) ($po->product_id ?? 0),
            )),
        };
    }

    public static function purchaseOrderReceivedQty(object $po): float
    {
        return match ($po->type ?? PurchaseOrderType::PRODUCT) {
            PurchaseOrderType::YARN => (float) ($po->received_kg ?? $po->received ?? 0),
            default => (float) (int) ($po->received ?? 0),
        };
    }

    private static function statusProgress(string $status): int
    {
        return match ($status) {
            PurchaseOrderStatus::DRAFT => 10,
            PurchaseOrderStatus::ORDERED => 40,
            PurchaseOrderStatus::PARTIAL => 70,
            PurchaseOrderStatus::RECEIVED => 100,
            PurchaseOrderStatus::CANCELLED => 0,
            default => 0,
        };
    }

    /** 出荷一覧 */
    public static function shipments(): Collection
    {
        if (self::usesShipmentDatabase()) {
            return \App\Models\Shipment::displayList();
        }

        $rows = [
            ['id' => 1, 'code' => 'SH-2606-001', 'order_code' => 'SO-2606-001', 'customer' => '東レ商事',        'product_id' => 1, 'qty' => 120, 'date' => '2026-06-11', 'due_date' => '2026-06-12', 'ship_to' => '東レ商事 滋賀倉庫',     'note' => '時間指定 午前中'],
            ['id' => 2, 'code' => 'SH-2606-002', 'order_code' => 'SO-2606-004', 'customer' => 'ユニフォーム製作所', 'product_id' => 2, 'qty' => 60,  'date' => '2026-06-12', 'due_date' => '2026-06-15', 'ship_to' => 'ユニフォーム製作所 本社', 'note' => ''],
            ['id' => 3, 'code' => 'SH-2606-003', 'order_code' => 'SO-2606-002', 'customer' => 'アパレル東京',    'product_id' => 3, 'qty' => 80,  'date' => '2026-06-14', 'due_date' => '2026-06-18', 'ship_to' => 'アパレル東京 物流センター', 'note' => '分納の1回目'],
            ['id' => 4, 'code' => 'SH-2606-004', 'order_code' => 'SO-2606-006', 'customer' => 'アパレル東京',    'product_id' => 1, 'qty' => 40,  'date' => '2026-06-15', 'due_date' => '2026-06-28', 'ship_to' => 'アパレル東京 物流センター', 'note' => ''],
        ];

        return collect($rows)->map(function ($r) {
            $product = self::findProduct($r['product_id']);
            $r['product'] = $product->sku;
            $r['sku'] = $product->sku;
            $r['color'] = $product->color;
            $r['unit'] = $product->unit;
            $r['price'] = $product->price;
            $r['amount'] = $product->price * $r['qty'];

            return (object) $r;
        });
    }

    /** 入荷の生データ（DemoState 参照なし） */
    public static function baseReceivingRows(): Collection
    {
        return collect([
            ['id' => 1, 'code' => 'RC-2606-001', 'po_code' => 'PO-2606-001', 'po_type' => PurchaseOrderType::PRODUCT, 'supplier' => '紡績ワークス', 'product_id' => 1, 'qty' => 200, 'date' => '2026-06-08'],
            ['id' => 2, 'code' => 'RC-2606-002', 'po_code' => 'PO-G-2606-002', 'po_type' => PurchaseOrderType::GREIGE, 'supplier' => '東洋織物', 'greige_sku' => 'KB-T', 'qty_meters' => 200, 'date' => '2026-06-18'],
            ['id' => 3, 'code' => 'RC-2606-003', 'po_code' => 'PO-2606-002', 'po_type' => PurchaseOrderType::PRODUCT, 'supplier' => 'ケミカル商会', 'product_id' => 3, 'qty' => 150, 'date' => '2026-06-14'],
            ['id' => 4, 'code' => 'RC-2606-004', 'po_code' => 'PO-2606-008', 'po_type' => PurchaseOrderType::PRODUCT, 'supplier' => '紡績ワークス', 'product_id' => 7, 'qty' => 120, 'date' => '2026-06-25'],
            ['id' => 5, 'code' => 'RC-2606-005', 'po_code' => 'PO-Y-2606-002', 'po_type' => PurchaseOrderType::YARN, 'supplier' => '紡績ワークス', 'material_id' => 2, 'qty_kg' => 150.0, 'date' => '2026-06-16'],
        ]);
    }

    /** 入荷一覧 */
    public static function receivings(): Collection
    {
        if (self::usesReceivingDatabase()) {
            return Receiving::displayList();
        }

        return self::baseReceivingRows()->map(function ($r) {
            $r['po_type'] = $r['po_type'] ?? PurchaseOrderType::PRODUCT;

            if ($r['po_type'] === PurchaseOrderType::YARN) {
                $material = self::findMaterial((int) $r['material_id']);
                $r['sku'] = $material->sku;
                $r['unit'] = 'kg';
                $r['qty'] = $r['qty_kg'];
            } elseif ($r['po_type'] === PurchaseOrderType::GREIGE) {
                $r['sku'] = $r['greige_sku'];
                $r['unit'] = '反';
                $r['qty'] = $r['qty_meters'];
            } else {
                $product = self::findProduct((int) $r['product_id']);
                $r['product'] = $product->sku;
                $r['sku'] = $product->sku;
                $r['unit'] = $product->unit;
            }

            return (object) $r;
        });
    }

    public static function usesReceivingDatabase(): bool
    {
        return self::cachedDatabaseUsage('receivings', fn () => Schema::hasTable('receivings')
            && Receiving::query()->exists());
    }

    public static function usesGreigeRollDatabase(): bool
    {
        return self::cachedDatabaseUsage('greige_rolls', fn () => Schema::hasTable('greige_rolls')
            && \App\Models\GreigeRoll::query()->exists());
    }

    public static function usesProductRollDatabase(): bool
    {
        return self::cachedDatabaseUsage('product_rolls', fn () => Schema::hasTable('product_rolls')
            && \App\Models\ProductRoll::query()->exists());
    }

    public static function usesShipmentDatabase(): bool
    {
        return self::cachedDatabaseUsage('shipments', fn () => Schema::hasTable('shipments')
            && \App\Models\Shipment::query()->exists());
    }

    public static function usesRecipeDatabase(): bool
    {
        return self::cachedDatabaseUsage('product_recipes', fn () => Schema::hasTable('product_recipes')
            && ProductRecipe::query()->exists());
    }

    public static function usesMaterialPriceDatabase(): bool
    {
        return self::cachedDatabaseUsage('material_prices', fn () => Schema::hasTable('material_prices')
            && MaterialPrice::query()->exists());
    }

    public static function usesYarnStockDatabase(): bool
    {
        return self::cachedDatabaseUsage('yarn_stock', fn () => Schema::hasTable('yarn_stock_movements')
            && Schema::hasTable('yarn_allocations')
            && YarnStockMovement::query()->exists());
    }

    /** 在庫移動履歴 */
    public static function stockMovements(): Collection
    {
        $rows = [
            ['date' => '2026-06-08', 'product_id' => 1, 'type' => '入庫', 'qty' => 200, 'note' => '入荷 RC-2606-001'],
            ['date' => '2026-06-11', 'product_id' => 1, 'type' => '出庫', 'qty' => 120, 'note' => '出荷 SH-2606-001'],
            ['date' => '2026-06-12', 'product_id' => 2, 'type' => '出庫', 'qty' => 60,  'note' => '出荷 SH-2606-002'],
            ['date' => '2026-06-14', 'product_id' => 3, 'type' => '入庫', 'qty' => 150, 'note' => '入荷 RC-2606-003'],
            ['date' => '2026-06-14', 'product_id' => 3, 'type' => '出庫', 'qty' => 80,  'note' => '出荷 SH-2606-003'],
            ['date' => '2026-06-15', 'product_id' => 1, 'type' => '出庫', 'qty' => 40,  'note' => '出荷 SH-2606-004'],
            ['date' => '2026-06-25', 'product_id' => 7, 'type' => '入庫', 'qty' => 120,  'note' => '入荷 RC-2606-004'],
        ];

        return collect($rows)->map(function ($r) {
            $product = self::findProduct($r['product_id']);
            $r['product'] = $product->sku;
            $r['sku'] = $product->sku;
            $r['unit'] = $product->unit;

            return (object) $r;
        })->sortByDesc('date')->values();
    }

    /** 進捗からステータス文字列を返す */
    private static function progressStatus(int $done, int $total, string $kind): string
    {
        $shipped = $kind === '受注';
        if ($done <= 0) {
            return $shipped ? '未出荷' : '未入荷';
        }
        if ($done < $total) {
            return $shipped ? '一部出荷' : '一部入荷';
        }

        return $shipped ? '出荷済み' : '入荷済み';
    }

    /** 売上・粗利画面で選べる対象月の一覧 */
    public static function salesMonthOptions(): Collection
    {
        $fromShipments = self::shipments()->map(fn ($s) => substr($s->date, 0, 7));
        $fromPrices = self::materialPrices()->pluck('ym');

        return $fromShipments->merge($fromPrices)
            ->unique()
            ->sortDesc()
            ->values();
    }

    /** 指定年月が売上画面の対象月として有効か */
    public static function isValidSalesMonth(string $ym): bool
    {
        return self::salesMonthOptions()->contains($ym);
    }

    /** 在庫予想画面で選べる対象月 */
    public static function forecastMonthOptions(): Collection
    {
        $base = self::salesMonthOptions();
        $current = self::CURRENT_YM;
        if (! $base->contains($current)) {
            $base = $base->prepend($current);
        }

        return $base->unique()->sortDesc()->values();
    }

    public static function isValidForecastMonth(string $ym): bool
    {
        return self::forecastMonthOptions()->contains($ym);
    }

    /**
     * 推移グラフ用の直近 N か月（古い順）。
     *
     * @return list<string>
     */
    public static function salesTrendMonths(string $endYm, int $count = 6): array
    {
        $end = \DateTimeImmutable::createFromFormat('Y-m', $endYm) ?: new \DateTimeImmutable(self::CURRENT_YM.'-01');
        $months = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $months[] = $end->modify("-{$i} months")->format('Y-m');
        }

        return $months;
    }

    /**
     * 出荷実績から月別の売上・製造コスト・粗利を集計する。
     *
     * @return Collection<int, object{ym: string, sales: int, cost: int, profit: int, has_uncalculable_cost: bool}>
     */
    public static function salesTrend(string $endYm, ?int $productId = null, int $months = 6): Collection
    {
        return collect(self::salesTrendMonths($endYm, $months))->map(function (string $ym) use ($productId) {
            $shipments = self::shipments()
                ->filter(fn ($s) => str_starts_with($s->date, $ym))
                ->when($productId !== null, fn ($rows) => $rows->where('product_id', $productId));

            $summary = self::summarizeShipmentSales($shipments, $ym);

            return (object) [
                'ym' => $ym,
                'sales' => $summary->sales,
                'cost' => $summary->cost,
                'profit' => $summary->profit,
                'has_uncalculable_cost' => $summary->has_uncalculable_cost,
            ];
        });
    }

    /**
     * 出荷コレクションを売上・コスト・粗利に集計する。
     *
     * @return object{sales: int, cost: int, profit: int, has_uncalculable_cost: bool}
     */
    public static function summarizeShipmentSales(Collection $shipments, string $ym): object
    {
        $sales = (int) round($shipments->sum('amount'));
        $cost = 0;
        $profit = 0;
        $hasUncalculableCost = false;

        foreach ($shipments->groupBy('product_id') as $productId => $group) {
            $unitCost = self::unitCost((int) $productId, $ym);
            if ($unitCost === null) {
                $hasUncalculableCost = true;

                continue;
            }

            $groupCost = (int) round($unitCost * $group->sum('qty'));
            $groupSales = (int) round($group->sum('amount'));
            $cost += $groupCost;
            $profit += $groupSales - $groupCost;
        }

        return (object) [
            'sales' => $sales,
            'cost' => $cost,
            'profit' => $profit,
            'has_uncalculable_cost' => $hasUncalculableCost,
        ];
    }

    /** 指定月の売上・製造コスト・粗利を商品別に集計 */
    public static function monthlySalesByProduct(?string $ym = null): Collection
    {
        $ym = $ym ?? self::CURRENT_YM;

        return self::shipments()
            ->filter(fn ($s) => str_starts_with($s->date, $ym))
            ->groupBy('product_id')
            ->map(function ($group) use ($ym) {
                $first = $group->first();
                $product = self::findProduct($first->product_id);
                $qty = $group->sum('qty');
                $sales = $group->sum('amount');
                $unitCost = self::unitCost($first->product_id, $ym);
                $cost = $unitCost !== null ? (int) round($unitCost * $qty) : null;

                return (object) [
                    'product_id' => $first->product_id,
                    'product' => $first->product,
                    'sku' => $first->sku,
                    'unit' => $first->unit,
                    'price' => (int) ($product->price ?? 0),
                    'qty' => $qty,
                    'sales' => (int) round($sales),
                    'cost' => $cost,
                    'profit' => $cost !== null ? (int) round($sales - $cost) : null,
                    'cost_calculable' => $unitCost !== null,
                ];
            })
            ->values();
    }

    /** ダッシュボード用のKPIなどをまとめて返す */
    public static function dashboard(): array
    {
        $salesByProduct = self::monthlySalesByProduct();
        $calculableRows = $salesByProduct->where('cost_calculable', true);

        $orders = self::orders();
        $purchaseOrders = self::purchaseOrders();
        $lowStock = self::products()->filter(fn ($p) => $p->stock < $p->stock_min)->values();
        $hasUncalculableCost = $salesByProduct->contains(fn ($row) => ! $row->cost_calculable);

        return [
            'sales' => $salesByProduct->sum('sales'),
            'shippedQty' => $salesByProduct->sum('qty'),
            'profit' => $calculableRows->sum('profit'),
            'cost' => $calculableRows->sum('cost'),
            'hasUncalculableCost' => $hasUncalculableCost,
            'costWarnings' => self::collectCostWarnings(
                $salesByProduct->where('cost_calculable', false)->pluck('product_id'),
                self::CURRENT_YM
            ),
            'unshippedOrders' => $orders->whereIn('status', ['未出荷', '一部出荷'])->count(),
            'unreceivedPurchaseOrders' => $purchaseOrders
                ->filter(fn ($po) => PurchaseOrderStatus::isActive($po->status))
                ->count(),
            'lowStock' => $lowStock,
            'salesByProduct' => $salesByProduct,
            // 売上・粗利の推移（過去6か月のデモ値）
            'trend' => collect([
                ['ym' => '2026-01', 'sales' => 980000,  'profit' => 320000],
                ['ym' => '2026-02', 'sales' => 1120000, 'profit' => 360000],
                ['ym' => '2026-03', 'sales' => 1040000, 'profit' => 335000],
                ['ym' => '2026-04', 'sales' => 1260000, 'profit' => 410000],
                ['ym' => '2026-05', 'sales' => 1180000, 'profit' => 388000],
                ['ym' => '2026-06', 'sales' => $salesByProduct->sum('sales'), 'profit' => $calculableRows->sum('profit')],
            ]),
        ];
    }
}
