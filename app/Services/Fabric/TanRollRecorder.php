<?php

namespace App\Services\Fabric;

use App\Support\MasterCatalog;
use App\Support\DemoData;
use App\Support\FabricTanRoll;
use App\Support\GreigeRoll;
use App\Support\ProductRoll;
use App\Support\QtyHelper;

/**
 * 織り上がり・染め上がりの反明細を記録する。
 */
class TanRollRecorder
{
    /**
     * 織り上がり：反行入力から生機反を作成。
     *
     * @param  list<array{tan_qty: float|int, actual_qty_m: float|int}>  $lines
     * @return list<object>
     */
    public static function recordWeavingFromLines(
        int $poId,
        string $greigeSku,
        array $lines,
        ?string $measuredAt = null,
        ?int $receivingId = null,
        ?int $receivingLineId = null,
    ): array {
        if ($lines === []) {
            return [];
        }

        $greige = MasterCatalog::findGreige($greigeSku);
        $nominal = (int) ($greige?->meters_per_tan ?? QtyHelper::METERS_PER_TAN_GREIGE);
        $measuredAt ??= date('Y-m-d');
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        $poCode = $po?->code ?? 'PO-'.$poId;
        $created = [];

        foreach ($lines as $index => $line) {
            $tanQty = QtyHelper::roundReceivingTan((float) ($line['tan_qty'] ?? 1));
            $actualM = round((float) ($line['actual_qty_m'] ?? 0), 2);
            if ($tanQty <= 0 || $actualM <= 0) {
                continue;
            }

            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $created[] = GreigeRoll::create([
                'code' => $greigeSku.'-'.$poCode.'-'.$seq,
                'greige_sku' => $greigeSku,
                'purchase_order_id' => $poId,
                'receiving_id' => $receivingId,
                'receiving_line_id' => $receivingLineId,
                'tan_qty' => $tanQty,
                'actual_qty_m' => $actualM,
                'nominal_meters' => $nominal,
                'status' => GreigeRoll::STATUS_IN_STOCK,
                'received_date' => $measuredAt,
            ]);
        }

        return $created;
    }

    /**
     * @deprecated 按分方式。recordWeavingFromLines を使用。
     *
     * @return list<object>
     */
    public static function recordWeavingCompletion(
        int $poId,
        string $greigeSku,
        int $rollCount,
        int $actualMetersTotal,
        ?string $measuredAt = null,
    ): array {
        $perRoll = self::distributeMeters($actualMetersTotal, max(1, $rollCount));
        $lines = array_map(fn ($m) => ['tan_qty' => 1.0, 'actual_qty_m' => $m], $perRoll);

        return self::recordWeavingFromLines($poId, $greigeSku, $lines, $measuredAt);
    }

    /**
     * 染め上がり：生機反を半分カットして製品反を生成。
     *
     * @return array{product_rolls: list<object>, total_meters: float}
     */
    public static function recordDyeingCompletion(
        int $productPoId,
        int $productId,
        ?string $measuredAt = null,
    ): array {
        $po = DemoData::purchaseOrders()->firstWhere('id', $productPoId);
        if ($po === null) {
            return ['product_rolls' => [], 'total_meters' => 0.0];
        }

        $product = MasterCatalog::findProduct($productId);
        $greige = MasterCatalog::findGreigeByProductId($productId);
        if ($product === null || $greige === null) {
            return ['product_rolls' => [], 'total_meters' => 0.0];
        }

        $measuredAt ??= $po->finish_date ?? date('Y-m-d');
        $greigeNominal = (int) ($greige->meters_per_tan ?? QtyHelper::METERS_PER_TAN_GREIGE);
        $productNominal = (int) ($product->meters_per_tan ?? QtyHelper::METERS_PER_TAN_PRODUCT);

        $targetTan = (float) ($po->qty_tan ?? QtyHelper::roundIntegerTan(
            QtyHelper::tanCount((int) $po->qty_meters, $productId)
        ));
        $greigeRolls = GreigeRoll::inDyeingForPurchaseOrder($productPoId);
        if ($greigeRolls->isEmpty()) {
            $greigeRolls = GreigeRoll::inDyeingForSku($greige->sku);
        }
        $productRolls = [];
        $totalMeters = 0.0;
        $seq = 0;

        foreach ($greigeRolls as $greigeRoll) {
            if ($targetTan <= 0.0001) {
                break;
            }

            $rollTan = (float) $greigeRoll->tan_qty;
            $rollM = (float) $greigeRoll->actual_qty_m;
            $useTan = min($targetTan, $rollTan);
            $consumeM = $rollM;
            if ($useTan + 0.0001 < $rollTan) {
                $consumeM = round($rollM * ($useTan / $rollTan), 2);
            }
            $dyeingMeters = self::dyeingMetersFromWeaving($consumeM, $productNominal, $greigeNominal);

            self::consumeGreigeRollFromDyeing((int) $greigeRoll->id, $useTan, $consumeM);

            $seq++;
            $seqStr = str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
            $productRolls[] = ProductRoll::create([
                'code' => $product->sku.'-'.$po->code.'-'.$seqStr,
                'product_id' => $productId,
                'parent_greige_roll_id' => (int) $greigeRoll->id,
                'purchase_order_id' => $productPoId,
                'tan_qty' => $useTan,
                'actual_qty_m' => $dyeingMeters,
                'nominal_meters' => $productNominal,
                'status' => ProductRoll::STATUS_IN_STOCK,
                'received_date' => $measuredAt,
            ]);

            $totalMeters += $dyeingMeters;
            $targetTan = round($targetTan - $useTan, 2);
        }

        return [
            'product_rolls' => $productRolls,
            'total_meters' => round($totalMeters, 2),
        ];
    }

