<?php

namespace App\Services\Sales;

use App\Models\ForecastManualAdjustment;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\SalesForecast;
use App\Models\SalesForecastLine;
use App\Support\DemoData;
use App\Support\FreightCalculator;
use App\Support\MasterCatalog;
use App\Support\ProductStock;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\SalesForecastSourceType;
use App\Support\ShipmentPlan;
use App\Support\StockAllocation;
use Illuminate\Support\Collection;

class SalesForecastEngine
{
    public static function build(string $targetYm): object
    {
        return self::assembleBuild($targetYm, self::buildProductLines($targetYm), includeSnapshot: true);
    }

    /** 実績タブ用の軽量ビルド（見通し詳細・提出版スナップショットを省略） */
    public static function buildForActualTab(string $targetYm): object
    {
        return self::assembleBuild($targetYm, self::buildProgressLines($targetYm), includeSnapshot: false);
    }

    /**
     * @return Collection<int, object>
     */
    private static function buildProductLines(string $targetYm): Collection
    {
        SalesForecastLine::preloadDraftForMonth($targetYm);
        $salesByProduct = DemoData::monthlySalesByProduct($targetYm)->keyBy('product_id');

        return MasterCatalog::products()
            ->map(fn ($product) => self::buildProductLine(
                (int) $product->id,
                $product,
                $targetYm,
                $salesByProduct->get((int) $product->id),
            ))
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    private static function buildProgressLines(string $targetYm): Collection
    {
        SalesForecastLine::preloadDraftForMonth($targetYm);
        $salesByProduct = DemoData::monthlySalesByProduct($targetYm)->keyBy('product_id');

        return MasterCatalog::products()
            ->map(fn ($product) => self::buildProgressLine(
                (int) $product->id,
                $product,
                $targetYm,
                $salesByProduct->get((int) $product->id),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, object>  $lines
     */
    private static function assembleBuild(string $targetYm, Collection $lines, bool $includeSnapshot): object
    {
        $calculable = $lines->where('cost_calculable', true);

        return (object) [
            'target_ym' => $targetYm,
            'month_end_date' => SalesRecognition::monthEndDate($targetYm),
            'lines' => $lines,
            'actual_qty' => (float) $lines->sum('actual_qty'),
            'actual_sales' => (int) $lines->sum('actual_sales'),
            'actual_cost' => (int) $calculable->sum('actual_cost'),
            'actual_profit' => (int) $calculable->sum('actual_profit'),
            'actual_freight' => (int) $lines->sum('actual_freight'),
            'forecast_remaining_qty' => (float) $lines->sum('forecast_remaining_qty'),
            'forecast_remaining_sales' => (int) $lines->sum('forecast_remaining_sales'),
            'forecast_remaining_cost' => (int) $calculable->sum('forecast_remaining_cost'),
            'forecast_remaining_profit' => (int) $calculable->sum('forecast_remaining_profit'),
            'forecast_remaining_freight' => (int) $lines->sum('forecast_remaining_freight'),
            'total_qty' => (float) $lines->sum('total_qty'),
            'total_sales' => (int) $lines->sum('total_sales'),
            'total_cost' => (int) $calculable->sum('actual_cost') + (int) $calculable->sum('forecast_remaining_cost'),
            'total_profit' => (int) $calculable->sum('total_profit'),
            'total_freight' => (int) $lines->sum('total_freight'),
            'adjusted_count' => $lines->where('is_adjusted', true)->count(),
            'warning_count' => $lines->filter(fn ($l) => $l->warning_text !== '')->count(),
            'has_uncalculable_cost' => $lines->contains(fn ($l) => ! $l->cost_calculable),
            'latest_snapshot' => $includeSnapshot ? self::latestSnapshotForMonth($targetYm) : null,
        ];
    }

    /**
     * 提出版保存・CSV 用の品番別行（見通し対象のみ）。
     *
     * @return Collection<int, object>
     */
    public static function exportableLines(string $targetYm): Collection
    {
        return self::build($targetYm)->lines
            ->filter(fn ($line) => $line->has_forecast_activity || $line->actual_qty > 0)
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function snapshotLinePayloads(string $targetYm): array
    {
        return self::exportableLines($targetYm)->map(fn ($line) => [
            'product_id' => $line->product_id,
            'sku' => $line->sku,
            'total_qty' => $line->total_qty,
            'actual_qty' => $line->actual_qty,
            'forecast_remaining_qty' => $line->forecast_remaining_qty,
            'total_sales' => $line->total_sales,
            'total_profit' => $line->total_profit,
            'warning_text' => $line->warning_text,
        ])->all();
    }

    public static function buildComparison(string $targetYm, ?object $current = null): object
    {
        $current ??= self::build($targetYm);
        $snapshot = self::latestSnapshotForMonth($targetYm);

        $actualVsForecast = (object) [
            'actual_qty' => $current->actual_qty,
            'forecast_remaining_qty' => $current->forecast_remaining_qty,
            'total_qty' => $current->total_qty,
            'actual_sales' => $current->actual_sales,
            'forecast_remaining_sales' => $current->forecast_remaining_sales,
            'total_sales' => $current->total_sales,
            'actual_profit' => $current->actual_profit,
            'forecast_remaining_profit' => $current->forecast_remaining_profit,
            'total_profit' => $current->total_profit,
        ];

        if ($snapshot === null) {
            return (object) [
                'has_snapshot' => false,
                'actual_vs_forecast' => $actualVsForecast,
                'snapshot_vs_current' => null,
                'line_diffs' => collect(),
            ];
        }

        $snapshotLines = collect($snapshot->lines ?? [])->keyBy('product_id');

        $lineDiffs = $current->lines
            ->filter(fn ($line) => $snapshotLines->has($line->product_id))
            ->map(function ($line) use ($snapshotLines) {
                $snap = $snapshotLines->get($line->product_id);

                return (object) [
                    'product_id' => $line->product_id,
                    'sku' => $line->sku,
                    'diff_qty' => round($line->total_qty - (float) ($snap['total_qty'] ?? 0), 2),
                    'diff_sales' => (int) $line->total_sales - (int) ($snap['total_sales'] ?? 0),
                    'diff_profit' => (int) $line->total_profit - (int) ($snap['total_profit'] ?? 0),
                ];
            })
            ->filter(fn ($diff) => $diff->diff_qty != 0.0 || $diff->diff_sales !== 0 || $diff->diff_profit !== 0)
            ->keyBy('product_id');

        return (object) [
            'has_snapshot' => true,
            'actual_vs_forecast' => $actualVsForecast,
            'snapshot_vs_current' => (object) [
                'version' => (int) $snapshot->version,
                'submitted_at' => $snapshot->submitted_at ?? null,
                'snapshot_total_sales' => (int) ($snapshot->total_sales ?? 0),
                'snapshot_total_qty' => (float) ($snapshot->total_qty ?? 0),
                'snapshot_total_profit' => (int) ($snapshot->total_profit ?? 0),
                'current_total_sales' => $current->total_sales,
                'current_total_qty' => $current->total_qty,
                'current_total_profit' => $current->total_profit,
                'diff_sales' => $current->total_sales - (int) ($snapshot->total_sales ?? 0),
                'diff_qty' => round($current->total_qty - (float) ($snapshot->total_qty ?? 0), 2),
                'diff_profit' => $current->total_profit - (int) ($snapshot->total_profit ?? 0),
            ],
            'line_diffs' => $lineDiffs,
        ];
    }

    /**
     * 推移グラフ用。対象月は見通し、それ以前は出荷実績。
     *
     * @return Collection<int, object{ym: string, sales: int, cost: int, profit: int, is_forecast: bool, has_uncalculable_cost: bool}>
     */
    public static function forecastTrend(
        string $endYm,
        ?int $productId = null,
        int $months = 6,
        ?object $forecast = null,
    ): Collection {
        $forecast ??= self::build($endYm);

        return collect(DemoData::salesTrendMonths($endYm, $months))->map(function (string $ym) use ($endYm, $productId, $forecast) {
            if ($ym === $endYm) {
                if ($productId !== null) {
                    $line = $forecast->lines->firstWhere('product_id', $productId);
                    if ($line === null) {
                        return (object) [
                            'ym' => $ym,
                            'sales' => 0,
                            'cost' => 0,
                            'profit' => 0,
                            'is_forecast' => true,
                            'has_uncalculable_cost' => false,
                        ];
                    }

                    $cost = $line->cost_calculable
                        ? (int) ($line->actual_cost + $line->forecast_remaining_cost)
                        : 0;

                    return (object) [
                        'ym' => $ym,
                        'sales' => (int) $line->total_sales,
                        'cost' => $cost,
                        'profit' => $line->cost_calculable ? (int) $line->total_profit : 0,
                        'is_forecast' => true,
                        'has_uncalculable_cost' => ! $line->cost_calculable,
                    ];
                }

                return (object) [
                    'ym' => $ym,
                    'sales' => (int) $forecast->total_sales,
                    'cost' => (int) $forecast->total_cost,
                    'profit' => (int) $forecast->total_profit,
                    'is_forecast' => true,
                    'has_uncalculable_cost' => $forecast->has_uncalculable_cost,
                ];
            }

            $actual = DemoData::salesTrend($ym, $productId, 1)->first();

            return (object) [
                'ym' => $ym,
                'sales' => (int) ($actual->sales ?? 0),
                'cost' => (int) ($actual->cost ?? 0),
                'profit' => (int) ($actual->profit ?? 0),
                'is_forecast' => false,
                'has_uncalculable_cost' => (bool) ($actual->has_uncalculable_cost ?? false),
            ];
        });
    }

    public static function buildProductLine(
        int $productId,
        object $product,
        string $targetYm,
        ?object $actualRow = null,
    ): object {
        $actualRow ??= DemoData::monthlySalesByProduct($targetYm)
            ->firstWhere('product_id', $productId);

        $actualQty = (float) ($actualRow->qty ?? 0);
        $actualSales = (int) ($actualRow->sales ?? 0);
        $price = (int) ($product->price ?? 0);
        $unitCost = DemoData::unitCost($productId, $targetYm);
        $costCalculable = $unitCost !== null;
        $unitCostInt = $costCalculable ? (int) round($unitCost) : null;

        $actualCost = $costCalculable ? (int) round($actualQty * $unitCostInt) : 0;
        $actualProfit = $costCalculable ? $actualSales - $actualCost : 0;
        $actualFreight = FreightCalculator::forQty($actualQty, $productId);

        $forecastRemainingQty = self::totalOutboundQty($productId, $targetYm);
        $forecastRemainingSales = (int) round($forecastRemainingQty * $price);
        $forecastRemainingCost = $costCalculable
            ? (int) round($forecastRemainingQty * $unitCostInt)
            : 0;
        $forecastRemainingProfit = $costCalculable
            ? $forecastRemainingSales - $forecastRemainingCost
            : 0;
        $forecastRemainingFreight = FreightCalculator::forQty($forecastRemainingQty, $productId);

        $totalQty = round($actualQty + $forecastRemainingQty, 2);
        $totalSales = $actualSales + $forecastRemainingSales;
        $totalProfit = $costCalculable ? $actualProfit + $forecastRemainingProfit : 0;
        $totalFreight = $actualFreight + $forecastRemainingFreight;

        $inboundForecast = self::totalInboundQty($productId, $targetYm);
        $currentStock = (float) ProductStock::effectiveStock($productId);
        $isShortage = $forecastRemainingQty > ($currentStock + $inboundForecast);
        $isAdjusted = SalesForecastLine::productHasSavedDraft($productId, $targetYm);

        $warnings = [];
        if (! $costCalculable) {
            $warnings[] = '原価未登録';
        }
        if ($isShortage) {
            $warnings[] = '在庫不足見通し';
        }
        if ($isAdjusted) {
            $warnings[] = '手動調整あり';
        }

        return (object) [
            'product_id' => $productId,
            'sku' => $product->sku,
            'price' => $price,
            'unit_cost' => $unitCostInt,
            'cost_calculable' => $costCalculable,
            'actual_qty' => $actualQty,
            'actual_sales' => $actualSales,
            'actual_cost' => $actualCost,
            'actual_profit' => $actualProfit,
            'actual_freight' => $actualFreight,
            'forecast_remaining_qty' => $forecastRemainingQty,
            'forecast_remaining_sales' => $forecastRemainingSales,
            'forecast_remaining_cost' => $forecastRemainingCost,
            'forecast_remaining_profit' => $forecastRemainingProfit,
            'forecast_remaining_freight' => $forecastRemainingFreight,
            'total_qty' => $totalQty,
            'total_sales' => $totalSales,
            'total_profit' => $totalProfit,
            'total_freight' => $totalFreight,
            'inbound_forecast_qty' => $inboundForecast,
            'is_adjusted' => $isAdjusted,
            'is_shortage' => $isShortage,
            'warnings' => $warnings,
            'warning_text' => implode(' / ', $warnings),
            'has_forecast_activity' => $forecastRemainingQty > 0 || $inboundForecast > 0,
        ];
    }

    private static function buildProgressLine(
        int $productId,
        object $product,
        string $targetYm,
        ?object $actualRow = null,
    ): object {
        $actualRow ??= DemoData::monthlySalesByProduct($targetYm)
            ->firstWhere('product_id', $productId);

        $actualQty = (float) ($actualRow->qty ?? 0);
        $actualSales = (int) ($actualRow->sales ?? 0);
        $price = (int) ($product->price ?? 0);
        $unitCost = DemoData::unitCost($productId, $targetYm);
        $costCalculable = $unitCost !== null;
        $unitCostInt = $costCalculable ? (int) round($unitCost) : null;

        $actualCost = $costCalculable ? (int) round($actualQty * $unitCostInt) : 0;
        $actualProfit = $costCalculable ? $actualSales - $actualCost : 0;

        $forecastRemainingQty = self::totalOutboundQty($productId, $targetYm);
        $forecastRemainingSales = (int) round($forecastRemainingQty * $price);
        $forecastRemainingCost = $costCalculable
            ? (int) round($forecastRemainingQty * $unitCostInt)
            : 0;
        $forecastRemainingProfit = $costCalculable
            ? $forecastRemainingSales - $forecastRemainingCost
            : 0;

        $totalQty = round($actualQty + $forecastRemainingQty, 2);
        $totalSales = $actualSales + $forecastRemainingSales;
        $totalProfit = $costCalculable ? $actualProfit + $forecastRemainingProfit : 0;

        return (object) [
            'product_id' => $productId,
            'sku' => $product->sku,
            'price' => $price,
            'unit_cost' => $unitCostInt,
            'cost_calculable' => $costCalculable,
            'actual_qty' => $actualQty,
            'actual_sales' => $actualSales,
            'actual_cost' => $actualCost,
            'actual_profit' => $actualProfit,
            'actual_freight' => 0,
            'forecast_remaining_qty' => $forecastRemainingQty,
            'forecast_remaining_sales' => $forecastRemainingSales,
            'forecast_remaining_cost' => $forecastRemainingCost,
            'forecast_remaining_profit' => $forecastRemainingProfit,
            'forecast_remaining_freight' => 0,
            'total_qty' => $totalQty,
            'total_sales' => $totalSales,
            'total_profit' => $totalProfit,
            'total_freight' => 0,
            'inbound_forecast_qty' => 0,
            'is_adjusted' => false,
            'is_shortage' => false,
            'warnings' => $costCalculable ? [] : ['原価未登録'],
            'warning_text' => $costCalculable ? '' : '原価未登録',
            'has_forecast_activity' => $forecastRemainingQty > 0,
        ];
    }

    public static function buildDetail(int $productId, object $product, string $targetYm): object
    {
        SalesForecastLine::preloadDraftForMonth($targetYm);
        $pairs = self::buildPairs($productId, $targetYm);
        $futureOrders = self::futureOrdersForProduct($productId, $targetYm);
        $futurePurchaseOrders = self::futurePurchaseOrdersForProduct($productId, $targetYm);

        $isAdjusted = $pairs->contains(fn ($p) => $p->outbound_is_saved || $p->inbound_is_saved);

        $forecastRemainingQty = self::totalOutboundQty($productId, $targetYm);
        $inboundForecastQty = self::totalInboundQty($productId, $targetYm);
        $actualRow = DemoData::monthlySalesByProduct($targetYm)->firstWhere('product_id', $productId);
        $actualQty = (float) ($actualRow->qty ?? 0);
        $price = (int) ($product->price ?? 0);

        return (object) [
            'product_id' => $productId,
            'sku' => $product->sku,
            'pairs' => $pairs,
            'future_orders' => $futureOrders,
            'future_purchase_orders' => $futurePurchaseOrders,
            'future_count' => $futureOrders->count() + $futurePurchaseOrders->count(),
            'is_adjusted' => $isAdjusted,
            'actual_qty' => $actualQty,
            'forecast_remaining_qty' => $forecastRemainingQty,
            'total_qty' => round($actualQty + $forecastRemainingQty, 2),
            'forecast_remaining_sales' => (int) round($forecastRemainingQty * $price),
            'total_sales' => (int) ($actualRow->sales ?? 0) + (int) round($forecastRemainingQty * $price),
            'forecast_remaining_freight' => FreightCalculator::forQty($forecastRemainingQty, $productId),
            'inbound_forecast_qty' => $inboundForecastQty,
            'current_stock_qty' => (float) ProductStock::effectiveStock($productId),
        ];
    }

    public static function defaultOutboundQty(int $orderId, string $targetYm): float
    {
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order || ! SalesRecognition::countsOrderForSalesMonth($order, $targetYm)) {
            return 0.0;
        }

        $remaining = (float) Order::remainingFor($orderId);
        if ($remaining <= 0) {
            return 0.0;
        }

        $monthEnd = SalesRecognition::monthEndDate($targetYm);
        $fromPlans = (float) ShipmentPlan::forOrder($orderId)
            ->filter(fn ($p) => ShipmentPlan::isActiveForForecast($p))
            ->filter(fn ($p) => (string) $p->planned_ship_date <= $monthEnd)
            ->sum(fn ($p) => ShipmentPlan::unshippedQty($p));

        if ($fromPlans > 0) {
            return min($fromPlans, $remaining);
        }

        return $remaining;
    }

    public static function defaultInboundQty(int $poId, string $targetYm): float
    {
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        if (! $po || ($po->type ?? '') !== PurchaseOrderType::PRODUCT) {
            return 0.0;
        }
        if (($po->status ?? '') === PurchaseOrderStatus::CANCELLED) {
            return 0.0;
        }
        if (! SalesRecognition::countsPoForInboundMonth($po, $targetYm)) {
            return 0.0;
        }

        return max(0.0, (float) PurchaseOrder::remainingQtyFor($poId));
    }

    public static function effectiveOutboundQty(int $productId, int $orderId, string $targetYm): float
    {
        $default = self::defaultOutboundQty($orderId, $targetYm);

        return SalesForecastLine::effectiveQty(
            $productId,
            $targetYm,
            SalesForecastSourceType::ORDER,
            $orderId,
            $default
        );
    }

    public static function effectiveInboundQty(int $productId, int $poId, string $targetYm): float
    {
        $default = self::defaultInboundQty($poId, $targetYm);

        return SalesForecastLine::effectiveQty(
            $productId,
            $targetYm,
            SalesForecastSourceType::PURCHASE_ORDER,
            $poId,
            $default
        );
    }

    public static function totalInboundQty(int $productId, string $targetYm): float
    {
        return (float) self::inboundPurchaseOrdersForProduct($productId, $targetYm)
            ->sum(fn ($po) => self::effectiveInboundQty($productId, (int) $po->id, $targetYm));
    }

    public static function totalOutboundQty(int $productId, string $targetYm): float
    {
        return (float) self::ordersForSalesMonth($productId, $targetYm)
            ->sum(fn ($order) => self::effectiveOutboundQty($productId, (int) $order->id, $targetYm));
    }

    /**
     * @return Collection<int, object>
     */
    public static function ordersForSalesMonth(int $productId, string $targetYm): Collection
    {
        return DemoData::orders()
            ->where('product_id', $productId)
            ->map(function ($order) {
                $order->remaining = Order::remainingFor((int) $order->id);

                return $order;
            })
            ->filter(fn ($order) => $order->remaining > 0)
            ->filter(fn ($order) => SalesRecognition::countsOrderForSalesMonth($order, $targetYm))
            ->sortBy('planned_ship_date')
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public static function inboundDetailsForInventory(int $productId, string $targetYm): Collection
    {
        return self::inboundPurchaseOrdersForProduct($productId, $targetYm)
            ->map(function ($po) use ($productId, $targetYm) {
                $poId = (int) $po->id;
                $forecastQty = self::effectiveInboundQty($productId, $poId, $targetYm);

                return (object) [
                    'po_id' => $poId,
                    'po_code' => $po->code,
                    'finish_date' => DemoData::expectedArrivalDate($po),
                    'qty_meters' => (float) ($po->qty_meters ?? 0),
                    'received_qty' => (float) PurchaseOrder::receivedQtyFor($poId),
                    'remaining_qty' => (float) PurchaseOrder::remainingQtyFor($poId),
                    'forecast_qty' => $forecastQty,
                ];
            })
            ->filter(fn ($row) => $row->forecast_qty > 0)
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public static function outboundDetailsForInventory(int $productId, string $targetYm): Collection
    {
        return self::ordersForSalesMonth($productId, $targetYm)
            ->map(function ($order) use ($productId, $targetYm) {
                $forecastQty = self::effectiveOutboundQty($productId, (int) $order->id, $targetYm);

                return (object) [
                    'order_id' => (int) $order->id,
                    'order_code' => $order->code,
                    'customer' => $order->customer,
                    'planned_ship_date' => $order->planned_ship_date,
                    'remaining_qty' => (float) $order->remaining,
                    'forecast_qty' => $forecastQty,
                ];
            })
            ->filter(fn ($row) => $row->forecast_qty > 0)
            ->values();
    }

    public static function inventoryImpact(int $productId, string $targetYm): object
    {
        $inbound = self::totalInboundQty($productId, $targetYm);
        $outbound = self::totalOutboundQty($productId, $targetYm);
        $manual = ForecastManualAdjustment::totalFor($productId, $targetYm);
        $currentStock = (float) ProductStock::effectiveStock($productId);
        $autoForecast = round($currentStock + $inbound - $outbound, 2);

        return (object) [
            'inbound_qty' => $inbound,
            'outbound_qty' => $outbound,
            'manual_adjustment_qty' => $manual,
            'current_stock_qty' => $currentStock,
            'auto_forecast_qty' => $autoForecast,
            'forecast_qty' => round($autoForecast + $manual, 2),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public static function buildPairs(int $productId, string $targetYm): Collection
    {
        $pairs = collect();
        $usedPoIds = [];
        $usedOrderIds = [];

        $orders = self::ordersForSalesMonth($productId, $targetYm);

        foreach ($orders as $order) {
            $allocLines = StockAllocation::linesForOrder((int) $order->id);
            $poLines = $allocLines->where('type', StockAllocation::TYPE_PO)->values();
            $stockLines = $allocLines->where('type', StockAllocation::TYPE_STOCK)->values();

            if ($poLines->isNotEmpty()) {
                foreach ($poLines->groupBy('po_id') as $poId => $lines) {
                    $poId = (int) $poId;
                    $pairs->push(self::makePair($productId, $targetYm, $poId > 0 ? $poId : null, (int) $order->id));
                    if ($poId > 0) {
                        $usedPoIds[$poId] = true;
                    }
                }
                $usedOrderIds[(int) $order->id] = true;

                continue;
            }

            if ($stockLines->isNotEmpty()) {
                $grouped = $stockLines->groupBy('po_id');
                foreach ($grouped as $poId => $lines) {
                    $poId = (int) $poId;
                    $pairs->push(self::makePair($productId, $targetYm, $poId > 0 ? $poId : null, (int) $order->id));
                    if ($poId > 0) {
                        $usedPoIds[$poId] = true;
                    }
                }
                $usedOrderIds[(int) $order->id] = true;

                continue;
            }

            $pairs->push(self::makePair($productId, $targetYm, null, (int) $order->id));
            $usedOrderIds[(int) $order->id] = true;
        }

        foreach (self::inboundPurchaseOrdersForProduct($productId, $targetYm) as $po) {
            $poId = (int) $po->id;
            if (isset($usedPoIds[$poId])) {
                continue;
            }
            $pairs->push(self::makePair($productId, $targetYm, $poId, null));
        }

        return $pairs->values();
    }

    private static function makePair(int $productId, string $targetYm, ?int $poId, ?int $orderId): object
    {
        $po = $poId !== null
            ? DemoData::purchaseOrders()->firstWhere('id', $poId)
            : null;
        $order = $orderId !== null
            ? DemoData::orders()->firstWhere('id', $orderId)
            : null;

        if ($order !== null) {
            $order->remaining = Order::remainingFor((int) $order->id);
        }

        $defaultInbound = $poId !== null ? self::defaultInboundQty($poId, $targetYm) : 0.0;
        $defaultOutbound = $orderId !== null ? self::defaultOutboundQty($orderId, $targetYm) : 0.0;

        $inboundIsSaved = $poId !== null
            && SalesForecastLine::isSaved($productId, $targetYm, SalesForecastSourceType::PURCHASE_ORDER, $poId);
        $outboundIsSaved = $orderId !== null
            && SalesForecastLine::isSaved($productId, $targetYm, SalesForecastSourceType::ORDER, $orderId);

        $effectiveInbound = $poId !== null
            ? self::effectiveInboundQty($productId, $poId, $targetYm)
            : 0.0;
        $effectiveOutbound = $orderId !== null
            ? self::effectiveOutboundQty($productId, $orderId, $targetYm)
            : 0.0;

        $shipmentPlans = $orderId !== null
            ? ShipmentPlan::forOrder($orderId)
                ->filter(fn ($p) => ShipmentPlan::isActiveForForecast($p))
                ->values()
            : collect();

        return (object) [
            'po_id' => $poId,
            'order_id' => $orderId,
            'po' => $po,
            'order' => $order,
            'po_code' => $po?->code,
            'order_code' => $order?->code,
            'arrival_date' => $po ? DemoData::expectedArrivalDate($po) : null,
            'planned_ship_date' => $order?->planned_ship_date,
            'po_remaining_qty' => $poId !== null ? (float) PurchaseOrder::remainingQtyFor($poId, $po) : null,
            'order_remaining_qty' => $order?->remaining,
            'default_inbound_qty' => $defaultInbound,
            'default_outbound_qty' => $defaultOutbound,
            'effective_inbound_qty' => $effectiveInbound,
            'effective_outbound_qty' => $effectiveOutbound,
            'inbound_is_saved' => $inboundIsSaved,
            'outbound_is_saved' => $outboundIsSaved,
            'shipment_plans' => $shipmentPlans,
            'default_outbound_source' => $shipmentPlans->isNotEmpty() ? '出荷確定' : '受注残',
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public static function inboundPurchaseOrdersForProduct(int $productId, string $targetYm): Collection
    {
        return DemoData::purchaseOrders()
            ->filter(fn ($po) => ($po->type ?? '') === PurchaseOrderType::PRODUCT)
            ->filter(fn ($po) => (int) $po->product_id === $productId)
            ->filter(fn ($po) => ($po->status ?? '') !== PurchaseOrderStatus::CANCELLED)
            ->filter(fn ($po) => SalesRecognition::countsPoForInboundMonth($po, $targetYm))
            ->filter(fn ($po) => PurchaseOrder::remainingQtyFor((int) $po->id, $po) > 0)
            ->sortBy(fn ($po) => DemoData::expectedArrivalDate($po))
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public static function futureOrdersForProduct(int $productId, string $targetYm): Collection
    {
        return DemoData::orders()
            ->where('product_id', $productId)
            ->map(function ($order) {
                $order->remaining = Order::remainingFor((int) $order->id);

                return $order;
            })
            ->filter(fn ($order) => $order->remaining > 0)
            ->reject(fn ($order) => SalesRecognition::countsOrderForSalesMonth($order, $targetYm))
            ->sortBy('planned_ship_date')
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public static function futurePurchaseOrdersForProduct(int $productId, string $targetYm): Collection
    {
        return DemoData::purchaseOrders()
            ->filter(fn ($po) => ($po->type ?? '') === PurchaseOrderType::PRODUCT)
            ->filter(fn ($po) => (int) $po->product_id === $productId)
            ->filter(fn ($po) => ($po->status ?? '') !== PurchaseOrderStatus::CANCELLED)
            ->reject(fn ($po) => SalesRecognition::countsPoForInboundMonth($po, $targetYm))
            ->filter(fn ($po) => PurchaseOrder::remainingQtyFor((int) $po->id, $po) > 0)
            ->sortBy(fn ($po) => DemoData::expectedArrivalDate($po))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $requestData
     */
    public static function parseAndSaveProduct(int $productId, string $targetYm, array $requestData): void
    {
        $detail = self::buildDetail($productId, MasterCatalog::findProduct($productId) ?? (object) [], $targetYm);
        $inputs = [];

        foreach ($detail->pairs as $pair) {
            if ($pair->po_id !== null) {
                $key = 'inbound_'.$pair->po_id;
                if (array_key_exists($key, $requestData)) {
                    $inputs[] = [
                        'source_type' => SalesForecastSourceType::PURCHASE_ORDER,
                        'source_id' => (int) $pair->po_id,
                        'forecast_qty_m' => (float) $requestData[$key],
                    ];
                }
            }
            if ($pair->order_id !== null) {
                $key = 'outbound_'.$pair->order_id;
                if (array_key_exists($key, $requestData)) {
                    $inputs[] = [
                        'source_type' => SalesForecastSourceType::ORDER,
                        'source_id' => (int) $pair->order_id,
                        'forecast_qty_m' => (float) $requestData[$key],
                    ];
                }
            }
        }

        SalesForecastLine::saveDraftForProduct($productId, $targetYm, $inputs);
    }

    public static function latestSnapshotForMonth(string $targetYm): ?object
    {
        $forecast = SalesForecast::latestSubmittedForMonth($targetYm);
        if ($forecast === null) {
            return null;
        }

        $forecast->load('lines');

        return $forecast->toSnapshotObject();
    }

    /**
     * @param  list<array<string, mixed>>  $productLinePayloads
     */
    public static function submitSnapshot(
        string $targetYm,
        string $createdByName,
        string $baseDate,
        int $totalSales,
        float $totalQty,
        int $totalProfit,
        array $productLinePayloads,
    ): object {
        $forecast = SalesForecast::submitMonth(
            $targetYm,
            $createdByName,
            $baseDate,
            $totalSales,
            $totalQty,
            $totalProfit,
            $productLinePayloads,
        );

        $forecast->load('lines');

        return $forecast->toSnapshotObject();
    }
}
