<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code',
    'product_id',
    'purchase_order_id',
    'receiving_line_id',
    'parent_greige_roll_id',
    'tan_qty',
    'actual_qty_m',
    'nominal_meters',
    'status',
    'received_date',
])]
class ProductRoll extends Model
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_SHIPPED = 'shipped';

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'purchase_order_id' => 'integer',
            'receiving_line_id' => 'integer',
            'parent_greige_roll_id' => 'integer',
            'tan_qty' => 'decimal:2',
            'actual_qty_m' => 'decimal:2',
            'nominal_meters' => 'integer',
            'received_date' => 'date',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<ReceivingLine, $this> */
    public function receivingLine(): BelongsTo
    {
        return $this->belongsTo(ReceivingLine::class);
    }

    /** @return BelongsTo<GreigeRoll, $this> */
    public function parentGreigeRoll(): BelongsTo
    {
        return $this->belongsTo(GreigeRoll::class, 'parent_greige_roll_id');
    }

    /** @return array<string, mixed> */
    public function toSupportArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'product_id' => (int) $this->product_id,
            'parent_greige_roll_id' => $this->parent_greige_roll_id,
            'purchase_order_id' => $this->purchase_order_id,
            'receiving_id' => $this->receivingLine?->receiving_id,
            'tan_qty' => (float) $this->tan_qty,
            'actual_qty_m' => (float) $this->actual_qty_m,
            'nominal_meters' => (int) ($this->nominal_meters ?? 0),
            'status' => (string) $this->status,
            'received_date' => $this->received_date?->toDateString() ?? '',
        ];
    }
}
