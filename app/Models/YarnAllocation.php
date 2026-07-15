<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_order_id', 'material_id', 'qty_kg'])]
class YarnAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'material_id' => 'integer',
            'qty_kg' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @param  list<array{material_id: int, qty_kg: float}>  $lines
     */
    public static function replaceForGreigePo(int $purchaseOrderId, array $lines): void
    {
        self::query()->where('purchase_order_id', $purchaseOrderId)->delete();

        $now = now();
        foreach ($lines as $line) {
            $qtyKg = round((float) ($line['qty_kg'] ?? 0), 3);
            if ($qtyKg <= 0) {
                continue;
            }

            self::query()->create([
                'purchase_order_id' => $purchaseOrderId,
                'material_id' => (int) $line['material_id'],
                'qty_kg' => $qtyKg,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public static function releaseGreigePo(int $purchaseOrderId): void
    {
        self::query()->where('purchase_order_id', $purchaseOrderId)->delete();
    }

    public static function allocatedKg(int $materialId, ?int $excludeGreigePoId = null): float
    {
        $query = self::query()->where('material_id', $materialId);

        if ($excludeGreigePoId !== null) {
            $query->where('purchase_order_id', '!=', $excludeGreigePoId);
        }

        return round((float) $query->sum('qty_kg'), 3);
    }
}
