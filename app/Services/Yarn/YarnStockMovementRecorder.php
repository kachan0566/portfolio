<?php

namespace App\Services\Yarn;

use App\Models\ReceivingLine;
use App\Models\YarnStockMovement;
use App\Support\YarnMovementReference;
use App\Support\YarnMovementType;

final class YarnStockMovementRecorder
{
    public static function recordYarnReceiving(
        ReceivingLine $line,
        int $materialId,
        float $qtyKg,
        string $movementDate,
        ?string $note = null,
    ): void {
        if ($qtyKg <= 0) {
            return;
        }

        $qtyKg = round($qtyKg, 3);
        $refType = YarnMovementReference::RECEIVING_LINE;
        $refId = $line->id;

        $exists = YarnStockMovement::query()
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->where('movement_type', YarnMovementType::RECEIVING)
            ->exists();

        if ($exists) {
            return;
        }

        $baseNote = $note ?? "入荷明細 #{$line->id}";

        YarnStockMovement::query()->create([
            'material_id' => $materialId,
            'movement_type' => YarnMovementType::RECEIVING,
            'qty_kg' => $qtyKg,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'movement_date' => $movementDate,
            'note' => $baseNote,
        ]);

        YarnStockMovement::query()->create([
            'material_id' => $materialId,
            'movement_type' => YarnMovementType::CONSUMPTION,
            'qty_kg' => -$qtyKg,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'movement_date' => $movementDate,
            'note' => $baseNote.'（織工場消費）',
        ]);
    }

    public static function recordAdjustment(
        int $materialId,
        float $qtyKg,
        string $movementDate,
        string $note = '初期在庫',
    ): void {
        if ($qtyKg <= 0) {
            return;
        }

        $exists = YarnStockMovement::query()
            ->where('material_id', $materialId)
            ->where('movement_type', YarnMovementType::ADJUSTMENT)
            ->where('reference_type', YarnMovementReference::MANUAL)
            ->whereNull('reference_id')
            ->exists();

        if ($exists) {
            return;
        }

        YarnStockMovement::query()->create([
            'material_id' => $materialId,
            'movement_type' => YarnMovementType::ADJUSTMENT,
            'qty_kg' => round($qtyKg, 3),
            'reference_type' => YarnMovementReference::MANUAL,
            'reference_id' => null,
            'movement_date' => $movementDate,
            'note' => $note,
        ]);
    }
}
