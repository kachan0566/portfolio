<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_order_id', 'material_id', 'qty_kg', 'received_qty_kg'])]
class YarnPurchaseOrder extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'purchase_order_id';

    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'material_id' => 'integer',
            'qty_kg' => 'decimal:3',
            'received_qty_kg' => 'decimal:3',
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
}
