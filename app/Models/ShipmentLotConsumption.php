<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentLotConsumption extends Model
{
    protected $fillable = [
        'shipment_ref',
        'inbound_lot_id',
        'consumed_qty_m',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'consumed_qty_m' => 'decimal:2',
        ];
    }

    public function inboundLot(): BelongsTo
    {
        return $this->belongsTo(InventoryInboundLot::class, 'inbound_lot_id');
    }
}
