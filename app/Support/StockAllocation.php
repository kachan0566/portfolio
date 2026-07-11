<?php

namespace App\Support;

use App\Models\OrderAllocation;
use Illuminate\Support\Collection;

/**
 * 受注への在庫引当を記録する。
 * DB（order_allocations）が投入済みなら DB を正とし、未投入時は JSON ファイルに保存する。
 *
 * 行ベースのデータ形式:
 * {
 *   "lines": [
 *     { "product_id": 1, "order_id": 5, "po_id": 3, "qty": 90, "type": "stock" }
 *   ]
 * }
 *
 * - type     … "stock"（現在庫引当）| "po"（発注引当）
 * - order_id … どの受注に充てるか
 * - po_id    … 来歴の発注ID
 * - qty_tan  … 反数（正）
 * - qty      … 標準換算メートル（派生・ロット連携用）
 */
class StockAllocation
{
    public const TYPE_STOCK = 'stock';

    public const TYPE_PO = 'po';

    private const FILE = 'stock_allocations.json';

    /** @var list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: int, type: string}>|null */
    private static ?array $linesCache = null;

    /** @internal テスト用にメモリキャッシュを破棄する */
    public static function resetCacheForTesting(): void
    {
        self::$linesCache = null;
    }

    /**
     * @return list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: int, type: string}>
     */
    public static function allLines(): array
    {
        if (DemoData::usesOrderAllocationDatabase()) {
            return OrderAllocation::query()
                ->orderBy('id')
                ->get()
                ->map(fn (OrderAllocation $row) => [
                    'product_id' => (int) $row->product_id,
                    'order_id' => (int) $row->order_id,
                    'po_id' => (int) ($row->purchase_order_id ?? 0),
                    'qty_tan' => QtyHelper::roundTan((float) $row->qty_tan),
                    'qty' => (int) $row->qty_m,
                    'type' => (string) $row->allocation_type,
                ])
                ->values()
                ->all();
        }

        if (self::$linesCache !== null) {
            return self::$linesCache;
        }

        $path = storage_path('app/'.self::FILE);
        if (! is_file($path)) {
            return self::$linesCache = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return self::$linesCache = [];
        }

        if (isset($data['lines']) && is_array($data['lines'])) {
            return self::$linesCache = self::normalizeLines($data['lines']);
        }

        return self::$linesCache = self::migrateLegacyFormat($data);
    }

    /**
     * @param  array<mixed, mixed>  $legacy
     * @return list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: int, type: string}>
     */
    private static function migrateLegacyFormat(array $legacy): array
    {
        $lines = [];

        foreach ($legacy as $orderId => $poMap) {
            if (! is_array($poMap)) {
                continue;
            }

            $order = DemoData::orders()->firstWhere('id', (int) $orderId);
            if (! $order) {
                continue;
            }

            foreach ($poMap as $poId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }

                $lines[] = self::buildLine(
                    (int) $order->product_id,
                    (int) $orderId,
                    (int) $poId,
                    QtyHelper::tanCount($qty, (int) $order->product_id),
                    self::inferType((int) $poId, $qty)
                );
            }
        }

        if (! empty($lines)) {
            self::write($lines);
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: int, type: string}>
     */
    private static function normalizeLines(array $lines): array
    {
        $result = [];
        $needsRewrite = false;

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $qtyTan = isset($line['qty_tan'])
                ? QtyHelper::roundTan((float) $line['qty_tan'])
                : QtyHelper::tanCount((int) ($line['qty'] ?? 0), (int) ($line['product_id'] ?? 0));
            if ($qtyTan <= 0) {
                continue;
            }

            $poId = (int) ($line['po_id'] ?? 0);
            $productId = (int) ($line['product_id'] ?? 0);
            $type = $line['type'] ?? null;
            if (! in_array($type, [self::TYPE_STOCK, self::TYPE_PO], true)) {
                $type = self::inferType($poId, QtyHelper::metersFromTan($qtyTan, $productId));
                $needsRewrite = true;
            }

            $result[] = self::buildLine(
                $productId,
                (int) ($line['order_id'] ?? 0),
                $poId,
                $qtyTan,
                $type
            );
        }

