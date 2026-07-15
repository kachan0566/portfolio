<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['material_id', 'ym', 'unit_price'])]
class MaterialPrice extends Model
{
    protected function casts(): array
    {
        return [
            'material_id' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
