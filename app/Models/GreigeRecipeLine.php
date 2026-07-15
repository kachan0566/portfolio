<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['greige_recipe_id', 'material_id', 'qty_per_m'])]
class GreigeRecipeLine extends Model
{
    protected function casts(): array
    {
        return [
            'greige_recipe_id' => 'integer',
            'material_id' => 'integer',
            'qty_per_m' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<GreigeRecipe, $this> */
    public function greigeRecipe(): BelongsTo
    {
        return $this->belongsTo(GreigeRecipe::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
