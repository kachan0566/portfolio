<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['sku', 'name', 'category', 'unit', 'meters_per_tan', 'note'])]
class Greige extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'meters_per_tan' => 'integer',
        ];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return Collection<int, object> */
    public static function displayList(): Collection
    {
        return self::query()
            ->orderBy('id')
            ->get()
            ->map(fn (self $greige) => $greige->toDisplayObject());
    }

    public function toDisplayObject(): object
    {
        return (object) [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'meters_per_tan' => $this->meters_per_tan,
        ];
    }

    public static function findBySku(string $sku): ?self
    {
        return self::query()->where('sku', $sku)->first();
    }

    public static function findDisplayBySku(string $sku): ?object
    {
        return self::findBySku($sku)?->toDisplayObject();
    }

    public static function findByProductId(int $productId): ?self
    {
        $product = Product::query()->find($productId);
        if ($product === null || $product->greige_id === null) {
            return null;
        }

        return self::query()->find($product->greige_id);
    }

    public static function findDisplayByProductId(int $productId): ?object
    {
        return self::findByProductId($productId)?->toDisplayObject();
    }
}
