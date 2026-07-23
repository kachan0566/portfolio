<?php

namespace App\Support;

use App\Models\Greige;
use App\Models\Material;
use App\Models\Product;
use App\Models\ShipTo;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * マスタ参照の統一窓口。DB にデータがあれば Eloquent、なければ DemoData の固定配列へフォールバックする。
 */
class MasterCatalog
{
    /** @return Collection<int, object> */
    public static function products(): Collection
    {
        return self::tableHasRows('products')
            ? Product::displayList()
            : DemoData::products();
    }

    public static function findProduct(int $id): ?object
    {
        if (self::tableHasRows('products')) {
            return Product::findDisplay($id);
        }

        return DemoData::products()->firstWhere('id', $id);
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
        return self::tableHasRows('greiges')
            ? Greige::displayList()
            : DemoData::greiges();
    }

    public static function findGreige(string $sku): ?object
    {
        if (self::tableHasRows('greiges')) {
            return Greige::findDisplayBySku($sku);
        }

        return DemoData::greiges()->firstWhere('sku', $sku);
    }

    public static function findGreigeByProductId(int $productId): ?object
    {
        if (self::tableHasRows('greiges')) {
            return Greige::findDisplayByProductId($productId);
        }

        return DemoData::findGreigeByProductId($productId);
    }

    /** @return Collection<int, object> */
    public static function yarnMaterials(): Collection
    {
        return self::tableHasRows('materials')
            ? Material::yarnMaterials()
            : DemoData::materials()->where('type', Material::TYPE_YARN)->values();
    }

    public static function findMaterial(int $id): ?object
    {
        if (self::tableHasRows('materials')) {
            return Material::findDisplay($id);
        }

        return DemoData::materials()->firstWhere('id', $id);
    }

    /** @return Collection<int, object{id: int, name: string}> */
    public static function categoryOptions(): Collection
    {
        return self::tableHasRows('products')
            ? Product::categoryOptions()
            : DemoData::categories();
    }

    public static function findSupplier(int $id): ?object
    {
        if (self::tableHasRows('suppliers')) {
            return Supplier::query()->find($id);
        }

        return DemoData::suppliers()->firstWhere('id', $id);
    }

    public static function findShipTo(int $id): ?object
    {
        if (self::tableHasRows('ship_tos')) {
            return ShipTo::query()->find($id);
        }

        return DemoData::shipTos()->firstWhere('id', $id);
    }

    private static function tableHasRows(string $table): bool
    {
        try {
            return Schema::hasTable($table)
                && \Illuminate\Support\Facades\DB::table($table)->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
