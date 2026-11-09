<?php

namespace App\Support\Inventory;

use App\Models\Purchase\PurchaseOrder;
use App\Support\Qty\QtyHelper;

/**
 * 製品発注向け：生機の供給量（染工場仕掛 + 生機発注残 − 製品発注予約）の判定。
 */
class GreigeSupply
{
    public static function greigeSkuForProduct(int $productId): ?string
    {
        return \App\Support\Master\MasterCatalog::findProduct($productId)?->greige_sku;
    }

    /** 染工場仕掛の生機（m）— 生機発注入荷実績 */
    public static function dyeFactoryMeters(string $greigeSku): float
    {
        return GreigeInventory::totalMetersForSku($greigeSku);
    }

    /**
     * 生機発注の未入荷残（m）を品番ごとに合計して返す。
     *
     * @param  string  $greigeSku  生機 SKU。明細の greige.sku と一致比較する
     * @param  int|null  $excludeProductPoId  製品発注 ID。生機発注残の計算では使わない（availableMeters と同じ引数形）
     * @return float  未入荷残メートル数の合計。該当なしのときは 0.0
     */
    public static function greigePoRemainingMeters(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        $total = 0.0;
        PurchaseOrder::query()
            ->where('type', \App\Support\Purchase\PurchaseOrderType::GREIGE)
            ->whereIn('status', [
                \App\Support\Purchase\PurchaseOrderStatus::ORDERED,
                \App\Support\Purchase\PurchaseOrderStatus::PARTIAL,
            ])
            ->with(['lines.greige'])
            ->each(function (PurchaseOrder $po) use ($greigeSku, &$total) {
                foreach ($po->lines as $line) {
                    if (($line->greige?->sku ?? '') !== $greigeSku) {
                        continue;
                    }
                    $ordered = (float) ($line->qty_meters ?? 0);
                    $received = (float) ($line->received_qty_m ?? 0);
                    $total += max(0, $ordered - $received);
                }
            });

        return $total;
    }

    /**
     * 製品発注に使える生機のメートル数（染工場在庫 + 生機発注残 − 製品発注予約）を返す。
     *
     * 予約が供給を超えても 0 未満にはしない。
     *
     * @param  string  $greigeSku  生機 SKU。在庫・発注残・予約の品番絞り込みに使う
     * @param  int|null  $excludeProductPoId  予約合計から除外する製品発注 ID。null のときは全発注を引く
     * @return float  利用可能な生機メートル数。予約が供給を超えるときは 0.0
     */
    public static function availableMeters(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        return max(0.0, round(
            self::dyeFactoryMeters($greigeSku)
            + self::greigePoRemainingMeters($greigeSku, $excludeProductPoId)
            - self::allocatedGreigeMeters($greigeSku, $excludeProductPoId),
            2
        ));
    }

    /**
     * 製品発注の必要メートル数を、利用可能な生機で賄えるかを返す。
     *
     * @param  int  $productId  製品マスタ ID。生機 SKU の特定に使う
     * @param  float  $requiredMeters  製品の必要メートル数
     * @param  int|null  $excludeProductPoId  予約合計から除外する製品発注 ID。availableMeters へ渡す
     * @return bool  足りるなら true。品番なし・必要量が 0 以下・不足なら false
     */
    public static function canFulfillProductMeters(int $productId, float $requiredMeters, ?int $excludeProductPoId = null): bool
    {
        $sku = self::greigeSkuForProduct($productId);
        if ($sku === null || $requiredMeters <= 0) {
            return false;
        }

        return self::availableMeters($sku, $excludeProductPoId) >= $requiredMeters;
    }

    /**
     * 製品発注の必要メートル数に対する生機不足メッセージを返す。足りていれば null。
     *
     * @param  int  $productId  製品マスタ ID。生機 SKU の特定に使う
     * @param  float  $requiredMeters  製品の必要メートル数
     * @param  int|null  $excludeProductPoId  予約合計から除外する製品発注 ID。availableMeters へ渡す
     * @return string|null  不足時の日本語メッセージ。足りるときは null
     */
    public static function shortageMessage(int $productId, float $requiredMeters, ?int $excludeProductPoId = null): ?string
    {
        $sku = self::greigeSkuForProduct($productId);
        if ($sku === null) {
            return '製品に紐づく生機品番が見つかりません。';
        }

        $available = self::availableMeters($sku, $excludeProductPoId);
        if ($available >= $requiredMeters) {
            return null;
        }

        $short = $requiredMeters - $available;
        $greige = \App\Support\Master\MasterCatalog::findGreige($sku);

        return ($greige?->name ?? $sku).'（'.$sku.'）が '.\App\Support\Qty\QtyHelper::formatMeters($short).'m 不足しています（必要 '.\App\Support\Qty\QtyHelper::formatMeters($requiredMeters).'m / 利用可能 '.\App\Support\Qty\QtyHelper::formatMeters($available).'m）。';
    }

