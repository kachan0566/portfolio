<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 製品＋生機の合算月末在庫予想スナップショット（デモ用 JSON）。
 */
class CombinedForecastSnapshot
{
    private const FILE = 'combined_month_end_forecast_snapshots.json';

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
        if (! is_array($data) || ! isset($data['snapshots']) || ! is_array($data['snapshots'])) {
            return self::$cache = [];
        }

        return self::$cache = $data['snapshots'];
    }

    /**
     * @return Collection<int, object>
     */
    public static function forMonth(string $targetYm): Collection
    {
        return collect(self::all())
            ->filter(fn ($s) => ($s['target_ym'] ?? '') === $targetYm)
            ->sortByDesc('version')
            ->map(fn ($s) => (object) $s)
            ->values();
    }

    public static function latestForMonth(string $targetYm): ?object
    {
        return self::forMonth($targetYm)->first();
    }

    public static function maxVersionForMonth(string $targetYm): int
    {
        return (int) self::forMonth($targetYm)->max('version');
    }

    /**
     * @param  array<string, mixed>  $header
     */
    public static function save(array $header): object
    {
        $targetYm = (string) $header['target_ym'];

        return self::saveWithVersion($header, self::maxVersionForMonth($targetYm) + 1);
    }

    /**
     * @param  array<string, mixed>  $header
     */
    public static function saveWithVersion(array $header, int $version): object
    {
        $snapshots = self::all();
        $snapshot = [
            'id' => (int) (collect($snapshots)->max('id') ?? 0) + 1,
            'target_ym' => (string) $header['target_ym'],
            'base_date' => (string) ($header['base_date'] ?? date('Y-m-d')),
            'version' => $version,
            'created_by' => (string) ($header['created_by'] ?? '木村 勝也'),
            'submitted_at' => date('c'),
            'submission_status' => 'submitted',
            'total_forecast_value' => (int) ($header['total_forecast_value'] ?? 0),
            'total_current_stock_value' => (int) ($header['total_current_stock_value'] ?? 0),
            'product_forecast_value' => (int) ($header['product_forecast_value'] ?? 0),
            'greige_forecast_value' => (int) ($header['greige_forecast_value'] ?? 0),
            'product_summary' => $header['product_summary'] ?? [],
            'greige_summary' => $header['greige_summary'] ?? [],
        ];

        $snapshots[] = $snapshot;
        self::persist($snapshots);

        return (object) $snapshot;
    }

    /**
     * @param  list<array<string, mixed>>  $snapshots
     */
    private static function persist(array $snapshots): void
    {
        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['snapshots' => array_values($snapshots)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        self::$cache = null;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