        if ($needsRewrite && ! empty($result)) {
            self::write($result);
        }

        return $result;
    }

    private static function inferType(int $poId, int $qty): string
    {
        if ($poId <= 0) {
            return self::TYPE_STOCK;
        }

        $received = DemoState::effectiveReceived($poId);
        $remaining = DemoState::poRemaining($poId);

        if ($received > 0 && $remaining === 0) {
            return self::TYPE_STOCK;
        }

        if ($received === 0 && $remaining > 0) {
            return self::TYPE_PO;
        }

        return $qty <= $remaining ? self::TYPE_PO : self::TYPE_STOCK;
    }

    /**
     * @return array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: int, type: string}
     */
    private static function buildLine(int $productId, int $orderId, int $poId, float $qtyTan, string $type): array
    {
        $qtyTan = QtyHelper::roundTan($qtyTan);

        return [
            'product_id' => $productId,
            'order_id' => $orderId,
            'po_id' => $poId,
            'qty_tan' => $qtyTan,
            'qty' => QtyHelper::metersFromTan($qtyTan, $productId),
            'type' => $type,
        ];
    }

    /**
     * @param  list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: int, type: string}>  $lines
     */
    private static function write(array $lines): void
    {
        $lines = array_values($lines);

        if (DemoData::usesOrderAllocationDatabase()) {
            OrderAllocation::syncAll($lines);

            return;
        }

        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['lines' => $lines], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        self::$linesCache = $lines;
    }

    /** @return Collection<int, object> */
    public static function linesForProduct(int $productId): Collection
    {
        return collect(self::allLines())
            ->filter(fn ($line) => $line['product_id'] === $productId)
            ->map(fn ($line) => (object) $line)
            ->values();
    }

    /** @return Collection<int, object> */
    public static function linesForOrder(int $orderId): Collection
    {
        return collect(self::allLines())
            ->filter(fn ($line) => $line['order_id'] === $orderId)
            ->map(fn ($line) => (object) $line)
            ->values();
    }

    /** @return Collection<int, object> */
    public static function stockLinesForOrder(int $orderId): Collection
    {
        return self::linesForOrder($orderId)
            ->filter(fn ($line) => $line->type === self::TYPE_STOCK)
            ->values();
    }

    /** @return Collection<int, object> */
    public static function poLinesForOrder(int $orderId): Collection
    {
        return self::linesForOrder($orderId)
            ->filter(fn ($line) => $line->type === self::TYPE_PO)
            ->values();
    }

    public static function stockAllocatedForOrder(int $orderId): int
    {
        return (int) self::stockLinesForOrder($orderId)->sum('qty');
    }

    public static function poAllocatedForOrder(int $orderId): int
    {
        return (int) self::poLinesForOrder($orderId)->sum('qty');
    }

    public static function get(int $orderId): int
    {
        return self::stockAllocatedForOrder($orderId) + self::poAllocatedForOrder($orderId);
    }

    public static function shippableQty(int $orderId): int
    {
        return max(0, self::stockAllocatedForOrder($orderId) - self::alreadyShippedFromStock($orderId));
    }

    private static function alreadyShippedFromStock(int $orderId): int
    {
        $shipped = DemoState::effectiveShipped($orderId);
        $stockAlloc = self::stockAllocatedForOrder($orderId);

        return min($shipped, $stockAlloc);
    }

    /**
     * @return array<int, int> [po_id => qty]
     */
    public static function getPoMap(int $orderId, ?string $type = null): array
    {
        $map = [];
        $lines = $type === null
            ? self::linesForOrder($orderId)
            : self::linesForOrder($orderId)->filter(fn ($l) => $l->type === $type);

        foreach ($lines as $line) {
            $map[$line->po_id] = ($map[$line->po_id] ?? 0) + $line->qty;
        }

        return $map;
    }

    /**
     * 品番内の発注別・区分別引当合計。
     *
     * @return array{stock: array<int, int>, po: array<int, int>}
     */
    public static function usageByPoAndType(int $productId): array
    {
        $usage = ['stock' => [], 'po' => []];

        foreach (self::linesForProduct($productId) as $line) {
            $key = $line->type === self::TYPE_PO ? 'po' : 'stock';
            $usage[$key][$line->po_id] = ($usage[$key][$line->po_id] ?? 0) + $line->qty;
        }

        return $usage;
    }

    /** @return array<int, int> */
    public static function poUsageForProduct(int $productId): array
    {
        $usage = self::usageByPoAndType($productId);
        $merged = [];

        foreach (array_merge($usage['stock'], $usage['po']) as $poId => $qty) {
            $merged[$poId] = ($merged[$poId] ?? 0) + $qty;
        }

        foreach ($usage['po'] as $poId => $qty) {
            $merged[$poId] = ($merged[$poId] ?? 0);
        }

        $allPoIds = array_unique(array_merge(array_keys($usage['stock']), array_keys($usage['po'])));
        $result = [];
        foreach ($allPoIds as $poId) {
            $result[$poId] = ($usage['stock'][$poId] ?? 0) + ($usage['po'][$poId] ?? 0);
        }

        return $result;
    }

    public static function stockUsageForProduct(int $productId): int
    {
        return (int) self::linesForProduct($productId)
            ->filter(fn ($l) => $l->type === self::TYPE_STOCK)
            ->sum('qty');
    }

    /**
     * @return Collection<int, Collection<int, object>>
     */
    public static function forProduct(int $productId): Collection
    {
        $orderIds = DemoData::orders()
            ->where('product_id', $productId)
            ->pluck('id');

        return self::linesForProduct($productId)
            ->groupBy('order_id')
            ->only($orderIds->all())
            ->map(fn ($group) => $group->values());
    }

    public static function hasForProduct(int $productId): bool
    {
        return self::linesForProduct($productId)->isNotEmpty();
    }

    /**
     * @param  list<array{order_id: int, po_id: int, qty: int, type: string}>  $lines
     */
    public static function saveLinesForProduct(int $productId, array $lines): void
    {
        $built = [];

        foreach ($lines as $line) {
            $qtyTan = QtyHelper::roundTan((float) ($line['qty_tan'] ?? $line['qty'] ?? 0));
            if ($qtyTan <= 0 && isset($line['qty'])) {
                $qtyTan = QtyHelper::tanCount((int) $line['qty'], $productId);
            }
            if ($qtyTan <= 0) {
                continue;
            }

            $type = $line['type'] ?? self::TYPE_STOCK;
            if (! in_array($type, [self::TYPE_STOCK, self::TYPE_PO], true)) {
                $type = self::TYPE_STOCK;
            }

            $built[] = self::buildLine(
                $productId,
                (int) ($line['order_id'] ?? 0),
                (int) ($line['po_id'] ?? 0),
                $qtyTan,
                $type
            );
        }

        if (DemoData::usesOrderAllocationDatabase()) {
            OrderAllocation::replaceForProduct($productId, $built);

            return;
        }

        $all = collect(self::allLines())
            ->reject(fn ($line) => $line['product_id'] === $productId)
            ->values()
            ->all();

        self::write(array_merge($all, $built));
    }

    /**
     * フォーム形式 [order_id => [type => [po_id => qty]]] から行を保存する。
     *
     * @param  array<int, array<string, array<int, int>>>  $orderTypePoMaps
     */
    public static function saveFromTypedMaps(int $productId, array $orderTypePoMaps): void
    {
        $lines = [];

        foreach ($orderTypePoMaps as $orderId => $typeMaps) {
            if (! is_array($typeMaps)) {
                continue;
            }

            foreach ([self::TYPE_STOCK, self::TYPE_PO] as $type) {
                $poMap = $typeMaps[$type] ?? [];
                if (! is_array($poMap)) {
                    continue;
                }

                foreach ($poMap as $poId => $qtyTan) {
                    $qtyTan = QtyHelper::roundTan((float) $qtyTan);
                    if ($qtyTan <= 0) {
                        continue;
                    }

                    $lines[] = [
                        'order_id' => (int) $orderId,
                        'po_id' => (int) $poId,
                        'qty_tan' => $qtyTan,
                        'type' => $type,
                    ];
                }
            }
        }

        self::saveLinesForProduct($productId, $lines);
    }

    /** @deprecated saveFromTypedMaps を使用 */
    public static function saveFromOrderPoMaps(int $productId, array $orderPoMaps): void
    {
        $typed = [];
        foreach ($orderPoMaps as $orderId => $poMap) {
            $typed[$orderId] = [self::TYPE_STOCK => $poMap];
        }
        self::saveFromTypedMaps($productId, $typed);
    }

    public static function addLine(int $productId, int $orderId, int $poId, float $qtyTan, string $type = self::TYPE_STOCK): void
    {
        $qtyTan = QtyHelper::roundTan($qtyTan);
        if ($qtyTan <= 0) {
            return;
        }

        if (DemoData::usesOrderAllocationDatabase()) {
            OrderAllocation::upsertLine(self::buildLine($productId, $orderId, $poId, $qtyTan, $type));

            return;
        }

        $all = self::allLines();
        $merged = false;

        foreach ($all as &$line) {
            if ($line['product_id'] === $productId
                && $line['order_id'] === $orderId
                && $line['po_id'] === $poId
                && $line['type'] === $type) {
                $newTan = QtyHelper::roundTan($line['qty_tan'] + $qtyTan);
                $line['qty_tan'] = $newTan;
                $line['qty'] = QtyHelper::metersFromTan($newTan, $productId);
                $merged = true;
                break;
            }
        }
        unset($line);

        if (! $merged) {
            $all[] = self::buildLine($productId, $orderId, $poId, $qtyTan, $type);
        }

        self::write($all);
    }

    public static function clearForOrder(int $orderId): void
    {
        if (DemoData::usesOrderAllocationDatabase()) {
            OrderAllocation::deleteForOrder($orderId);

            return;
        }

        $all = collect(self::allLines())
            ->reject(fn ($line) => $line['order_id'] === $orderId)
            ->values()
            ->all();

        self::write($all);
    }

    public static function removeLineFromOrder(int $orderId, int $poId, string $type): void
    {
        if (DemoData::usesOrderAllocationDatabase()) {
            OrderAllocation::deleteLine($orderId, $poId, $type);

            return;
        }

        $all = collect(self::allLines())
            ->reject(fn ($line) => $line['order_id'] === $orderId
                && $line['po_id'] === $poId
                && $line['type'] === $type)
            ->values()
            ->all();

        self::write($all);
    }

    /** @deprecated removeLineFromOrder を使用 */
    public static function removePoFromOrder(int $orderId, int $poId): void
    {
        $all = collect(self::allLines())
            ->reject(fn ($line) => $line['order_id'] === $orderId && $line['po_id'] === $poId)
            ->values()
            ->all();

        self::write($all);
    }

    /**
     * フォーム入力を検証する。エラー時はメッセージ文字列、成功時は null。
     *
     * @param  array<int, array<string, array<int|string, int>>>  $input  allocations[order_id][stock|po][po_id]
     */
    public static function validateSubmission(int $productId, array $input): ?string
    {
        $allOrders = DemoData::orders()->where('product_id', $productId)->keyBy('id');
        $purchases = DemoData::purchaseOrders()
            ->where('product_id', $productId)
            ->keyBy('id');

        $stockUsageByPo = [];
        $poUsageByPo = [];
        $totalStockAlloc = 0;
        $effectiveStock = DemoState::effectiveStock($productId);

        foreach ($input as $orderId => $typeMaps) {
            $orderId = (int) $orderId;
            if (! is_array($typeMaps)) {
                continue;
            }

            $order = $allOrders->get($orderId);
            if (! $order) {
                continue;
            }

            $remaining = DemoState::orderRemaining($orderId);
            $orderStockTotal = 0;
            $orderPoTotal = 0;

            foreach ([self::TYPE_STOCK, self::TYPE_PO] as $type) {
                $poMap = $typeMaps[$type] ?? [];
                if (! is_array($poMap)) {
                    continue;
                }

                foreach ($poMap as $poKey => $qtyTan) {
                    $qtyTan = max(0.0, (float) $qtyTan);
                    if ($qtyTan <= 0) {
                        continue;
                    }

                    if (! QtyHelper::isIntegerTan($qtyTan)) {
                        return "受注 {$order->code} の引当反数は整数で入力してください。";
                    }

                    $qty = QtyHelper::metersFromTan($qtyTan, $productId);

                    $poId = self::parsePoId($poKey);
                    if ($poId === null) {
                        return "受注 {$order->code} は割当元の発注を選択してください。";
                    }

                    $po = $purchases->get($poId);
                    if (! $po) {
                        return "受注 {$order->code} の割当元発注が無効です。";
                    }

                    if ($type === self::TYPE_STOCK) {
                        if (! DemoState::poHasReceived($poId)) {
                            return "発注 {$po->code} は未入荷のため、現在庫引当の対象にできません。";
                        }

                        $received = DemoState::effectiveReceived($poId);
                        $usedFromPo = ($stockUsageByPo[$poId] ?? 0) + $qty;
                        if ($usedFromPo > $received) {
                            return "発注 {$po->code} の入荷済み数量（".QtyHelper::format($received, $productId).'）を超える現在庫引当（'.QtyHelper::format($usedFromPo, $productId).'）はできません。';
                        }

                        $stockUsageByPo[$poId] = $usedFromPo;
                        $orderStockTotal += $qty;
                        $totalStockAlloc += $qty;
                    } else {
                        $poRemaining = DemoState::poRemaining($poId);
                        if ($poRemaining <= 0) {
                            return "発注 {$po->code} に発注残がないため、発注引当の対象にできません。";
                        }

                        $usedFromPo = ($poUsageByPo[$poId] ?? 0) + $qty;
                        if ($usedFromPo > $poRemaining) {
                            return "発注 {$po->code} の発注残（".QtyHelper::format($poRemaining, $productId).'）を超える発注引当（'.QtyHelper::format($usedFromPo, $productId).'）はできません。';
                        }

                        $poUsageByPo[$poId] = $usedFromPo;
                        $orderPoTotal += $qty;
                    }
                }
            }

            if ($orderStockTotal + $orderPoTotal > $remaining) {
                return "受注 {$order->code} への引当（".QtyHelper::format($orderStockTotal + $orderPoTotal, $productId).'）が受注残（'.QtyHelper::format($remaining, $productId).'）を超えています。';
            }
        }

        if ($totalStockAlloc > $effectiveStock) {
            return '現在庫引当合計（'.QtyHelper::format($totalStockAlloc, $productId).'）が現在庫（'.QtyHelper::format($effectiveStock, $productId).'）を超えています。数量を調整してください。';
        }

        return null;
    }

    /**
     * 検証済み入力を保存用マップに変換する。
     *
     * @param  array<int, array<string, array<int|string, int>>>  $input
     * @return array<int, array<string, array<int, int>>>
     */
    public static function parseSubmission(int $productId, array $input): array
    {
        $allOrders = DemoData::orders()->where('product_id', $productId)->keyBy('id');
        $toSave = [];

        foreach ($input as $orderId => $typeMaps) {
            $orderId = (int) $orderId;
            if (! is_array($typeMaps) || ! $allOrders->has($orderId)) {
                continue;
            }

            $orderMaps = [];

            foreach ([self::TYPE_STOCK, self::TYPE_PO] as $type) {
                $poMap = $typeMaps[$type] ?? [];
                if (! is_array($poMap)) {
                    continue;
                }

                foreach ($poMap as $poKey => $qtyTan) {
                    $qtyTan = max(0.0, (float) $qtyTan);
                    $poId = self::parsePoId($poKey);
                    if ($qtyTan <= 0 || $poId === null) {
                        continue;
                    }

                    $orderMaps[$type][$poId] = QtyHelper::roundTan(($orderMaps[$type][$poId] ?? 0.0) + $qtyTan);
                }
            }

            if (! empty($orderMaps)) {
                $toSave[$orderId] = $orderMaps;
            }
        }

        return $toSave;
    }

    public static function parsePoId(mixed $key): ?int
    {
        if ($key === '' || $key === '__NEW__') {
            return null;
        }

        $id = filter_var($key, FILTER_VALIDATE_INT);

        return ($id !== false && $id > 0) ? $id : null;
    }

    /**
     * 入荷時: 発注引当を納期順に現在庫引当へ変換する。
     *
     * @return list<array{order_id: int, qty: int}>
     */
    public static function convertOnReceiving(int $poId, int $receivedQty, string $receivingCode): array
    {
        if ($receivedQty <= 0) {
            return [];
        }

        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        if (! $po) {
            return [];
        }

        $productId = (int) $po->product_id;
        $all = self::allLines();

        $poLines = collect($all)
            ->filter(fn ($l) => $l['po_id'] === $poId && $l['type'] === self::TYPE_PO && $l['qty'] > 0)
            ->values();

        if ($poLines->isEmpty()) {
            return [];
        }

        $orderIds = $poLines->pluck('order_id')->unique()->all();
        $orders = DemoData::orders()
            ->whereIn('id', $orderIds)
            ->sortBy([
                ['due_date', 'asc'],
                ['order_date', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $converted = [];
        $remainingReceive = $receivedQty;

        foreach ($orders as $order) {
            if ($remainingReceive <= 0) {
                break;
            }

            $orderId = (int) $order->id;
            $poAllocQty = (int) $poLines->where('order_id', $orderId)->sum('qty');
            if ($poAllocQty <= 0) {
                continue;
            }

            $convertQty = min($poAllocQty, $remainingReceive);
            $remainingReceive -= $convertQty;
            $leftToConvert = $convertQty;

            foreach ($all as &$line) {
                if ($leftToConvert <= 0) {
                    break;
                }
                if ($line['po_id'] !== $poId
                    || $line['type'] !== self::TYPE_PO
                    || $line['order_id'] !== $orderId
                    || $line['qty'] <= 0) {
                    continue;
                }

                $move = min($line['qty'], $leftToConvert);
                $line['qty'] -= $move;
                $leftToConvert -= $move;

                self::addLineToArray($all, $productId, $orderId, $poId, $move, self::TYPE_STOCK);
            }
            unset($line);

            $all = collect($all)->filter(fn ($l) => $l['qty'] > 0)->values()->all();

            AllocationConversion::record([
                'receiving_code' => $receivingCode,
                'po_id' => $poId,
                'order_id' => $orderId,
                'qty' => $convertQty,
            ]);

            $converted[] = ['order_id' => $orderId, 'qty' => $convertQty];
        }

        self::write($all);

        return $converted;
    }

    /**
     * @param  list<array{product_id: int, order_id: int, po_id: int, qty: int, type: string}>  $all
     */
    private static function addLineToArray(
        array &$all,
        int $productId,
        int $orderId,
        int $poId,
        int $qty,
        string $type = self::TYPE_STOCK
    ): void {
        if ($qty <= 0) {
            return;
        }

        foreach ($all as &$line) {
            if ($line['product_id'] === $productId
                && $line['order_id'] === $orderId
                && $line['po_id'] === $poId
                && $line['type'] === $type) {
                $line['qty'] += $qty;

                return;
            }
        }
        unset($line);

        $all[] = self::buildLine($productId, $orderId, $poId, $qty, $type);
    }

    /**
     * @return array{
     *     allocations: Collection,
     *     allocatedTotal: int,
     *     stockAllocatedTotal: int,
     *     poAllocatedTotal: int,
     *     unallocatedStock: int,
     *     allocationShortage: int,
     *     isRecorded: bool,
     * }
     */
    public static function resolveForProduct(object $product, Collection $orders, Collection $purchases): array
    {
        $pending = $orders
            ->where('remaining', '>', 0)
            ->sortBy([
                ['due_date', 'asc'],
                ['order_date', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $isRecorded = self::hasForProduct($product->id);
        $savedLines = self::forProduct($product->id);
        $effectiveStock = DemoState::effectiveStock($product->id);

        $allocations = $pending->map(function ($order) use ($isRecorded, $savedLines) {
            if ($isRecorded) {
                $lines = $savedLines->get($order->id, collect());
                $stockAlloc = (int) $lines->where('type', self::TYPE_STOCK)->sum('qty');
                $poAlloc = (int) $lines->where('type', self::TYPE_PO)->sum('qty');
                $allocated = $stockAlloc + $poAlloc;
            } else {
                $lines = collect();
                $stockAlloc = 0;
                $poAlloc = 0;
                $allocated = 0;
            }

            $status = self::buildStatus($stockAlloc, $poAlloc, $order->remaining);

            return (object) [
                'order' => $order,
                'allocated' => $allocated,
                'stock_allocated' => $stockAlloc,
                'po_allocated' => $poAlloc,
                'bar_allocated' => $isRecorded ? $allocated : 0,
                'bar_rate' => $order->remaining > 0 && $isRecorded
                    ? (int) round($allocated / $order->remaining * 100)
                    : 0,
                'lines' => $isRecorded ? $lines : collect(),
                'stock_lines' => $isRecorded ? $lines->where('type', self::TYPE_STOCK)->values() : collect(),
                'po_lines' => $isRecorded ? $lines->where('type', self::TYPE_PO)->values() : collect(),
                'unallocated' => $isRecorded ? max(0, $order->remaining - $allocated) : $order->remaining,
                'status' => $status['status'],
                'badge_class' => $status['badge_class'],
                'shippable_status' => $status['shippable_status'],
                'shippable_badge' => $status['shippable_badge'],
            ];
        });

        $stockAllocatedTotal = $isRecorded ? self::stockUsageForProduct($product->id) : 0;
        $poAllocatedTotal = $isRecorded
            ? (int) self::linesForProduct($product->id)->where('type', self::TYPE_PO)->sum('qty')
            : 0;

        return [
            'allocations' => $allocations,
            'allocatedTotal' => $stockAllocatedTotal + $poAllocatedTotal,
            'stockAllocatedTotal' => $stockAllocatedTotal,
            'poAllocatedTotal' => $poAllocatedTotal,
            'unallocatedStock' => max(0, $effectiveStock - $stockAllocatedTotal),
            'allocationShortage' => (int) $allocations->sum('unallocated'),
            'isRecorded' => $isRecorded,
        ];
    }

    /**
     * @return array{
     *     status: ?string,
     *     badge_class: ?string,
     *     shippable_status: ?string,
     *     shippable_badge: ?string,
     *     allocated: int,
     *     stock_allocated: int,
     *     po_allocated: int,
     *     remaining: int,
     *     shippable: bool,
     * }
     */
    public static function statusForOrder(object $order): array
    {
        $remaining = DemoState::orderRemaining((int) $order->id);
        $stockAllocated = self::stockAllocatedForOrder((int) $order->id);
        $poAllocated = self::poAllocatedForOrder((int) $order->id);
        $allocated = $stockAllocated + $poAllocated;

        if ($remaining === 0) {
            return [
                'status' => null,
                'badge_class' => null,
                'shippable_status' => null,
                'shippable_badge' => null,
                'allocated' => 0,
                'stock_allocated' => 0,
                'po_allocated' => 0,
                'remaining' => 0,
                'shippable' => false,
            ];
        }

        $status = self::buildStatus($stockAllocated, $poAllocated, $remaining);

        return [
            'status' => $status['status'],
            'badge_class' => $status['badge_class'],
            'shippable_status' => $status['shippable_status'],
            'shippable_badge' => $status['shippable_badge'],
            'allocated' => $allocated,
            'stock_allocated' => $stockAllocated,
            'po_allocated' => $poAllocated,
            'remaining' => $remaining,
            'shippable' => $status['shippable'],
        ];
    }

    /**
     * @return array{status: string, badge_class: string, shippable_status: ?string, shippable_badge: ?string, shippable: bool}
     */
    private static function buildStatus(int $stockAllocated, int $poAllocated, int $remaining): array
    {
        $total = $stockAllocated + $poAllocated;
        $shippable = $stockAllocated >= $remaining;

        if ($total === 0) {
            return [
                'status' => '未引当',
                'badge_class' => 'badge-rose',
                'shippable_status' => null,
                'shippable_badge' => null,
                'shippable' => false,
            ];
        }

        if ($total < $remaining) {
            return [
                'status' => '一部引当',
                'badge_class' => 'badge-amber',
                'shippable_status' => null,
                'shippable_badge' => null,
                'shippable' => false,
            ];
        }

        if ($shippable) {
            return [
                'status' => '引当完了',
                'badge_class' => 'badge-green',
                'shippable_status' => '出荷可能',
                'shippable_badge' => 'badge-green',
                'shippable' => true,
            ];
        }

        return [
            'status' => '引当完了',
            'badge_class' => 'badge-green',
            'shippable_status' => '入荷待ち',
            'shippable_badge' => 'badge-amber',
            'shippable' => false,
        ];
    }

    /**
     * 発注ごとの未割当（現在庫引当用）。入荷済み − 既引当を、品番の未割当在庫で上限。
     */
    public static function unallocatedStockFromPo(int $productId, int $poId): int
    {
        if (! DemoState::poHasReceived($poId)) {
            return 0;
        }

        $stockUsed = self::usageByPoAndType($productId)['stock'][$poId] ?? 0;
        $perPo = max(0, DemoState::effectiveReceived($poId) - $stockUsed);
        $globalRoom = self::unallocatedStockForProduct($productId);

        return min($perPo, $globalRoom);
    }

    /**
     * 発注ごとの未割当（発注引当用）。発注残 − 既引当。
     */
    public static function unallocatedPoFromPo(int $productId, int $poId): int
    {
        if (! DemoState::poHasRemaining($poId)) {
            return 0;
        }

        $poUsed = self::usageByPoAndType($productId)['po'][$poId] ?? 0;

        return max(0, DemoState::poRemaining($poId) - $poUsed);
    }

    /**
     * 引当可能な発注オプションを区分別に返す。
     *
     * @return array{stock: Collection, po: Collection}
     */
    public static function poOptionsForProduct(int $productId): array
    {
        return self::poOptionsFromPurchases(
            DemoData::purchaseOrders()->where('product_id', $productId),
            $productId
        );
    }

    /**
     * @return array{stock: Collection, po: Collection}
     */
    public static function poOptionsFromPurchases(Collection $purchases, int $productId): array
    {
        $stockOptions = $purchases
            ->filter(fn ($po) => DemoState::poHasReceived($po->id))
            ->map(function ($po) use ($productId) {
                $unallocated = self::unallocatedStockFromPo($productId, $po->id);

                return (object) [
                    'id' => $po->id,
                    'code' => $po->code,
                    'qty' => $unallocated,
                    'stage' => $po->stage,
                    'label' => $po->code.'（未割当 '.QtyHelper::format($unallocated, $productId).' / '.$po->stage.'）',
                ];
            })
            ->values();

        $poOptions = $purchases
            ->filter(fn ($po) => DemoState::poHasRemaining($po->id))
            ->map(function ($po) use ($productId) {
                $unallocated = self::unallocatedPoFromPo($productId, $po->id);

                return (object) [
                    'id' => $po->id,
                    'code' => $po->code,
                    'qty' => $unallocated,
                    'stage' => $po->stage,
                    'label' => $po->code.'（未割当 '.QtyHelper::format($unallocated, $productId).' / '.$po->stage.'）',
                ];
            })
            ->values();

        return ['stock' => $stockOptions, 'po' => $poOptions];
    }

    /** 品番の未割当在庫（現在庫 − 現在庫引当合計） */
    public static function unallocatedStockForProduct(int $productId): int
    {
        return max(0, DemoState::effectiveStock($productId) - self::stockUsageForProduct($productId));
    }

    /** 品番の未引当の発注残（各発注の発注残 − 発注引当合計の合計） */
    public static function unallocatedPoRemainingForProduct(int $productId): int
    {
        $poUsage = self::usageByPoAndType($productId)['po'];
        $total = 0;

        foreach (DemoData::purchaseOrders()->where('product_id', $productId) as $po) {
            $remaining = DemoState::poRemaining($po->id);
            $allocated = $poUsage[$po->id] ?? 0;
            $total += max(0, $remaining - $allocated);
        }

        return $total;
    }

    /**
     * 供給ベースの不足量（受注残 − 未割当在庫 − 未引当の発注残）。
     * 他受注との取り合いは考慮しない。
     */
    public static function supplyShortageForOrder(int $orderId): int
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return 0;
        }

        $remaining = DemoState::orderRemaining($orderId);
        if ($remaining <= 0) {
            return 0;
        }

        $supply = self::unallocatedStockForProduct($order->product_id)
            + self::unallocatedPoRemainingForProduct($order->product_id);

        return max(0, $remaining - $supply);
    }
}
