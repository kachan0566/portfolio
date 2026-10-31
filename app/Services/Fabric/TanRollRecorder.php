<?php

namespace App\Services\Fabric;

use App\Models\Inventory\GreigeRoll as GreigeRollModel;
use App\Models\Inventory\ProductRoll as ProductRollModel;
use App\Support\Core\DemoData;
use App\Support\Inventory\GreigeRoll;
use App\Support\Master\MasterCatalog;
use App\Support\Inventory\ProductRoll;
use App\Support\Qty\QtyHelper;

/**
 * 織り上がり・染め上がりの反明細を記録する。
 */
class TanRollRecorder
{
    /**
     * 織り上がり：反行入力から生機反を作成する。
     *
     * 反コードは SKU-発注コード-連番。連番は同じ発注の既存生機反の最大番号の次から付ける。
     *
     * @param  int  $poId  発注 ID。purchase_order_id と連番の起点に使う
     * @param  string  $greigeSku  生機 SKU。コード接頭辞とマスタ検索に使う
     * @param  list<array{tan_qty: float|int, actual_qty_m: float|int}>  $lines  今回入力の反行
     * @param  string|null  $measuredAt  実測日（YYYY-MM-DD）。省略時は当日
     * @param  int|null  $receivingId  入荷ヘッダ ID。明細解決の補助
     * @param  int|null  $receivingLineId  入荷明細 ID。反の紐づけ先
     * @return list<object>  作成した生機反
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
        $prefix = $greigeSku.'-'.$poCode.'-';
        $seq = self::nextGreigeRollSeq($poId, $greige?->id, $prefix);
        $created = [];

        foreach ($lines as $line) {
            $tanQty = QtyHelper::roundReceivingTan((float) ($line['tan_qty'] ?? 1));
            $actualM = round((float) ($line['actual_qty_m'] ?? 0), 2);
            if ($tanQty <= 0 || $actualM <= 0) {
                continue;
            }

            $seq++;
            $created[] = GreigeRoll::create([
                'code' => $prefix.self::formatRollSeq($seq),
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
     * 染め上がり：生機反を消費して製品反を生成する。
     *
     * 製品反コードの連番は、同じ発注・製品の既存 product_rolls の最大番号の次から付ける。
     *
     * @param  int  $productPoId  製品発注 ID
     * @param  int  $productId  製品 ID
     * @param  string|null  $measuredAt  実測日。省略時は発注の finish_date または当日
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

        $targetTan = (float) ($po->qty_tan ?? QtyHelper::roundTan(
            QtyHelper::tanCount((float) $po->qty_meters, $productId)
        ));
        $greigeRolls = GreigeRoll::inDyeingForPurchaseOrder($productPoId);
        if ($greigeRolls->isEmpty()) {
            $greigeRolls = GreigeRoll::inDyeingForSku($greige->sku);
        }
        $productRolls = [];
        $totalMeters = 0.0;
        $prefix = $product->sku.'-'.$po->code.'-';
        $seq = self::nextProductRollSeq($productPoId, $productId, $prefix);

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
            $productRolls[] = ProductRoll::create([
                'code' => $prefix.self::formatRollSeq($seq),
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
     * 製品入荷：反行入力から製品反を作成する。
     *
     * 反コードは SKU-発注コード-連番。連番は同じ発注・製品の既存反の最大番号の次から付ける。
     * 2回目以降の入荷でも 01 からやり直さない。
     *
     * @param  int  $poId  発注 ID
     * @param  int  $productId  製品 ID
     * @param  list<array{tan_qty: float|int, actual_qty_m: float|int}>  $lines  今回入力の反行
     * @param  string|null  $measuredAt  実測日（YYYY-MM-DD）。省略時は当日
     * @param  int|null  $receivingId  入荷ヘッダ ID。明細解決の補助
     * @param  int|null  $receivingLineId  入荷明細 ID。反の紐づけ先
     * @return list<object>  作成した製品反
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
        $prefix = $product->sku.'-'.$poCode.'-';
        $seq = self::nextProductRollSeq($poId, $productId, $prefix);
        $created = [];

        foreach ($lines as $line) {
            $tanQty = QtyHelper::roundReceivingTan((float) ($line['tan_qty'] ?? 1));
            $actualM = round((float) ($line['actual_qty_m'] ?? 0), 2);
            if ($tanQty <= 0 || $actualM <= 0) {
                continue;
            }

            $seq++;
            $created[] = ProductRoll::create([
                'code' => $prefix.self::formatRollSeq($seq),
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
     * @return list<array{tan_qty: float, actual_qty_m: float}>
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
                    'actual_qty_m' => round($perFull[$i] ?? ($totalMeters / $qtyTan), 2),
                ];
            }
            if ($fraction > 0) {
                $lines[] = [
                    'tan_qty' => $fraction,
                    'actual_qty_m' => round($perFull[$fullRolls] ?? 0, 2),
                ];
            }
        } else {
            $lines[] = ['tan_qty' => $fraction, 'actual_qty_m' => $totalMeters];
        }

        return $lines;
    }

    /**
     * 織り実測mを、生機反あたりmと製品反あたりmの比で染め実測mに換算する。
     *
     * @param  float  $weavingMeters  消費した生機の実測m
     * @param  int  $productNominal  製品1反あたりの基準m
     * @param  int  $greigeNominal  生機1反あたりの基準m
     * @return float  染め実測m。生機基準mが0以下なら織り実測をそのまま返す
     */
    private static function dyeingMetersFromWeaving(float $weavingMeters, int $productNominal, int $greigeNominal): float
    {
        if ($greigeNominal <= 0) {
            return round($weavingMeters, 2);
        }

        $ratio = $productNominal / $greigeNominal;

        return round($weavingMeters * $ratio, 2);
    }

