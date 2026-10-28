<?php

namespace App\Support;

use App\Models\AllocationConversion;
use App\Models\Order;
use App\Models\OrderAllocation;
use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;

/**
 * 受注への在庫引当を記録する。
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
    /** 引当区分: 現在庫引当 */
    public const TYPE_STOCK = 'stock';

    /** 引当区分: 発注引当 */
    public const TYPE_PO = 'po';

    /**
     * order_allocations 全行を引当行配列として返す。
     *
     * @return list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}>
     */
    public static function allLines(): array
    {
        return OrderAllocation::query()
            ->orderBy('id')
            ->get()
            ->map(fn (OrderAllocation $row) => [
                'product_id' => (int) $row->product_id,
                'order_id' => (int) $row->order_id,
                'po_id' => (int) ($row->purchase_order_id ?? 0),
                'qty_tan' => QtyHelper::roundTan((float) $row->qty_tan),
                'qty' => (float) $row->qty_m,
                'type' => (string) $row->allocation_type,
            ])
            ->values()
            ->all();
    }

    /**
     * 発注の入荷状況と数量から引当区分（stock / po）を推定する。
     *
     * @param  int  $poId  発注 ID。0 以下は現在庫扱い
     * @param  float  $qty  引当メートル数（発注残との比較用）
     * @return string  self::TYPE_STOCK または self::TYPE_PO
     */
    private static function inferType(int $poId, float $qty): string
    {
        if ($poId <= 0) {
            return self::TYPE_STOCK;
        }

        $received = PurchaseOrder::receivedQtyFor($poId);
        $remaining = PurchaseOrder::remainingQtyFor($poId);

        if ($received > 0 && $remaining === 0) {
            return self::TYPE_STOCK;
        }

        if ($received === 0 && $remaining > 0) {
            return self::TYPE_PO;
        }

        return $qty <= $remaining ? self::TYPE_PO : self::TYPE_STOCK;
    }

    /**
     * 1 行分の引当データ（反数・換算 m・区分）を組み立てる。
     *
     * @param  int  $productId  品番 ID
     * @param  int  $orderId  受注 ID
     * @param  int  $poId  割当元発注 ID
     * @param  float  $qtyTan  引当反数
     * @param  string  $type  引当区分（stock / po）
     * @return array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}
     */
    private static function buildLine(int $productId, int $orderId, int $poId, float $qtyTan, string $type): array
    {
        $qtyTan = QtyHelper::roundTan($qtyTan);

        return [
            'product_id' => $productId,
            'order_id' => $orderId,
            'po_id' => $poId,
            'qty_tan' => $qtyTan,
            'qty' => self::allocationMetersFromTan($productId, $poId, $qtyTan, $type),
            'type' => $type,
        ];
    }

    /**
     * 引当反数から保存用のメートル数（qty）を算出する。
     *
     * @param  int  $productId  品番 ID（標準換算用）
     * @param  int  $poId  発注 ID（在庫反の実測平均用。0 は品番全体）
     * @param  float  $qtyTan  引当反数
     * @param  string  $type  引当区分（stock は実測平均、po は標準換算）
     * @return float  メートル数（m）
     */
    private static function allocationMetersFromTan(int $productId, int $poId, float $qtyTan, string $type): float
    {
        if ($type === self::TYPE_PO || $qtyTan <= 0) {
            return QtyHelper::metersFromTan($qtyTan, $productId);
        }

        $rolls = $poId > 0
            ? ProductStock::inStockRollTotalsForPo($poId)
            : ProductStock::inStockRollTotals($productId);

        if ($rolls->tan > 0) {
            return round($qtyTan * ($rolls->meters / $rolls->tan), 2);
        }

        return QtyHelper::metersFromTan($qtyTan, $productId);
    }

    /**
     * 引当行配列を order_allocations に全件同期する。
     *
     * @param  list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}>  $lines  保存する引当行
     * @return void
     */
    private static function write(array $lines): void
    {
        OrderAllocation::syncAll(array_values($lines));
    }

    /**
     * 品番に紐づく引当行をオブジェクトのコレクションで返す。
     *
     * @param  int  $productId  品番 ID
     * @return Collection<int, object>
     */
    public static function linesForProduct(int $productId): Collection
    {
        return collect(self::allLines())
            ->filter(fn ($line) => $line['product_id'] === $productId)
            ->map(fn ($line) => (object) $line)
            ->values();
    }

    /**
     * 受注に紐づく引当行をオブジェクトのコレクションで返す。
     *
     * @param  int  $orderId  受注 ID
     * @return Collection<int, object>
     */
    public static function linesForOrder(int $orderId): Collection
    {
        return collect(self::allLines())
            ->filter(fn ($line) => $line['order_id'] === $orderId)
            ->map(fn ($line) => (object) $line)
            ->values();
    }

    /**
     * 受注の現在庫引当行のみを返す。
     *
     * @param  int  $orderId  受注 ID
     * @return Collection<int, object>
     */
    public static function stockLinesForOrder(int $orderId): Collection
    {
        return self::linesForOrder($orderId)
            ->filter(fn ($line) => $line->type === self::TYPE_STOCK)
            ->values();
    }

    /**
     * 受注の発注引当行のみを返す。
     *
     * @param  int  $orderId  受注 ID
     * @return Collection<int, object>
     */
    public static function poLinesForOrder(int $orderId): Collection
    {
        return self::linesForOrder($orderId)
            ->filter(fn ($line) => $line->type === self::TYPE_PO)
            ->values();
    }

    /**
     * 受注の現在庫引当メートル合計を返す。
     *
     * @param  int  $orderId  受注 ID
     * @return float  メートル数（m）
     */
    public static function stockAllocatedForOrder(int $orderId): float
    {
        return round((float) self::stockLinesForOrder($orderId)->sum('qty'), 2);
    }

    /**
     * 受注の発注引当メートル合計を返す。
     *
     * @param  int  $orderId  受注 ID
     * @return float  メートル数（m）
     */
    public static function poAllocatedForOrder(int $orderId): float
    {
        return round((float) self::poLinesForOrder($orderId)->sum('qty'), 2);
    }

    /**
     * 受注の引当メートル合計（現在庫＋発注）を返す。
     *
     * @param  int  $orderId  受注 ID
     * @return float  メートル数（m）
     */
    public static function get(int $orderId): float
    {
        return self::stockAllocatedForOrder($orderId) + self::poAllocatedForOrder($orderId);
    }

    /**
     * 受注から出荷可能な現在庫ベースの数量（m）を返す。
     *
     * @param  int  $orderId  受注 ID
     * @return float  出荷可能 m（0 未満は 0）
     */
    public static function shippableQty(int $orderId): float
    {
        return max(0, self::stockAllocatedForOrder($orderId) - self::alreadyShippedFromStock($orderId));
    }

    /**
     * 現在庫引当のうち、既出荷分として扱うメートル数を返す。
     *
     * @param  int  $orderId  受注 ID
     * @return float  メートル数（m）。出荷済みと現在庫引当の小さい方
     */
    private static function alreadyShippedFromStock(int $orderId): float
    {
        $shipped = Order::shippedMetersFor($orderId);
        $stockAlloc = self::stockAllocatedForOrder($orderId);

        return min($shipped, $stockAlloc);
    }

    /**
     * 受注の引当を発注 ID ごとのメートル合計マップで返す。
     *
     * @param  int  $orderId  受注 ID
     * @param  string|null  $type  区分で絞る場合は stock / po。null は全区分
     * @return array<int, float>  po_id => qty（m）
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
     * 品番内の発注別・区分別引当メートル合計を返す。
     *
     * @param  int  $productId  品番 ID
     * @return array{stock: array<int, float>, po: array<int, float>}
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

    /**
     * 品番の現在庫引当反数合計を返す。
     *
     * @param  int  $productId  品番 ID
     * @return float  反数
     */
    public static function stockUsageTanForProduct(int $productId): float
    {
        return round((float) self::linesForProduct($productId)
            ->filter(fn ($l) => $l->type === self::TYPE_STOCK)
            ->sum('qty_tan'), 2);
    }

    /**
     * 品番の現在庫引当メートル合計（保存済み qty）を返す。
     *
     * @param  int  $productId  品番 ID
     * @return float  メートル数（m）
     */
    public static function stockUsageMetersForProduct(int $productId): float
    {
        return round((float) self::linesForProduct($productId)
            ->filter(fn ($l) => $l->type === self::TYPE_STOCK)
            ->sum('qty'), 2);
    }

    /**
     * 品番の発注別現在庫引当反数合計を返す。
     *
     * @param  int  $productId  品番 ID
     * @return array<int, float>  po_id => 反数
     */
    public static function stockUsageTanByPo(int $productId): array
    {
        $usage = [];

        foreach (self::linesForProduct($productId) as $line) {
            if ($line->type !== self::TYPE_STOCK) {
                continue;
            }
            $usage[$line->po_id] = ($usage[$line->po_id] ?? 0) + (float) $line->qty_tan;
        }

        return $usage;
    }

    /**
     * 品番の発注別現在庫引当メートル合計を返す。
     *
     * @param  int  $productId  品番 ID
     * @return array<int, float>  po_id => m
     */
    public static function stockUsageMetersByPo(int $productId): array
    {
        $usage = [];

        foreach (self::linesForProduct($productId) as $line) {
            if ($line->type !== self::TYPE_STOCK) {
                continue;
            }
            $usage[$line->po_id] = ($usage[$line->po_id] ?? 0) + (float) $line->qty;
        }

        return $usage;
    }

    /**
     * 品番の発注別引当メートル合計（現在庫＋発注区分の合算）を返す。
     *
     * @param  int  $productId  品番 ID
     * @return array<int, float>  po_id => m
     */
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

    /**
     * 品番の現在庫引当メートル合計を返す（stockUsageMetersForProduct のエイリアス）。
     *
     * @param  int  $productId  品番 ID
     * @return float  メートル数（m）
     */
    public static function stockUsageForProduct(int $productId): float
    {
        return self::stockUsageMetersForProduct($productId);
    }

    /**
     * 品番の引当行を受注 ID ごとにグループ化して返す。
     *
     * @param  int  $productId  品番 ID
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

    /**
     * 品番に保存済み引当があるかどうかを返す。
     *
     * @param  int  $productId  品番 ID
     * @return bool
     */
    public static function hasForProduct(int $productId): bool
    {
        return self::linesForProduct($productId)->isNotEmpty();
    }

    /**
     * 品番の引当行を一括で置き換え保存する。
     *
     * @param  int  $productId  品番 ID
     * @param  list<array{order_id: int, po_id: int, qty?: float, qty_tan?: float, type: string}>  $lines  保存する行（反数・区分）
     * @return void
     */
    public static function saveLinesForProduct(int $productId, array $lines): void
    {
        $built = [];

        foreach ($lines as $line) {
            $qtyTan = QtyHelper::roundTan((float) ($line['qty_tan'] ?? $line['qty'] ?? 0));
            if ($qtyTan <= 0 && isset($line['qty'])) {
                $qtyTan = QtyHelper::tanCount((float) $line['qty'], $productId);
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

        OrderAllocation::replaceForProduct($productId, $built);
    }

    /**
     * 受注×区分×発注のネスト配列から引当行を保存する。
     *
     * @param  int  $productId  品番 ID
     * @param  array<int, array<string, array<int, float>>>  $orderTypePoMaps  order_id => [stock|po => [po_id => 反数]]
     * @return void
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

    /**
     * 旧形式の受注×発注マップから現在庫引当のみ保存する。
     *
     * @deprecated  saveFromTypedMaps を使用
     * @param  int  $productId  品番 ID
     * @param  array<int, array<int, float>>  $orderPoMaps  order_id => [po_id => 反数]
     * @return void
     */
    public static function saveFromOrderPoMaps(int $productId, array $orderPoMaps): void
    {
        $typed = [];
        foreach ($orderPoMaps as $orderId => $poMap) {
            $typed[$orderId] = [self::TYPE_STOCK => $poMap];
        }
        self::saveFromTypedMaps($productId, $typed);
    }

    /**
     * 引当行を 1 件追加または既存行に加算する。
     *
     * @param  int  $productId  品番 ID
     * @param  int  $orderId  受注 ID
     * @param  int  $poId  発注 ID
     * @param  float  $qtyTan  追加反数
     * @param  string  $type  引当区分（既定: 現在庫）
     * @return void
     */
    public static function addLine(int $productId, int $orderId, int $poId, float $qtyTan, string $type = self::TYPE_STOCK): void
    {
        $qtyTan = QtyHelper::roundTan($qtyTan);
        if ($qtyTan <= 0) {
            return;
        }

        OrderAllocation::upsertLine(self::buildLine($productId, $orderId, $poId, $qtyTan, $type));
    }

    /**
     * 受注に紐づく引当行をすべて削除する。
     *
     * @param  int  $orderId  受注 ID
     * @return void
     */
    public static function clearForOrder(int $orderId): void
    {
        OrderAllocation::deleteForOrder($orderId);
    }

    /**
     * 受注の指定発注・区分の引当行を削除する。
     *
     * @param  int  $orderId  受注 ID
     * @param  int  $poId  発注 ID
     * @param  string  $type  引当区分（stock / po）
     * @return void
     */
    public static function removeLineFromOrder(int $orderId, int $poId, string $type): void
    {
        OrderAllocation::deleteLine($orderId, $poId, $type);
    }

    /**
     * 受注から指定発注の引当行をすべて削除する（区分を問わない旧 API）。
     *
     * @deprecated  removeLineFromOrder を使用
     * @param  int  $orderId  受注 ID
     * @param  int  $poId  発注 ID
     * @return void
     */
    public static function removePoFromOrder(int $orderId, int $poId): void
    {
        $all = collect(self::allLines())
            ->reject(fn ($line) => $line['order_id'] === $orderId && $line['po_id'] === $poId)
            ->values()
            ->all();

        self::write($all);
    }

    /**
     * 引当フォーム入力の整合性を検証し、エラー時はメッセージを返す。
     *
     * @param  int  $productId  品番 ID
     * @param  array<int, array<string, array<int|string, float>>>  $input  allocations[order_id][stock|po][po_id]
     * @return string|null  エラーメッセージ。問題なければ null
     */
    public static function validateSubmission(int $productId, array $input): ?string
    {
        $allOrders = DemoData::orders()->where('product_id', $productId)->keyBy('id');
        $purchases = DemoData::purchaseOrders()
            ->where('product_id', $productId)
            ->keyBy('id');

        $stockUsageByPoTan = [];
        $poUsageByPo = [];
        $totalStockAllocTan = 0.0;
        $stockRolls = ProductStock::inStockRollTotals($productId);

        foreach ($input as $orderId => $typeMaps) {
            $orderId = (int) $orderId;
            if (! is_array($typeMaps)) {
                continue;
            }

            $order = $allOrders->get($orderId);
            if (! $order) {
                continue;
            }

            $remaining = Order::remainingFor($orderId);
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

                    if (! QtyHelper::isValidTanStep($qtyTan)) {
                        return "受注 {$order->code} の引当反数は0.25反刻みで入力してください。";
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
                        if (! PurchaseOrder::hasReceivedFor($poId)) {
                            return "発注 {$po->code} は未入荷のため、現在庫引当の対象にできません。";
                        }

                        $rollForPo = ProductStock::inStockRollTotalsForPo($poId);
                        $usedTanFromPo = ($stockUsageByPoTan[$poId] ?? 0) + $qtyTan;
                        if ($usedTanFromPo > $rollForPo->tan + 0.0001) {
                            return "発注 {$po->code} の在庫反（"
                                .QtyHelper::formatAggregate($rollForPo->meters, $rollForPo->tan)
                                .'）を超える現在庫引当（'
                                .QtyHelper::formatFromTan($usedTanFromPo, $productId)
                                .'）はできません。';
                        }

                        $stockUsageByPoTan[$poId] = $usedTanFromPo;
                        $orderStockTotal += $qty;
                        $totalStockAllocTan += $qtyTan;
                    } else {
                        $poRemaining = PurchaseOrder::remainingQtyFor($poId);
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

        if ($totalStockAllocTan > $stockRolls->tan + 0.0001) {
            return '現在庫引当合計（'
                .QtyHelper::formatFromTan($totalStockAllocTan, $productId)
                .'）が現在庫（'
                .QtyHelper::formatAggregate($stockRolls->meters, $stockRolls->tan)
                .'）を超えています。数量を調整してください。';
        }

        return null;
    }

    /**
     * フォーム入力を saveFromTypedMaps 用のネスト配列に正規化する。
     *
     * @param  int  $productId  品番 ID
     * @param  array<int, array<string, array<int|string, float>>>  $input  フォームの allocations
     * @return array<int, array<string, array<int, float>>>  order_id => [stock|po => [po_id => 反数]]
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

    /**
     * フォームの発注キーを正の発注 ID に変換する。
     *
     * @param  mixed  $key  フォーム値（空・__NEW__ は無効）
     * @return int|null  有効な発注 ID。無効時は null
     */
    public static function parsePoId(mixed $key): ?int
    {
        if ($key === '' || $key === '__NEW__') {
            return null;
        }

        $id = filter_var($key, FILTER_VALIDATE_INT);

        return ($id !== false && $id > 0) ? $id : null;
    }

    /**
     * 入荷数量に応じて発注引当を納期順に現在庫引当へ変換する。
     *
     * @param  int  $poId  入荷した発注 ID
     * @param  float  $receivedQty  入荷メートル数
     * @param  string  $receivingCode  入荷伝票コード（変換履歴用）
     * @return list<array{order_id: int, qty: float}>  変換した受注と数量
     */
    public static function convertOnReceiving(int $poId, float $receivedQty, string $receivingCode): array
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
            $poAllocQty = round((float) $poLines->where('order_id', $orderId)->sum('qty'), 2);
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

            AllocationConversion::recordEvent([
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
     * 引当行配列にメートル数量を加算する（同一キーはマージ、なければ新規行）。
     *
     * @param  list<array{product_id: int, order_id: int, po_id: int, qty_tan: float, qty: float, type: string}>  $all  更新対象の引当行配列
     * @param  int  $productId  品番 ID
     * @param  int  $orderId  受注 ID
     * @param  int  $poId  発注 ID
     * @param  float  $qty  加算メートル数
     * @param  string  $type  引当区分
     * @return void
     */
    private static function addLineToArray(
        array &$all,
        int $productId,
        int $orderId,
        int $poId,
        float $qty,
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
                $addTan = QtyHelper::roundTan(QtyHelper::tanCount($qty, $productId));
                $line['qty_tan'] = QtyHelper::roundTan((float) $line['qty_tan'] + $addTan);
                $line['qty'] = round((float) $line['qty'] + $qty, 2);

                return;
            }
        }
        unset($line);

        $qtyTan = QtyHelper::roundTan(QtyHelper::tanCount($qty, $productId));
        $built = self::buildLine($productId, $orderId, $poId, $qtyTan, $type);
        $built['qty'] = round($qty, 2);
        $all[] = $built;
    }

    /**
     * 品番の引当画面用に受注ごとの引当サマリと合計値を組み立てる。
     *
     * @param  object  $product  品番（id 等）
     * @param  Collection  $orders  当該品番の受注コレクション
     * @param  Collection  $purchases  当該品番の発注コレクション（将来拡張用）
     * @return array{
     *     allocations: Collection,
     *     allocatedTotal: float,
     *     stockAllocatedTotal: float,
     *     poAllocatedTotal: float,
     *     unallocatedStock: float,
     *     allocationShortage: float,
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

        $allocations = $pending->map(function ($order) use ($isRecorded, $savedLines) {
            if ($isRecorded) {
                $lines = $savedLines->get($order->id, collect());
                $stockAlloc = round((float) $lines->where('type', self::TYPE_STOCK)->sum('qty'), 2);
                $poAlloc = round((float) $lines->where('type', self::TYPE_PO)->sum('qty'), 2);
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
            ? round((float) self::linesForProduct($product->id)->where('type', self::TYPE_PO)->sum('qty'), 2)
            : 0;

        return [
            'allocations' => $allocations,
            'allocatedTotal' => $stockAllocatedTotal + $poAllocatedTotal,
            'stockAllocatedTotal' => $stockAllocatedTotal,
            'poAllocatedTotal' => $poAllocatedTotal,
            'unallocatedStock' => self::unallocatedStockForProduct($product->id),
            'allocationShortage' => round((float) $allocations->sum('unallocated'), 2),
            'isRecorded' => $isRecorded,
        ];
    }

    /**
     * 受注の引当状況ラベルと数量サマリを返す。
     *
     * @param  object  $order  受注（id 等）
     * @return array{
     *     status: ?string,
     *     badge_class: ?string,
     *     shippable_status: ?string,
     *     shippable_badge: ?string,
     *     allocated: float,
     *     stock_allocated: float,
     *     po_allocated: float,
     *     remaining: float,
     *     shippable: bool,
     * }
     */
    public static function statusForOrder(object $order): array
    {
        $remaining = Order::remainingFor((int) $order->id);
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
     * 引当メートル数と受注残から表示用ステータスとバッジクラスを決定する。
     *
     * @param  float  $stockAllocated  現在庫引当 m
     * @param  float  $poAllocated  発注引当 m
     * @param  float  $remaining  受注残 m
     * @return array{status: string, badge_class: string, shippable_status: ?string, shippable_badge: ?string, shippable: bool}
     */
    private static function buildStatus(float $stockAllocated, float $poAllocated, float $remaining): array
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
     * 発注単位の未割当在庫メートル数（現在庫引当の上限用）を返す。
     *
     * @param  int  $productId  品番 ID
     * @param  int  $poId  発注 ID
     * @return float  未割当 m
     */
    public static function unallocatedStockFromPo(int $productId, int $poId): float
    {
        return self::unallocatedStockQuantityFromPo($productId, $poId)->meters;
    }

    /**
     * 品番×発注の未割当在庫（反明細 − 既引当、品番全体で上限）を返す。
     *
     * @param  int  $productId  品番 ID
     * @param  int  $poId  発注 ID
     * @return object{tan: float, meters: float}
     */
    public static function unallocatedStockQuantityFromPo(int $productId, int $poId): object
    {
        if (! PurchaseOrder::hasReceivedFor($poId)) {
            return (object) ['tan' => 0.0, 'meters' => 0.0];
        }

        $rolls = ProductStock::inStockRollTotalsForPo($poId);
        $usedTan = self::stockUsageTanByPo($productId)[$poId] ?? 0.0;
        $usedM = self::stockUsageMetersByPo($productId)[$poId] ?? 0.0;
        $perPoTan = max(0.0, $rolls->tan - $usedTan);
        $perPoM = max(0.0, $rolls->meters - $usedM);
        $global = self::unallocatedStockQuantityForProduct($productId);

        return (object) [
            'tan' => min($perPoTan, $global->tan),
            'meters' => min($perPoM, $global->meters),
        ];
    }

    /**
     * 発注単位の未割当発注残メートル数（発注引当の上限用）を返す。
     *
     * @param  int  $productId  品番 ID
     * @param  int  $poId  発注 ID
     * @return float  未割当 m
     */
    public static function unallocatedPoFromPo(int $productId, int $poId): float
    {
        if (! PurchaseOrder::hasRemainingFor($poId)) {
            return 0;
        }

        $poUsed = self::usageByPoAndType($productId)['po'][$poId] ?? 0;

        return max(0, PurchaseOrder::remainingQtyFor($poId) - $poUsed);
    }

    /**
     * 品番の引当 UI 用に、区分別の発注選択肢を返す。
     *
     * @param  int  $productId  品番 ID
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
     * 発注コレクションから区分別の引当選択肢ラベルを組み立てる。
     *
     * @param  Collection  $purchases  発注一覧
     * @param  int  $productId  品番 ID（未割当計算用）
     * @return array{stock: Collection, po: Collection}
     */
    public static function poOptionsFromPurchases(Collection $purchases, int $productId): array
    {
        $stockOptions = $purchases
            ->filter(fn ($po) => PurchaseOrder::hasReceivedFor((int) $po->id, $po))
            ->map(function ($po) use ($productId) {
                $unallocated = self::unallocatedStockQuantityFromPo($productId, $po->id);

                return (object) [
                    'id' => $po->id,
                    'code' => $po->code,
                    'qty' => $unallocated->meters,
                    'qty_tan' => $unallocated->tan,
                    'stage' => $po->stage,
                    'label' => $po->code.'（未割当 '
                        .QtyHelper::formatAggregate($unallocated->meters, $unallocated->tan)
                        .' / '.$po->stage.'）',
                ];
            })
            ->values();

        $poOptions = $purchases
            ->filter(fn ($po) => PurchaseOrder::hasRemainingFor((int) $po->id, $po))
            ->map(function ($po) use ($productId) {
                $unallocated = self::unallocatedPoFromPo($productId, $po->id);
                $unallocatedTan = QtyHelper::tanCount($unallocated, $productId);

                return (object) [
                    'id' => $po->id,
                    'code' => $po->code,
                    'qty' => $unallocated,
                    'qty_tan' => $unallocatedTan,
                    'stage' => $po->stage,
                    'label' => $po->code.'（未割当 '.QtyHelper::format($unallocated, $productId).' / '.$po->stage.'）',
                ];
            })
            ->values();

        return ['stock' => $stockOptions, 'po' => $poOptions];
    }

    /**
     * 品番全体の未割当在庫メートル数を返す。
     *
     * @param  int  $productId  品番 ID
     * @return float  未割当 m
     */
    public static function unallocatedStockForProduct(int $productId): float
    {
        return self::unallocatedStockQuantityForProduct($productId)->meters;
    }

    /**
     * 品番全体の未割当在庫（反明細 − 現在庫引当）を反数・m で返す。
     *
     * @param  int  $productId  品番 ID
     * @return object{tan: float, meters: float}
     */
    public static function unallocatedStockQuantityForProduct(int $productId): object
    {
        $rolls = ProductStock::inStockRollTotals($productId);

        return (object) [
            'tan' => max(0.0, round($rolls->tan - self::stockUsageTanForProduct($productId), 2)),
            'meters' => max(0.0, round($rolls->meters - self::stockUsageMetersForProduct($productId), 2)),
        ];
    }

    /**
     * 品番全体の未割当在庫反数を返す。
     *
     * @param  int  $productId  品番 ID
     * @return float  未割当反数
     */
    public static function unallocatedStockTanForProduct(int $productId): float
    {
        return self::unallocatedStockQuantityForProduct($productId)->tan;
    }

    /**
     * 品番の未引当発注残メートル合計（各 PO の残 − 発注引当）を返す。
     *
     * @param  int  $productId  品番 ID
     * @return float  未引当発注残 m の合計
     */
    public static function unallocatedPoRemainingForProduct(int $productId): float
    {
        $poUsage = self::usageByPoAndType($productId)['po'];
        $total = 0;

        foreach (DemoData::purchaseOrders()->where('product_id', $productId) as $po) {
            $remaining = PurchaseOrder::remainingQtyFor((int) $po->id, $po);
            $allocated = $poUsage[$po->id] ?? 0;
            $total += max(0, $remaining - $allocated);
        }

        return $total;
    }

    /**
     * 受注残に対する供給ベースの不足メートル数を返す（他受注との取り合いは除外）。
     *
     * @param  int  $orderId  受注 ID
     * @return float  不足 m（供給が足りる場合は 0）
     */
    public static function supplyShortageForOrder(int $orderId): float
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return 0;
        }

        $remaining = Order::remainingFor($orderId);
        if ($remaining <= 0) {
            return 0;
        }

        $supply = self::unallocatedStockForProduct($order->product_id)
            + self::unallocatedPoRemainingForProduct($order->product_id);

        return max(0, $remaining - $supply);
    }
}
