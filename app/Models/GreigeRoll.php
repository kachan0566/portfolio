<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code',
    'greige_id',
    'purchase_order_id',
    'receiving_line_id',
    'tan_qty',
    'actual_qty_m',
    'nominal_meters',
    'status',
    'received_date',
])]
class GreigeRoll extends Model
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_PARTIALLY_CONSUMED = 'partially_consumed';

    public const STATUS_CONSUMED = 'consumed';

    protected function casts(): array
    {
        return [
            'greige_id' => 'integer',
            'purchase_order_id' => 'integer',
            'receiving_line_id' => 'integer',
            'tan_qty' => 'decimal:2',
            'actual_qty_m' => 'decimal:2',
            'nominal_meters' => 'integer',
            'received_date' => 'date',
        ];
    }

    /** @return BelongsTo<Greige, $this> */
    public function greige(): BelongsTo
    {
        return $this->belongsTo(Greige::class);
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

    /** @return array<string, mixed> */
    public function toSupportArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'greige_sku' => $this->greige?->sku ?? '',
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
