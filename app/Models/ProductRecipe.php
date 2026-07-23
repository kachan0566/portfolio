<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

#[Fillable(['product_id', 'processing_cost'])]
class ProductRecipe extends Model
{
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'processing_cost' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function existsForProduct(int $productId): bool
    {
        if (self::usesDatabase()) {
            return self::query()->where('product_id', $productId)->exists();
        }

        return \App\Support\DemoData::hasRecipe($productId);
    }

    private static function usesDatabase(): bool
    {
        try {
            return Schema::hasTable('product_recipes')
                && self::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
