<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * 引当の PO → 在庫 変換イベントを記録するモデル
 */
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

    /**
     * @return array<string, string>
     */
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

    /**
     * 指定受注の変換イベント一覧を取得する
     *
     * @param  int  $orderId  受注 ID
     * @return list<array<string, mixed>>
     */
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

    /**
     * 指定製品に紐づく PO の変換イベント一覧を取得する
     *
     * @param  int  $productId  製品 ID
     * @return list<array<string, mixed>>
     */
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
     * PO 引当から在庫引当への変換イベントを 1 件記録する
     *
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