    /**
     * 染工場仕掛の生機在庫反数を返す。
     *
     * 染色待ちの物理反（在庫中・一部消費）の tan_qty 合計。反明細が無い旧データは入荷mからの換算反になる。
     *
     * @param  string  $greigeSku  生機 SKU。在庫行の品番絞り込みに使う
     * @return float  在庫反数。該当なしのときは 0.0
     *
     * @see GreigeInventory::totalTanForSku()
     */
    public static function dyeFactoryTan(string $greigeSku): float
    {
        return GreigeInventory::totalTanForSku($greigeSku);
    }

    /**
     * 生機発注の未入荷残反数を品番ごとに合計して返す。
     *
     * 処理の流れ:
     * 1. 状態が発注済・一部入荷の生機発注を読む
     * 2. 明細の生機 SKU が一致する行だけ残す
     * 3. 各行の remainingTan()（発注反 − 入荷済反）を足す
     *
     * @param  string  $greigeSku  生機 SKU。明細の greige.sku と一致比較する
     * @param  int|null  $excludeProductPoId  製品発注 ID。生機発注残の計算では使わない（availableTan と同じ引数形）
     * @return float  未入荷残反数の合計。該当なしのときは 0.0
     */
    public static function greigePoRemainingTan(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        $total = 0.0;
        PurchaseOrder::query()
            ->where('type', \App\Support\Purchase\PurchaseOrderType::GREIGE)
            ->whereIn('status', [
                \App\Support\Purchase\PurchaseOrderStatus::ORDERED,
                \App\Support\Purchase\PurchaseOrderStatus::PARTIAL,
            ])
            ->with(['lines.greige'])
            ->each(function (PurchaseOrder $po) use ($greigeSku, &$total) {
                foreach ($po->lines as $line) {
                    if (($line->greige?->sku ?? '') !== $greigeSku) {
                        continue;
                    }
                    $line->setRelation('purchaseOrder', $po);
                    $total += $line->remainingTan();
                }
            });

        return round($total, QtyHelper::TAN_DECIMALS);
    }

    /**
     * 製品発注が予約している生機のメートル数を品番ごとに合計して返す。
     *
     * 処理の流れ:
     * 1. 状態が発注済・一部入荷の製品発注を読む（下書き・入荷完了・キャンセルは対象外）
     * 2. $excludeProductPoId があれば、その発注は合計から外す（編集中の自分を二重に数えない）
     * 3. 明細の製品が $greigeSku を使う行だけ残す
     * 4. 工程が染機投入済の行は外す（その分は染工場在庫から既に減っている）
     * 5. 各行の remainingQty()（発注m − 入荷済m）を足す
     *
     * @param  string  $greigeSku  生機 SKU。製品マスタの greige_sku と一致比較する
     * @param  int|null  $excludeProductPoId  合計から除外する製品発注 ID。null のときは全発注を含める
     * @return float  予約メートル数の合計。該当なしのときは 0.0
     *
     * @see allocatedGreigeTan()
     */
    public static function allocatedGreigeMeters(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        $total = 0.0;
        $query = PurchaseOrder::query()
            ->where('type', \App\Support\Purchase\PurchaseOrderType::PRODUCT)
            ->whereIn('status', [
                \App\Support\Purchase\PurchaseOrderStatus::ORDERED,
                \App\Support\Purchase\PurchaseOrderStatus::PARTIAL,
            ])
            ->with(['lines']);

        if ($excludeProductPoId !== null) {
            $query->where('id', '!=', $excludeProductPoId);
        }

        $query->each(function (PurchaseOrder $po) use ($greigeSku, &$total) {
            foreach ($po->lines as $line) {
                if ((string) ($line->stage ?? '') === \App\Support\Purchase\PurchaseOrderStages::PRODUCT_DYEING) {
                    continue;
                }

                $productId = (int) ($line->product_id ?? 0);
                if ($productId <= 0 || self::greigeSkuForProduct($productId) !== $greigeSku) {
                    continue;
                }

                $line->setRelation('purchaseOrder', $po);
                $total += $line->remainingQty();
            }
        });

        return round($total, 2);
    }

