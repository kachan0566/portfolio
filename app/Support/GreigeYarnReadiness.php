<?php

namespace App\Support;

use App\Models\PurchaseOrder;

/**
 * 生機発注に必要な糸が織工場へすべて入荷完了したかを判定する。
 */
class GreigeYarnReadiness
{
    public static function allRequiredYarnReceived(object $greigePo): bool
    {
        $requirements = $greigePo->yarn_requirements ?? [];
        if ($requirements === []) {
            $sku = (string) ($greigePo->sku ?? $greigePo->greige_sku ?? '');
            $meters = (int) ($greigePo->qty_meters ?? $greigePo->qty ?? 0);
            $requirements = DemoData::greigeYarnRequirements($sku, $meters);
        }

        if ($requirements === []) {
            return false;
        }

        foreach ($requirements as $req) {
            $materialId = (int) ($req->material_id ?? 0);
            if ($materialId <= 0) {
                return false;
            }

            $hasFullyReceived = self::rawYarnPurchaseOrders()
                ->contains(function ($yarnPo) use ($materialId) {
                    if ((int) ($yarnPo->material_id ?? 0) !== $materialId) {
                        return false;
                    }

                    return self::isYarnPoFullyReceived($yarnPo);
                });

            if (! $hasFullyReceived) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function rawYarnPurchaseOrders(): \Illuminate\Support\Collection
    {
        if (DemoData::usesPurchaseOrderDatabase()) {
            return PurchaseOrder::query()
                ->where('type', PurchaseOrderType::YARN)
                ->with('lines')
                ->get()
                ->map(function (PurchaseOrder $po) {
                    $line = $po->lines->sortBy('line_no')->first();

                    return (object) [
                        'id' => $po->id,
                        'type' => $po->type,
                        'status' => $po->status,
                        'material_id' => $line?->material_id,
                        'qty_kg' => $line?->qty_kg,
                        'received_kg' => $line?->received_qty_kg,
                    ];
                });
        }

        $rows = DemoData::basePurchaseOrderRows()->all();

        foreach (PurchaseOrderOverlay::additions() as $addition) {
            $rows[] = $addition;
        }

        return collect($rows)
            ->map(function ($row) {
                $row = is_array($row) ? $row : (array) $row;
                $id = (int) ($row['id'] ?? 0);
                $overrides = PurchaseOrderOverlay::overrides($id);
                if ($overrides !== []) {
                    $row = array_merge($row, $overrides);
                }

                return (object) $row;
            })
            ->filter(fn ($po) => ($po->type ?? '') === PurchaseOrderType::YARN)
            ->values();
    }

    public static function isYarnPoFullyReceived(object $yarnPo): bool
    {
        if (($yarnPo->status ?? '') === PurchaseOrderStatus::RECEIVED) {
            return true;
        }

        $poId = (int) ($yarnPo->id ?? 0);
        $ordered = DemoData::purchaseOrderOrderedQty($yarnPo);
        $received = PurchaseOrder::receivedQtyFor($poId, $yarnPo);

        return $ordered > 0 && $received + 0.001 >= $ordered;
    }

    /**
     * @return list<int>
     */
    public static function materialIdsForGreigeSku(string $greigeSku): array
    {
        $recipe = DemoData::greigeRecipeData()[$greigeSku] ?? null;
        if ($recipe === null) {
            return [];
        }

        $ids = [];
        foreach ($recipe['lines'] as $line) {
            $ids[] = (int) $line[0];
        }

        return $ids;
    }
}
