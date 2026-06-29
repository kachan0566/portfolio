<?php

namespace App\Support;

/**
 * デモ用のセッションオーバーレイ（糸価格・レシピの登録・更新を画面に反映する）。
 */
class DemoOverlay
{
    private const YARN_PRICE_ADDITIONS = 'demo.yarn_price_additions';

    private const YARN_PRICE_UPDATES = 'demo.yarn_price_updates';

    private const RECIPES = 'demo.recipes';

    private const GREIGE_RECIPES = 'demo.greige_recipes';

    private const PRODUCT_PRICES = 'demo.product_prices';

    /** @return list<array{material_id: int, ym: string, price: int}> */
    public static function yarnPriceAdditions(): array
    {
        return session(self::YARN_PRICE_ADDITIONS, []);
    }

    public static function addYarnPrice(int $materialId, string $ym, int $price): void
    {
        $additions = self::yarnPriceAdditions();
        $additions[] = [
            'material_id' => $materialId,
            'ym' => $ym,
            'price' => $price,
        ];
        session([self::YARN_PRICE_ADDITIONS => $additions]);
    }

    /** @return array<string, int> material_id|ym => price */
    public static function yarnPriceUpdates(): array
    {
        return session(self::YARN_PRICE_UPDATES, []);
    }

    public static function updateYarnPrice(int $materialId, string $ym, int $price): void
    {
        $updates = self::yarnPriceUpdates();
        $updates[self::yarnPriceKey($materialId, $ym)] = $price;
        session([self::YARN_PRICE_UPDATES => $updates]);
    }

    public static function yarnPriceKey(int $materialId, string $ym): string
    {
        return $materialId.'|'.$ym;
    }

    /** @return array<int, array{processing_cost: int}> */
    public static function recipeOverrides(): array
    {
        return session(self::RECIPES, []);
    }

    /** @param array{processing_cost: int} $data */
    public static function saveRecipe(int $productId, array $data): void
    {
        $recipes = self::recipeOverrides();
        $recipes[$productId] = $data;
        session([self::RECIPES => $recipes]);
    }

    /** @return array<string, array{lines: list<array{0: int, 1: float}>, loss_rate: float, weaving_cost: int}> */
    public static function greigeRecipeOverrides(): array
    {
        return session(self::GREIGE_RECIPES, []);
    }

    /** @param array{lines: list<array{0: int, 1: float}>, loss_rate: float, weaving_cost: int} $data */
    public static function saveGreigeRecipe(string $greigeSku, array $data): void
    {
        $recipes = self::greigeRecipeOverrides();
        $recipes[$greigeSku] = $data;
        session([self::GREIGE_RECIPES => $recipes]);
    }

    /** @return array<int, int> product_id => price */
    public static function productPriceUpdates(): array
    {
        return session(self::PRODUCT_PRICES, []);
    }

    public static function saveProductPrice(int $productId, int $price): void
    {
        $updates = self::productPriceUpdates();
        $updates[$productId] = $price;
        session([self::PRODUCT_PRICES => $updates]);
    }

    public static function clear(): void
    {
        session()->forget([
            self::YARN_PRICE_ADDITIONS,
            self::YARN_PRICE_UPDATES,
            self::RECIPES,
            self::GREIGE_RECIPES,
            self::PRODUCT_PRICES,
        ]);
    }
}
