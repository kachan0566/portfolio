<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'code',
    'order_id',
    'product_id',
    'qty_tan',
    'qty_m',
    'shipped_date',
    'ship_to_name',
    'note',
])]
class Shipment extends Model
{
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'qty_tan' => 'decimal:2',
            'qty_m' => 'integer',
            'shipped_date' => 'date',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ShipmentRollAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ShipmentRollAllocation::class);
    }

    /** @return Collection<int, object> */
    public static function displayList(): Collection
    {
        return self::query()
            ->with(['order.customer', 'product'])
            ->orderByDesc('shipped_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (self $shipment) => $shipment->toDisplayObject());
    }

    public function toDisplayObject(): object
    {
        $product = $this->product;
        $order = $this->order;
        $qtyM = (int) $this->qty_m;
        $price = (int) ($product?->price ?? 0);

        return (object) [
            'id' => $this->id,
            'code' => $this->code,
            'order_id' => $this->order_id,
            'order_code' => $order?->code ?? '—',
            'customer' => $order?->customer?->name ?? '—',
            'product_id' => $this->product_id,
            'product' => $product?->sku ?? '—',
            'sku' => $product?->sku ?? '—',
            'color' => $product?->color ?? '—',
            'unit' => $product?->unit ?? '反',
            'qty' => $qtyM,
            'qty_tan' => (float) $this->qty_tan,
            'date' => $this->shipped_date?->toDateString(),
            'due_date' => $order?->due_date?->toDateString(),
            'ship_to' => (string) ($this->ship_to_name ?? ''),
            'note' => (string) ($this->note ?? ''),
            'price' => $price,
            'amount' => $price * $qtyM,
        ];
    }
}
