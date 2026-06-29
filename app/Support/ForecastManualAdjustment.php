<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 月末在庫予想の手動調整（デモ用 JSON）。
 */
class ForecastManualAdjustment
{
    private const FILE = 'forecast_manual_adjustments.json';

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
        if (! is_array($data) || ! isset($data['adjustments']) || ! is_array($data['adjustments'])) {
            return self::$cache = [];
        }

        return self::$cache = $data['adjustments'];
    }

    public static function totalFor(int $productId, string $targetYm): float
    {
        return (float) collect(self::all())
            ->filter(fn ($a) => (int) ($a['product_id'] ?? 0) === $productId)
            ->filter(fn ($a) => ($a['target_ym'] ?? '') === $targetYm)
            ->sum(fn ($a) => (float) ($a['adjustment_qty_m'] ?? 0));
    }

    /**
     * @return Collection<int, object>
     */
    public static function historyFor(int $productId, string $targetYm): Collection
    {
        return collect(self::all())
            ->filter(fn ($a) => (int) ($a['product_id'] ?? 0) === $productId)
            ->filter(fn ($a) => ($a['target_ym'] ?? '') === $targetYm)
            ->sortByDesc('updated_at')
            ->map(fn ($a) => (object) $a)
            ->values();
    }

    public static function add(int $productId, string $targetYm, float $qtyM, string $direction, string $reason, string $createdBy): object
    {
        $signed = $direction === 'decrease' ? -abs($qtyM) : abs($qtyM);
        $adjustments = self::all();
        $entry = [
            'id' => (int) (collect($adjustments)->max('id') ?? 0) + 1,
            'product_id' => $productId,
            'target_ym' => $targetYm,
            'adjustment_qty_m' => round($signed, 2),
            'direction' => $direction,
            'reason' => $reason,
            'created_by' => $createdBy,
            'updated_at' => date('c'),
        ];
        $adjustments[] = $entry;
        self::persist($adjustments);

        return (object) $entry;
    }

    /**
     * @param  list<array<string, mixed>>  $adjustments
     */
    private static function persist(array $adjustments): void
    {
        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['adjustments' => array_values($adjustments)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        self::$cache = null;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
