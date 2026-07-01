<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 売上・出荷見通しの明細入力（デモ用 JSON）。
 */
class SalesForecastLine
{
    public const SOURCE_ORDER = 'order';

    public const SOURCE_PURCHASE_ORDER = 'purchase_order';

    private const FILE = 'sales_forecast_lines.json';

    /** @var list<array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = storage_path('app/'.self::FILE);
        if (! is_file($path)) {
            return self::$cache = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['lines']) || ! is_array($data['lines'])) {
            return self::$cache = [];
        }

        return self::$cache = $data['lines'];
    }

    public static function find(int $productId, string $targetYm, string $sourceType, int $sourceId): ?float
    {
        $row = collect(self::all())->first(function ($line) use ($productId, $targetYm, $sourceType, $sourceId) {
            return (int) ($line['product_id'] ?? 0) === $productId
                && ($line['target_ym'] ?? '') === $targetYm
                && ($line['source_type'] ?? '') === $sourceType
                && (int) ($line['source_id'] ?? 0) === $sourceId;
        });

        if ($row === null) {
            return null;
        }

        return round((float) ($row['forecast_qty_m'] ?? 0), 2);
    }

    public static function effectiveQty(
        int $productId,
        string $targetYm,
        string $sourceType,
        int $sourceId,
        float $defaultQty
    ): float {
        $saved = self::find($productId, $targetYm, $sourceType, $sourceId);

        return $saved !== null ? $saved : round($defaultQty, 2);
    }

    public static function isSaved(int $productId, string $targetYm, string $sourceType, int $sourceId): bool
    {
        return self::find($productId, $targetYm, $sourceType, $sourceId) !== null;
    }

    /**
     * @return Collection<int, object>
     */
    public static function forProduct(int $productId, string $targetYm): Collection
    {
        return collect(self::all())
            ->filter(fn ($line) => (int) ($line['product_id'] ?? 0) === $productId)
            ->filter(fn ($line) => ($line['target_ym'] ?? '') === $targetYm)
            ->map(fn ($line) => (object) $line)
            ->values();
    }

    /**
     * @param  list<array{source_type: string, source_id: int, forecast_qty_m: float}>  $inputs
     */
    public static function saveForProduct(int $productId, string $targetYm, array $inputs): void
    {
        $remaining = collect(self::all())
            ->reject(fn ($line) => (int) ($line['product_id'] ?? 0) === $productId
                && ($line['target_ym'] ?? '') === $targetYm)
            ->values()
            ->all();

        $now = date('c');
        $nextId = (int) (collect(self::all())->max('id') ?? 0);

        foreach ($inputs as $input) {
            $qty = round((float) ($input['forecast_qty_m'] ?? 0), 2);
            if ($qty < 0) {
                continue;
            }

            $nextId++;
            $remaining[] = [
                'id' => $nextId,
                'product_id' => $productId,
                'target_ym' => $targetYm,
                'source_type' => (string) ($input['source_type'] ?? ''),
                'source_id' => (int) ($input['source_id'] ?? 0),
                'forecast_qty_m' => $qty,
                'updated_at' => $now,
            ];
        }

        self::persist($remaining);
    }

    public static function clearForProduct(int $productId, string $targetYm): void
    {
        $remaining = collect(self::all())
            ->reject(fn ($line) => (int) ($line['product_id'] ?? 0) === $productId
                && ($line['target_ym'] ?? '') === $targetYm)
            ->values()
            ->all();

        self::persist($remaining);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private static function persist(array $lines): void
    {
        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['lines' => array_values($lines)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        self::$cache = null;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
