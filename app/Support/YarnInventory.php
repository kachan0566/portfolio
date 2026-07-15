<?php

namespace App\Support;

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
        if (DemoData::usesYarnStockDatabase()) {
            return max(0.0, round((float) YarnStockMovement::query()
                ->where('material_id', $materialId)
                ->sum('qty_kg'), 3));
        }

        $base = DemoData::yarnStockKg($materialId);
        $historicalReceived = self::historicalPoReceivedKg($materialId);
        $overlay = self::readLegacyStockOverlay();

        return max(0.0, round($base + $historicalReceived + ($overlay[$materialId] ?? 0.0), 3));
    }

    /** レガシー（DB 未投入）入荷時の在庫加算 */
    public static function addStockKgLegacy(int $materialId, float $qty): void
    {
        if ($qty <= 0 || DemoData::usesYarnStockDatabase()) {
            return;
        }

        $overlay = self::readLegacyStockOverlay();
        $overlay[$materialId] = ($overlay[$materialId] ?? 0.0) + $qty;
        self::writeLegacyStockOverlay($overlay);
    }

    /** デモデータ上ですでに入荷済みの糸発注分 */
    private static function historicalPoReceivedKg(int $materialId): float
    {
        if (DemoData::usesPurchaseOrderDatabase()) {
            return (float) PurchaseOrderLine::query()
                ->whereHas('purchaseOrder', fn ($q) => $q->where('type', PurchaseOrderType::YARN))
                ->where('material_id', $materialId)
                ->sum('received_qty_kg');
        }

        $total = 0.0;
        foreach (DemoData::basePurchaseOrderRows() as $row) {
            if (($row['type'] ?? '') !== PurchaseOrderType::YARN) {
                continue;
            }
            if ((int) ($row['material_id'] ?? 0) !== $materialId) {
                continue;
            }
            $total += (float) ($row['received_kg'] ?? $row['received'] ?? 0);
        }

        return $total;
    }

    /** 糸発注の未入荷残（kg） */
    public static function onOrderRemainingKg(int $materialId): float
    {
        if (DemoData::usesPurchaseOrderDatabase()) {
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

        $total = 0.0;
        foreach (DemoData::basePurchaseOrderRows()->merge(collect(PurchaseOrderOverlay::additions())) as $row) {
            $row = is_array($row) ? $row : (array) $row;
            $id = (int) ($row['id'] ?? 0);
            $row = array_merge($row, PurchaseOrderOverlay::overrides($id));
            $po = (object) $row;
            if (($po->type ?? '') !== PurchaseOrderType::YARN) {
                continue;
            }
            if ((int) ($po->material_id ?? 0) !== $materialId) {
                continue;
            }
            if (! PurchaseOrderStatus::isActive($po->status ?? '')) {
                continue;
            }
            $ordered = (float) ($po->qty_kg ?? 0);
            $received = (float) ($po->received_kg ?? 0) + DemoState::receivedOverlayQty($id);
            $total += max(0.0, $ordered - $received);
        }

        return $total;
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
        if (DemoData::usesYarnStockDatabase()) {
            return YarnAllocation::allocatedKg($materialId, $excludeGreigePoId);
        }

        $total = 0.0;
        foreach (self::legacyAllocations() as $line) {
            if ((int) $line['material_id'] !== $materialId) {
                continue;
            }
            if ($excludeGreigePoId !== null && (int) ($line['greige_po_id'] ?? 0) === $excludeGreigePoId) {
                continue;
            }
            $total += (float) $line['qty_kg'];
        }

        return $total;
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
                $material = DemoData::findMaterial($materialId);
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
        if (DemoData::usesYarnStockDatabase()) {
            $rows = [];
            foreach ($lines as $line) {
                $rows[] = [
                    'material_id' => (int) $line['material_id'],
                    'qty_kg' => (float) $line['qty_kg'],
                ];
            }
            YarnAllocation::replaceForGreigePo($greigePoId, $rows);

            return;
        }

        $all = collect(self::legacyAllocations())
            ->reject(fn ($l) => (int) ($l['greige_po_id'] ?? 0) === $greigePoId)
            ->values()
            ->all();

        foreach ($lines as $line) {
            $all[] = $line;
        }

        self::writeLegacyAllocations($all);
    }

    public static function releaseGreigePo(int $greigePoId): void
    {
        if (DemoData::usesYarnStockDatabase()) {
            YarnAllocation::releaseGreigePo($greigePoId);

            return;
        }

        $all = collect(self::legacyAllocations())
            ->reject(fn ($l) => (int) ($l['greige_po_id'] ?? 0) === $greigePoId)
            ->values()
            ->all();

        self::writeLegacyAllocations($all);
    }

    /** @return list<array{date: string, material_id: int, qty_kg: float, note: string, movement_type?: string}> */
    public static function stockMovements(): array
    {
        if (DemoData::usesYarnStockDatabase()) {
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

        $movements = [];
        foreach (DemoData::receivings() as $r) {
            if (($r->po_type ?? '') !== PurchaseOrderType::YARN) {
                continue;
            }
            $movements[] = [
                'date' => $r->date,
                'material_id' => (int) $r->material_id,
                'qty_kg' => (float) ($r->qty_kg ?? $r->qty ?? 0),
                'note' => '入荷 '.$r->code,
            ];
        }
        foreach (DemoState::extraReceivings() as $r) {
            if (($r['po_type'] ?? '') !== PurchaseOrderType::YARN) {
                continue;
            }
            $movements[] = [
                'date' => $r['date'],
                'material_id' => (int) $r['material_id'],
                'qty_kg' => (float) ($r['qty_kg'] ?? $r['qty'] ?? 0),
                'note' => '入荷 '.($r['code'] ?? ''),
            ];
        }

        usort($movements, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $movements;
    }

    /** @return list<array{material_id: int, qty_kg: float, greige_po_id: int, status: string}> */
    private static function legacyAllocations(): array
    {
        $path = storage_path('app/yarn_allocations.json');
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['lines']) || ! is_array($data['lines'])) {
            return [];
        }

        return array_values(array_filter($data['lines'], fn ($l) => is_array($l) && (float) ($l['qty_kg'] ?? 0) > 0));
    }

    /** @param list<array{material_id: int, qty_kg: float, greige_po_id: int, status: string}> $lines */
    private static function writeLegacyAllocations(array $lines): void
    {
        $path = storage_path('app/yarn_allocations.json');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['lines' => array_values($lines)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /** @return array<int, float> */
    private static function readLegacyStockOverlay(): array
    {
        $path = storage_path('app/yarn_stock_state.json');
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
    private static function writeLegacyStockOverlay(array $map): void
    {
        $path = storage_path('app/yarn_stock_state.json');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
