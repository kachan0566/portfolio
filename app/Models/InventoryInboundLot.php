<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryInboundLot extends Model
{
    protected $table = 'inbound_lots';

    protected $fillable = [
        'product_id',
        'receiving_code',
        'received_date',
        'received_qty_m',
        'remaining_qty_m',
        'purchase_order_id',
        'source_type',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'received_qty_m' => 'decimal:2',
            'remaining_qty_m' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ShipmentLotConsumption::class, 'inbound_lot_id');
    }
}