    /**
     * 同じ発注・製品の既存製品反から、次に使う連番の直前値を返す。
     *
     * product_rolls の code から接頭辞に続く数字の最大値を返す。
     * 該当が無ければ 0（呼び出し側で +1 する）。
     *
     * @param  int  $poId  purchase_orders.id。product_rolls.purchase_order_id の絞り込みに使う
     * @param  int  $productId  products.id。product_rolls.product_id の絞り込みに使う
     * @param  string  $prefix  コード接頭辞（例: FAB-T-BK-PO-2606-009-）
     * @return int  既存の最大連番。該当なしは 0
     */
    private static function nextProductRollSeq(int $poId, int $productId, string $prefix): int
    {
        $codes = ProductRollModel::query()
            ->where('purchase_order_id', $poId)
            ->where('product_id', $productId)
            ->pluck('code')
            ->all();

        return self::maxRollSeq($codes, $prefix);
    }

    /**
     * 同じ発注の既存生機反から、次に使う連番の直前値を返す。
     *
     * @param  int  $poId  purchase_orders.id。greige_rolls.purchase_order_id の絞り込みに使う
     * @param  int|null  $greigeId  greiges.id。分かれば greige_id でも絞る
     * @param  string  $prefix  コード接頭辞（例: KB-A-PO-G-2606-001-）
     * @return int  既存の最大連番。該当なしは 0
     */
    private static function nextGreigeRollSeq(int $poId, ?int $greigeId, string $prefix): int
    {
        $query = GreigeRollModel::query()->where('purchase_order_id', $poId);
        if ($greigeId !== null && $greigeId > 0) {
            $query->where('greige_id', $greigeId);
        }

        return self::maxRollSeq($query->pluck('code')->all(), $prefix);
    }

    /**
     * 同じ接頭辞の既存コードから、使われている連番の最大値を返す。
     *
     * 例: 接頭辞が FAB-T-BK-PO-2606-009- で、既存が …-01 と …-03 なら 3。
     * 件数ではなく末尾数字を見るので、欠番があっても次番号が既存とぶつからない。
     *
     * @param  list<string>  $codes  既存の反コード
     * @param  string  $prefix  SKU + 発注コード + '-'（例: FAB-T-BK-PO-2606-009-）
     * @return int  最大連番。該当なしは 0
     */
    private static function maxRollSeq(array $codes, string $prefix): int
    {
        $max = 0;
        foreach ($codes as $code) {
            if (! str_starts_with($code, $prefix)) {
                continue;
            }
            $suffix = substr($code, strlen($prefix));
            if ($suffix !== '' && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $max;
    }

    /**
     * 連番を2桁以上の文字列にする。
     *
     * 1 → 01、12 → 12、100 → 100（2桁未満のときだけ 0 を足す）。
     *
     * @param  int  $seq  1以上の連番
     * @return string  ゼロ埋めした連番
     */
    private static function formatRollSeq(int $seq): string
    {
        return str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
    }
}
