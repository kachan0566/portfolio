<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CombinedMonthEndForecast extends Model
{
    protected $fillable = [
        'target_ym',
        'base_date',
        'version',
        'created_by_name',
        'submitted_at',
        'submission_status',
        'total_forecast_value',
        'total_current_stock_value',
        'product_forecast_value',
        'greige_forecast_value',
        'product_summary',
        'greige_summary',
    ];

    protected function casts(): array
    {
        return [
            'base_date' => 'date',
            'version' => 'integer',
            'submitted_at' => 'datetime',
            'total_forecast_value' => 'integer',
            'total_current_stock_value' => 'integer',
            'product_forecast_value' => 'integer',
            'greige_forecast_value' => 'integer',
            'product_summary' => 'array',
            'greige_summary' => 'array',
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public static function forMonth(string $targetYm): Collection
    {
        return self::query()
            ->where('target_ym', $targetYm)
            ->where('submission_status', 'submitted')
            ->orderByDesc('version')
            ->get()
            ->map(fn (self $forecast) => $forecast->toSnapshotObject())
            ->values();
    }

    public static function latestForMonth(string $targetYm): ?object
    {
        $forecast = self::query()
            ->where('target_ym', $targetYm)
            ->where('submission_status', 'submitted')
            ->orderByDesc('version')
            ->first();

        return $forecast?->toSnapshotObject();
    }

    public static function maxVersionForMonth(string $targetYm): int
    {
        return (int) (self::query()
            ->where('target_ym', $targetYm)
            ->where('submission_status', 'submitted')
            ->max('version') ?? 0);
    }

    /**
     * @param  array<string, mixed>  $header
     */
    public static function saveSnapshot(array $header): object
    {
        $targetYm = (string) $header['target_ym'];

        return self::saveSnapshotWithVersion(
            $header,
            self::maxVersionForMonth($targetYm) + 1,
        );
    }

    /**
     * @param  array<string, mixed>  $header
     */
    public static function saveSnapshotWithVersion(array $header, int $version): object
    {
        $forecast = self::query()->create([
            'target_ym' => (string) $header['target_ym'],
            'base_date' => (string) ($header['base_date'] ?? now()->toDateString()),
            'version' => $version,
            'created_by_name' => (string) ($header['created_by'] ?? '木村 勝也'),
            'submitted_at' => now(),
            'submission_status' => 'submitted',
            'total_forecast_value' => (int) ($header['total_forecast_value'] ?? 0),
            'total_current_stock_value' => (int) ($header['total_current_stock_value'] ?? 0),
            'product_forecast_value' => (int) ($header['product_forecast_value'] ?? 0),
            'greige_forecast_value' => (int) ($header['greige_forecast_value'] ?? 0),
            'product_summary' => $header['product_summary'] ?? [],
            'greige_summary' => $header['greige_summary'] ?? [],
        ]);

        return $forecast->toSnapshotObject();
    }

    public function toSnapshotObject(): object
    {
        return (object) [
            'id' => $this->id,
            'target_ym' => $this->target_ym,
            'base_date' => $this->base_date?->format('Y-m-d'),
            'version' => $this->version,
            'created_by' => $this->created_by_name,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submission_status' => $this->submission_status,
            'total_forecast_value' => (int) $this->total_forecast_value,
            'total_current_stock_value' => (int) $this->total_current_stock_value,
            'product_forecast_value' => (int) $this->product_forecast_value,
            'greige_forecast_value' => (int) $this->greige_forecast_value,
            'product_summary' => $this->product_summary ?? [],
            'greige_summary' => $this->greige_summary ?? [],
        ];
    }
}
