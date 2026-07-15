<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Schema;

/**
 * デモ用の可変状態オーバーレイ（入荷・出荷による received / stock / shipped の増減）。
 * DemoData 本体は変更しない。
 *
 * Phase 2: 製品在庫・出荷オーバーレイは反数（float）を正とする。
 */
class DemoState
{
    private const RECEIVED_FILE = 'po_received_state.json';

    private const STOCK_FILE = 'product_stock_state.json';

    private const STOCK_TAN_FILE = 'product_stock_tan_state.json';

    private const SHIPPED_FILE = 'order_shipped_state.json';

    private const SHIPPED_TAN_FILE = 'order_shipped_tan_state.json';

    private const SHIPPED_M_FILE = 'order_shipped_m_state.json';

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
        if (DemoData::usesPurchaseOrderDatabase()) {
            $model = PurchaseOrder::query()->with('lines')->find($poId);
            if ($model !== null) {
                return self::receivedQtyFromPurchaseOrder($model);
            }
        }

        $po ??= self::findBasePurchase($poId);
        if (! $po) {
            return 0.0;
        }

        $base = DemoData::purchaseOrderReceivedQty($po);

        return $base + self::receivedOverlayQty($poId);
    }

    private static function receivedQtyFromPurchaseOrder(PurchaseOrder $purchaseOrder): float
    {
        $type = (string) $purchaseOrder->type;

        return match ($type) {
            PurchaseOrderType::YARN => (float) $purchaseOrder->lines->sum(
                fn ($line) => (float) ($line->received_qty_kg ?? 0),
            ),
            default => (float) $purchaseOrder->lines->sum(
                fn ($line) => (int) ($line->received_qty_m ?? 0),
            ),
        };
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

    /** @return array<int, float> */
    private static function readStockTanOverlay(): array
    {
        $tanPath = storage_path('app/'.self::STOCK_TAN_FILE);
        if (is_file($tanPath)) {
            return self::readFloatMap(self::STOCK_TAN_FILE);
        }

        $legacy = self::readIntMap(self::STOCK_FILE);
        if ($legacy === []) {
            return [];
        }

        $migrated = [];
        foreach ($legacy as $productId => $meters) {
            if ($meters === 0) {
                continue;
            }
            $migrated[$productId] = QtyHelper::tanCount($meters, $productId);
        }

        if ($migrated !== []) {
            self::writeFloatMap(self::STOCK_TAN_FILE, $migrated);
        }

        return $migrated;
    }

    /** @param array<int, float> $map */
    private static function writeStockTanOverlay(array $map): void
    {
        self::writeFloatMap(self::STOCK_TAN_FILE, $map);
    }

    /** @return array<int, float> */
    private static function readShippedTanOverlay(): array
    {
        $tanPath = storage_path('app/'.self::SHIPPED_TAN_FILE);
        if (is_file($tanPath)) {
            return self::readFloatMap(self::SHIPPED_TAN_FILE);
        }

        $legacy = self::readIntMap(self::SHIPPED_FILE);
        if ($legacy === []) {
            return [];
        }

        $migrated = [];
        foreach ($legacy as $orderId => $meters) {
            if ($meters === 0) {
                continue;
            }
            $order = DemoData::orders()->firstWhere('id', $orderId);
            $productId = $order ? (int) $order->product_id : null;
            $migrated[$orderId] = QtyHelper::tanCount($meters, $productId);
        }

        if ($migrated !== []) {
            self::writeFloatMap(self::SHIPPED_TAN_FILE, $migrated);
        }

        return $migrated;
    }

    /** @param array<int, float> $map */
    private static function writeShippedTanOverlay(array $map): void
    {
        self::writeFloatMap(self::SHIPPED_TAN_FILE, $map);
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

        $ordered = match ($po->type ?? PurchaseOrderType::PRODUCT) {
            PurchaseOrderType::YARN => DemoData::purchaseOrderOrderedQty($po),
            default => DemoData::purchaseOrderOrderedMeters($po),
        };

        return max(0.0, $ordered - self::effectiveReceivedQty($poId, $po));
    }

    public static function effectiveStockTan(int $productId): float
    {
        FabricTanRoll::ensureBootstrapped();
        $rollTan = ProductRoll::stockTanForProduct($productId);
        if ($rollTan > 0) {
            return $rollTan;
        }

        $product = DemoData::findProduct($productId);
        if (! $product) {
            return 0.0;
        }

        $overlay = self::readStockTanOverlay();
        $baseTan = (float) ($product->stock_tan ?? QtyHelper::tanCount((int) ($product->stock ?? 0), $productId));

        return max(0.0, QtyHelper::roundReceivingTan($baseTan + ($overlay[$productId] ?? 0.0)));
    }

    public static function effectiveStock(int $productId): int
    {
        FabricTanRoll::ensureBootstrapped();
        $rollM = ProductRoll::stockMetersForProduct($productId);
        if ($rollM > 0) {
            return (int) round($rollM);
        }

        return QtyHelper::metersFromTan(self::effectiveStockTan($productId), $productId);
    }

    public static function effectiveShippedTan(int $orderId): float
    {
        if (DemoData::usesShipmentDatabase()) {
            $order = Order::query()->find($orderId);

            return max(0.0, QtyHelper::roundReceivingTan((float) ($order?->shipped_qty_tan ?? 0)));
        }

        $row = DemoData::findBaseOrder($orderId);
        if (! $row) {
            return 0.0;
        }

        $overlay = self::readShippedTanOverlay();
        $productId = (int) $row['product_id'];
        $baseTan = isset($row['shipped_tan'])
            ? (float) $row['shipped_tan']
            : QtyHelper::roundIntegerTan(QtyHelper::tanCount((int) ($row['shipped'] ?? 0), $productId));

        return max(0.0, QtyHelper::roundReceivingTan($baseTan + ($overlay[$orderId] ?? 0.0)));
    }

    public static function effectiveShippedM(int $orderId): int
    {
        if (DemoData::usesShipmentDatabase()) {
            $order = Order::query()->find($orderId);

            return max(0, (int) ($order?->shipped_qty_m ?? 0));
        }

        $row = DemoData::findBaseOrder($orderId);
        if (! $row) {
            return 0;
        }

        $overlay = self::readFloatMap(self::SHIPPED_M_FILE);
        $baseM = (int) ($row['shipped_meters'] ?? $row['shipped'] ?? 0);

        return max(0, (int) round($baseM + ($overlay[$orderId] ?? 0)));
    }

    public static function effectiveShipped(int $orderId): int
    {
        return self::effectiveShippedM($orderId);
    }

    public static function orderRemainingTan(int $orderId): float
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return 0.0;
        }

        if (($order->order_qty_mode ?? 'tan') === 'meters') {
            $remainingM = self::orderRemainingM($orderId);
            if ($remainingM <= 0) {
                return 0.0;
            }

            return QtyHelper::tanCountCeilForShipment($remainingM, (int) $order->product_id);
        }

        $qtyTan = (float) ($order->qty_tan ?? QtyHelper::roundIntegerTan(
            QtyHelper::tanCount((int) $order->qty, (int) $order->product_id)
        ));

        return max(0.0, QtyHelper::roundReceivingTan($qtyTan - self::effectiveShippedTan($orderId)));
    }

    public static function orderRemainingM(int $orderId): int
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return 0;
        }

        if (($order->order_qty_mode ?? 'tan') === 'meters') {
            $qtyM = (int) ($order->qty_meters ?? $order->qty ?? 0);

            return max(0, $qtyM - self::effectiveShippedM($orderId));
        }

        return QtyHelper::metersFromTan(self::orderRemainingTan($orderId), (int) $order->product_id);
    }

    public static function orderRemaining(int $orderId): int
    {
        return self::orderRemainingM($orderId);
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
            $qtyTan = isset($receiving['qty_tan'])
                ? QtyHelper::roundReceivingTan((float) $receiving['qty_tan'])
                : QtyHelper::tanCount((int) floor($qty), $productId);
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

    public static function applyShipment(int $orderId, int $productId, float $qtyTan, ?int $qtyMeters = null): void
    {
        if (Schema::hasTable('shipments')) {
            $order = DemoData::orders()->firstWhere('id', $orderId);
            $isMetersOrder = ($order->order_qty_mode ?? 'tan') === 'meters';

            \App\Services\Shipment\ShipmentRegistrar::register(
                $orderId,
                $qtyTan,
                $isMetersOrder ? ($qtyMeters ?? self::orderRemainingM($orderId)) : null,
                DemoData::today(),
                null,
                null,
            );

            return;
        }

        $order = DemoData::orders()->firstWhere('id', $orderId);
        $isMetersOrder = ($order->order_qty_mode ?? 'tan') === 'meters';

        if ($isMetersOrder) {
            $remainingM = self::orderRemainingM($orderId);
            $result = \App\Services\Inventory\ShipmentRollAllocator::allocateForMeters(
                $productId,
                (float) $remainingM,
                $orderId,
                'order:'.$orderId,
            );
        } else {
            $qtyTan = QtyHelper::roundIntegerTan($qtyTan);
            if ($qtyTan <= 0) {
                return;
            }
            $result = \App\Services\Inventory\ShipmentRollAllocator::allocate(
                $productId,
                $qtyTan,
                $orderId,
                'order:'.$orderId,
            );
        }

        $allocatedTan = (float) ($result['allocated_tan'] ?? 0);
        $allocatedM = (float) ($result['allocated_m'] ?? 0);
        if ($allocatedTan <= 0 && $allocatedM <= 0) {
            return;
        }

        $shippedTanOverlay = self::readShippedTanOverlay();
        $shippedTanOverlay[$orderId] = ($shippedTanOverlay[$orderId] ?? 0.0) + $allocatedTan;
        self::writeShippedTanOverlay($shippedTanOverlay);

        $shippedMOverlay = self::readFloatMap(self::SHIPPED_M_FILE);
        $shippedMOverlay[$orderId] = ($shippedMOverlay[$orderId] ?? 0) + $allocatedM;
        self::writeFloatMap(self::SHIPPED_M_FILE, $shippedMOverlay);

        \App\Support\ShipmentPlan::recordShipment($orderId, $allocatedM, $allocatedTan);
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
        $overlay = self::readStringMap(self::PO_STAGE_FILE);
        if (isset($overlay[$poId]) && $overlay[$poId] !== '') {
            $type = self::purchaseOrderTypeForStage($poId);

            return match ($type) {
                PurchaseOrderType::PRODUCT => PurchaseOrderStages::normalizeProductManualStage($overlay[$poId]),
                PurchaseOrderType::GREIGE => PurchaseOrderStages::normalizeGreigeManualStage($overlay[$poId]) ?? '',
                default => '',
            };
        }

        if (Schema::hasTable('purchase_orders')) {
            $po = PurchaseOrder::query()
                ->with(['lines'])
                ->find($poId);

            if ($po !== null) {
                $detail = $po->primaryLine();
                $raw = match ($po->type) {
                    PurchaseOrderType::GREIGE => $detail?->stage,
                    PurchaseOrderType::PRODUCT => $detail?->stage,
                    default => null,
                };

                return match ($po->type) {
                    PurchaseOrderType::PRODUCT => PurchaseOrderStages::normalizeProductManualStage($raw),
                    PurchaseOrderType::GREIGE => PurchaseOrderStages::normalizeGreigeManualStage($raw) ?? '',
                    default => '',
                };
            }
        }

        $row = DemoData::basePurchaseOrderRows()->firstWhere('id', $poId);
        if ($row !== null) {
            $row = array_merge($row, PurchaseOrderOverlay::overrides($poId));
        } else {
            $row = collect(PurchaseOrderOverlay::additions())->firstWhere('id', $poId);
        }

        if ($row === null) {
            return '';
        }

        $raw = (string) ($row['stage'] ?? '');
        $type = (string) ($row['type'] ?? '');

        return match ($type) {
            PurchaseOrderType::PRODUCT => PurchaseOrderStages::normalizeProductManualStage($raw !== '' ? $raw : null),
            PurchaseOrderType::GREIGE => PurchaseOrderStages::normalizeGreigeManualStage($raw !== '' ? $raw : null) ?? '',
            default => '',
        };
    }

    private static function purchaseOrderTypeForStage(int $poId): string
    {
        if (Schema::hasTable('purchase_orders')) {
            $po = PurchaseOrder::query()->find($poId);
            if ($po !== null) {
                return (string) $po->type;
            }
        }

        $row = DemoData::basePurchaseOrderRows()->firstWhere('id', $poId)
            ?? collect(PurchaseOrderOverlay::additions())->firstWhere('id', $poId);

        return (string) ($row['type'] ?? PurchaseOrderType::PRODUCT);
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
    }
}
