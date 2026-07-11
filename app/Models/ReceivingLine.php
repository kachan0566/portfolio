<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'receiving_id',
    'purchase_order_line_id',
    'line_no',
    'qty_tan',
    'qty_m',
    'qty_kg',
])]
class ReceivingLine extends Model
{
    protected function casts(): array
    {
        return [
            'receiving_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'line_no' => 'integer',
            'qty_tan' => 'decimal:2',
            'qty_m' => 'integer',
            'qty_kg' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<Receiving, $this> */
    public function receiving(): BelongsTo
    {
        return $this->belongsTo(Receiving::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return HasMany<GreigeRoll, $this> */
    public function greigeRolls(): HasMany
    {
        return $this->hasMany(GreigeRoll::class);
    }

    /** @return HasMany<ProductRoll, $this> */
    public function productRolls(): HasMany
    {
        return $this->hasMany(ProductRoll::class);
    }
}
