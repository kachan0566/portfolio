<?php

namespace App\Models;

use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable([
    'order_id',
    'product_id',
    'purchase_order_id',
    'allocation_type',
    'qty_tan',
    'qty_m',
])]
class OrderAllocation extends Model
{
    public const TYPE_STOCK = 'stock';

    public const TYPE_PO = 'po';

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'purchase_order_id' => 'integer',
            'qty_tan' => 'decimal:2',
            'qty_m' => 'integer',
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

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return Collection<int, object> */
    public static function linesForProduct(int $productId): Collection
    {
        return self::query()
            ->where('product_id', $productId)
            ->orderBy('order_id')
            ->orderBy('id')
            ->get()
            ->map(fn (self $row) => $row->toStockLineObject());
    }

    /**
     * @param  list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: int, type: string}>  $lines
     */
    public static function syncAll(array $lines): void
    {
        self::query()->delete();

        $now = now();
        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $qtyTan = QtyHelper::roundTan((float) ($line['qty_tan'] ?? 0));
            if ($productId <= 0 || $qtyTan <= 0) {
                continue;
            }

            $poId = (int) ($line['po_id'] ?? 0);

            self::query()->create([
                'order_id' => (int) ($line['order_id'] ?? 0),
                'product_id' => $productId,
                'purchase_order_id' => $poId > 0 ? $poId : null,
                'allocation_type' => (string) ($line['type'] ?? self::TYPE_STOCK),
                'qty_tan' => $qtyTan,
                'qty_m' => (int) ($line['qty'] ?? QtyHelper::metersFromTan($qtyTan, $productId)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function toStockLineObject(): object
    {
        return (object) [
            'product_id' => (int) $this->product_id,
            'order_id' => (int) $this->order_id,
            'po_id' => (int) ($this->purchase_order_id ?? 0),
            'qty_tan' => (float) $this->qty_tan,
            'qty' => (int) $this->qty_m,
            'type' => (string) $this->allocation_type,
        ];
    }
}
