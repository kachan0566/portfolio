<?php

namespace App\Models;

use App\Support\FabricQuantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentPlanRecord extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'shipment_plans';

    protected $fillable = [
        'code',
        'order_id',
        'product_id',
        'planned_ship_date',
        'confirmed_qty_m',
        'confirmed_qty_tan',
        'shipped_qty_m',
        'shipped_qty_tan',
        'status',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'planned_ship_date' => 'date',
            'confirmed_qty_m' => 'decimal:2',
            'confirmed_qty_tan' => 'decimal:2',
            'shipped_qty_m' => 'decimal:2',
            'shipped_qty_tan' => 'decimal:2',
            'created_by' => 'integer',
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function unshippedQtyM(): float
    {
        return max(0.0, round((float) $this->confirmed_qty_m - (float) $this->shipped_qty_m, 2));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMED => '出荷確定',
            self::STATUS_PARTIAL => '一部出荷',
            self::STATUS_COMPLETED => '出荷完了',
            self::STATUS_CANCELLED => 'キャンセル',
            default => (string) $this->status,
        };
    }

    public function toDisplayObject(): object
    {
        return (object) [
            'id' => $this->id,
            'code' => $this->code,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'planned_ship_date' => $this->planned_ship_date?->toDateString(),
            'confirmed_qty_m' => (float) $this->confirmed_qty_m,
            'confirmed_qty_tan' => (float) $this->confirmed_qty_tan,
            'shipped_qty_m' => (float) $this->shipped_qty_m,
            'shipped_qty_tan' => (float) $this->shipped_qty_tan,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'note' => (string) ($this->note ?? ''),
            'order_code' => $this->relationLoaded('order')
                ? ($this->order?->code ?? '—')
                : (Order::query()->whereKey($this->order_id)->value('code') ?? '—'),
            'sku' => $this->relationLoaded('product')
                ? ($this->product?->sku ?? '—')
                : (Product::query()->whereKey($this->product_id)->value('sku') ?? '—'),
            'unshipped_qty_m' => $this->unshippedQtyM(),
        ];
    }

    public static function tanFromMeters(float $meters, int $productId): float
    {
        if ($meters <= 0) {
            return 0.0;
        }

        return FabricQuantity::resolve(
            null,
            $meters,
            $productId,
            false,
            null,
            FabricQuantity::CONTEXT_SHIPMENT,
        )->qty_tan;
    }
}