    /**
     * 製品入荷：反行入力から製品反を作成。
     *
     * @param  list<array{tan_qty: float|int, actual_qty_m: float|int}>  $lines
     * @return list<object>
     */
    public static function recordProductReceivingFromLines(
        int $poId,
        int $productId,
        array $lines,
        ?string $measuredAt = null,
        ?int $receivingId = null,
        ?int $receivingLineId = null,
    ): array {
        if ($lines === []) {
            return [];
        }

        $product = MasterCatalog::findProduct($productId);
        if ($product === null) {
            return [];
        }

        $productNominal = (int) ($product->meters_per_tan ?? QtyHelper::METERS_PER_TAN_PRODUCT);
        $measuredAt ??= date('Y-m-d');
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        $poCode = $po?->code ?? 'PO-'.$poId;
        $created = [];

        foreach ($lines as $index => $line) {
            $tanQty = QtyHelper::roundReceivingTan((float) ($line['tan_qty'] ?? 1));
            $actualM = round((float) ($line['actual_qty_m'] ?? 0), 2);
            if ($tanQty <= 0 || $actualM <= 0) {
                continue;
            }

            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $created[] = ProductRoll::create([
                'code' => $product->sku.'-'.$poCode.'-'.$seq,
                'product_id' => $productId,
                'purchase_order_id' => $poId,
                'receiving_id' => $receivingId,
                'receiving_line_id' => $receivingLineId,
                'tan_qty' => $tanQty,
                'actual_qty_m' => $actualM,
                'nominal_meters' => $productNominal,
                'status' => ProductRoll::STATUS_IN_STOCK,
                'received_date' => $measuredAt,
            ]);
        }

        return $created;
    }

    /**
     * @deprecated recordProductReceivingFromLines を使用
     *
     * @return list<object>
     */
    public static function recordProductReceiving(
        int $poId,
        int $productId,
        float $qtyTan,
        int $actualMetersTotal,
        ?string $measuredAt = null,
    ): array {
        $rollCount = max(1, (int) round($qtyTan));
        $perRoll = self::distributeMeters($actualMetersTotal, $rollCount);
        $lines = array_map(fn ($m) => ['tan_qty' => 1.0, 'actual_qty_m' => $m], $perRoll);

        return self::recordProductReceivingFromLines($poId, $productId, $lines, $measuredAt);
    }

    private static function consumeGreigeRollFromDyeing(int $greigeRollId, float $useTan, float $consumeM): void
    {
        $roll = GreigeRoll::find($greigeRollId);
        if ($roll === null) {
            return;
        }

        $rollTan = (float) $roll->tan_qty;
        $rollM = (float) $roll->actual_qty_m;
        $remainingTan = round($rollTan - $useTan, 2);
        $remainingM = round($rollM - $consumeM, 2);

        if ($remainingTan <= 0.0001) {
            GreigeRoll::update($greigeRollId, [
                'status' => GreigeRoll::STATUS_CONSUMED,
                'tan_qty' => $useTan,
                'actual_qty_m' => $consumeM,
                'dyeing_purchase_order_line_id' => null,
            ]);

            return;
        }

        GreigeRoll::update($greigeRollId, [
            'status' => GreigeRoll::STATUS_CONSUMED,
            'tan_qty' => $useTan,
            'actual_qty_m' => $consumeM,
            'dyeing_purchase_order_line_id' => null,
        ]);

        GreigeRoll::create([
            'code' => $roll->code.'-R',
            'greige_sku' => $roll->greige_sku,
            'purchase_order_id' => $roll->purchase_order_id,
            'receiving_id' => $roll->receiving_id ?? null,
            'tan_qty' => $remainingTan,
            'actual_qty_m' => $remainingM,
            'nominal_meters' => (int) $roll->nominal_meters,
            'status' => GreigeRoll::STATUS_IN_DYEING,
            'dyeing_purchase_order_line_id' => $roll->dyeing_purchase_order_line_id ?? null,
            'received_date' => $roll->received_date,
        ]);
    }

