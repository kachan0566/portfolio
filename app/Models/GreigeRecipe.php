<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
