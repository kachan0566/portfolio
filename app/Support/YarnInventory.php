<?php

namespace App\Support;

/**
 * 糸の在庫・発注残・引当（デモ用 JSON）。
 */
class YarnInventory
{
    private const STOCK_FILE = 'yarn_stock_state.json';

    private const ALLOC_FILE = 'yarn_allocations.json';

    public static function effectiveStockKg(int $materialId): float
    {
        $base = DemoData::yarnStockKg($materialId);
        $overlay = self::readFloatMap(self::STOCK_FILE);
        $historicalReceived = self::historicalPoReceivedKg($materialId);

        return max(0.0, $base + $historicalReceived + ($overlay[$materialId] ?? 0.0));
    }

    /** デモデータ上ですでに入荷済みの糸発注分（セッション入荷は overlay で加算） */
    private static function historicalPoReceivedKg(int $materialId): float
    {
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

    /**
     * @return list<array{material_id: int, qty_kg: float, greige_po_id: int, status: string}>
     */
    public static function allocations(): array
    {
        $path = storage_path('app/'.self::ALLOC_FILE);
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['lines']) || ! is_array($data['lines'])) {
            return [];
        }

        return array_values(array_filter($data['lines'], fn ($l) => is_array($l) && (float) ($l['qty_kg'] ?? 0) > 0));
    }

    /** @param list<array{material_id: int, qty_kg: float}> $requirements */
    public static function canFulfill(array $requirements, ?int $excludeGreigePoId = null): bool
    {
        foreach ($requirements as $req) {
            $materialId = (int) (is_array($req) ? $req['material_id'] : $req->material_id);
            $needed = (float) (is_array($req) ? ($req['required_kg'] ?? 0) : $req->required_kg);
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

        return max(0.0, $stock + $onOrder - $allocated);
    }

    public static function allocatedKg(int $materialId, ?int $excludeGreigePoId = null): float
    {
        $total = 0.0;
        foreach (self::allocations() as $line) {
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

    public static function setAllocationsForGreigePo(int $greigePoId, array $lines): void
    {
        $all = collect(self::allocations())
            ->reject(fn ($l) => (int) ($l['greige_po_id'] ?? 0) === $greigePoId)
            ->values()
            ->all();

        foreach ($lines as $line) {
            $all[] = $line;
        }

        self::writeAllocations($all);
    }

    public static function releaseGreigePo(int $greigePoId): void
    {
        $all = collect(self::allocations())
            ->reject(fn ($l) => (int) ($l['greige_po_id'] ?? 0) === $greigePoId)
            ->values()
            ->all();

        self::writeAllocations($all);
    }

    public static function addStockKg(int $materialId, float $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $overlay = self::readFloatMap(self::STOCK_FILE);
        $overlay[$materialId] = ($overlay[$materialId] ?? 0.0) + $qty;
        self::writeStockOverlay($overlay);
    }

    /** @return list<array{date: string, material_id: int, qty_kg: float, note: string}> */
    public static function stockMovements(): array
    {
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

    /** @param list<array{material_id: int, qty_kg: float, greige_po_id: int, status: string}> $lines */
    private static function writeAllocations(array $lines): void
    {
        $path = storage_path('app/'.self::ALLOC_FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['lines' => array_values($lines)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private static function writeStockOverlay(array $map): void
    {
        $path = storage_path('app/'.self::STOCK_FILE);
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
}
