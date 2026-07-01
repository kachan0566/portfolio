<?php

namespace App\Support;

/**
 * 入荷時の発注引当 → 現在庫引当 変換履歴（デモ用 JSON）。
 */
class AllocationConversion
{
    private const FILE = 'allocation_conversions.json';

    /**
     * @return list<array{id: int, at: string, receiving_code: string, po_id: int, order_id: int, qty: int, from_type: string, to_type: string}>
     */
    public static function allEvents(): array
    {
        $path = storage_path('app/'.self::FILE);
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['events']) || ! is_array($data['events'])) {
            return [];
        }

        return array_values($data['events']);
    }

    /** @return list<array<string, mixed>> */
    public static function forOrder(int $orderId): array
    {
        return array_values(array_filter(
            self::allEvents(),
            fn ($e) => (int) ($e['order_id'] ?? 0) === $orderId
        ));
    }

    /** @return list<array<string, mixed>> */
    public static function forProduct(int $productId): array
    {
        $poIds = DemoData::purchaseOrders()
            ->where('product_id', $productId)
            ->pluck('id')
            ->all();

        return array_values(array_filter(
            self::allEvents(),
            fn ($e) => in_array((int) ($e['po_id'] ?? 0), $poIds, true)
        ));
    }

    /**
     * @param  array{receiving_code: string, po_id: int, order_id: int, qty: int}  $event
     */
    public static function record(array $event): void
    {
        $events = self::allEvents();
        $nextId = empty($events) ? 1 : max(array_column($events, 'id')) + 1;

        $events[] = [
            'id' => $nextId,
            'at' => now()->toIso8601String(),
            'receiving_code' => $event['receiving_code'],
            'po_id' => (int) $event['po_id'],
            'order_id' => (int) $event['order_id'],
            'qty' => (int) $event['qty'],
            'from_type' => 'po',
            'to_type' => 'stock',
        ];

        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['events' => $events], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
