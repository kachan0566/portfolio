<?php

namespace App\Support;

/**
 * デモ用の可変状態オーバーレイ（入荷・出荷による received / stock / shipped の増減）。
 * DemoData 本体は変更しない。
 */
class DemoState
{
    private const RECEIVED_FILE = 'po_received_state.json';

    private const STOCK_FILE = 'product_stock_state.json';

    private const SHIPPED_FILE = 'order_shipped_state.json';

    private const RECEIVINGS_FILE = 'receivings_state.json';

    /** @return array<int, int> */
    private static function readIntMap(string $file): array
    {
        $path = storage_path('app/'.$file);
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $id => $value) {
            $result[(int) $id] = (int) $value;
        }

        return $result;
    }

    /** @param array<int, int> $map */
    private static function writeIntMap(string $file, array $map): void
    {
        $path = storage_path('app/'.$file);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function effectiveReceived(int $poId): int
    {
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        if (! $po) {
            return 0;
        }

        $overlay = self::readIntMap(self::RECEIVED_FILE);

        return (int) $po->received + ($overlay[$poId] ?? 0);
    }

    public static function poRemaining(int $poId): int
    {
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        if (! $po) {
            return 0;
        }

        return max(0, (int) $po->qty - self::effectiveReceived($poId));
    }

    public static function effectiveStock(int $productId): int
    {
        $product = DemoData::findProduct($productId);
        if (! $product) {
            return 0;
        }

        $overlay = self::readIntMap(self::STOCK_FILE);

        return max(0, (int) $product->stock + ($overlay[$productId] ?? 0));
    }

    public static function effectiveShipped(int $orderId): int
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return 0;
        }

        $overlay = self::readIntMap(self::SHIPPED_FILE);

        return (int) $order->shipped + ($overlay[$orderId] ?? 0);
    }

    public static function orderRemaining(int $orderId): int
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return 0;
        }

        return max(0, (int) $order->qty - self::effectiveShipped($orderId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function extraReceivings(): array
    {
        $path = storage_path('app/'.self::RECEIVINGS_FILE);
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array{po_id: int, product_id: int, qty: int, code: string, date: string, supplier?: string}  $receiving
     */
    public static function applyReceiving(array $receiving): void
    {
        $poId = (int) $receiving['po_id'];
        $productId = (int) $receiving['product_id'];
        $qty = (int) $receiving['qty'];

        if ($qty <= 0) {
            return;
        }

        $receivedOverlay = self::readIntMap(self::RECEIVED_FILE);
        $receivedOverlay[$poId] = ($receivedOverlay[$poId] ?? 0) + $qty;
        self::writeIntMap(self::RECEIVED_FILE, $receivedOverlay);

        $stockOverlay = self::readIntMap(self::STOCK_FILE);
        $stockOverlay[$productId] = ($stockOverlay[$productId] ?? 0) + $qty;
        self::writeIntMap(self::STOCK_FILE, $stockOverlay);

        $receivings = self::extraReceivings();
        $receivings[] = $receiving;
        $path = storage_path('app/'.self::RECEIVINGS_FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $path,
            json_encode($receivings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function applyShipment(int $orderId, int $productId, int $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $shippedOverlay = self::readIntMap(self::SHIPPED_FILE);
        $shippedOverlay[$orderId] = ($shippedOverlay[$orderId] ?? 0) + $qty;
        self::writeIntMap(self::SHIPPED_FILE, $shippedOverlay);

        $stockOverlay = self::readIntMap(self::STOCK_FILE);
        $stockOverlay[$productId] = ($stockOverlay[$productId] ?? 0) - $qty;
        self::writeIntMap(self::STOCK_FILE, $stockOverlay);
    }

    /** 入荷済み数量がある発注か */
    public static function poHasReceived(int $poId): bool
    {
        return self::effectiveReceived($poId) > 0;
    }

    /** 発注残がある発注か */
    public static function poHasRemaining(int $poId): bool
    {
        return self::poRemaining($poId) > 0;
    }
}