    /**
     * 製品発注が予約している生機の反数を品番ごとに合計して返す。
     *
     * allocatedGreigeMeters() の合計 m を、生機の標準 m/反で割った値。
     * 明細ごとに反へ換算せず、最後に1回だけ換算する（丸めのずれを減らすため）。
     *
     * @param  string  $greigeSku  生機 SKU。メートル合計の m/反換算に使う
     * @param  int|null  $excludeProductPoId  合計から除外する製品発注 ID。allocatedGreigeMeters へそのまま渡す
     * @return float  予約反数の合計。該当なしのときは 0.0
     *
     * @see allocatedGreigeMeters()
     */
    public static function allocatedGreigeTan(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        return QtyHelper::tanCount(
            self::allocatedGreigeMeters($greigeSku, $excludeProductPoId),
            null,
            true,
            $greigeSku
        );
    }

    /**
     * 製品発注に使える生機の反数（染工場在庫 + 生機発注残 − 製品発注予約）を返す。
     *
     * 単位は生機反。製品反との比較は shortageTan() 側で換算する。
     * 予約が供給を超えても 0 未満にはしない。
     *
     * @param  string  $greigeSku  生機 SKU。在庫・発注残・予約の品番絞り込みに使う
     * @param  int|null  $excludeProductPoId  予約合計から除外する製品発注 ID。null のときは全発注を引く
     * @return float  利用可能な生機反数。予約が供給を超えるときは 0.0
     */
    public static function availableTan(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        return max(0.0, round(
            self::dyeFactoryTan($greigeSku)
            + self::greigePoRemainingTan($greigeSku, $excludeProductPoId)
            - self::allocatedGreigeTan($greigeSku, $excludeProductPoId),
            QtyHelper::TAN_DECIMALS
        ));
    }

    /**
     * 製品発注の必要反数を、利用可能な生機（製品反換算）で賄えるかを返す。
     *
     * shortageTan() と同じ判定を使う。警告フラグと不足メッセージがずれないようにするため。
     *
     * @param  int  $productId  製品マスタ ID。生機 SKU の特定と換算に使う
     * @param  float  $requiredTan  製品の発注反数
     * @param  int|null  $excludeProductPoId  予約合計から除外する製品発注 ID。shortageTan へ渡す
     * @return bool  足りる（メッセージなし）なら true。品番なし・不足なら false
     *
     * @see shortageTan()
     */
    public static function canFulfillProductTan(int $productId, float $requiredTan, ?int $excludeProductPoId = null): bool
    {
        return self::shortageTan($productId, $requiredTan, $excludeProductPoId) === null;
    }

    /**
     * 製品発注の必要反数に対する生機不足メッセージを返す。足りていれば null。
     *
     * 処理の流れ:
     * 1. 製品に紐づく生機品番を探す。無ければエラー文を返す
     * 2. 生機の利用可能反（在庫 + 発注残 − 他の製品発注予約）を、同じ長さの製品反へ換算する
     * 3. 必要反以下なら null、足りなければ不足反数つきのメッセージを返す
     *
     * @param  int  $productId  製品マスタ ID。生機 SKU の特定と m/反換算に使う
     * @param  float  $requiredTan  製品の発注反数（0.25反刻みの入力値）
     * @param  int|null  $excludeProductPoId  予約合計から除外する製品発注 ID。availableTan へ渡す
     * @return string|null  不足時の日本語メッセージ。足りるとき・必要反が 0 以下のときは null
     *
     * @see QtyHelper::productTanFromGreigeTan()
     */
    public static function shortageTan(int $productId, float $requiredTan, ?int $excludeProductPoId = null): ?string
    {
        $sku = self::greigeSkuForProduct($productId);
        if ($sku === null) {
            return '製品に紐づく生機品番が見つかりません。';
        }

        if ($requiredTan <= 0) {
            return null;
        }

        $availableGreigeTan = self::availableTan($sku, $excludeProductPoId);
        $availableProductTan = QtyHelper::productTanFromGreigeTan($availableGreigeTan, $productId);
        if ($availableProductTan >= $requiredTan) {
            return null;
        }

        $short = $requiredTan - $availableProductTan;
        $greige = \App\Support\Master\MasterCatalog::findGreige($sku);

        return ($greige?->name ?? $sku).'（'.$sku.'）が '.QtyHelper::formatTanCount($short).'反 不足しています（必要 '.QtyHelper::formatTanCount($requiredTan).'反 / 利用可能 '.QtyHelper::formatTanCount($availableProductTan).'反）。';
    }
}
