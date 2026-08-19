<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ForecastManualAdjustment extends Model
{
    protected $fillable = [
        'product_id',
        'target_ym',
        'adjustment_qty_m',
        'direction',
        'reason',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_qty_m' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function totalFor(int $productId, string $targetYm): float
    {
        return (float) self::query()
            ->where('product_id', $productId)
            ->where('target_ym', $targetYm)
            ->sum('adjustment_qty_m');
    }

    /**
     * @return Collection<int, object>
     */
    public static function historyFor(int $productId, string $targetYm): Collection
    {
        return self::query()
            ->where('product_id', $productId)
            ->where('target_ym', $targetYm)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (self $row) => $row->toHistoryObject())
            ->values();
    }

    public static function add(
        int $productId,
        string $targetYm,
        float $qtyM,
        string $direction,
        string $reason,
        string $createdBy,
    ): object {
        $signed = $direction === 'decrease' ? -abs($qtyM) : abs($qtyM);

        $row = self::query()->create([
            'product_id' => $productId,
            'target_ym' => $targetYm,
            'adjustment_qty_m' => round($signed, 2),
            'direction' => $direction,
            'reason' => $reason,
            'created_by_name' => $createdBy,
        ]);

        return $row->toHistoryObject();
    }

    public function toHistoryObject(): object
    {
        return (object) [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'target_ym' => $this->target_ym,
            'adjustment_qty_m' => (float) $this->adjustment_qty_m,
            'direction' => $this->direction,
            'reason' => $this->reason,
            'created_by' => $this->created_by_name,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
