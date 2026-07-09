<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'product_id',
    'qty_tan',
    'qty_meters',
    'received_qty_tan',
    'received_qty_m',
    'stage',
    'finish_date',
    'contact_date',
])]
class ProductPurchaseOrder extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'purchase_order_id';

    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'product_id' => 'integer',
            'qty_tan' => 'integer',
            'qty_meters' => 'integer',
            'received_qty_tan' => 'decimal:2',
            'received_qty_m' => 'integer',
            'finish_date' => 'date',
            'contact_date' => 'date',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
