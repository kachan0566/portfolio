<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentPlanRecord extends Model
{
    protected $table = 'shipment_plans';

    protected $fillable = [
        'code',
        'order_id',
        'product_id',
        'planned_ship_date',
        'confirmed_qty_m',
        'shipped_qty_m',
        'status',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_ship_date' => 'date',
            'confirmed_qty_m' => 'decimal:2',
            'shipped_qty_m' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
