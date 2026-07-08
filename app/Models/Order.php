<?php

namespace App\Models;

use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\FabricQuantity;
use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'code',
    'customer_id',
    'product_id',
    'order_qty_mode',
    'qty_tan',
    'qty_meters',
    'shipped_qty_tan',
    'shipped_qty_m',
    'order_date',
    'due_date',
    'planned_ship_date',
    'ship_memo',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'product_id' => 'integer',
            'qty_tan' => 'integer',
            'qty_meters' => 'integer',
            'shipped_qty_tan' => 'decimal:2',
            'shipped_qty_m' => 'integer',
            'order_date' => 'date',
            'due_date' => 'date',
            'planned_ship_date' => 'date',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<OrderAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(OrderAllocation::class);
    }

    /** @return Collection<int, object> */
    public static function displayList(): Collection
    {
        return self::query()
            ->with(['customer', 'product'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (self $order) => $order->toDisplayObject());
    }

    public static function findForDisplay(int $id): ?object
    {
        $order = self::query()->with(['customer', 'product'])->find($id);

        return $order?->toDisplayObject();
    }

    public function metersOverridden(): bool
    {
        if (($this->order_qty_mode ?? 'tan') === 'meters') {
            return true;
        }

        if ($this->qty_tan <= 0 || $this->qty_meters <= 0) {
            return false;
        }

        $nominal = QtyHelper::metersFromTan($this->qty_tan, (int) $this->product_id);

        return $this->qty_meters !== $nominal;
    }

    public function toDisplayObject(): object
    {
        $product = $this->product;
        $mode = $this->order_qty_mode ?? 'tan';

        $qtyTan = $mode === 'tan'
            ? QtyHelper::roundIntegerTan((float) $this->qty_tan)
            : FabricQuantity::tanFromRecord(
                ['qty_tan' => $this->qty_tan, 'qty_meters' => $this->qty_meters],
                (int) $this->product_id,
            );

        $shippedTan = FabricQuantity::tanFromRecord(
            ['qty_tan' => $this->shipped_qty_tan, 'qty' => $this->shipped_qty_m],
            (int) $this->product_id,
        );

        $qtyMeters = $mode === 'meters'
            ? (int) $this->qty_meters
            : FabricQuantity::metersFromRecord(
                ['qty_tan' => $qtyTan, 'qty_meters' => $this->qty_meters],
                (int) $this->product_id,
            );

        $shippedMeters = DemoState::effectiveShippedM((int) $this->id);

        $statusInput = [
            'id' => $this->id,
            'order_qty_mode' => $mode,
            'qty_tan' => $qtyTan,
            'qty_meters' => $qtyMeters,
            'qty' => $qtyMeters,
        ];

        return (object) [
            'id' => $this->id,
            'code' => $this->code,
            'customer' => $this->customer?->name ?? '—',
            'customer_id' => $this->customer_id,
            'product_id' => $this->product_id,
            'product' => $product?->sku,
            'sku' => $product?->sku,
            'color' => $product?->color,
            'unit' => $product?->unit,
            'order_qty_mode' => $mode,
            'qty_tan' => $qtyTan,
            'shipped_tan' => $shippedTan,
            'qty_meters' => $qtyMeters,
            'shipped_meters' => $shippedMeters,
            'qty' => $qtyMeters,
            'shipped' => $shippedMeters,
            'meters_overridden' => $this->metersOverridden(),
            'order_date' => $this->order_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'planned_ship_date' => $this->planned_ship_date?->toDateString(),
            'ship_memo' => $this->ship_memo,
            'status' => DemoData::orderProgressStatus($statusInput),
            'is_new_today' => $this->order_date?->toDateString() === DemoData::today(),
        ];
    }
}
