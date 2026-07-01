<?php

namespace App\Support;

/**
 * 発注と受注の紐づけを JSON ファイルに記録する（デモ用）。
 * 生産意図（この発注はどの受注のために作るか）を表す。在庫引当とは別管理。
 *
 * データ形式: { "po_id": order_id, ... }
 */
class PurchaseOrderLink
{
    private const FILE = 'po_order_links.json';

    /** @return array<int, int> [po_id => order_id] */
    public static function all(): array
    {
        $path = storage_path('app/'.self::FILE);
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $poId => $orderId) {
            $result[(int) $poId] = (int) $orderId;
        }

        return $result;
    }

    public static function orderIdForPurchase(int $poId, ?int $staticOrderId = null): ?int
    {
        $links = self::all();

        if (isset($links[$poId])) {
            return $links[$poId];
        }

        return $staticOrderId;
    }

    public static function link(int $poId, int $orderId): void
    {
        $all = self::all();
        $all[$poId] = $orderId;

        self::write($all);
    }

    /** JSON に保存された紐づけのみ解除する（DemoData の固定値は変更しない） */
    public static function unlink(int $poId): void
    {
        $all = self::all();
        unset($all[$poId]);
        self::write($all);
    }

    /** 「使用」ボタンなどで JSON に保存された紐づけかどうか */
    public static function isStoredLink(int $poId): bool
    {
        $path = storage_path('app/'.self::FILE);
        if (! is_file($path)) {
            return false;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && array_key_exists($poId, $data);
    }

    /** @param array<int, int> $links */
    private static function write(array $links): void
    {
        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /** 受注に紐づいた発注一覧（同じ品番） */
    public static function linkedToOrder(int $orderId, int $productId): \Illuminate\Support\Collection
    {
        return DemoData::purchaseOrders()
            ->where('product_id', $productId)
            ->filter(fn ($po) => $po->order_id === $orderId)
            ->values();
    }

    /** どの受注にも紐づいていないフリー発注（同じ品番） */
    public static function freeForProduct(int $productId): \Illuminate\Support\Collection
    {
        return DemoData::purchaseOrders()
            ->where('product_id', $productId)
            ->filter(fn ($po) => $po->order_id === null)
            ->values();
    }
}
