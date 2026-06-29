<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 出荷ロット消化記録（デモ用 JSON）。
 */
class ShipmentLotConsumption
{
    private const FILE = 'shipment_lot_consumptions.json';

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

    /**
     * @return Collection<int, object>
     */
    public static function forProduct(int $productId): Collection
    {
        $lotIds = InboundLot::forProduct($productId)->pluck('id')->all();

        return collect(self::all())
            ->filter(fn ($line) => in_array((int) ($line['inbound_lot_id'] ?? 0), $lotIds, true))
            ->map(fn ($line) => (object) $line)
            ->values();
    }

    public static function record(int $shipmentRef, int $inboundLotId, float $qtyM, ?string $note = null): void
    {
        if ($qtyM <= 0) {
            return;
        }

        $lines = self::all();
        $lines[] = [
            'id' => (int) (collect($lines)->max('id') ?? 0) + 1,
            'shipment_ref' => $shipmentRef,
            'inbound_lot_id' => $inboundLotId,
            'consumed_qty_m' => round($qtyM, 2),
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