    private static function consumeGreigeRollHalf(int $greigeRollId, float $halfTan, float $consumeM): void
    {
        $roll = GreigeRoll::find($greigeRollId);
        if ($roll === null) {
            return;
        }

        $rollTan = (float) $roll->tan_qty;
        $rollM = (float) $roll->actual_qty_m;
        $remainingTan = round($rollTan - $halfTan, 2);
        $remainingM = round($rollM - $consumeM, 2);

        if ($remainingTan <= 0.0001) {
            GreigeRoll::update($greigeRollId, [
                'status' => GreigeRoll::STATUS_CONSUMED,
                'tan_qty' => $halfTan,
                'actual_qty_m' => $consumeM,
            ]);

            return;
        }

        GreigeRoll::update($greigeRollId, [
            'status' => GreigeRoll::STATUS_CONSUMED,
            'tan_qty' => $halfTan,
            'actual_qty_m' => $consumeM,
        ]);

        GreigeRoll::create([
            'code' => $roll->code.'-R',
            'greige_sku' => $roll->greige_sku,
            'purchase_order_id' => $roll->purchase_order_id,
            'receiving_id' => $roll->receiving_id ?? null,
            'tan_qty' => $remainingTan,
            'actual_qty_m' => $remainingM,
            'nominal_meters' => (int) $roll->nominal_meters,
            'status' => GreigeRoll::STATUS_IN_STOCK,
            'received_date' => $roll->received_date,
        ]);
    }

    /**
     * @return list<float>
     */
    public static function distributeMeters(int $totalMeters, int $rollCount): array
    {
        if ($rollCount <= 0) {
            return [];
        }

        if ($rollCount === 1) {
            return [(float) $totalMeters];
        }

        $base = intdiv($totalMeters, $rollCount);
        $remainder = $totalMeters - ($base * $rollCount);
        $result = array_fill(0, $rollCount, (float) $base);

        for ($i = 0; $i < $remainder; $i++) {
            $result[$i] += 1.0;
        }

        if ($rollCount >= 2 && $totalMeters >= 4) {
            $result[0] += 1.0;
            $result[$rollCount - 1] -= 1.0;
        }

        return $result;
    }

    /**
     * 入荷反数から反行の初期値を生成（0.25刻み対応）。
     *
     * @return list<array{tan_qty: float, actual_qty_m: int}>
     */
    public static function defaultRollLines(float $qtyTan, int $totalMeters): array
    {
        $qtyTan = QtyHelper::roundReceivingTan($qtyTan);
        if ($qtyTan <= 0 || $totalMeters <= 0) {
            return [];
        }

        $fullRolls = (int) floor($qtyTan);
        $fraction = round($qtyTan - $fullRolls, 2);
        $lines = [];

        if ($fullRolls > 0) {
            $perFull = self::distributeMeters($totalMeters, max(1, $fullRolls + ($fraction > 0 ? 1 : 0)));
            for ($i = 0; $i < $fullRolls; $i++) {
                $lines[] = [
                    'tan_qty' => 1.0,
                    'actual_qty_m' => (int) round($perFull[$i] ?? ($totalMeters / $qtyTan)),
                ];
            }
            if ($fraction > 0) {
                $lines[] = [
                    'tan_qty' => $fraction,
                    'actual_qty_m' => (int) round($perFull[$fullRolls] ?? 0),
                ];
            }
        } else {
            $lines[] = ['tan_qty' => $fraction, 'actual_qty_m' => $totalMeters];
        }

        return $lines;
    }

    private static function dyeingMetersFromWeaving(float $weavingMeters, int $productNominal, int $greigeNominal): float
    {
        if ($greigeNominal <= 0) {
            return round($weavingMeters, 2);
        }

        $ratio = $productNominal / $greigeNominal;

        return round($weavingMeters * $ratio, 2);
    }
}
