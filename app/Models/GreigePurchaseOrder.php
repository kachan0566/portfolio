<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'greige_id',
    'qty_tan',
    'meters_per_tan',
    'qty_meters',
    'received_qty_tan',
    'received_qty_m',
    'stage',
])]
class GreigePurchaseOrder extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'purchase_order_id';

    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'greige_id' => 'integer',
            'qty_tan' => 'integer',
            'meters_per_tan' => 'integer',
            'qty_meters' => 'integer',
            'received_qty_tan' => 'decimal:2',
            'received_qty_m' => 'integer',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Greige, $this> */
    public function greige(): BelongsTo
    {
        return $this->belongsTo(Greige::class);
    }
}
