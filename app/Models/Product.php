<?php

namespace App\Models;

use App\Support\DemoState;
use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable(['greige_id', 'name', 'sku', 'color', 'price', 'category', 'unit', 'meters_per_tan', 'stock_min_m'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'greige_id' => 'integer',
            'price' => 'integer',
            'meters_per_tan' => 'integer',
            'stock_min_m' => 'integer',
        ];
    }

    /** @return BelongsTo<Greige, $this> */
    public function greige(): BelongsTo
    {
        return $this->belongsTo(Greige::class);
    }

    /** @return Collection<int, object> */
    public static function displayCatalog(): Collection
    {
        return self::query()
            ->with('greige')
            ->orderBy('id')
            ->get()
            ->map(fn (self $product) => $product->toDisplayObject());
    }

    public function toDisplayObject(): object
    {
        $perTan = $this->meters_per_tan;
        $stockTan = QtyHelper::roundTan((float) DemoState::effectiveStock($this->id));
        $stock = $perTan > 0
            ? (int) round($stockTan * $perTan)
            : 0;

        return (object) [
            'id' => $this->id,
            'sku' => $this->sku,
            'greige_sku' => $this->greige?->sku,
            'greige_name' => $this->greige?->name,
            'color' => $this->color,
            'price' => $this->price,
            'category' => $this->category,
            'unit' => $this->unit,
            'meters_per_tan' => $perTan,
            'stock' => $stock,
            'stock_tan' => $stockTan,
            'stock_min' => $this->stock_min_m,
            'stock_min_tan' => $perTan > 0
                ? round($this->stock_min_m / $perTan, QtyHelper::TAN_DECIMALS)
                : 0.0,
        ];
    }
}
