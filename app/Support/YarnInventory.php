<?php

namespace App\Support;

use App\Support\MasterCatalog;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\YarnAllocation;
use App\Models\YarnStockMovement;

/**
 * 糸の在庫・発注残・引当。
 */
class YarnInventory
{
    public static function effectiveStockKg(int $materialId): float
    {
        return max(0.0, round((float) YarnStockMovement::query()
            ->where('material_id', $materialId)
            ->sum('qty_kg'), 3));
    }

    /** 糸発注の未入荷残（kg） */
    public static function onOrderRemainingKg(int $materialId): float
    {
        $total = 0.0;
        PurchaseOrder::query()
            ->where('type', PurchaseOrderType::YARN)
            ->whereIn('status', [
                PurchaseOrderStatus::ORDERED,
                PurchaseOrderStatus::PARTIAL,
            ])
            ->with('lines')
            ->each(function (PurchaseOrder $po) use ($materialId, &$total) {
                foreach ($po->lines as $line) {
                    if ((int) ($line->material_id ?? 0) !== $materialId) {
                        continue;
                    }
                    $ordered = (float) ($line->qty_kg ?? 0);
                    $received = (float) ($line->received_qty_kg ?? 0);
                    $total += max(0.0, $ordered - $received);
                }
            });

        return round($total, 3);
    }

    /** @param list<array{material_id: int, qty_kg: float}> $requirements */
    public static function canFulfill(array $requirements, ?int $excludeGreigePoId = null): bool
    {
        foreach ($requirements as $req) {
            $materialId = (int) (is_array($req) ? $req['material_id'] : $req->material_id);
            $needed = (float) (is_array($req) ? ($req['required_kg'] ?? $req['qty_kg'] ?? 0) : $req->required_kg);
            if ($needed <= 0) {
                continue;
            }
            if (self::availableKg($materialId, $excludeGreigePoId) + 0.001 < $needed) {
                return false;
            }
        }

        return true;
    }

    public static function availableKg(int $materialId, ?int $excludeGreigePoId = null): float
    {
        $stock = self::effectiveStockKg($materialId);
        $onOrder = self::onOrderRemainingKg($materialId);
        $allocated = self::allocatedKg($materialId, $excludeGreigePoId);

        return max(0.0, round($stock + $onOrder - $allocated, 3));
    }

    public static function allocatedKg(int $materialId, ?int $excludeGreigePoId = null): float
    {
        return YarnAllocation::allocatedKg($materialId, $excludeGreigePoId);
    }

    /** @param list<object{material_id: int, required_kg: float}> $requirements */
    public static function shortageMessages(array $requirements, ?int $excludeGreigePoId = null): array
    {
        $messages = [];
        foreach ($requirements as $req) {
            $materialId = (int) $req->material_id;
            $needed = (float) $req->required_kg;
            $available = self::availableKg($materialId, $excludeGreigePoId);
            if ($needed > 0 && $available + 0.001 < $needed) {
                $material = MasterCatalog::findMaterial($materialId);
                $short = round($needed - $available, 2);
                $messages[] = ($material?->name ?? '糸').'（'.($material?->sku ?? '').'）が '.$short.'kg 不足しています（必要 '.round($needed, 2).'kg / 利用可能 '.round($available, 2).'kg）。';
            }
        }

        return $messages;
    }

    /**
     * @param list<object{material_id: int, required_kg: float}> $requirements
     * @return list<array{material_id: int, qty_kg: float, greige_po_id: int, status: string}>
     */
    public static function buildAllocationLines(int $greigePoId, array $requirements, string $status): array
    {
        $lines = [];
        foreach ($requirements as $req) {
            if ((float) $req->required_kg <= 0) {
                continue;
            }
            $lines[] = [
                'material_id' => (int) $req->material_id,
                'qty_kg' => (float) $req->required_kg,
                'greige_po_id' => $greigePoId,
                'status' => $status,
            ];
        }

        return $lines;
    }

    /**
     * @param list<array{material_id: int, qty_kg: float, greige_po_id?: int, status?: string}> $lines
     */
    public static function setAllocationsForGreigePo(int $greigePoId, array $lines): void
    {
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = [
                'material_id' => (int) $line['material_id'],
                'qty_kg' => (float) $line['qty_kg'],
            ];
        }
        YarnAllocation::replaceForGreigePo($greigePoId, $rows);
    }

    public static function releaseGreigePo(int $greigePoId): void
    {
        YarnAllocation::releaseGreigePo($greigePoId);
    }

    /** @return list<array{date: string, material_id: int, qty_kg: float, note: string, movement_type?: string}> */
    public static function stockMovements(): array
    {
        return YarnStockMovement::query()
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (YarnStockMovement $row) => [
                'date' => $row->movement_date->format('Y-m-d'),
                'material_id' => (int) $row->material_id,
                'qty_kg' => (float) $row->qty_kg,
                'note' => (string) ($row->note ?? ''),
                'movement_type' => (string) $row->movement_type,
            ])
            ->values()
            ->all();
    }
}
