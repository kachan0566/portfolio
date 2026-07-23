<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

#[Fillable(['greige_id', 'loss_rate', 'weaving_cost'])]
class GreigeRecipe extends Model
{
    protected function casts(): array
    {
        return [
            'greige_id' => 'integer',
            'loss_rate' => 'decimal:4',
            'weaving_cost' => 'integer',
        ];
    }

    /** @return BelongsTo<Greige, $this> */
    public function greige(): BelongsTo
    {
        return $this->belongsTo(Greige::class);
    }

    /** @return HasMany<GreigeRecipeLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(GreigeRecipeLine::class);
    }

    public static function existsForSku(string $sku): bool
    {
        if (self::usesDatabase()) {
            return self::query()
                ->whereHas('greige', fn ($query) => $query->where('sku', $sku))
                ->exists();
        }

        return \App\Support\DemoData::hasGreigeRecipe($sku);
    }

    private static function usesDatabase(): bool
    {
        try {
            return Schema::hasTable('greige_recipes')
                && self::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
