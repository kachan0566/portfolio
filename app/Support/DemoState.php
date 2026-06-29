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

    private const PO_STAGE_FILE = 'po_stage_state.json';

    private const DYE_TRANSFER_FILE = 'po_dye_transfer_state.json';

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
        return (int) floor(self::effectiveReceivedQty($poId));
    }

    public static function effectiveReceivedQty(int $poId, ?object $po = null): float
    {
        $po ??= self::findBasePurchase($poId);
        if (! $po) {
            return 0.0;
        }

        $base = DemoData::purchaseOrderReceivedQty($po);

        return $base + self::receivedOverlayQty($poId);
    }

    public static function receivedOverlayQty(int $poId): float
    {
        $overlay = self::readFloatMap(self::RECEIVED_FILE);

        return (float) ($overlay[$poId] ?? 0);
    }

    /** @return array<int, float> */
    private static function readFloatMap(string $file): array
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
            $result[(int) $id] = (float) $value;
        }

        return $result;
    }

    /** @param array<int, float> $map */
    private static function writeFloatMap(string $file, array $map): void
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

    private static function findBasePurchase(int $poId): ?object
    {
        $row = DemoData::basePurchaseOrderRows()->firstWhere('id', $poId);
        if ($row) {
            $merged = array_merge($row, PurchaseOrderOverlay::overrides($poId));

            return DemoData::enrichPurchaseOrder($merged);
        }
        foreach (PurchaseOrderOverlay::additions() as $addition) {
            if ((int) ($addition['id'] ?? 0) === $poId) {
                return DemoData::enrichPurchaseOrder($addition);
            }
        }

        return null;
    }

    public static function poRemaining(int $poId): float
    {
        $po = self::findBasePurchase($poId);
        if (! $po) {
            return 0;
        }

        $ordered = DemoData::purchaseOrderOrderedQty($po);

        return max(0.0, $ordered - self::effectiveReceivedQty($poId, $po));
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
     * @param  array<string, mixed>  $receiving
     */
    public static function applyReceiving(array $receiving): void
    {
        $poId = (int) $receiving['po_id'];
        $poType = (string) ($receiving['po_type'] ?? PurchaseOrderType::PRODUCT);
        $qty = (float) ($receiving['qty'] ?? $receiving['qty_kg'] ?? $receiving['qty_meters'] ?? 0);

        if ($qty <= 0) {
            return;
        }

        $receivedOverlay = self::readFloatMap(self::RECEIVED_FILE);
        $receivedOverlay[$poId] = ($receivedOverlay[$poId] ?? 0) + $qty;
        self::writeFloatMap(self::RECEIVED_FILE, $receivedOverlay);

        if ($poType === PurchaseOrderType::YARN) {
            YarnInventory::addStockKg((int) $receiving['material_id'], $qty);
        } elseif ($poType === PurchaseOrderType::PRODUCT) {
            $productId = (int) $receiving['product_id'];
            $stockOverlay = self::readIntMap(self::STOCK_FILE);
            $stockOverlay[$productId] = ($stockOverlay[$productId] ?? 0) + (int) floor($qty);
            self::writeIntMap(self::STOCK_FILE, $stockOverlay);

            \App\Support\InboundLot::create([
                'product_id' => $productId,
                'receiving_code' => $receiving['code'] ?? null,
                'received_date' => $receiving['date'] ?? date('Y-m-d'),
                'received_qty_m' => $qty,
                'remaining_qty_m' => $qty,
                'purchase_order_id' => $poId,
                'source_type' => \App\Support\InboundLot::SOURCE_RECEIVING,
            ]);
        }

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

        \App\Support\ShipmentPlan::recordShipment($orderId, (float) $qty);
        \App\Services\Inventory\ShipmentLotAllocator::consume($productId, (float) $qty, $orderId, 'order:'.$orderId);
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

    /** @return array<int, string> */
    private static function readStringMap(string $file): array
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
            $result[(int) $id] = (string) $value;
        }

        return $result;
    }

    /** @param array<int, string> $map */
    private static function writeStringMap(string $file, array $map): void
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

    public static function effectivePoStage(int $poId): string
    {
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        if ($po === null) {
            return '';
        }

        $overlay = self::readStringMap(self::PO_STAGE_FILE);

        return $overlay[$poId] ?? $po->stage;
    }

    public static function setPoStage(int $poId, string $stage): void
    {
        $overlay = self::readStringMap(self::PO_STAGE_FILE);
        $overlay[$poId] = $stage;
        self::writeStringMap(self::PO_STAGE_FILE, $overlay);
    }

    /** 染機投入済：生機在庫から製品在庫へ移動（同一メートル、反数は換算） */
    public static function applyDyeTransfer(int $poId, int $productId, int $meters): void
    {
        if ($meters <= 0) {
            return;
        }

        $transferred = self::readIntMap(self::DYE_TRANSFER_FILE);
        if (($transferred[$poId] ?? 0) > 0) {
            return;
        }

        $transferred[$poId] = $meters;
        self::writeIntMap(self::DYE_TRANSFER_FILE, $transferred);

        $stockOverlay = self::readIntMap(self::STOCK_FILE);
        $stockOverlay[$productId] = ($stockOverlay[$productId] ?? 0) + $meters;
        self::writeIntMap(self::STOCK_FILE, $stockOverlay);

        $po = self::findBasePurchase($poId);
        \App\Support\InboundLot::create([
            'product_id' => $productId,
            'receiving_code' => null,
            'received_date' => $po?->finish_date ?? date('Y-m-d'),
            'received_qty_m' => (float) $meters,
            'remaining_qty_m' => (float) $meters,
            'purchase_order_id' => $poId,
            'source_type' => \App\Support\InboundLot::SOURCE_DYE_TRANSFER,
        ]);
    }
}
