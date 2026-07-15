<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'material_id',
    'movement_type',
    'qty_kg',
    'reference_type',
    'reference_id',
    'movement_date',
    'note',
])]
class YarnStockMovement extends Model
{
    protected function casts(): array
    {
        return [
            'material_id' => 'integer',
            'qty_kg' => 'decimal:3',
            'reference_id' => 'integer',
            'movement_date' => 'date',
        ];
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
