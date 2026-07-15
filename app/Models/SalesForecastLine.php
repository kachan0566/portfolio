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

    private static ?string $draftTargetYm = null;

    private static ?int $draftForecastId = null;

    /** @var array<string, float> */
    private static array $draftQtyByKey = [];

    private static bool $draftPreloaded = false;

    /** @var array<int, bool> */
    private static array $productHasDraftById = [];

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

    /** @internal テスト用にドラフト読み込みキャッシュを破棄する */
    public static function resetDraftCacheForTesting(): void
    {
        self::invalidateDraftCache();
    }

    public static function invalidateDraftCache(): void
    {
        self::$draftTargetYm = null;
        self::$draftForecastId = null;
        self::$draftQtyByKey = [];
        self::$draftPreloaded = false;
        self::$productHasDraftById = [];
    }

    public static function preloadDraftForMonth(string $targetYm): void
    {
        if (self::$draftPreloaded && self::$draftTargetYm === $targetYm) {
            return;
        }

        self::$draftTargetYm = $targetYm;
        self::$draftQtyByKey = [];
        self::$productHasDraftById = [];
        $draft = SalesForecast::ensureDraft($targetYm);
        self::$draftForecastId = $draft->id;
        self::$draftPreloaded = true;

        foreach (self::query()
            ->where('sales_forecast_id', $draft->id)
            ->whereIn('source_type', [
                SalesForecastSourceType::ORDER,
                SalesForecastSourceType::PURCHASE_ORDER,
            ])
            ->get(['product_id', 'source_type', 'source_id', 'forecast_qty_m']) as $line) {
            $productId = (int) $line->product_id;
            self::$draftQtyByKey[self::draftKey(
                $productId,
                (string) $line->source_type,
                (int) $line->source_id,
            )] = round((float) $line->forecast_qty_m, 2);
            self::$productHasDraftById[$productId] = true;
        }
    }

    public static function productHasSavedDraft(int $productId, string $targetYm): bool
    {
        self::preloadDraftForMonth($targetYm);

        return self::$productHasDraftById[$productId] ?? false;
    }

    public static function findDraftQty(
        int $productId,
        string $targetYm,
        string $sourceType,
        int $sourceId,
    ): ?float {
        if (self::$draftPreloaded && self::$draftTargetYm === $targetYm) {
            return self::$draftQtyByKey[self::draftKey($productId, $sourceType, $sourceId)] ?? null;
        }

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

        self::invalidateDraftCache();
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

        self::invalidateDraftCache();
    }

    private static function draftKey(int $productId, string $sourceType, int $sourceId): string
    {
        return "{$productId}|{$sourceType}|{$sourceId}";
    }
}
