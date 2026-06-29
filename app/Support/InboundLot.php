<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 製品入荷ロット（デモ用 JSON）。
 */
class InboundLot
{
    public const SOURCE_RECEIVING = 'receiving';

    public const SOURCE_DYE_TRANSFER = 'dye_transfer';

    public const SOURCE_OPENING_BALANCE = 'opening_balance';

    private const FILE = 'inbound_lots.json';

    private const BOOTSTRAP_FLAG = 'inbound_lots_bootstrapped.flag';

    /** @var list<array<string, mixed>>|null */
    private static ?array $cache = null;

    public static function ensureBootstrapped(): void
    {
        $flag = storage_path('app/'.self::BOOTSTRAP_FLAG);
        if (is_file($flag)) {
            return;
        }

        \App\Services\Inventory\InboundLotBootstrap::run();
        $dir = dirname($flag);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($flag, date('c'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        self::ensureBootstrapped();

        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = storage_path('app/'.self::FILE);
        if (! is_file($path)) {
            return self::$cache = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['lots']) || ! is_array($data['lots'])) {
            return self::$cache = [];
        }

        return self::$cache = self::normalizeLots($data['lots']);
    }

    /**
     * @return Collection<int, object>
     */
    public static function forProduct(int $productId): Collection
    {
        return collect(self::all())
            ->filter(fn ($lot) => (int) ($lot['product_id'] ?? 0) === $productId)
            ->sortBy([
                ['received_date', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($lot) => (object) $lot)
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public static function withRemaining(int $productId): Collection
    {
        return self::forProduct($productId)
            ->filter(fn ($lot) => (float) ($lot->remaining_qty_m ?? 0) > 0);
    }

    public static function find(int $id): ?object
    {
        $lot = collect(self::all())->firstWhere('id', $id);

        return $lot ? (object) $lot : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): object
    {
        $lots = self::all();
        $nextId = collect($lots)->max('id') ?? 0;
        $nextId = (int) $nextId + 1;

        $lot = self::normalizeLot(array_merge([
            'id' => $nextId,
            'product_id' => 0,
            'receiving_code' => null,
            'received_date' => date('Y-m-d'),
            'received_qty_m' => 0.0,
            'remaining_qty_m' => 0.0,
            'purchase_order_id' => null,
            'source_type' => self::SOURCE_RECEIVING,
        ], $attributes));

        $lots[] = $lot;
        self::persist($lots);

        return (object) $lot;
    }

    public static function consume(int $lotId, float $qtyM): float
    {
        if ($qtyM <= 0) {
            return 0.0;
        }

        $lots = self::all();
        $consumed = 0.0;

        foreach ($lots as &$lot) {
            if ((int) $lot['id'] !== $lotId) {
                continue;
            }
            $remaining = (float) $lot['remaining_qty_m'];
            $take = min($remaining, $qtyM);
            $lot['remaining_qty_m'] = round($remaining - $take, 2);
            $consumed = $take;
            break;
        }
        unset($lot);

        if ($consumed > 0) {
            self::persist($lots);
        }

        return $consumed;
    }

    /**
     * @param  list<array<string, mixed>>  $lots
     */
    public static function replaceAll(array $lots): void
    {
        self::persist(self::normalizeLots($lots));
    }

    public static function resetBootstrap(): void
    {
        $flag = storage_path('app/'.self::BOOTSTRAP_FLAG);
        $file = storage_path('app/'.self::FILE);
        if (is_file($flag)) {
            unlink($flag);
        }
        if (is_file($file)) {
            unlink($file);
        }
        self::$cache = null;
    }

    /**
     * @param  list<array<string, mixed>>  $lots
     */
    private static function persist(array $lots): void
    {
        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['lots' => self::normalizeLots($lots)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        self::$cache = null;
    }

    /**
     * @param  list<array<mixed>>  $lots
     * @return list<array<string, mixed>>
     */
    private static function normalizeLots(array $lots): array
    {
        return array_values(array_map(fn ($lot) => self::normalizeLot((array) $lot), $lots));
    }

    /**
     * @param  array<string, mixed>  $lot
     * @return array<string, mixed>
     */
    private static function normalizeLot(array $lot): array
    {
        $received = round((float) ($lot['received_qty_m'] ?? 0), 2);
        $remaining = array_key_exists('remaining_qty_m', $lot)
            ? round((float) $lot['remaining_qty_m'], 2)
            : $received;

        return [
            'id' => (int) ($lot['id'] ?? 0),
            'product_id' => (int) ($lot['product_id'] ?? 0),
            'receiving_code' => $lot['receiving_code'] ?? null,
            'received_date' => (string) ($lot['received_date'] ?? date('Y-m-d')),
            'received_qty_m' => $received,
            'remaining_qty_m' => max(0.0, $remaining),
            'purchase_order_id' => isset($lot['purchase_order_id']) ? (int) $lot['purchase_order_id'] : null,
            'source_type' => (string) ($lot['source_type'] ?? self::SOURCE_RECEIVING),
        ];
    }
}
