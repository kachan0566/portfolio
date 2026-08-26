<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AllocationConversion extends Model
{
    public const FROM_PO = 'po';

    public const TO_STOCK = 'stock';

    protected $fillable = [
        'converted_at',
        'receiving_code',
        'purchase_order_id',
        'order_id',
        'qty',
        'from_type',
        'to_type',
    ];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'purchase_order_id' => 'integer',
            'order_id' => 'integer',
            'qty' => 'integer',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Blade が期待する配列形式（旧 JSON 互換）
     *
     * @return array{id: int, at: string, receiving_code: string, po_id: int, order_id: int, qty: int, from_type: string, to_type: string}
     */
    public function toEventArray(): array
    {
        return [
            'id' => $this->id,
            'at' => $this->converted_at?->toIso8601String() ?? '',
            'receiving_code' => $this->receiving_code,
            'po_id' => $this->purchase_order_id,
            'order_id' => $this->order_id,
            'qty' => $this->qty,
            'from_type' => $this->from_type,
            'to_type' => $this->to_type,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function eventsForOrder(int $orderId): array
    {
        return self::query()
            ->where('order_id', $orderId)
            ->orderBy('converted_at')
            ->orderBy('id')
            ->get()
            ->map(fn (self $row) => $row->toEventArray())
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public static function eventsForProduct(int $productId): array
    {
        $poIds = DB::table('purchase_order_lines')
            ->where('product_id', $productId)
            ->distinct()
            ->pluck('purchase_order_id')
            ->all();

        if ($poIds === []) {
            return [];
        }

        return self::query()
            ->whereIn('purchase_order_id', $poIds)
            ->orderBy('converted_at')
            ->orderBy('id')
            ->get()
            ->map(fn (self $row) => $row->toEventArray())
            ->all();
    }

    /**
     * @param  array{receiving_code: string, po_id: int, order_id: int, qty: int}  $event
     */
    public static function recordEvent(array $event): void
    {
        self::query()->create([
            'converted_at' => now(),
            'receiving_code' => $event['receiving_code'],
            'purchase_order_id' => (int) $event['po_id'],
            'order_id' => (int) $event['order_id'],
            'qty' => (int) $event['qty'],
            'from_type' => self::FROM_PO,
            'to_type' => self::TO_STOCK,
        ]);
    }
}
