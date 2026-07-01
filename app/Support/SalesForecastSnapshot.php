<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 売上・出荷見通しの提出版スナップショット（デモ用 JSON）。
 */
class SalesForecastSnapshot
{
    private const FILE = 'sales_forecast_snapshots.json';

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

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public static function save(array $header, array $lines): object
    {
        $snapshots = self::all();
        $targetYm = (string) $header['target_ym'];
        $version = (int) collect($snapshots)
            ->filter(fn ($s) => ($s['target_ym'] ?? '') === $targetYm)
            ->max('version') + 1;

        $snapshot = [
            'id' => (int) (collect($snapshots)->max('id') ?? 0) + 1,
            'target_ym' => $targetYm,
            'base_date' => (string) ($header['base_date'] ?? date('Y-m-d')),
            'version' => $version,
            'created_by' => (string) ($header['created_by'] ?? '木村 勝也'),
            'submitted_at' => date('c'),
            'submission_status' => 'submitted',
            'total_sales' => (int) ($header['total_sales'] ?? 0),
            'total_qty' => (float) ($header['total_qty'] ?? 0),
            'total_profit' => (int) ($header['total_profit'] ?? 0),
            'lines' => $lines,
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
