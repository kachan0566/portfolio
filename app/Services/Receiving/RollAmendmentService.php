<?php

namespace App\Services\Receiving;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\ReceivingLine;
use App\Models\ReceivingRollAmendment;
use App\Models\ShipmentRollAllocation;
use App\Support\QtyHelper;
use Illuminate\Support\Facades\DB;

class RollAmendmentService
{
    public static function greigeRollEditBlockReason(GreigeRoll $roll): ?string
    {
        if ((int) $roll->receiving_line_id <= 0) {
            return '入荷明細に紐づいていない反は修正できません。';
        }

        if ((string) $roll->status !== GreigeRoll::STATUS_IN_STOCK) {
            return '消費済みの生機反は修正できません。';
        }

        return null;
    }

    public static function productRollEditBlockReason(ProductRoll $roll): ?string
    {
        if ((int) $roll->receiving_line_id <= 0) {
            return '入荷明細に紐づいていない反は修正できません。';
        }

        if ((string) $roll->status === ProductRoll::STATUS_SHIPPED) {
            return '出荷済みの反は修正できません。';
        }

        if (ShipmentRollAllocation::query()->where('product_roll_id', $roll->id)->exists()) {
            return '出荷引当がある反は修正できません。';
        }

        return null;
    }

    /**
     * @return array{changed: int, message: string}
     */
    public static function amendGreigeRoll(
        ReceivingLine $line,
        GreigeRoll $roll,
        float $tanQty,
        float $actualQtyM,
        ?string $reason = null,
    ): array {
        self::assertRollOnLine($line, $roll->receiving_line_id);
        $block = self::greigeRollEditBlockReason($roll);
        if ($block !== null) {
            throw new \InvalidArgumentException($block);
        }

        return self::applyAmendment(
            $line,
            ReceivingRollAmendment::ROLL_TYPE_GREIGE,
            (int) $roll->id,
            (string) $roll->code,
            [
                ReceivingRollAmendment::FIELD_TAN_QTY => [
                    'old' => (float) $roll->tan_qty,
                    'new' => QtyHelper::roundReceivingTan($tanQty),
                ],
                ReceivingRollAmendment::FIELD_ACTUAL_QTY_M => [
                    'old' => (float) $roll->actual_qty_m,
                    'new' => round($actualQtyM, 2),
                ],
            ],
            $reason,
            function () use ($roll, $tanQty, $actualQtyM) {
                $roll->update([
                    'tan_qty' => QtyHelper::roundReceivingTan($tanQty),
                    'actual_qty_m' => round($actualQtyM, 2),
                ]);
            },
        );
    }

    /**
     * @return array{changed: int, message: string}
     */
    public static function amendProductRoll(
        ReceivingLine $line,
        ProductRoll $roll,
        float $tanQty,
        float $actualQtyM,
        ?string $reason = null,
    ): array {
        self::assertRollOnLine($line, $roll->receiving_line_id);
        $block = self::productRollEditBlockReason($roll);
        if ($block !== null) {
            throw new \InvalidArgumentException($block);
        }

        return self::applyAmendment(
            $line,
            ReceivingRollAmendment::ROLL_TYPE_PRODUCT,
            (int) $roll->id,
            (string) $roll->code,
            [
                ReceivingRollAmendment::FIELD_TAN_QTY => [
                    'old' => (float) $roll->tan_qty,
                    'new' => QtyHelper::roundReceivingTan($tanQty),
                ],
                ReceivingRollAmendment::FIELD_ACTUAL_QTY_M => [
                    'old' => (float) $roll->actual_qty_m,
                    'new' => round($actualQtyM, 2),
                ],
            ],
            $reason,
            function () use ($roll, $tanQty, $actualQtyM) {
                $roll->update([
                    'tan_qty' => QtyHelper::roundReceivingTan($tanQty),
                    'actual_qty_m' => round($actualQtyM, 2),
                ]);
            },
        );
    }

    /**
     * @param  array<string, array{old: float, new: float}>  $fields
     * @return array{changed: int, message: string}
     */
    private static function applyAmendment(
        ReceivingLine $line,
        string $rollType,
        int $rollId,
        string $rollCode,
        array $fields,
        ?string $reason,
        callable $updateRoll,
    ): array {
        $changedFields = [];
        foreach ($fields as $field => $values) {
            if (abs($values['old'] - $values['new']) < 0.001) {
                continue;
            }
            $changedFields[$field] = $values;
        }

        if ($changedFields === []) {
            return ['changed' => 0, 'message' => '変更がありませんでした。'];
        }

        foreach ($changedFields as $field => $values) {
            if ($field === ReceivingRollAmendment::FIELD_TAN_QTY
                && ! QtyHelper::isValidReceivingTanStep($values['new'])) {
                throw new \InvalidArgumentException('反数は 0.25反刻みで入力してください。');
            }
            if ($field === ReceivingRollAmendment::FIELD_ACTUAL_QTY_M && $values['new'] <= 0) {
                throw new \InvalidArgumentException('実測mは 0 より大きい値を入力してください。');
            }
        }

        return DB::transaction(function () use ($line, $rollType, $rollId, $rollCode, $changedFields, $reason, $updateRoll) {
            $line = $line->fresh();
            $tanBefore = (float) ($line->qty_tan ?? 0);
            $mBefore = (int) ($line->qty_m ?? 0);
            $changedAt = now();

            $updateRoll();

            ReceivingLineTotals::sync($line->fresh());
            PurchaseOrderLineReceiver::syncFromReceivingLine($line->fresh());

            $lineAfter = $line->fresh();
            $tanAfter = (float) ($lineAfter->qty_tan ?? 0);
            $mAfter = (int) ($lineAfter->qty_m ?? 0);

            foreach ($changedFields as $field => $values) {
                ReceivingRollAmendment::query()->create([
                    'receiving_line_id' => $line->id,
                    'roll_type' => $rollType,
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'field' => $field,
                    'old_value' => $values['old'],
                    'new_value' => $values['new'],
                    'line_qty_tan_before' => $tanBefore,
                    'line_qty_m_before' => $mBefore,
                    'line_qty_tan_after' => $tanAfter,
                    'line_qty_m_after' => $mAfter,
                    'reason' => $reason,
                    'changed_at' => $changedAt,
                ]);
            }

            return [
                'changed' => count($changedFields),
                'message' => "反 {$rollCode} を修正しました。明細合計: {$tanBefore}反/{$mBefore}m → {$tanAfter}反/{$mAfter}m",
            ];
        });
    }

    private static function assertRollOnLine(ReceivingLine $line, ?int $rollReceivingLineId): void
    {
        if ((int) $rollReceivingLineId !== (int) $line->id) {
            throw new \InvalidArgumentException('この反は指定の入荷明細行に属していません。');
        }
    }
}
