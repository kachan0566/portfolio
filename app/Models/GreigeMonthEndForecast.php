<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GreigeMonthEndForecast extends Model
{
    protected $fillable = [
        'target_ym',
        'base_date',
        'version',
        'created_by_name',
        'submitted_at',
        'submission_status',
        'total_forecast_value',
        'total_long_term_value',
    ];

    protected function casts(): array
    {
        return [
            'base_date' => 'date',
            'version' => 'integer',
            'submitted_at' => 'datetime',
            'total_forecast_value' => 'integer',
            'total_long_term_value' => 'integer',
        ];
    }

    /** @return HasMany<GreigeMonthEndForecastLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(GreigeMonthEndForecastLine::class);
    }

    /**
     * @return Collection<int, object>
     */
    public static function forMonth(string $targetYm): Collection
    {
        return self::query()
            ->with('lines.greige')
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
            ->with('lines.greige')
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
     * @param  list<array<string, mixed>>  $lines
     */
    public static function saveSnapshot(array $header, array $lines): object
    {
        $targetYm = (string) $header['target_ym'];

        return self::saveSnapshotWithVersion(
            $header,
            $lines,
            self::maxVersionForMonth($targetYm) + 1,
        );
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public static function saveSnapshotWithVersion(
        array $header,
        array $lines,
        int $version,
    ): object {
        return DB::transaction(function () use ($header, $lines, $version) {
            $forecast = self::query()->create([
                'target_ym' => (string) $header['target_ym'],
                'base_date' => (string) ($header['base_date'] ?? now()->toDateString()),
                'version' => $version,
                'created_by_name' => (string) ($header['created_by'] ?? '木村 勝也'),
                'submitted_at' => now(),
                'submission_status' => 'submitted',
                'total_forecast_value' => (int) ($header['total_forecast_value'] ?? 0),
                'total_long_term_value' => (int) ($header['total_long_term_value'] ?? 0),
            ]);

            foreach ($lines as $line) {
                $greige = Greige::query()
                    ->where('sku', (string) $line['greige_sku'])
                    ->firstOrFail();

                $forecast->lines()->create([
                    'greige_id' => $greige->id,
                    'current_stock_qty' => (float) ($line['current_stock_qty'] ?? 0),
                    'inbound_scheduled_qty' => (float) ($line['inbound_scheduled_qty'] ?? 0),
                    'outbound_scheduled_qty' => (float) ($line['outbound_scheduled_qty'] ?? 0),
                    'manual_adjustment_qty' => (float) ($line['manual_adjustment_qty'] ?? 0),
                    'forecast_qty' => (float) ($line['forecast_qty'] ?? 0),
                    'unit_cost_snapshot' => isset($line['unit_cost'])
                        ? (int) $line['unit_cost']
                        : null,
                    'forecast_value' => (int) ($line['forecast_value'] ?? 0),
                    'long_term_qty' => (float) ($line['long_term_qty'] ?? 0),
                    'long_term_value' => (int) ($line['long_term_value'] ?? 0),
                    'oldest_received_date' => $line['oldest_received_date'] ?? null,
                    'oldest_age_months' => $line['oldest_age_months'] ?? null,
                    'note' => $line['note'] ?? null,
                ]);
            }

            $forecast->load('lines.greige');

            return $forecast->toSnapshotObject();
        });
    }

    public function toSnapshotObject(): object
    {
        $lines = $this->relationLoaded('lines')
            ? $this->lines
            : $this->lines()->with('greige')->get();

        return (object) [
            'id' => $this->id,
            'target_ym' => $this->target_ym,
            'base_date' => $this->base_date?->format('Y-m-d'),
            'version' => $this->version,
            'created_by' => $this->created_by_name,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submission_status' => $this->submission_status,
            'total_forecast_value' => (int) $this->total_forecast_value,
            'total_long_term_value' => (int) $this->total_long_term_value,
            'lines' => $lines
                ->map(fn (GreigeMonthEndForecastLine $line) => [
                    'greige_sku' => $line->greige?->sku,
                    'sku' => $line->greige?->sku,
                    'current_stock_qty' => (float) $line->current_stock_qty,
                    'inbound_scheduled_qty' => (float) $line->inbound_scheduled_qty,
                    'outbound_scheduled_qty' => (float) $line->outbound_scheduled_qty,
                    'manual_adjustment_qty' => (float) $line->manual_adjustment_qty,
                    'forecast_qty' => (float) $line->forecast_qty,
                    'unit_cost' => $line->unit_cost_snapshot !== null
                        ? (int) $line->unit_cost_snapshot
                        : null,
                    'forecast_value' => (int) $line->forecast_value,
                    'long_term_qty' => (float) $line->long_term_qty,
                    'long_term_value' => (int) $line->long_term_value,
                    'oldest_received_date' => $line->oldest_received_date?->format('Y-m-d'),
                    'oldest_age_months' => $line->oldest_age_months !== null
                        ? (int) $line->oldest_age_months
                        : null,
                    'note' => $line->note,
                ])
                ->values()
                ->all(),
        ];
    }
}
