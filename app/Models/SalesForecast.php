<?php

namespace App\Models;

use App\Support\SalesForecastSourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SalesForecast extends Model
{
    protected $fillable = [
        'target_ym',
        'base_date',
        'version',
        'created_by_name',
        'submitted_at',
        'submission_status',
        'total_sales',
        'total_qty',
        'total_profit',
    ];

    protected function casts(): array
    {
        return [
            'base_date' => 'date',
            'submitted_at' => 'datetime',
            'total_qty' => 'decimal:2',
        ];
    }

    /** @return HasMany<SalesForecastLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesForecastLine::class);
    }

    public static function ensureDraft(string $targetYm): self
    {
        return self::query()->firstOrCreate(
            [
                'target_ym' => $targetYm,
                'version' => SalesForecastSourceType::DRAFT_VERSION,
            ],
            [
                'base_date' => now()->toDateString(),
                'submission_status' => SalesForecastSourceType::DRAFT,
                'created_by_name' => 'システム',
                'submitted_at' => now(),
                'total_sales' => 0,
                'total_qty' => 0,
                'total_profit' => 0,
            ],
        );
    }

    public static function latestSubmittedForMonth(string $targetYm): ?self
    {
        return self::query()
            ->where('target_ym', $targetYm)
            ->where('submission_status', SalesForecastSourceType::SUBMITTED)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $productLinePayloads
     */
    public static function submitMonth(
        string $targetYm,
        string $createdByName,
        string $baseDate,
        int $totalSales,
        float $totalQty,
        int $totalProfit,
        array $productLinePayloads,
    ): self {
        return DB::transaction(function () use (
            $targetYm,
            $createdByName,
            $baseDate,
            $totalSales,
            $totalQty,
            $totalProfit,
            $productLinePayloads,
        ) {
            $version = (int) (self::query()
                ->where('target_ym', $targetYm)
                ->where('submission_status', SalesForecastSourceType::SUBMITTED)
                ->max('version') ?? 0) + 1;

            $forecast = self::query()->create([
                'target_ym' => $targetYm,
                'base_date' => $baseDate,
                'version' => $version,
                'created_by_name' => $createdByName,
                'submitted_at' => now(),
                'submission_status' => SalesForecastSourceType::SUBMITTED,
                'total_sales' => $totalSales,
                'total_qty' => $totalQty,
                'total_profit' => $totalProfit,
            ]);

            $now = now();
            foreach ($productLinePayloads as $line) {
                $forecast->lines()->create([
                    'product_id' => (int) $line['product_id'],
                    'source_type' => SalesForecastSourceType::PRODUCT,
                    'source_id' => (int) $line['product_id'],
                    'forecast_qty_m' => (float) ($line['total_qty'] ?? 0),
                    'forecast_sales' => (int) ($line['total_sales'] ?? 0),
                    'forecast_profit' => (int) ($line['total_profit'] ?? 0),
                    'note' => ($line['warning_text'] ?? '') !== '' ? (string) $line['warning_text'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $forecast;
        });
    }

    public function toSnapshotObject(): object
    {
        $lines = $this->relationLoaded('lines')
            ? $this->lines
            : $this->lines()->get();

        return (object) [
            'id' => $this->id,
            'target_ym' => $this->target_ym,
            'base_date' => $this->base_date->format('Y-m-d'),
            'version' => $this->version,
            'created_by' => $this->created_by_name,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submission_status' => $this->submission_status,
            'total_sales' => (int) $this->total_sales,
            'total_qty' => (float) $this->total_qty,
            'total_profit' => (int) $this->total_profit,
            'lines' => $lines
                ->where('source_type', SalesForecastSourceType::PRODUCT)
                ->map(fn (SalesForecastLine $line) => [
                    'product_id' => $line->product_id,
                    'total_qty' => (float) $line->forecast_qty_m,
                    'total_sales' => (int) $line->forecast_sales,
                    'total_profit' => (int) $line->forecast_profit,
                ])
                ->values()
                ->all(),
        ];
    }
}
