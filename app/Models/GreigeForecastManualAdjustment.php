<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class GreigeForecastManualAdjustment extends Model
{
    protected $fillable = [
        'greige_id',
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

    /** @return BelongsTo<Greige, $this> */
    public function greige(): BelongsTo
    {
        return $this->belongsTo(Greige::class);
    }

    public static function totalFor(string $greigeSku, string $targetYm): float
    {
        $greigeId = self::greigeIdForSku($greigeSku);
        if ($greigeId === null) {
            return 0.0;
        }

        return (float) self::query()
            ->where('greige_id', $greigeId)
            ->where('target_ym', $targetYm)
            ->sum('adjustment_qty_m');
    }

    /**
     * @return Collection<int, object>
     */
    public static function historyFor(string $greigeSku, string $targetYm): Collection
    {
        $greigeId = self::greigeIdForSku($greigeSku);
        if ($greigeId === null) {
            return collect();
        }

        return self::query()
            ->with('greige')
            ->where('greige_id', $greigeId)
            ->where('target_ym', $targetYm)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (self $row) => $row->toHistoryObject())
            ->values();
    }

    public static function add(
        string $greigeSku,
        string $targetYm,
        float $qtyM,
        string $direction,
        string $reason,
        string $createdBy,
    ): object {
        $greigeId = self::greigeIdForSku($greigeSku);
        if ($greigeId === null) {
            abort(404);
        }

        $signed = $direction === 'decrease' ? -abs($qtyM) : abs($qtyM);

        $row = self::query()->create([
            'greige_id' => $greigeId,
            'target_ym' => $targetYm,
            'adjustment_qty_m' => round($signed, 2),
            'direction' => $direction,
            'reason' => $reason,
            'created_by_name' => $createdBy,
        ]);

        $row->load('greige');

        return $row->toHistoryObject();
    }

    public function toHistoryObject(): object
    {
        return (object) [
            'id' => $this->id,
            'greige_sku' => $this->greige?->sku ?? Greige::query()->find($this->greige_id)?->sku,
            'target_ym' => $this->target_ym,
            'adjustment_qty_m' => (float) $this->adjustment_qty_m,
            'direction' => $this->direction,
            'reason' => $this->reason,
            'created_by' => $this->created_by_name,
            'updated_at' => $this->updated_at?->toIso8601String(), 
        ];
    }

    private static function greigeIdForSku(string $greigeSku): ?int
    {
        return Greige::findBySku($greigeSku)?->id;
    }
}
