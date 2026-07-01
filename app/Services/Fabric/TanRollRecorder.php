<?php

namespace App\Services\Fabric;

use App\Support\DemoData;
use App\Support\FabricTanRoll;
use App\Support\QtyHelper;

/**
 * 織り上がり・染め上がりの反明細を記録する。
 */
class TanRollRecorder
{
    /**
     * 織り上がり：実測 m 合計を物理反数に按分して反明細を作成する。
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
        if ($rollCount <= 0 || $actualMetersTotal <= 0) {
            return [];
        }

        $greige = DemoData::findGreige($greigeSku);
        $nominal = (int) ($greige->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
        $measuredAt ??= date('Y-m-d');
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        $poCode = $po?->code ?? 'PO-'.$poId;

        $perRoll = self::distributeMeters($actualMetersTotal, $rollCount);
        $created = [];

        foreach ($perRoll as $index => $meters) {
            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $created[] = FabricTanRoll::create([
                'code' => $greigeSku.'-'.$poCode.'-'.$seq,
                'po_id' => $poId,
                'greige_sku' => $greigeSku,
                'stage' => FabricTanRoll::STAGE_GREIGE_WIP,
                'nominal_meters' => $nominal,
                'weaving_meters' => $meters,
                'measured_at' => $measuredAt,
                'weaving_measured_at' => $measuredAt,
            ]);
        }

        return $created;
    }

    /**
     * 染め上がり：生機反を製品反へ変換し、染め上がり実測 m を記録する。
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

        $product = DemoData::findProduct($productId);
        $greige = DemoData::findGreigeByProductId($productId);
        if ($product === null || $greige === null) {
            return ['product_rolls' => [], 'total_meters' => 0.0];
        }

        $measuredAt ??= $po->finish_date ?? date('Y-m-d');
        $greigeNominal = (int) ($greige->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
        $productNominal = (int) ($product->meters_per_tan ?? DemoData::METERS_PER_TAN_PRODUCT);

        $greigeRolls = FabricTanRoll::forGreigeSku($greige->sku, FabricTanRoll::STAGE_GREIGE_WIP);
        $rolls = self::allRollsMutable();
        $productRolls = [];
        $totalMeters = 0.0;

        if ($greigeRolls->isNotEmpty()) {
            $takeCount = min($greigeRolls->count(), max(1, (int) round((float) ($po->qty_tan ?? QtyHelper::tanCount((int) $po->qty_meters, $productId)))));
            $selected = $greigeRolls->take($takeCount);

            foreach ($selected as $index => $greigeRoll) {
                $dyeingMeters = self::dyeingMetersFromWeaving((float) $greigeRoll->weaving_meters, $productNominal, $greigeNominal);
                $totalMeters += $dyeingMeters;

                foreach ($rolls as &$roll) {
                    if ((int) $roll['id'] === (int) $greigeRoll->id) {
                        $roll['stage'] = FabricTanRoll::STAGE_CONSUMED;
                        break;
                    }
                }
                unset($roll);

                $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $productRolls[] = self::appendRoll($rolls, [
                    'code' => $product->sku.'-'.$po->code.'-'.$seq,
                    'po_id' => $productPoId,
                    'greige_sku' => $greige->sku,
                    'product_id' => $productId,
                    'parent_roll_id' => (int) $greigeRoll->id,
                    'stage' => FabricTanRoll::STAGE_PRODUCT,
                    'nominal_meters' => $productNominal,
                    'weaving_meters' => (float) $greigeRoll->weaving_meters,
                    'dyeing_meters' => $dyeingMeters,
                    'measured_at' => $measuredAt,
                    'weaving_measured_at' => $greigeRoll->weaving_measured_at,
                    'dyeing_measured_at' => $measuredAt,
                ]);
            }
        } else {
            $rollCount = max(1, (int) round((float) ($po->qty_tan ?? QtyHelper::tanCount((int) $po->qty_meters, $productId))));
            $targetMeters = (int) ($po->qty_meters ?? QtyHelper::metersFromTan((float) $po->qty_tan, $productId));
            $perRoll = self::distributeMeters($targetMeters, $rollCount);

            foreach ($perRoll as $index => $weavingMeters) {
                $dyeingMeters = self::dyeingMetersFromWeaving($weavingMeters, $productNominal, $greigeNominal);
                $totalMeters += $dyeingMeters;
                $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $productRolls[] = self::appendRoll($rolls, [
                    'code' => $product->sku.'-'.$po->code.'-'.$seq,
                    'po_id' => $productPoId,
                    'greige_sku' => $greige->sku,
                    'product_id' => $productId,
                    'stage' => FabricTanRoll::STAGE_PRODUCT,
                    'nominal_meters' => $productNominal,
                    'weaving_meters' => $weavingMeters,
                    'dyeing_meters' => $dyeingMeters,
                    'measured_at' => $measuredAt,
                    'weaving_measured_at' => $measuredAt,
                    'dyeing_measured_at' => $measuredAt,
                ]);
            }
        }

        FabricTanRoll::replaceAll($rolls);

        return [
            'product_rolls' => $productRolls,
            'total_meters' => round($totalMeters, 2),
        ];
    }

    /**
     * 製品入荷：染め上がり実測 m で反明細を追加する。
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
        if ($actualMetersTotal <= 0) {
            return [];
        }

        $product = DemoData::findProduct($productId);
        $greige = DemoData::findGreigeByProductId($productId);
        if ($product === null) {
            return [];
        }

        $productNominal = (int) ($product->meters_per_tan ?? DemoData::METERS_PER_TAN_PRODUCT);
        $greigeNominal = (int) ($greige->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
        $measuredAt ??= date('Y-m-d');
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        $poCode = $po?->code ?? 'PO-'.$poId;
        $perRoll = self::distributeMeters($actualMetersTotal, $rollCount);
        $created = [];

        foreach ($perRoll as $index => $dyeingMeters) {
            $weavingMeters = $greigeNominal > 0 && $productNominal > 0
                ? round($dyeingMeters * ($greigeNominal / $productNominal), 2)
                : $dyeingMeters;
            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $created[] = FabricTanRoll::create([
                'code' => $product->sku.'-'.$poCode.'-'.$seq,
                'po_id' => $poId,
                'greige_sku' => $greige?->sku ?? '',
                'product_id' => $productId,
                'stage' => FabricTanRoll::STAGE_PRODUCT,
                'nominal_meters' => $productNominal,
                'weaving_meters' => $weavingMeters,
                'dyeing_meters' => $dyeingMeters,
                'measured_at' => $measuredAt,
                'weaving_measured_at' => $measuredAt,
                'dyeing_measured_at' => $measuredAt,
            ]);
        }

        return $created;
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

        // 軽いばらつき（合計を維持したまま ±1m を入れ替え）
        if ($rollCount >= 2 && $totalMeters >= 4) {
            $result[0] += 1.0;
            $result[$rollCount - 1] -= 1.0;
        }

        return $result;
    }

    private static function dyeingMetersFromWeaving(float $weavingMeters, int $productNominal, int $greigeNominal): float
    {
        if ($greigeNominal <= 0) {
            return round($weavingMeters, 2);
        }

        $ratio = $productNominal / $greigeNominal;

        return round($weavingMeters * $ratio, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function allRollsMutable(): array
    {
        return FabricTanRoll::all();
    }

    /**
     * @param  list<array<string, mixed>>  $rolls
     * @param  array<string, mixed>  $attributes
     */
    private static function appendRoll(array &$rolls, array $attributes): object
    {
        $nextId = (int) (collect($rolls)->max('id') ?? 0) + 1;
        $roll = array_merge([
            'id' => $nextId,
            'tan_qty' => 1.0,
        ], $attributes);
        $rolls[] = $roll;

        return (object) $roll;
    }
}
