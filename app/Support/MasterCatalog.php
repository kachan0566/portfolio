<?php

namespace App\Support;

use App\Models\Greige;
use App\Models\Material;
use App\Models\Product;
use App\Models\ShipTo;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * マスタ参照の統一窓口。実行時の正本は DB（Eloquent）のみ。
 * シード用の固定配列は DemoData にあり、ここからは参照しない。
 */
class MasterCatalog
{
    /** @return Collection<int, object> */
    public static function products(): Collection
    {
        return Product::displayList();
    }

    public static function findProduct(int $id): ?object
    {
        return Product::findDisplay($id);
    }

    public static function findProductOrFail(int $id): object
    {
        $product = self::findProduct($id);
        if ($product === null) {
            abort(404);
        }

        return $product;
    }

    /** @return Collection<int, object> */
    public static function greiges(): Collection
    {
        return Greige::displayList();
    }

    public static function findGreige(string $sku): ?object
    {
        return Greige::findDisplayBySku($sku);
    }

    public static function findGreigeByProductId(int $productId): ?object
    {
        return Greige::findDisplayByProductId($productId);
    }

    /** @return Collection<int, object> */
    public static function yarnMaterials(): Collection
    {
        return Material::yarnMaterials();
    }

    public static function findMaterial(int $id): ?object
    {
        return Material::findDisplay($id);
    }

    /**
     * 製品カテゴリの選択肢（products.category の DISTINCT）。
     * 専用マスタテーブルはなく、シード未投入時は空コレクション。
     *
     * @return Collection<int, object{id: int, name: string}>
     */
    public static function categoryOptions(): Collection
    {
        return Product::categoryOptions();
    }

    public static function findSupplier(int $id): ?object
    {
        return Supplier::query()->find($id);
    }

    public static function findShipTo(int $id): ?object
    {
        return ShipTo::query()->find($id);
    }
}
