<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'line_no',
    'material_id',
    'greige_id',
    'product_id',
    'qty_kg',
    'received_qty_kg',
    'qty_tan',
    'meters_per_tan',
    'qty_meters',
    'received_qty_tan',
    'received_qty_m',
    'stage',
    'finish_date',
    'contact_date',
])]
class PurchaseOrderLine extends Model
{
    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'line_no' => 'integer',
            'material_id' => 'integer',
            'greige_id' => 'integer',
            'product_id' => 'integer',
            'qty_kg' => 'decimal:3',
            'received_qty_kg' => 'decimal:3',
            'qty_tan' => 'integer',
            'meters_per_tan' => 'integer',
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

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<Greige, $this> */
    public function greige(): BelongsTo
    {
        return $this->belongsTo(Greige::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
