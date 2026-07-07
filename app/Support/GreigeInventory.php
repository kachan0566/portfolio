<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 染工場の生機在庫（生機発注の入荷実績ベース）。
 * 旧製品発注の工程 stage 連動は Phase C で廃止。
 */
class GreigeInventory
{
    /**
     * @return Collection<int, object{
     *     po_id: int,
     *     po_code: string,
     *     greige_sku: string,
     *     greige_name: string,
     *     qty_meters: int,
     *     qty_tan_calc: float,
     *     ship_to: string,
     *     due_date: string
     * }>
     */
    public static function entries(): Collection
    {
        return DemoData::purchaseOrders()
            ->filter(fn ($po) => ($po->type ?? '') === PurchaseOrderType::GREIGE)
            ->map(function ($po) {
                $poId = (int) $po->id;
                $greigeSku = (string) ($po->greige_sku ?? $po->sku ?? '');
                $greige = DemoData::findGreige($greigeSku);
                if ($greige === null) {
                    return null;
                }

                $rolls = GreigeRoll::forPo($poId)
                    ->filter(fn ($roll) => in_array($roll->status, [
                        GreigeRoll::STATUS_IN_STOCK,
                        GreigeRoll::STATUS_PARTIALLY_CONSUMED,
                    ], true));

                if ($rolls->isNotEmpty()) {
                    $received = (int) round($rolls->sum(fn ($roll) => (float) $roll->actual_qty_m));
                    $rollCount = (float) $rolls->sum(fn ($roll) => (float) $roll->tan_qty);
                } else {
                    $received = (int) floor(DemoState::effectiveReceivedQty($poId, $po));
                    if ($received <= 0) {
                        return null;
                    }
                    $rollCount = 0;
                }

                $shipTo = DemoData::findShipTo((int) ($po->ship_to_id ?? 0));

                return (object) [
                    'po_id' => $poId,
                    'po_code' => $po->code,
                    'greige_sku' => $greigeSku,
                    'greige_name' => $greige->name,
                    'qty_meters' => $received,
                    'qty_tan_calc' => $rollCount > 0
                        ? (float) $rollCount
                        : QtyHelper::tanCount($received, null, true, $greigeSku),
                    'roll_count' => $rollCount,
                    'ship_to' => $shipTo?->name ?? '—',
                    'due_date' => $po->due_date ?? '—',
                ];
            })
            ->filter()
            ->values();
    }

    public static function totalMeters(): int
    {
        return (int) self::entries()->sum('qty_meters');
    }

    public static function totalMetersForSku(string $greigeSku): int
    {
        return (int) self::entries()
            ->where('greige_sku', $greigeSku)
            ->sum('qty_meters');
    }

    /** 製品発注詳細用：紐づく生機品番の染工場在庫 */
    public static function forPurchase(int $productPoId): ?object
    {
        $po = DemoData::purchaseOrders()->firstWhere('id', $productPoId);
        if ($po === null || ($po->type ?? '') !== PurchaseOrderType::PRODUCT) {
            return null;
        }

        $greige = DemoData::findGreigeByProductId((int) $po->product_id);
        if ($greige === null) {
            return null;
        }

        $meters = self::totalMetersForSku($greige->sku);
        if ($meters <= 0) {
            return null;
        }

        return (object) [
            'greige_sku' => $greige->sku,
            'greige_name' => $greige->name,
            'qty_meters' => $meters,
            'qty_tan_calc' => QtyHelper::tanCount($meters, null, true, $greige->sku),
        ];
    }

    /**
     * 製品発注の工程変更（レガシー互換）。
     * 生機在庫は生機発注入荷で管理するため、ここでは製品在庫への移動のみ行う。
     */
    public static function handleStageTransition(int $poId, string $oldStage, string $newStage): void
    {
        $oldIdx = array_search($oldStage, DemoData::PO_STAGES, true);
        $newIdx = array_search($newStage, DemoData::PO_STAGES, true);
        $dyeIdx = array_search('染機投入済', DemoData::PO_STAGES, true);

        if ($oldIdx === false || $newIdx === false || $dyeIdx === false) {
            return;
        }

        if ($oldIdx < $dyeIdx && $newIdx >= $dyeIdx) {
            $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
            if ($po !== null && ($po->type ?? '') === PurchaseOrderType::PRODUCT) {
                $result = \App\Services\Fabric\TanRollRecorder::recordDyeingCompletion(
                    $poId,
                    (int) $po->product_id,
                    $po->finish_date ?? null,
                );
                $meters = (int) round($result['total_meters']);
                if ($meters > 0) {
                    DemoState::applyDyeTransfer($poId, (int) $po->product_id, $meters);
                }
            }
        }
    }
}
