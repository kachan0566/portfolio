<?php

namespace App\Models;

use App\Support\SalesForecastSourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class SalesForecastLine extends Model
{
    protected $fillable = [
        'sales_forecast_id',
        'product_id',
        'source_type',
        'source_id',
        'forecast_qty_m',
        'forecast_sales',
        'forecast_profit',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'forecast_qty_m' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<SalesForecast, $this> */
    public function salesForecast(): BelongsTo
    {
        return $this->belongsTo(SalesForecast::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function findDraftQty(
        int $productId,
        string $targetYm,
        string $sourceType,
        int $sourceId,
    ): ?float {
        $draft = SalesForecast::ensureDraft($targetYm);

        $line = self::query()
            ->where('sales_forecast_id', $draft->id)
            ->where('product_id', $productId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($line === null) {
            return null;
        }

        return round((float) $line->forecast_qty_m, 2);
    }

    public static function effectiveQty(
        int $productId,
        string $targetYm,
        string $sourceType,
        int $sourceId,
        float $defaultQty,
    ): float {
        $saved = self::findDraftQty($productId, $targetYm, $sourceType, $sourceId);

        return $saved !== null ? $saved : round($defaultQty, 2);
    }

    public static function isSaved(
        int $productId,
        string $targetYm,
        string $sourceType,
        int $sourceId,
    ): bool {
        return self::findDraftQty($productId, $targetYm, $sourceType, $sourceId) !== null;
    }

    /**
     * @param  list<array{source_type: string, source_id: int, forecast_qty_m: float}>  $inputs
     */
    public static function saveDraftForProduct(int $productId, string $targetYm, array $inputs): void
    {
        DB::transaction(function () use ($productId, $targetYm, $inputs) {
            $draft = SalesForecast::ensureDraft($targetYm);

            self::query()
                ->where('sales_forecast_id', $draft->id)
                ->where('product_id', $productId)
                ->whereIn('source_type', [
                    SalesForecastSourceType::ORDER,
                    SalesForecastSourceType::PURCHASE_ORDER,
                ])
                ->delete();

            $now = now();
            foreach ($inputs as $input) {
                $qty = round((float) ($input['forecast_qty_m'] ?? 0), 2);
                if ($qty < 0) {
                    continue;
                }

                self::query()->create([
                    'sales_forecast_id' => $draft->id,
                    'product_id' => $productId,
                    'source_type' => (string) ($input['source_type'] ?? ''),
                    'source_id' => (int) ($input['source_id'] ?? 0),
                    'forecast_qty_m' => $qty,
                    'forecast_sales' => 0,
                    'forecast_profit' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $draft->update(['submitted_at' => $now]);
        });
    }

    public static function clearDraftForProduct(int $productId, string $targetYm): void
    {
        $draft = SalesForecast::query()
            ->where('target_ym', $targetYm)
            ->where('version', SalesForecastSourceType::DRAFT_VERSION)
            ->first();

        if ($draft === null) {
            return;
        }

        self::query()
            ->where('sales_forecast_id', $draft->id)
            ->where('product_id', $productId)
            ->whereIn('source_type', [
                SalesForecastSourceType::ORDER,
                SalesForecastSourceType::PURCHASE_ORDER,
            ])
            ->delete();
    }
}
