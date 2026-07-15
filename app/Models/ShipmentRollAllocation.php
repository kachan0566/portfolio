<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shipment_id',
    'product_roll_id',
    'consumed_tan_qty',
    'consumed_qty_m',
    'note',
])]
class ShipmentRollAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'shipment_id' => 'integer',
            'product_roll_id' => 'integer',
            'consumed_tan_qty' => 'decimal:2',
            'consumed_qty_m' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<ProductRoll, $this> */
    public function productRoll(): BelongsTo
    {
        return $this->belongsTo(ProductRoll::class);
    }
}
