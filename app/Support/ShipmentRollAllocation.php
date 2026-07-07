<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 出荷時の製品反消費記録（丸ごと1反単位）。
 */
class ShipmentRollAllocation
{
    public const FILE = 'shipment_roll_allocations.json';

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

        return self::$cache = array_values($data['lines']);
    }

    /** @return Collection<int, object> */
    public static function forShipment(int $shipmentRef): Collection
    {
        return collect(self::all())
            ->filter(fn ($line) => (int) ($line['shipment_ref'] ?? 0) === $shipmentRef)
            ->map(fn ($line) => (object) $line)
            ->values();
    }

    /** @return Collection<int, object> */
    public static function forProduct(int $productId): Collection
    {
        $rollIds = ProductRoll::all();
        $productRollIds = collect($rollIds)
            ->filter(fn ($roll) => (int) ($roll['product_id'] ?? 0) === $productId)
            ->pluck('id')
            ->all();

        return collect(self::all())
            ->filter(fn ($line) => in_array((int) ($line['product_roll_id'] ?? 0), $productRollIds, true))
            ->map(fn ($line) => (object) $line)
            ->values();
    }

    public static function record(
        int $shipmentRef,
        int $productRollId,
        float $consumedTanQty,
        float $consumedQtyM,
        ?string $note = null,
    ): void {
        $lines = self::all();
        $lines[] = [
            'id' => (int) (collect($lines)->max('id') ?? 0) + 1,
            'shipment_ref' => $shipmentRef,
            'product_roll_id' => $productRollId,
            'consumed_tan_qty' => QtyHelper::roundReceivingTan($consumedTanQty),
            'consumed_qty_m' => round($consumedQtyM, 2),
            'note' => $note,
            'created_at' => date('c'),
        ];

        self::persist($lines);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public static function replaceAll(array $lines): void
    {
        self::persist($lines);
    }

    public static function resetBootstrap(): void
    {
        $file = storage_path('app/'.self::FILE);
        if (is_file($file)) {
            unlink($file);
        }
        self::$cache = null;
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
}
