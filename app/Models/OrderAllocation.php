<?php

namespace App\Models;

use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * 受注に対する現在庫・発注引当を、0.25反と換算mで保持する。
 */
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

    /**
     * 0.25反と小数mを失わず読み書きする型変換を定義する。
     *
     * @return array<string, string> Eloquentの列別キャスト
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'purchase_order_id' => 'integer',
            'qty_tan' => 'decimal:2',
            'qty_m' => 'decimal:2',
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
     * @param  list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}>  $lines
     */
    public static function syncAll(array $lines): void
    {
        self::query()->delete();
        self::insertLines($lines);
    }

    /**
     * @param  list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}>  $lines
     */
    public static function replaceForProduct(int $productId, array $lines): void
    {
        self::query()->where('product_id', $productId)->delete();
        self::insertLines($lines);
    }

    public static function deleteForOrder(int $orderId): void
    {
        self::query()->where('order_id', $orderId)->delete();
    }

    public static function deleteLine(int $orderId, int $poId, string $type): void
    {
        $query = self::query()
            ->where('order_id', $orderId)
            ->where('allocation_type', $type);

        if ($poId > 0) {
            $query->where('purchase_order_id', $poId);
        } else {
            $query->whereNull('purchase_order_id');
        }

        $query->delete();
    }

    /**
     * 同じ受注・品番・発注元・区分の引当へ0.25反数量を加算する。
     *
     * @param  array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}  $line
     * @return void DBへ保存するため戻り値は使わない
     */
    public static function upsertLine(array $line): void
    {
        $productId = (int) ($line['product_id'] ?? 0);
        $orderId = (int) ($line['order_id'] ?? 0);
        $poId = (int) ($line['po_id'] ?? 0);
        $type = (string) ($line['type'] ?? self::TYPE_STOCK);
        $qtyTan = QtyHelper::roundTan((float) ($line['qty_tan'] ?? 0));

        if ($productId <= 0 || $orderId <= 0 || $qtyTan <= 0) {
            return;
        }

        $query = self::query()
            ->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->where('allocation_type', $type);

        if ($poId > 0) {
            $query->where('purchase_order_id', $poId);
        } else {
            $query->whereNull('purchase_order_id');
        }

        $existing = $query->first();

        if ($existing !== null) {
            $newTan = QtyHelper::roundTan((float) $existing->qty_tan + $qtyTan);
            $incrementM = round(
                (float) ($line['qty'] ?? QtyHelper::metersFromTan($qtyTan, $productId)),
                2
            );
            $existing->update([
                'qty_tan' => $newTan,
                'qty_m' => round((float) $existing->qty_m + $incrementM, 2),
            ]);

            return;
        }

        self::query()->create([
            'order_id' => $orderId,
            'product_id' => $productId,
            'purchase_order_id' => $poId > 0 ? $poId : null,
            'allocation_type' => $type,
            'qty_tan' => $qtyTan,
            'qty_m' => round((float) ($line['qty'] ?? QtyHelper::metersFromTan($qtyTan, $productId)), 2),
        ]);
    }

    /**
     * 引当行を0.25反と小数mを維持してまとめて登録する。
     *
     * @param  list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}>  $lines
     * @return void DBへ保存するため戻り値は使わない
     */
    private static function insertLines(array $lines): void
    {
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
                'qty_m' => round((float) ($line['qty'] ?? QtyHelper::metersFromTan($qtyTan, $productId)), 2),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * 引当計算で使用する小数数量付きの共通オブジェクトへ変換する。
     *
     * @return object{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}
     */
    public function toStockLineObject(): object
    {
        return (object) [
            'product_id' => (int) $this->product_id,
            'order_id' => (int) $this->order_id,
            'po_id' => (int) ($this->purchase_order_id ?? 0),
            'qty_tan' => (float) $this->qty_tan,
            'qty' => (float) $this->qty_m,
            'type' => (string) $this->allocation_type,
        ];
    }
}
